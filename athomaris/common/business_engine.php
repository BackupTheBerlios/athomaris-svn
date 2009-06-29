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
    echo "INFO: $txt <br>\n";
  }
}

function engine_warn($txt) {
  echo "WARN: $txt <br>\n";
}

function engine_error($txt) {
  echo "ERROR: $txt <br>\n";
}

/////////////////////////////////////////////////////////////////////

// Helper functions

/* Runtime creation of default values when the orchestrator
 * has omitted some values.
 */
function make_default($value, $basis, $prefix, $plus = 1) {
  if($value) {
    if(preg_match("/\A\+\s+(-?[0-9]+)/", $basis, $matches)) {
      return $value + $matches[1];
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
  if(preg_match("/\A\?\s*([A-Za-z_0-9]+)\s*(.*)\Z/", $cnd, $matches)) {
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
  while(preg_match("/\A(.*?)@(?:\{([A-Za-z_0-9]+)\}|\(($SUBEXPR)\))(.*)/", $cmd, $mymatches)) {
    $field = $mymatches[2];
    $subcmd = $mymatches[3];
    if($subcmd) { // inline expansion of shell commands
      $subcmd = subst_macros($env, $subcmd);
      $fd = popen($subcmd, "r");
      $text = fread($fd, 4096);
      fclose($fd);
      $cmd = $mymatches[1] . str_replace($search, $replace, $text) . $mymatches[4];      
    } else { // ordinary macro expansion
      if(preg_match("/\A\s*[0-9]+/", $field)) { // expand outer_matches
	$field = (int)$field;
	if(!array_key_exists($field, $env)) {
	  engine_warn("macro substitution: subexpression '$field' does not exist in regular expression at runtime");
	}
	$subst = @$env[$field];
	engine_log("macro subst '$field' -> '$subst'");
	$cmd = $mymatches[1] . str_replace($search, $replace, $subst) . $mymatches[4];
	continue;
      }
      if(!array_key_exists($field, $env)) {
	engine_warn("macro substitution '$field' does not exist in tuple at runtime");
      }
      $subst = @$env[$field];
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


/////////////////////////////////////////////////////////////////////

// Engine actions

/* Write back results to the database.
 */
function do_writeback($rec, $tablename, $fieldname, $fieldvalue, $other = array()) {
  if(true) {
    $primary = _db_primary($tablename);
    $id = @$rec[$primary];
    echo "writeback id=$id $tablename.$fieldname='$fieldvalue'\n";
  }
  global $ERROR;
  $primary = _db_primary($tablename);
  foreach(split(",", $primary) as $pri) {
    $id = $rec[$pri];
    engine_log("action: table $tablename primary $pri = '$id' : field $fieldname = '$fieldvalue'");
    $other[$pri] = $id;
  }
  $other[$fieldname] = $fieldvalue;
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
  $result = true;
  if(!$line)
    return true;
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

/* Execute the given @cmd as a new subprocess.
 */
function run_script(&$env, $cmd) {
  if(true) {
    // clean the connection cache: mysql seems to be disturbed by fork()
    _db_close();
    // fork a process for each script
    $pid = pcntl_fork();
    if($pid < 0) { // error
      engine_error("could not fork() a new process for command '$cmd'");
      return false;
    }
    if($pid > 0) { // father
      $env["HAS_FORKED"] = true;
      return true;
    }
    // son
    $env["IS_SON"] = true;
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
  $ok &= check_answer($env, "START $pid '$cmd'");
  $timeout = $env["rule_timeout"];
  if($timeout < 1)
    $timeout = 3600 * 24 * 365;
  do {
    $tests = $pipes; // create a copy, because stream_select() will modifiy it
    $dummy1 = null;
    $dummy2 = null;
    $status = stream_select($tests, $dummy1, $dummy2, $timeout);
    if(!$status) { // timeout
      $ok &= check_answer($env, "TIMEOUT $timeout");
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
      exit(1);
    }
  } while($closed < 2);
  fclose($pipes[1]);
  fclose($pipes[2]);
  $status = proc_close($proc);
  check_answer($env, "STATUS $status");
  return true;
}

/* Interpret what to do.
 */
function do_action(&$env, $action) {
  global $RAW_ID;
  if(is_array($action)) {
    foreach($action as $sub) {
      if(!do_action($env, $sub)) {
	return false;
      }
    }
    return true;
  }
  $ok = false;
  if(!$action) {
    return true;
  } elseif(preg_match("/\A(script|url)\s+(.*)/", $action, $matches)) {
    $op = $matches[1];
    $cmd = $matches[2];
    if($op == "url")
      $cmd = "wget -O - '$cmd'";
    $cmd = subst_macros($env, $cmd);
    $ok = run_script($env, $cmd);
  } else if(preg_match("/\Ainsert\s+($RAW_ID)\s+(.*)/", $action, $matches)) {
    $table = $matches[1];
    $rest = $matches[2];
    $data = array();
    while(preg_match("/\A\s*($RAW_ID)\s*=\s*'((?:[^\\']|\\.)*)'(.*)/", $rest, $matches)) {
      $field = $matches[1];
      $value = $matches[2];
      $rest = $matches[3];
      $data[0][$field] = subst_macros($env, $value, "'", "\\'");
    }
    $ok = db_insert($table, $data);
    check_answer($env, "INSERT $ok");
  } else if(preg_match("/\Aupdate\s+(.*)/", $action, $matches)) {
    $table = $env["TABLE"];
    $rest = $matches[1];
    $primary = _db_primary($table);
    $data = array(array($primary => $env[$primary]));
    while(preg_match("/\A\s*($RAW_ID)\s*=\s*'((?:[^\\']|\\.)*)'(.*)/", $rest, $matches)) {
      $field = $matches[1];
      $value = $matches[2];
      $rest = $matches[3];
      $data[0][$field] = subst_macros($env, $value, "'", "\\'");
    }
    $ok = db_update($table, $data);
    check_answer($env, "UPDATE $ok");
  } else {
    engine_error("cannot parse action '$action'. correct your rules!");
  }
  if($ok) {
    $env["SUCCESS_FLAG"] = true;
  }
  return $ok;
}

/////////////////////////////////////////////////////////////////////

// The engine itself

function treat_rec($tablename, $fieldname, $rec, $deflist) {
  global $debug;
  $cell = $rec[$fieldname];
  foreach($deflist as $def) {
    $startvalue = $def["rule_startvalue"];
    // initialize the environment with some reasonable knowledge about our world
    $env = array_merge($def, $rec);
    $env["TABLE"] = $tablename;
    $env["FIELD"] = $fieldname;
    if(value_matches($env, $startvalue, $cell)) {
      if(test_condition($env, $def["rule_condition"])) {
	// before starting the action, remember that we have fired...
	$firevalue = make_default($def["rule_firevalue"], $cell, "start");
	do_writeback($env, $tablename, $fieldname, $firevalue);

	// now do the action...
	$env["VALUE"] = $cell;

	$ok = do_action($env, $env["rule_action"]);

	if($ok && @$env["HAS_FORKED"]) {
	  break;
	}

	// record final result of execution,
	$endvalue = "-1 ENGINE_ERROR"; // sorry, but this should never happen: the SUCCESS_FLAG must always be set because of the $APPEND fallbacks
	if($ok && @$env["SUCCESS_FLAG"]) {
	  $endvalue = make_default($env["cont_endvalue"], $env["VALUE"], "end", 2);
	}
	do_writeback($env, $tablename, $fieldname, $endvalue);

	if(@$env["IS_SON"]) { // mission completed...
	  if($debug) echo "process mission completed.\n";
	  exit(0);
	}
	break;
      }
    }
  }
}

function engine_run_once() {
  global $ENGINE;
  foreach($ENGINE as $statefield => $deflist) {
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
	  treat_rec($tablename, $fieldname, $subrec, $deflist);
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

if(@$argv[3]) {
  engine_run_once();
 } else {
  engine_run_endless();
 }

?>
