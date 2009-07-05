<?php

  /* Copyright (C) 2008 Thomas Schoebel-Theuer (ts@athomux.net)
   * 
   * This program is free software; you can redistribute it and/or modify
   * it under the terms of the GNU General Public License as published by
   * the Free Software Foundation; either version 2 of the License, or
   * (at your option) any later version.
   *
   * This program is distributed in the hope that it will be useful,
   * but WITHOUT ANY WARRANTY; without even the implied warranty of
   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   * GNU General Public License for more details.
   *
   * You should have received a copy of the GNU General Public License
   * along with this program; if not, write to the Free Software
   * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301 USA
   */

  // requires pcntl extensions

require_once("$BASEDIR/../common/app.php");
require_once("$BASEDIR/compiled/engine_table.php");

$trace = false;

$FORKS = array();

// helper RE obeying correct parenthesis and single_quote nesting...
$SUBEXPR = ".*";
for($i = 0; $i < 5; $i++) {
  $SUBEXPR = "(?:[^\\({']|\\.|\($SUBEXPR\)|\{$SUBEXPR\}|'(?:[^\\']|\\.)*')*";
 }

/////////////////////////////////////////////////////////////////////

// Logging

function engine_log($txt) {
  global $debug;
  if($debug) {
    echo "ENGINE_INFO: $txt\n";
  } 
}

function engine_warn($txt) {
  echo "ENGINE_WARN: $txt\n";
  check_global_answer("ENGINE_WARN ($txt)");
}

function engine_error($txt) {
  echo "ENGINE_ERROR: $txt\n";
  check_global_answer("ENGINE_ERROR ($txt)");
}

/////////////////////////////////////////////////////////////////////

// Helper functions

/* Runtime creation of default values when the orchestrator
 * has omitted some values.
 */
function make_default($value, $basis, $prefix, $plus = 1) {
  //echo "aha....($value)($basis)($prefix)($plus)\n";
  if($value) {
    if(preg_match("/\A\+\s+(-?[0-9]+)/", $value, $matches)) {
      return (int)$basis + (int)$matches[1];
    }
    return $value;
  }
  if(is_int($basis)) {
    return $basis + $plus;
  }
  if(preg_match("/\A=?\s*([0-9]+)/", $basis, $matches)) {
    return ((int)$matches[1]) + $plus;
  }
  return "${prefix}_$basis";
}

/* Create db_read() condition for selection of all states
 * for the given table and field.
 */
function make_selectcond($tablename, $fieldname, $deflist) {
  $select = array();
  foreach($deflist as $def) {
    $loc = $def["rule_location"];
    //...
    $value = $def["rule_startvalue"];
    if(preg_match("/\A=(.*)/", $value, $matches)) {
      $select["${fieldname}="][] = $matches[1];
    } elseif(preg_match("/\A%(.*)/", $value, $matches)) {
      $select["${fieldname}%"][] = "%" . $matches[1] . "%";
    } elseif(preg_match("/\A\/(.*)\/\Z/", $value, $matches)) {
      $select["${fieldname} rlike"][] = $matches[1];
    } else {
      engine_error("cannot parse the rule_startvalue '$value'. correct your rules!");
      return false;
    }
  }
  $cond = array($select);
  return $cond;
}

/* Generic check whether the given value has been reached.
 */
function value_matches(&$env, $value, $check) {
  if(preg_match("/\A=(.*)/", $value, $matches)) {
    if($matches[1] == $check) {
      $env[0] = $check;
      $env[1] = $check;
      return true;
    }
  } elseif(preg_match("/\A%(.*)/", $value, $matches)) {
    if(!strpos($check, $matches[1]) === false) {
      $env[0] = $check;
      $env[1] = $matches[1];
      return true;
    }
  } elseif(preg_match("/\A\/(.*)\/\Z/", $value)) {
    if(preg_match($value, $check, $matches)) {
      foreach($matches as $idx => $match) {
	$env[$idx] = $match;
      }
      return true;
    }
  } else {
    engine_error("cannot parse value '$value' at runtime. this should not happen!");
  }
  return false;
}

/* Test whether a given condition @cnd is true on a given environment @env.
 */
function test_condition(&$env, $cnd) {
  global $RAW_ID;
  if(!$cnd) { // empty condition field => accept always
    return true;
  }
  if(is_array($cnd)) {
    foreach($cnd as $sub) {
      if(!test_condition($env, $sub)) {
	return false;
      }
    }
    return true;
  }
  if(preg_match("/\A\?\s*($RAW_ID)\s*(.*)\Z/", $cnd, $matches)) {
    $field = $matches[1];
    $rest = $matches[2];
    if(!array_key_exists($field, $env)) {
      engine_error("variable '$field' does not exist at runtime");
      return false;
    }
    $check = $env[$field];
    return value_matches($env, $rest, $check);
  } else {
    engine_error("cannot parse the condition '$cnd'. correct your rules!");
    return false;
  }
}

/* Substitute macros in strings
 */
function subst_macros(&$env, $cmd, $search = array(), $replace = array()) {
  global $SUBEXPR;
  $count = 0;
  while(preg_match("/\A(.*?)@(?:\{([0-9A-Za-z_]+(?:[-][>][0-9A-Za-z_]+)*)\}|\(($SUBEXPR)\))(.*)/", $cmd, $mymatches)) {
    $field = $mymatches[2];
    $subcmd = $mymatches[3];
    if($subcmd) { // inline expansion of shell commands
      $subcmd = subst_macros($env, $subcmd);
      $fd = popen($subcmd, "r");
      $text = fread($fd, 4096);
      fclose($fd);
      $cmd = $mymatches[1] . str_replace($search, $replace, $text) . $mymatches[4];      
    } else { // ordinary macro expansion
      $subst = $env;
      while(preg_match("/\A(?:->)?([0-9A-Za-z_]+)(.*)/", $field, $matches)) {
	$subfield = $matches[1];
	$field = $matches[2];
	if(preg_match("/\A\s*[0-9]+\s*\Z/", $subfield)) { // convert to integer
	  $subfield = (int)$subfield;
	}
	if(!@array_key_exists($subfield, $subst)) {
	  engine_warn("macro substitution '$subfield' does not exist at runtime");
	  $subst = "";
	  $field = "";
	  break;
	}
	$subst = @$subst[$subfield];
      }
      if($field) {
	engine_error("variable substitution '$field' is syntactically incorrect");
	continue;
      }
      engine_log("macro subst '$field' -> '$subst'");
      $cmd = $mymatches[1] . str_replace($search, $replace, $subst) . $mymatches[4];
    }
    if($count++ > 999) { // prevent endless substitutions
      engine_error("endless macro substitution on string '$cmd'");
      return "";
    }
  }
  return $cmd;
}

function echo_rule($env) {
  $rule_id = $env["rule_id"];
  $rule_prio = $env["rule_prio"];
  $cont_prio = @$env["cont_prio"];
  echo "rule ($rule_id,$rule_prio,$cont_prio)\t";
}

/////////////////////////////////////////////////////////////////////

// Engine actions

/* Write back results to the database.
 */
function do_writeback($env, $fieldvalue, $other = array()) {
  global $ERROR;
  $tablename = $env["TABLE"];
  $fieldname = $env["FIELD"];
  $env_field = $env["ENV_FIELD"];
  $primary = _db_primary($tablename);
  $keytxt = "";
  foreach(split(",", $primary) as $pri) {
    $id = $env[$pri];
    engine_log("action: table $tablename primary $pri = '$id' : field $fieldname = '$fieldvalue'");
    $other[$pri] = $id;
    if($keytxt)
      $keytxt .= ", ";
    $keytxt .= "$pri = '$id'";
  }
  if(true) {
    echo_rule($env);
    echo "writeback ($keytxt) $tablename.$fieldname = '$fieldvalue'\n";
  }
  $other[$fieldname] = $fieldvalue;
  if($env_field) {
    $other[$env_field] = db_data_to_code($env);
  }
  $ok = db_update($tablename, array($other));
  if(!$ok || $ERROR) {
    echo "writeback error: $ERROR<br>\n";
  }
}

/* Interpret the data (or pseudo-events) sent by the script
 * and execute appropriate actions.
 */
function check_answer(&$env, $line) {
  global $debug;
  global $trace;
  if(!$line)
    return true;
  if(@$trace) {
    echo "==> $line\n";
  }

  if(@$env["LEVEL"] > 1) { // disallow recursion on the output of cont_action
    //echo"break.....($line)\n";
    return true;
  }

  $result = true;
  if($debug) {
    echo "LINE: $line\n";
  }

  $tablename = $env["TABLE"];
  $fieldname = $env["FIELD"];
  $count = 0;
  // check all continuation candidates
  foreach($env["CONTI"] as $conti) {
    $value = $conti["cont_match"];
    if(value_matches($env, $value, $line)) { // the candidate has been selected
      $action = $conti["cont_action"];
      $newenv = array_merge($env, $conti);
      do_action($newenv, $action);
      if(@$newenv["DO_BREAK"]) {
	$env["DO_BREAK"] = true;
	return $result;
      }
      $count++;
      if(!$newenv["cont_endvalue"]) {
	// the orchestrator requested to continue examining continuation candidates...
	continue;
      }
      // otherwise we are done
      $env["cont_endvalue"] = $newenv["cont_endvalue"];
      break;
    }
  }
  if(!$count) {
    engine_log("no match found for line '$line'");
  }
  return $result;
}

function check_global_answer($line) {
  global $ENGINE;
  $env = $ENGINE["GLOBAL.GLOBAL"];
  $env = array_replace_recursive($env[0], $env);
  $env["IS_GLOBAL"] = true;
  $env["TABLE"] = "GLOBAL";
  $env["FIELD"] = "GLOBAL";
  $env["LEVEL"] = 1;
  return check_answer($env, $line);
}

/* Execute the given @cmd as a new subprocess.
 */
function run_script(&$env, $cmd) {
  /* Only fork once, if there are many sequential script invocations.
   * The father returns "success", and the son will treat the rest
   * of the action chain.
   */
  if(!@$env["HAS_FORKED"] && !@$env["IS_SON"] && !@$env["IS_GLOBAL"]) {
    global $FORKS;
    // clean the connection cache: mysql seems to be disturbed by fork()
    _db_close();
    flush();
    // fork a process for execution of the script
    $pid = pcntl_fork();
    if($pid < 0) { // error
      engine_error("could not fork() a new process for command '$cmd'");
      return false;
    }
    if($pid > 0) { // father
      $env["HAS_FORKED"] = true;
      $FORKS[$pid] = $cmd;
      return true;
    }
    // son
    $env["IS_SON"] = true;
    $pid = getmypid();
    
    check_answer($env, "FORKED $pid");
    //global $debug; $debug = true;
  }

  $pipes = array();
  $descr = array(
		 0 => array("file", "/dev/null", "r"),
		 1 => array("pipe", "w"),
		 2 => array("pipe", "w"),
		 );
  //echo "cmd: $cmd\n";
  $proc = proc_open($cmd, $descr, $pipes);
  if(!$proc) {
    engine_error("proc_open() on command '$cmd' failed");
    exit(-1);
  }
  $pid = proc_get_status($proc);
  $pid = @$pid["pid"];
  $ok = true;
  $ok &= check_answer($env, "START $pid ($cmd)");
  $timeout = $env["rule_timeout"];
  if($timeout < 1)
    $timeout = 3600 * 24 * 365;
  do {
    $tests = $pipes; // create a copy, because stream_select() will modifiy it
    $dummy1 = null;
    $dummy2 = null;
    $status = stream_select($tests, $dummy1, $dummy2, $timeout);
    if(!$status) { // timeout
      $ok &= check_answer($env, "TIMEOUT $pid $timeout");
    }
    $closed = 0;
    foreach($tests as $stream) {
      if(feof($stream)) {
	$closed++;
	continue;
      }
      $line = chop(fgets($stream));
      $ok &= check_answer($env, $line);
    }
    if(!$ok) { // kill it...
      engine_log("killing process $pid");
      proc_terminate($proc);
      check_answer($env, "KILL $pid");
      exit(1);
    }
  } while($closed < 2);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $has_reported = false;
  if(true) {
    $code = pcntl_waitpid($pid, $status, 0);
    if($code > 0) {
      if(pcntl_wifsignaled($status)) {
	$status = pcntl_wtermsig($status);
	check_answer($env, "SIGNALED $code $status");
      }
      if(pcntl_wifexited($status)) {
	$status = pcntl_wexitstatus($status);
	check_answer($env, "STATUS $code $status");
	$has_reported = true;
      }
    }
  }
  if(!$has_reported) { // after waitpid(), the zombie is lost => proc_close() will no longer work
    $status = proc_close($proc);
    check_answer($env, "STATUS $pid $status");
  }
  return true;
}

/* Interpret what to do.
 */
function do_action(&$env, $action) {
  global $RAW_ID;
  global $ERROR;
  if(is_array($action)) {
    foreach($action as $sub) {
      if(!do_action($env, $sub)) {
	return false;
      }
      if(@$env["HAS_FORKED"] || @$env["DO_BREAK"]) {
	return true;
      }
    }
    return true;
  }

  if(!$action)
    return true;

  //echo "ACTION: $action\n";
  $oldlevel = $env["LEVEL"];
  $env["LEVEL"]++;

  $ok = false;
  if(!$action) {
    $ok = true;
  } elseif(preg_match("/\A(script|url)\s+(.*)/", $action, $matches)) {
    $op = $matches[1];
    $cmd = $matches[2];
    if($op == "url")
      $cmd = "wget -O - '$cmd'";
    $cmd = subst_macros($env, $cmd);
    $ok = run_script($env, $cmd);
  } else if(preg_match("/\A(insert|update|delete)\s+(?:($RAW_ID)\s+)?($RAW_ID\s*=.*)/", $action, $matches)) {
    $mode = $matches[1];
    $table = $env["TABLE"];
    if(@$matches[2])
      $table = $matches[2];
    $rest = $matches[3];
    $data = array();
    //echo "MODE: $mode\n";
    if($mode == "update" || $mode == "delete") {
      $primary = _db_primary($table);
      foreach(split(",", $primary) as $pri) {
	if(isset($env[$pri])) {
	  $data[0][$pri] = $env[$pri];
	}
      }
    }
    while(preg_match("/\A\s*($RAW_ID)\s*=\s*'((?:[^\\']|\\.)*)'(.*)/", $rest, $matches)) {
      $field = $matches[1];
      $value = $matches[2];
      $rest = $matches[3];
      $data[0][$field] = subst_macros($env, $value, "'", "\\'");
    }
    echo_rule($env);
    echo "$mode $table:";
    foreach($data[0] as $field => $value) {
      echo " $field = '$value'";
    }
    echo "\n";
    if($mode == "delete") {
      $ok = db_delete($table, $data) && !$ERROR;
      check_answer($env, "DELETE $ok");
    } elseif($mode == "update") {
      $ok = db_update($table, $data) && !$ERROR;
      check_answer($env, "UPDATE $ok");
    } else {
      $ok = db_insert($table, $data) && !$ERROR;
      check_answer($env, "INSERT $ok");
    }
    if(!$ok) {
      check_answer($env, "DB_ERROR $mode ($ERROR)");
    }
  } else if(preg_match("/\Aquery\s+($RAW_ID)\s+($RAW_ID)\s+($RAW_ID\s*=.*)/", $action, $matches)) {
    $var = $matches[1];
    $table = $matches[2];
    $rest = $matches[3];
    $cond = array();
    while(preg_match("/\A\s*($RAW_ID)\s*=\s*'((?:[^\\']|\\.)*)'(.*)/", $rest, $matches)) {
      $field = $matches[1];
      $value = $matches[2];
      $rest = $matches[3];
      $cond[$field] = subst_macros($env, $value, "'", "\\'");
    }
    $data = db_read($table, null, $cond, null, 0, 0);
    //echo "got....."; print_r($data); echo "\n";
    $env[$var] = $data;
    $ok = true;
  } else if(preg_match("/\Avar\s+($RAW_ID(?:->$RAW_ID)*)\s*=\s*'((?:[^\\']|\\.)*)'/", $action, $matches)) {
    $var = $matches[1];
    $expr = $matches[2];
    $lvalue = &$env;
    while(preg_match("/\A(?:->)?($RAW_ID)(.*)/", $var, $matches)) {
      $field = $matches[1];
      $var = $matches[2];
      $lvalue = &$lvalue[$field];
    }
    if($var) {
      engine_error("cannot assign to variable '$var'");
    } else {
      $lvalue = subst_macros($env, $expr, "'", "\\'");
      $ok = true;
    }
  } else if(preg_match("/\A(call|start)\s+($RAW_ID)(.*)/", $action, $matches)) {
    $ok = false;
    $mode = $matches[1];
    $call = $matches[2];
    $rest = $matches[3];
    $cond = array("bp_name" => $call);
    $data = db_read("bps", null, $cond);
    if(!$data || $ERROR) {
      check_answer($env, "DB_ERROR read ($ERROR)");
    } else {
      $newenv = array();
      while(preg_match("/\A\s*($RAW_ID)\s*=\s*'((?:[^\\']|\\.)*)'(.*)/", $rest, $matches)) {
	$field = $matches[1];
	$value = $matches[2];
	$rest = $matches[3];
	$newenv[$field] = subst_macros($env, $value, "'", "\\'");
      }
      if($rest) {
	engine_error("bad call '$call', syntax rest '$rest'");
      } else {
	$statefield = $data[0]["bp_statefield"];
	$newrec = array();
	$newrec["state_id"] = null;
	$newrec["bp_name"] = $call;
	if($mode == "start") { // asynchronous call: make "return" later a nop
	  $newenv["NO_RETURN"] = true;
	}
	$newrec["state_env"] = db_data_to_code($newenv);
	if(@$newenv["state_value"]) {
	  $newrec["state_value"] = $newenv["state_value"];
	}
	$table = $env["TABLE"];
	$primary = _db_primary($table);
	$field = $env["FIELD"];
	$newrec["state_returnfield"] = "$table.$field";
	$id_rec = array();
	foreach(split(",", $primary) as $pri) {
	  $id_rec[$pri] = $env[$pri];
	}
	$newrec["state_returnid"] = db_data_to_code($id_rec);

	$ok = db_insert("states", array($newrec)) && !$ERROR;

	if($ok) {
	  if(true) {
	    echo_rule($env);
	    echo "call $table.$field to states\n";
	  }
	  // decide whether to finish the caller or not
	  if($mode == "call") {
	    //echo "SHOULD_BREAK....\n";
	    $env["DO_BREAK"] = true;
	  } else {
	  }
	} else {
	  check_answer($env, "DB_ERROR insert ($ERROR)");
	}
      }
    }
  } else if(preg_match("/\Areturn\s+'((?:[^\\']|\\.)*)'(.*)/", $action, $matches)) {
    $returnvalue = subst_macros($env, $matches[1], "'", "\\'");
    $rest = $matches[2];
    if(@$env["NO_RETURN"]) { // original call was asynchronous: ignore return statement
      check_answer($env, "NO_RETURN");
      if(true) {
	$table = $env["TABLE"];
	$field = $env["FIELD"];
	echo_rule($env);
	echo "done asynchronous call $table.$field\n";
      }
      $ok = true;
    } else { // advance the caller's state
      $split = split("\.", $env["state_returnfield"]);
      $tablename = $split[0];
      $fieldname = $split[1];
      $oldrec = eval("return " . $env["state_returnid"] . ";");

      $data = db_read($tablename, null, $oldrec);

      if(!$data || $ERROR) {
	engine_error("cannot re-read caller's data from table $tablename");
      } else {
	if($test = @$data[0]["state_env"]) { // original caller had an environment
	  $oldenv = eval("return $test;");
	  while(preg_match("/\A\s*($RAW_ID)\s*=\s*'((?:[^\\']|\\.)*)'(.*)/", $rest, $matches)) {
	    $field = $matches[1];
	    $value = $matches[2];
	    $rest = $matches[3];
	    $oldenv[$field] = subst_macros($env, $value, "'", "\\'");
	  }
	  if($rest) {
	    engine_warn("return statement has unparsable rest '$rest'");
	  }
	  $oldrec["state_env"] = db_data_to_code($oldenv);
	}
	$oldrec[$fieldname] = $returnvalue;
	if(true) {
	  echo_rule($env);
	  echo "return to $tablename.$fieldname = '$returnvalue'\n";
	}
	$ok = db_update($tablename, array($oldrec)) && !$ERROR;
	//echo "RETURN $tablename $fieldname='$returnvalue' ok='$ok' ERROR='$ERROR'\n";
	if(!$ok) {
	  check_answer($env, "DB_ERROR update ($ERROR)");
	}
      }
    }
  } else {
    engine_error("cannot parse action '$action'. correct your rules!");
  }
  if($ok) {
    $env["HIT_FLAG"] = true;
  }
  $env["LEVEL"] = $oldlevel;
  return $ok;
}

/////////////////////////////////////////////////////////////////////

// The engine itself

function treat_rec($rec, $deflist) {
  global $debug;
  foreach($deflist as $def) {
    $fieldname = $def["FIELD"];
    $env_field = $def["ENV_FIELD"];
    $cell = $rec[$fieldname];
    $startvalue = $def["rule_startvalue"];
    // initialize the environment with some reasonable knowledge about our world
    $env = array_merge($def, $rec);
    // THINK: what's the correct precedence? Should full $rec override the stored environment $rec[$env_field] or not? I'm unsure
    if($env_field) {
      $stored_env = eval("return " . $rec[$env_field] . ";");
      $env = array_merge($stored_env, $env);
    }
    $env["HAS_FORKED"] = false;
    $env["IS_SON"] = false;
    $env["IS_GLOBAL"] = false;
    $env["LEVEL"] = 0;
    if(value_matches($env, $startvalue, $cell)) {
      if(test_condition($env, $def["rule_condition"])) {
	// before starting the action, remember that we have fired...
	$firevalue = make_default($def["rule_firevalue"], $cell, "start");
	do_writeback($env, $firevalue);

	// now do the action...
	$env["VALUE"] = $cell;

	$ok = do_action($env, $env["rule_action"]);
	if($ok && (@$env["HAS_FORKED"] || @$env["DO_BREAK"])) {
	  //echo "BREAK......\n";
	  break;
	}

	// record final result of execution,
	$endvalue = "-1 ENGINE_ERROR"; // sorry, but this should never happen: the HIT_FLAG must always be set because of the $APPEND fallbacks
	if(@$env["HIT_FLAG"] && @$env["cont_endvalue"]) {
	  $endvalue = make_default($env["cont_endvalue"], $env["VALUE"], "end", 2);
	}
	do_writeback($env, $endvalue);

	if(@$env["IS_SON"]) { // mission completed...
	  if($debug) echo "forked mission completed.\n";
	  exit(0);
	}
	break;
      }
    }
  }
}

/* Poll child process status and report it
 */
function poll_childs() {
  global $FORKS;
  global $ENGINE;
  for(;;) {
    $code = pcntl_waitpid(0, $status, WNOHANG);
    if($code <= 0) {
      return; // do nothing
    }
    $cmd = @$FORKS[$code];
    unset($FORKS[$code]); // avoid memory leaks
    if(pcntl_wifsignaled($status)) {
      $status = pcntl_wtermsig($status);
      check_global_answer("GLOBAL_SIGNALED $code $status ($cmd)");
    }
    if(pcntl_wifexited($status)) {
      $status = pcntl_wexitstatus($status);
      check_global_answer("GLOBAL_EXITED $code $status ($cmd)");
    }
  }
}

function engine_run_once() {
  global $ENGINE;
  poll_childs();
  foreach($ENGINE as $statefield => $deflist) {
    if($statefield == "GLOBAL.GLOBAL") {
      continue;
    }
    $tmp = explode(".", $statefield);
    $tablename = $tmp[0];
    $fieldname = $tmp[1];
    $primary = _db_primary($tablename);
      
    $cond = make_selectcond($tablename, $fieldname, $deflist);
    if(!$cond)
      continue;

    $data = db_read($tablename, null, $cond, null, 0, 0);

    foreach($data as $rec) {
      $cache = array();
      foreach($deflist as $def) {
	$joinwith = $def["bp_joinwith"];
	if(!@$cache[$joinwith]) {
	  if($joinwith) {
	    $subcond = $cond;
	    foreach(split(",", $primary) as $pri) {
	      $subcond[$pri] = $rec[$pri];
	    }
	    $subdata = db_read("$tablename,$joinwith", null, $subcond, null, 0, 0);
	  } else {
	    $subdata = array($rec);
	  }
	  $cache[$joinwith] = $subdata;
	}
      }

      foreach($cache as $subdata) {
	foreach($subdata as $subrec) {
	  treat_rec($subrec, $deflist);
	}
      }
    }
  }
}

$STOP = false;

function engine_run_endless($pause = 3) {
  global $STOP;
  while(!$STOP) {
    engine_run_once();
    sleep($pause);
  }
}
if(@$argv[4]) {
  $trace = true;
 }
$count = @$argv[3];
if(!$count) {
  engine_run_endless();
 } else { // debugging
  for($i = 0; $i < $count; $i++) {
    engine_run_once();
    sleep(3);
  }
 }

?>
