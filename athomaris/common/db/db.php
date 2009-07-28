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

/* Remove fields we must not process due to permissions
 */
function _db_strip_permissions($qstruct, $fields) {
  $new = array();
  if($fields) {
    foreach($fields as $alias => $field) {
      if(is_string($alias) && is_string($field) && $alias != $field) { // don't check named subexpressions for REALNAME
	$new[$alias] = $field;
	continue;
      }
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

function _db_stripcond(&$cond, $db_reduce) {
}

function _db_stripfields(&$fieldlist, $db_reduce) {
  foreach($fieldlist as $alias => $field) {
    if(is_string($alias) && is_string($field)) {
      // NYI
      continue;
    } elseif(is_string($field)) {
      // remove functions such as min(), max(), count() etc
      $field = preg_replace("/\A\s*[a-zAZ]+\s*\(\s*([a-zA-Z0-9])\s*\)\Z/", "\\1", $field);
      if($db_reduce && !_db_check($db_reduce, null, $field)) {
	unset($fieldlist[$alias]);
      }
    } elseif(is_array($field)) {
      $fieldlist[$alias] = _db_homogenize($field, $db_reduce);
    } else {
      die("bad qstruct (FIELD) - this should not happen\n");
    }
  }
}

function _db_homogenize_field(&$homo, $qstruct, $base_table, $db_reduce) {
  global $SCHEMA;
  $homo["FIELD"] = @$qstruct["FIELD"];
  if(($test = @$qstruct["FIELD"]) && is_string($test)) {
    $homo["FIELD"] = split(",", $test);
  }
  $makeall = !($test = @$homo["FIELD"]) || !count($test);
  if(is_array($homo["FIELD"]) && array_search("*", $homo["FIELD"]) !== false) {
    $makeall = true;
    // remove the star field
    $old = $homo["FIELD"];
    $homo["FIELD"] = array();
    foreach($old as $idx => $val) {
      if(is_string($val) && $val == "*")
	continue;
      $homo["FIELD"][$idx] = $val;
    }
  }
  if($makeall) { // make all field names explicit, avoid using "*" because there might be access restrictions
    foreach($base_table as $table) {
      foreach($SCHEMA[$table]["FIELDS"] as $field => $fdef) {
	if(!@$fdef["VIRTUAL"] && db_access_field($table, $field, "r")) {
	  //echo "listing '$table' '$field'<br>\n";
	  $homo["FIELD"][$field] = $field;
	}
      }
    }
  }
}

/* Replace comma-separated strings by exploded arrays.
 * When $db_reduce is non-null, remove anything not belonging
 * to that databases.
 */
function _db_homogenize($qstruct, $db_reduce = null) {
  global $SCHEMA;
  global $debug;
  if($debug) { echo "homogenize: "; print_r($qstruct); echo"<br>\n";}
  $homo = $qstruct;
  if(($test = @$qstruct["TABLE"]) && is_string($test)) {
    $homo["TABLE"] = split(",", $test);
  }
  $homo["BASE_TABLE"] = array();
  foreach($homo["TABLE"] as $alias => $tp_table) {
    if(is_string($tp_table)) {
      _db_temporal($tp_table, $table);
      if($db_reduce && !_db_check($db_reduce, $table)) {
	unset($homo["TABLE"][$alias]);
	continue;
      }
      if(is_int($alias))
	$alias = $tp_table;
      $homo["BASE_TABLE"][$alias] = $table;
    } elseif(is_array($tp_table)) {
      $homo["TABLE"][$alias] = _db_homogenize($tp_table, $db_reduce);
    } else {
      die("bad qstruct (TABLE) - this should not happen\n");
    }
  }

  _db_homogenize_field($homo, $qstruct, $homo["BASE_TABLE"], $db_reduce);
  $homo["FIELD"] = _db_strip_permissions($homo, $homo["FIELD"]);
  _db_stripfields($homo["FIELD"], $db_reduce);

  if($test = @$qstruct["AGG"]["FIELD"]) {
    _db_homogenize_field($homo["AGG"], $qstruct["AGG"], $homo["BASE_TABLE"], $db_reduce);
    _db_stripfields($homo["AGG"]["FIELD"], $db_reduce);
  }
  if($test = @$qstruct["AGG"]["GROUP"]) {
    $homo["AGG"]["GROUP"] = split(",", $test);
    _db_stripfields($homo["AGG"]["GROUP"], $db_reduce);
  }
  if($test = @$qstruct["ORDER"]) {
    $homo["ORDER"] = split(",", $test);
    _db_stripfields($homo["ORDER"], $db_reduce);
  }
  _db_stripcond($homo["COND"], $db_reduce);
  if($debug) { echo "homogenized: "; print_r($homo); echo"<br>\n";}
  return $homo;
}

/* Make POOL_DATA and SUB_DATA explicit. 
 * In contrast to _db_homogenize(), this will not be called
 * recursively.
 */
function _db_add_schema($qstruct, $db_reduce = null) {
  global $SCHEMA;
  $homo = $qstruct;
  foreach($qstruct["BASE_TABLE"] as $table) {
    if(($fields = @$SCHEMA[$table]["FIELDS"])) {
      foreach($fields as $field => $fdef) {
	if(($test = @$fdef["POOL_DATA"])) {
	  $newfield = "${field}_pool";
	  $homo["FIELD"][$newfield] = _db_homogenize($test, $db_reduce);
	}
	if(($test = @$fdef["SUB_DATA"])) {
	  $homo["FIELD"][$field] = _db_homogenize($test, $db_reduce);
	}
      }
    }
  }
  return $homo;
}

/* Compute reasonable defaults if JOIN_ON has been omitted
 * => leads to natural join over all possibilities which could be joined.
 */
function _db_mangle_joins($qstruct) {
  global $SCHEMA;
  if(!@$qstruct["JOINFIELDS"]) { // make defaults: find all equally-named fields
    $candidate_fields = array();
    $joinfields = array();
    foreach($qstruct["TABLE"] as $alias => $tp_table) {
      if(is_array($tp_table)) {
	// currently, sub-queries are not taken into account. this could be improved.
	continue;
      }
      _db_temporal($tp_table, $table);
      if(!$fields = @$SCHEMA[$table]["FIELDS"])
	die("table $table has no FIELDS definition");
      $myfields = array_keys($fields);
      foreach(array_intersect($candidate_fields, $myfields) as $field) {
	$joinfields[$field] = $field;
      }
      $candidate_fields = array_merge($candidate_fields, $myfields);
    }
    $qstruct["JOINFIELDS"] = $joinfields;
  }
  if(is_string($qstruct["JOINFIELDS"])) {
    $qstruct["JOINFIELDS"] = split(",", $qstruct["JOINFIELDS"]);
  }
  if(!@$qstruct["JOIN_ON"]) { // make defaults: all pairs of tables are joined whenever possible
    // first, compute the set of all occurring aliases
    $alias_names = array();
    foreach($qstruct["TABLE"] as $alias => $tp_table) {
      if(is_array($tp_table)) {
	// currently, sub-queries are not taken into account. this could be improved.
	continue;
      }
      if(!is_string($alias))
	$alias = $tp_table;
      $alias_names[$alias] = $tp_table;
    }
    // now, join over all fields which are joinable -> result table will be as restricted as possible
    $join_on = array();
    foreach($qstruct["JOINFIELDS"] as $field) {
      foreach($alias_names as $alias1 => $tp_table1) {
	_db_temporal($tp_table1, $table1);
	foreach($alias_names as $alias2 => $tp_table2) {
	  if($alias1 == $alias2) {
	    break; // only run over triangular matrix
	  }
	  _db_temporal($tp_table2, $table2);
	  if(!@$SCHEMA[$table1]["FIELDS"][$field] || !@$SCHEMA[$table2]["FIELDS"][$field]) {
	    continue;
	  }
	  $joinstring = "$alias1.$field=$alias2.$field";
	  $join_on[] = $joinstring;
	}
      }
    }
    $qstruct["JOIN_ON"] = $join_on;
  }
  // Compute the set of all occurring fields. Needed for VIEW.
  $schema_fields = array();
  foreach($qstruct["TABLE"] as $alias => $tp_table) {
    if(is_array($tp_table)) {
      // currently, sub-queries are not taken into account. this could be improved.
      continue;
    }
    _db_temporal($tp_table, $table);
    foreach($SCHEMA[$table]["FIELDS"] as $field => $fdef) {
      if(!$field || @$schema_fields[$field]) {
	continue;
      }
      if(preg_match("/_pool\Z/", $field)) {
	continue;
      }
      $schema_fields[$field] = $fdef;
    }
  }
  // Construct some dummy values for ad-hoc fields
  $test = @$qstruct["FIELD"];
  if(@$qstruct["AGG"])
    $test = @$qstruct["AGG"]["FIELD"];
  foreach($test as $alias => $expr) {
    if(!is_string($alias) || @$schema_fields[$alias]) {
      continue; // already known
    }
    if(preg_match("/_pool\Z/", $alias)) {
      continue;
    }
    $fdef =
      array( // currently type-agnostic. TODO: improve this!
	    "TYPE" => "text",
	    "ACCESS" => "R",
	    "MINLEN" => 0,
	    "MAXLEN" => 9999,
	    "SIZE" => 40,
	    "SQL_TYPE" => "text",
	    "DISPLAY_TYPE" => "string",
	    "TPL_DISPLAY" => "display_text",
	    "EXTRA_FIELD" => array(),
	    );
    $schema_fields[$alias] = $fdef;
  }
  $qstruct["SCHEMA_FIELDS"] = $schema_fields;
  return $qstruct;
}

/* Prepare a qstruct, unify its internal format,
 * add some defaults.
 */
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
  $homo = _db_mangle_joins($homo);
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

/* Handle type conversion and built-in
 * Splitting.
 * TODO: the old DATA_SPLIT is a special case of
 * the newer CB_CONV_READ, replace it!
 */
function _db_do_datasplit($data, $env) {
  global $SCHEMA;
  $res = $data;
  foreach($env["KEYS"] as $field => $dummy) {
    foreach($SCHEMA as $table => $tdef) {
      $fdef = @$tdef["FIELDS"][$field];
      // callbacks for conversion
      if($cb = @$fdef["CB_CONV_READ"]) {
	foreach($data as $idx => $rec) {
	  $res[$idx][$field] = $cb($table, $field, $rec[$field]);
	}
      }

      if(($data_split = @$fdef["DATA_SPLIT"])) {

	$delim = $data_split[0];
	$newfield = $data_split[1];
	foreach($res as $idx => $rec) {
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

function _db_push_stack(&$stack, $mode, $qstruct, $info) {
  global $debug;
  if($debug) {
    global $ERROR;
    $old_debug = $debug;
    $debug = false;
    $test = @$qstruct["MODE"] ? _db_update($qstruct) : _db_read($qstruct);
    $debug = $old_debug;
    if(!$test) {
      echo "-------BAD QSTRUCT-------($ERROR)\n";
      $ERROR = "";
    }
    echo "$info: push stack $mode qstruct="; print_r($qstruct); echo "<br>\n";
  }
  $stack[$mode][] = $qstruct;
}

function _db_check_unique(&$stack, $tp_table, $rec) {
  global $ERROR;
  global $SCHEMA;
  global $debug;
  _db_temporal($tp_table, $table);
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
      _db_push_stack($stack, "TEST", $qstruct, "_db_check_unique");
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
    _db_push_stack($stack, "TEST", $qstruct, "_db_check_ref");
  }
  return true;
}

function _db_check_xref(&$stack, $table, $field, $value, $mode, $idcond) {
  global $SCHEMA;
  global $ERROR;
  global $debug;
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
	      "ALLOW_MANY" => true,
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
	_db_push_stack($stack, "UPDATE", $qstruct, "_db_check_xref");
      }
    }
  }
  return true;
}

function _db_push_subdata(&$stack, $table, $field, $subdata, $master_row) {
  global $SCHEMA;
  global $debug;
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
  if($debug) { echo "_db_push_subdata: push stack UPDATE: "; print_r($qstruct); echo "<br>\n"; }
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
	$ok = true;
	foreach(split(",", $subprimary) as $subp) {
	  if(!array_key_exists($subp, $row)) {
	    $ok = false;
	    break;
	  }
	}
	$subsubcond = array();
	if($ok) {
	  foreach(split(",", $subprimary) as $subp) {
	    $subsubcond[$subp] = @$row[$subp];
	  }
	} else {
	  foreach($subunique as $field) {
	    if(!in_array($field, $joinfields)) {
	      $subsubcond[$field] = @$row[$field];
	    }
	  }
	}
	$subcond[] = $subsubcond;
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
    _db_push_stack($stack, "UPDATE", $qstruct, "_db_push_subdata");
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
      // callbacks for conversion
      if($cb = @$fdef["CB_CONV_WRITE"]) {
	$newrow[$field] = $cb($table, $field, $newrow[$field]);
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
  if($debug) { echo "_db_update: "; print_r($qstruct); echo "<br>\n"; }
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

  if($debug) echo "-----------QUERY: $query;<br>\n";

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
  global $debug;
  $primary = _db_primary($table);
  if($debug) { echo "_db_make_idcond table='$table' row="; print_r($row); echo "<br>\n"; }
  $ok = true;
  foreach(split(",", $primary) as $pr) {
    if(!array_key_exists($pr, @$row)) {
      $ok = false;
      break;
    }
  }
  $cond = array();
  if($row && $ok) {
    foreach(split(",", $primary) as $pr) {
      $cond[$pr] = @$row[$pr];
    }
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

function db_read($tp_table, $fields = array(), $cond = array(), $order = "", $start = 0, $count = 0) {
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
