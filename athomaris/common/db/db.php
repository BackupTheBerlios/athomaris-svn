<?php

  /* Copyright (C) 2007 Thomas Schoebel-Theuer (ts@athomux.net)
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

require_once($BASEDIR . "/../common/db/syntax.php");
require_once($BASEDIR . "/../common/db/infra.php");
require_once($BASEDIR . "/../common/db/connection.php");
require_once($BASEDIR . "/../common/db/schema.php");

$FROM = @$_SERVER["REMOTE_HOST"] ? $_SERVER["REMOTE_HOST"] : (@$_SERVER["REMOTE_ADDR"] ? $_SERVER["REMOTE_ADDR"] : (@$_SERVER["HTTP_HOST"] ? $_SERVER["HTTP_HOST"] : (@$_ENV["USER"] ? $_ENV["USER"] : (@$argv[0] ? $argv[0] : (@$_SERVER["SCRIPT_FILENAME"] ? $_SERVER["SCRIPT_FILENAME"] : null)))));

function _db_strip_permissions($qstruct, $fields) {
  $new = array();
  if($fields) {
    foreach($fields as $field) {
      $ok = false;
      foreach($qstruct["BASE_TABLE"] as $table) {
	if(db_access_field($table, $field, "r")) {
	  $ok = true;
	  break;
	}
      }
      if($ok) {
	$new[] = $field;
      }
    }
  }
  return $new;
}
/* replace comma-separated strings by exploded arrays
 */
function _db_homogenize($qstruct) {
  $homo = $qstruct;
  if(($test = @$qstruct["TABLE"]) && is_string($test)) {
    $homo["TABLE"] = split(",", $test);
  }
  $homo["BASE_TABLE"] = array();
  foreach($homo["TABLE"] as $alias => $tp_table) {
    if(is_string($tp_table)) {
      _db_temporal($tp_table, $table);
      if(is_int($alias))
	$alias = $tp_table;
      $homo["BASE_TABLE"][$alias] = $table;
    }
  }
  if(($test = @$qstruct["FIELD"]) && is_string($test)) {
    $homo["FIELD"] = split(",", $test);
  }
  $homo["FIELD"] = _db_strip_permissions($homo, $homo["FIELD"]);
  if($test = @$qstruct["AGG"]["FIELD"]) {
    $homo["AGG"]["FIELD"] = split(",", $test);
    //$homo["AGG"]["FIELD"] = _db_strip_permissions($homo, $homo["AGG"]["FIELD"]);
  }
  if($test = @$qstruct["AGG"]["GROUP"]) {
    $homo["AGG"]["GROUP"] = split(",", $test);
  }
  if($test = @$qstruct["ORDER"]) {
    $homo["ORDER"] = split(",", $test);
  }
  global $debug; if($debug) { echo "homo: "; print_r($homo); echo"<br>\n";}
  return $homo;
}

function _db_add_schema($qstruct) {
  global $SCHEMA;
  $homo = $qstruct;
  $makeall = !($test = @$qstruct["FIELD"]) || !count($test);
  foreach($qstruct["BASE_TABLE"] as $table) {
    if($makeall) { // make all field names explicit, avoid using "*" because there might be access restrictions
      foreach($SCHEMA[$table]["FIELDS"] as $field => $fdef) {
	if(!@$fdef["VIRTUAL"] && db_access_field($table, $field, "r")) {
	  //echo "listing '$table' '$field'<br>\n";
	  $homo["FIELD"][$field] = $field;
	}
      }
    }
    if(($fields = @$SCHEMA[$table]["FIELDS"])) {
      foreach($fields as $field => $fdef) {
	if(($test = @$fdef["POOL_DATA"])) {
	  $newfield = "${field}_pool";
	  $homo["FIELD"][$newfield] = $test;
	}
	if(($test = @$fdef["SUB_DATA"])) {
	  $homo["FIELD"][$field] = $test;
	}
      }
    }
  }
  return $homo;
}

function _db_mangle_query(&$databases, $qstruct) {
  global $SCHEMA;
  global $SYNTAX_QUERY;
  if($error = db_check_syntax($qstruct, $SYNTAX_QUERY)) {
    echo "qstruct error: $error<br>\n";
    global $debug; if($debug) { echo "syntax: "; print_r($qstruct); echo"<br>\n";}
    return null;
  }
  // make a homogenous array syntax out of comma-separated strings
  $homo = _db_homogenize($qstruct);
  $homo = _db_add_schema($homo);
  // check whether all tables are on the same database
  $databases = array();
  foreach($homo["BASE_TABLE"] as $table) {
    $database = _db_database($table);
    $databases[$database] = true;
  }
  if(count($databases) > 1) {
    die("NYI: connot treat queries spanning multiple databases\n");
  }
  return $homo;
}


////////////////////////////////////////////////////////////////////////

// subrecord handling

function _db_cb_make_keys(&$env, $row) {
  if(!@$env["KEYS"]) {
    foreach($row as $key => $val) {
      $env["KEYS"][$key] = $key;
    }
  }
  return $row;
}

function _db_cb_process_subdata(&$env, $resultset) {
  $newdata = _db_cb_process_data($env, $resultset);
  $olddata = $env["RES"][0];
  $tuple = array_shift($env["ARG"]);
  $joinfields = $tuple[0];
  if(is_string($joinfields))
    $joinfields = explode(",", $joinfields);
  $subquery = $tuple[1];
  $alias = $tuple[2];
  // TODO: the following is O(k*n^2), this should be replaced by O(k*n log n) using hashing
  foreach($olddata as $oldidx => $oldrec) {
    if($joinfields) { // compute join
      $oldrec[$alias] = array();
      foreach($newdata as $newidx => $newrec) {
	$ok = true;
	foreach($oldrec as $oldkey => $oldval) {
	  if(!@$env["KEYS"][$oldkey])
	    continue;
	  $newval = $newrec[$oldkey];
	  if($oldval != $newval) {
	    $ok = false;
	    break;
	  }
	}
	if($ok) {
	  $copy = array();
	  foreach($newrec as $newkey => $newval) {
	    if(!@$env["KEYS"][$newkey] || in_array($newkey, $joinfields)) {
	      //echo "copy $oldidx $newidx '$newkey'<br>\n";
	      $copy[$newkey] = $newval;
	    }
	  }
	  //echo "result: "; print_r($copy); echo"<br>\n";
	  $oldrec[$alias][] = $copy;
	}
      }
    } else { // full cartesian product
      $oldrec[$alias] = $newdata;
    }
    $env["RES"][0][$oldidx] = $oldrec;
  }
  return null;
}


function _db_do_datasplit($data, $env) {
  global $SCHEMA;
  $res = $data;
  foreach($env["KEYS"] as $field => $dummy) {
    foreach($SCHEMA as $table => $tdef) {
      if($data_split = @($tdef["FIELDS"][$field]["DATA_SPLIT"])) {


	$delim = $data_split[0];
	$newfield = $data_split[1];
	foreach($data as $idx => $rec) {
	  $list = @$rec[$field];
	  $sub_data = array();
	  if($list) {
	    foreach(explode($delim, $list) as $item) {
	      $subrec = array($newfield => $item);
	      //echo "newfield='$newfield' item='$item'<br>\n";
	      // try to get the whole record from the pool if it exists
	      if(($test = @$rec["${field}_pool"])) {
		//echo "AHA....."; print_r($test); echo "<br>\n";
		foreach($test as $testrec) {
		  if($testrec[$newfield] == $item) {
		    //echo "Ping.....<br>\n";
		    $subrec = $testrec;
		    break;
		  }
		}
	      }
	      $sub_data[] = $subrec;
	    }
	  }
	  $rec[$field] = $sub_data;
	  $res[$idx] = $rec;
	}



	break;
      }
    }
  }
  return $res;
}

function _db_read($qstruct) {
  global $ERROR;
  global $debug;
  if($debug) { echo "_db_read raw data: "; print_r($qstruct); echo "<br>\n"; }
  $q2 = _db_mangle_query($databases, $qstruct);
  // currently only 1 database supported
  $database = key($databases);
  $mainquery = _db_make_query($database, $subqs, $q2);
  $query = $mainquery;
  if($subqs) {
    foreach($subqs as $name => $tuple) {
      $joinfields = $tuple[0];
      $subquery = $tuple[1];
      if($joinfields) {
	if(is_array($joinfields))
	  $joinfields = implode(",", $joinfields);
	$subquery = "select * from ($mainquery) mainquery join ($subquery) subquery using($joinfields)";
      }
      if($debug) $subquery = "/* alias='$name' joinfields='$joinfields' */ $subquery";
      $query .= "; $subquery";
    }
  }

  $env = array("DB" => $database, "ARG" => $subqs, "CB_PROCESS" => "_db_cb_make_keys");
  $ok = _db_multiquery($env, false, $query, array("_db_cb_process_data", "_db_cb_process_subdata"));
  if(!$ok) {
    if(!$ERROR)
      $ERROR = "unknown retrieval error";
    if($debug) echo "oops............................ $ERROR <br>\n";
    return array();
  }
  $res = array_shift($env["RES"]);
  if($res) {
    $res = _db_do_datasplit($res, $env);
    if($debug) echo "got data.<br>\n";
  }
  return $res;
}


////////////////////////////////////////////////////////////////////////

/*
 * Referential integritiy.
 * This is not db-specific, but e.g. the mysql driver calls back to this
 * because mysql lacks built-in referential integrity.
 * Other drivers might use it as well.
 */

function _db_cb_check_test(&$env, $resultset) {
  $data = _db_cb_process_data($env, $resultset);
  $ok = $data[0]["test"];
  if(!$ok) {
    global $ERROR;
    $ERROR = "UNIQUE condition violated";
    global $debug; if($debug) echo "CHECK ---------- FAILED<br>\n";
    $env["ERROR"] = true;
  }
  return $data;
}

function _db_cb_update(&$env, $resultset) {
  return null;
}

function _db_check_field($table, $field, $value) {
  global $ERROR;
  global $SCHEMA;
  if(!($fdef = @$SCHEMA[$table]["FIELDS"][$field])) {
    $ERROR = "undefined field '$field' in table '$table'";
    return false;
  }
  if(($check = @$fdef["BETWEEN"])) {
    if($value < $check[0] || $value > $check[1]) {
      $ERROR = "BETWEEN: value '$value' for '$field' exceeds range [".$check[0].",".$check[1]."]";
      return false;
    }
  }
  if(($check = @$fdef["LENGTH"])) {
    $len = strlen($value);
    if($len < $check[0] || $len > $check[1]) {
      $ERROR = "LENGTH: length of value '$value' for '$field' exceeds range [".$check[0].",".$check[1]."]";
      return false;
    }
  }
  if(($check = @$fdef["REGEX"])) {
    if(!preg_match("/\A$check\Z/s", $value)) {
      $ERROR = "REGEX: value '$value' for '$field' does not match '/\A$check\Z/'";
      return false;
    }
  }
  return true;
}

function _db_check_unique(&$stack, $table, $rec) {
  global $ERROR;
  global $SCHEMA;
  if(($uniques = @$SCHEMA[$table]["UNIQUE"])) {
    foreach ($uniques as $raw_fields) {
      $cond = array();
      $fields = explode(",", $raw_fields);
      foreach($fields as $field) {
	$cond[$field] = $rec[$field];
      }
      $qstruct_inner =
	array(
	      "TABLE" => array($table),
	      "FIELD" => $fields,
	      "COND" => $cond,
	      "ORDER" => array(),
	      "START" => 0,
	      "COUNT" => 0,
	      );
      $qstruct =
	array(
	      "TABLE" => array(),
	      "FIELD" => array("test" => array("not exists" => $qstruct_inner)),
	      "COND" => array("EMPTY" => true),
	      "ORDER" => array(),
	      "START" => 0,
	      "COUNT" => 0,
	      "CB" => "_db_cb_check_test",
	      "TEST_MODE" => "UNIQUE",
	      );
      $stack["TEST"][] = $qstruct;
    }
  }
  return true;
}

function _db_check_ref(&$stack, $table, $field, $value) {
  global $SCHEMA;
  global $ERROR;
  $ref = @$SCHEMA[$table]["FIELDS"][$field]["REFERENCES"];
  if(!$ref)
    return true;
  foreach($ref as $foreign => $props) {
    $all = preg_split("/\s*\.\s*/s", $foreign, 2);
    $ftable = $all[0];
    $ffield = $all[1];
    if(!($ffdef = $SCHEMA[$ftable]["FIELDS"][$ffield])) {
      $ERROR = "REFERENCES: foreign field '$ffield' of table '$ftable' does not exist";
      return false;
    }
    $qstruct_inner =
      array(
	    "TABLE" => array($ftable),
	    "FIELD" => array($ffield),
	    "COND" => array($ffield => $value),
	    "ORDER" => array(),
	    "START" => 0,
	    "COUNT" => 0,
	    );
    $qstruct =
      array(
	    "TABLE" => array(),
	    "FIELD" => array("test" => array("exists" => $qstruct_inner)),
	    "COND" => array("EMPTY" => true),
	    "ORDER" => array(),
	    "START" => 0,
	    "COUNT" => 0,
	    "CB" => "_db_cb_check_test",
	    "TEST_MODE" => "EXISTS",
	    );
    $stack["TEST"][] = $qstruct;
  }
  return true;
}

function _db_check_xref(&$stack, $table, $field, $value, $mode, $idcond) {
  global $SCHEMA;
  global $ERROR;
  $xref = @$SCHEMA[$table]["XREF"][$field];
  if(!$xref)
    return true;
  foreach($xref as $xtuple) {
    $xtable = $xtuple[0];
    $xfield = $xtuple[1];
    $xprops = $xtuple[2];
    if(!($xfdef = @$SCHEMA[$xtable]["FIELDS"][$xfield])) {
      $ERROR = "REFERENCES: foreign field '$xfield' of table '$xtable' does not exist";
      return false;
    }
    foreach($xprops as $xprop) {
      $use = strpos($xprop, "set null") ? null : $value;
      $qstruct =
	array(
	      "TABLE" => $xtable,
	      "DATA" => array(array($xfield => $use)),
	      "COND" => array("$xfield in" => $idcond),
	      "CB" => "_db_cb_update",
	      "TEST_MODE" => "DO_UPDATE",
	      );
      switch($xprop) {
      case "on delete cascade":
	if($mode == "DELETE") {
	  $qstruct["MODE"] = $mode;
	}
	break;
      case "on delete set null":
	if($mode == "DELETE") {
	  $qstruct["MODE"] = "UPDATE";
	}
	break;
      case "on update set null":
      case "on update cascade":
	if($mode == "UPDATE" || $mode == "REPLACE") {
	  $qstruct["MODE"] = $mode;
	}
	break;
      }
      if(@$qstruct["MODE"]) {
	$stack["UPDATE"][] = $qstruct;
      }
    }
  }
  return true;
}

function _db_push_subdata(&$stack, $table, $field, $subdata, $master_row) {
  global $SCHEMA;
  $fdef = @$SCHEMA[$table]["FIELDS"][$field];
  if(!$fdef || !@$fdef["VIRTUAL"] || !($subdef = @$fdef["SUB_DATA"]) || !db_access_field($table, $field, "w")) {
    return;
  }

  // augment subdata with values from $master_row
  $subtable = $subdef["TABLE"];
  $newdata = array();
  foreach($subdata as $subidx => $subrow) {
    $newrow = array_merge($master_row, $subrow);
    $newdata[$subidx] = $newrow;
  }
  $newdata = _db_prepare_data($subtable, $newdata);

  $qstruct =
    array(
	  "TABLE" => $subtable, 
	  "MODE" => "REPLACE",
	  "DATA" => $newdata,
	  "CB" => "_db_cb_update",
	  "TEST_MODE" => "DO_UPDATE",
	  );
  $stack["UPDATE"][] = $qstruct;
  //echo "subdata: $table $field ".count($stack).": "; print_r($qstruct); echo "<br>\n";

  if(true || @$fdef["SUB_DELETE"]) { // generate qstruct for deletion of missing subrecords
    $cond = array();
    if(($joinfields = $subdef["JOINFIELDS"])) {
      $joinfields = split(",", $joinfields);
      foreach($joinfields as $field) {
	$cond[$field] = $master_row[$field];
      }
    }
    if($newdata) {
      $subcond = array();
      $subprimary = _db_primary($subtable);
      $subunique = split(",", _db_unique($subtable));
      foreach($newdata as $idx => $row) {
	if($test = @$row[$subprimary]) {
	  $subcond[] = array($subprimary => $test);
	} else {
	  $subsubcond = array();
	  foreach($subunique as $field) {
	    if(!in_array($field, $joinfields)) {
	      $subsubcond[$field] = @$row[$field];
	    }
	  }
	  $subcond[] = $subsubcond;
	}
      }
      $cond["not"] = array($subcond);
    }
    $qstruct =
      array(
	    "TABLE" => $subtable,
	    "MODE" => "DELETE",
	    "COND" => $cond,
	    "CB" => "_db_cb_update",
	    "TEST_MODE" => "DO_SET_DELETE",
	    );
    $stack["UPDATE"][] = $qstruct;
  }
}

////////////////////////////////////////////////////////////////////////

// updating

function _db_mangle_update($qstruct) {
  global $ERROR;
  global $SCHEMA;
  global $SYNTAX_UPDATE;
  if(($error = db_check_syntax($qstruct, $SYNTAX_UPDATE))) {
    echo "update qstruct error: $error<br>\n";
    global $debug; if($debug) { echo "syntax: "; print_r($qstruct); echo"<br>\n";}
    $ERROR = $error;
    return null;
  }
  $table = $qstruct["TABLE"];
  foreach($SCHEMA[$table]["FIELDS"] as $field => $fdef) {
    if(@$fdef["VIRTUAL"]) {
      continue;
    }
    if(@$fdef["AUTO_FIELD"] || db_access_field($table, $field, "w")) {
      $qstruct["UPDATE_FIELDS"][] = $field;
    }
    $qstruct["ALL_FIELDS"][] = $field;
  }
  return $qstruct;
}

function _db_prepare_data($table, $data) {
  global $SCHEMA;
  global $USER;
  global $FROM;

  //$primary = _db_primary($table);
  $version = _db_extfield($table, "version");
  $modified_from = _db_extfield($table, "modified_from");
  $modified_by = _db_extfield($table, "modified_by");

  $newdata = array();
  foreach($data as $idx => $row) {
    $newrow = $row;
    // initialize "automatic" fields
    $newrow[$version] = null;
    $newrow[$modified_from] = $FROM;
    $newrow[$modified_by] = $USER;
    foreach ($row as $field => $value) {
      // handle splitted fields
      $fdef = @$SCHEMA[$table]["FIELDS"][$field];
      if(($extra = @$fdef["DATA_SPLIT"]) && is_array($value)) {
	$delim = $extra[0];
	$key = $extra[1];
	$newarray = array();
	foreach($value as $rec) {
	  $newarray[] = $rec[$key];
	}
	$newrow[$field] = implode($delim, $newarray);
      }
    }
    $newdata[$idx] = $newrow;
  }
  return $newdata;
}

function _db_update(&$qstruct) {
  global $ERROR;
  global $SCHEMA;
  global $debug;
  $ERROR = "";
  $tp_table = $qstruct["TABLE"];
  // robustness against wrong calling with *_tp
  if(_db_temporal($tp_table, $table)) {
    $qstruct["TABLE"] = $table;
  }

  // handle callback hooks
  foreach(array("CB_BEFORE", "CB_BEFORE_" . $qstruct["MODE"]) as $cb) {
    if(($test = @$SCHEMA[$table][$cb])) {
      if($debug) echo "CALLBACK $cb -> $test<br>\n";
      $qstruct["DATA"] = $test($table, $qstruct["DATA"]);
      if($ERROR)
	return null;
    }
  }
  
  $database = _db_database($table);
  if(!@$qstruct["RAW_MODE"]) {
    $qstruct["DATA"] = _db_prepare_data($table, $qstruct["DATA"]);
  }
  $query = _db_make_update($database, $qstruct, $cb_list);
  if(!$query && !$ERROR) {
    $ERROR = "internal error: cannot create SQL statements";
  }
  if($ERROR) {
    return null;
  }

//echo "-----------QUERY: $query;<br>\n";
//return true;
  $env = array("DB" => $database, "CB" => "_db_cb_process_data");
  $ok = _db_multiquery($env, true, $query, $cb_list);

  if(!$ok || $ERROR) {
    if(!$ERROR)
      $ERROR = "internal error: cannot execute SQL statements";
    return null;
  }
  
  // handle callback hooks
  foreach(array("CB_AFTER", "CB_AFTER_" . $qstruct["MODE"]) as $cb) {
    if(($test = @$SCHEMA[$table][$cb])) {
      if($debug) echo "CALLBACK $cb -> $test<br>\n";
      $qstruct["DATA"] = $test($table, $qstruct["DATA"]);
      if($ERROR)
	return null;
    }
  }
  return true;
}

////////////////////////////////////////////////////////////////////////

// generate condition-clause for identifying records

function _db_make_idcond($table, $row) {
  global $ERROR;
  $primary = _db_primary($table);
  $cond = array();
  if($row && array_key_exists($primary, $row)) {
    $cond[$primary] = $row[$primary];
  } else {
    $keys = split(",", _db_unique($table));
    if(!$keys) {
      $ERROR = "primary key '$primary' is missing in your data, and other keys are not available";
      return null;
    }
    foreach($keys as $key) {
      if(!$row || !array_key_exists($key, $row)) {
	$ERROR = "id-key '$key' is missing in your data, and primary key '$primary' is also missing";
	return null;
      }
      $cond[$key] = $row[$key];
    }
  }
  return $cond;
}

////////////////////////////////////////////////////////////////////////

// interface

function db_read($tp_table, $fields, $cond, $order, $start, $count) {
  $qstruct = 
    array(
	  "TABLE" => $tp_table,
	  "FIELD" => $fields ? $fields : array(),
	  "COND" => $cond ? $cond : array(),
	  "ORDER" => $order ? $order : "",
	  "START" => $start ? (int)$start : 0,
	  "COUNT" => $count ? (int)$count : 0,
	  );
  return _db_read($qstruct);
}

function db_insert($tp_table, $data) {
  $qstruct =
    array(
	  "TABLE" => $tp_table,
	  "MODE" => "INSERT",
	  "DATA" => $data,
	  "CB" => "_db_cb_update",
	  "TEST_MODE" => array("EXISTS", "DO_UPDATE"),
	  );
  return _db_update($qstruct);
}

// only update pre-existing data, never create new records
function db_update($tp_table, $data) {
  $qstruct =
    array(
	  "TABLE" => $tp_table,
	  "MODE" => "UPDATE",
	  "DATA" => $data,
	  "CB" => "_db_cb_update",
	  "TEST_MODE" => array("EXISTS", "DO_UPDATE"),
	  );
  return _db_update($qstruct);
}

// update if already exists, otherwise create
function db_replace($tp_table, $data) {
  $qstruct =
    array(
	  "TABLE" => $tp_table,
	  "MODE" => "REPLACE",
	  "DATA" => $data,
	  "CB" => "_db_cb_update",
	  "TEST_MODE" => array("EXISTS", "DO_UPDATE"),
	  );
  return _db_update($qstruct);
}

function db_delete($tp_table, $data) {
  $qstruct =
    array(
	  "TABLE" => $tp_table,
	  "MODE" => "DELETE",
	  "DATA" => $data,
	  "CB" => "_db_cb_update",
	  "TEST_MODE" => array("EXISTS", "DO_UPDATE"),
	  );
  return _db_update($qstruct);
}

?>
