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

  // Driver for mysql. Contains all specific code.

////////////////////////////////////////////////////////////////////////

// queries

function mysql_do_open($host, $user, $passwd, $base = null) {
  global $ERROR;
  if($base) {
    $mysqli = new mysqli($host, $user, $passwd, $base);
  } else {
    $mysqli = new mysqli($host, $user, $passwd);
  }
  if(($ERROR = $mysqli->error)) {
    return null;
  }
  return $mysqli;
}

function mysql_do_close($mysqli) {
  $mysqli->close();
}

function mysql_raw_query($mysqli, $query) {
  global $ERROR;
  $res = $mysqli->query($query);
  if(!$res) {
    $ERROR = $mysqli->error;
  }
  return $res;
}

function mysql_multiquery(&$env, $mysqli, $query, $cb_list) {
  global $ERROR;
  global $debug;

  $cb = "";
  if($cb_list)
    $cb = array_shift($cb_list);

  $next = $mysqli->multi_query($query);
  if(!$next) {
    $ERROR .= $mysqli->error;
    _db_close($mysqli);
    return false;
  } elseif($cb) {
    $next = $mysqli->use_result();
    //print_r($next);
    if($debug) echo "first callback '$cb'<br>\n";
    $next = $cb($env, $next);
  }
  $env["RES"][] = $next;
  while($mysqli->more_results() && !@$env["ERROR"]) {
    if($cb_list)
      $cb = array_shift($cb_list);
    $next = $mysqli->next_result();
    if(!$next) {
      $ERROR .= $mysqli->error;
      _db_close($mysqli);
      return false;
    } elseif($cb) {
      $next = $mysqli->use_result();
      if($debug) echo "next callback '$cb'<br>\n";
      $next = $cb($env, $next);
    }
    $env["RES"][] = $next;
  }
  if(@$env["ERROR"]) {
    _db_close($mysqli);
    return false;
  }
  return true;
}

function mysql_cb_process_data(&$env, $resultset) {
  if(!is_object($resultset)) {
    global $ERROR;
    global $debug; if($debug) echo "bad resultset: "; print_r($resultset); echo "<br>\n";
    $ERROR = "internal error: no resultset available";
    return null;
  }
  $cb = @$env["CB_PROCESS"];
  $data = array();
  while($row = $resultset->fetch_assoc()) {
    //echo "Encountered row:"; print_r($row);
    if($cb) {
      //echo "cb_process=$cb<br>\n";
      $row = $cb($env, $row);
    }
    $data[] = $row;
  }
  $resultset->close();
  return $data;
}

function mysql_do_query($mysqli, $query) {

  $resultset = mysql_raw_query($mysqli, $query);

  if(isset($resultset) && is_object($resultset)) {
    return mysql_cb_process_data($dummy, $resultset);
  }
  return null;
}

////////////////////////////////////////////////////////////////////////

// generate mysql statements for reading

function _mysql_add_deleted($qstruct) {
  $add1 = "";
  $add2 = "";
  foreach($qstruct["TABLE"] as $alias => $tp_table) {
    if(_db_temporal($tp_table, $table)) {
      if(is_int($alias))
	$alias = $tp_table;
      if($add1) {
	$add1 .= " or ";
	$add2 .= " or ";
      }
      $realtable = _db_realname($table);
      if(!$realtable)
	die("cann find realtable for table '$table'\n");
      $realtable = _db_2temporal($realtable);
      $id = _db_extfield($table, "id");
      $realid = _db_realname($table, $id);
      if(!$realid)
	die("cann find realid for field '$id' in table '$table'\n");
      $version = _db_realname($table, _db_extfield($table, "version"));
      $add1 .= "$version < (select max($version) from $realtable old where old.$id = $alias.$id)";
      $add2 .= _db_realname($table, _db_extfield($table, "deleted"));
    }
  }
  if($add1) {
    return ", $add1 as outdated, $add2 as deleted";
  }
  return "";
}

function _mysql_make_select(&$subqs, $qstruct, $is_empty) {
  global $SCHEMA;
  $table = $qstruct["TABLE"];
  $res = "";
  if(($pair = @$qstruct["AGG"])) { // treat aggregate functions
    if(($list = @$pair["FIELD"])) {
      foreach($list as $alias => $field) {
	//echo "'$alias' / '$field'<br>\n";
	if($res)
	  $res .= ", ";
	if(is_string($alias) && is_string($field) && $alias != $field) {
	  $realfield = $field;
	} else {
	  $realfield = _db_realname($table, $field);
	  if(!$realfield)
	    die("cannot find aggregate field '$field' in table '$table'\n");
	}
	$res .= $realfield;
	if(is_string($alias)) {
	  $res .= " as $alias";
	}
      }
    }
  } elseif(($list = @$qstruct["FIELD"])) { // treat ordinary queries
    foreach($list as $alias => $field) {
      if(is_string($field)) {
	if($is_empty) {
	  if($res)
	    $res .= ", ";
	  $init = null;
	  foreach($qstruct["BASE_TABLE"] as $table) {
	    if(($init = @$SCHEMA[$table]["FIELDS"][$field]["DEFAULT"]))
	      break;
	  }
	  if(is_null($init)) {
	    $init = "null";
	  }
	  $res .= $init;
	  if(!$alias || !is_string($alias)) {
	    $realfield = _db_realname($table, $field);
	    if(!$realfield)
	      die("cannot find alias field '$init' in table '$table'\n");
	    $alias = $realfield;
	  }
	} elseif(is_string($alias) && $alias != $field) { // named subexpression
	  if($res)
	    $res .= ", ";
	  //echo ".......[$field/$alias].......<br>\n";
	  $res .= "($field) as $alias";
	  continue;
	} else { // normal case
	  if($res)
	    $res .= ", ";
	  //echo ".......($field/$alias).......<br>\n";
	  $realfield = _db_realname($table, $field);
	  if(!$realfield)
	    die("cannot find real fieldname for '$field'\n");
	  $res .= $realfield;
	}
      } elseif(!@$field["TABLE"] && !@$field["UNION"]) { // we have a boolean sub-expression
	if($res)
	  $res .= ", ";
	$subexpr = _mysql_make_where($table, $field);
	$res .= "($subexpr)";
      } elseif(is_array($field) && (@$field["TABLE"] || !@$field["UNION"])) { // we have a sub-query
	global $debug; if($debug) { echo "subquery: "; print_r($field); echo"<br>\n";}
	$dummy = array();
	$subquery = mysql_make_query($dummy, $field);
	$joinfields = @$field["JOINFIELDS"];
	if(!$joinfields && !preg_match("/_pool$/", $alias)) { // we can embed it as ordinary subquery (yields an ordinary field, not a subtable) TODO: make the difference clear by means of an exlicit SUB_TABLE expression, and use it for *_pool also!
	  if($res)
	    $res .= ", ";
	  $res .= "($subquery)";
	  if(is_string($alias)) {
	    $res .= " as $alias";
	  }
	} else { // We have a true subtable which cannot be executed in a single query. This is non-standard semantics!
	  if($debug) { echo "pushback subquery: alias='$alias' joinfields='$joinfields'<br>\n";}
	  $subqs[$alias] = array($joinfields, $subquery, $alias);
	  continue;
	}
      } else { // error
	die("this should not happen.");
      }
      if($is_empty || (is_string($alias) && is_string($field) && $alias != _db_realname($table, $field))) {
	$res .= " as $alias";
      }
    }
    // add pseudo fields indicating "outdated" and "deleted" (only for temporal tables in the join)
    $res .= _mysql_add_deleted($qstruct);
  }
  if(!$res) {
    //echo "PADDING '*'<br>\n";
    $res = "*";
  }
  //echo "got '$res'<br>\n";
  return $res;
}

function _mysql_make_group($qstruct) {
  $res = "";
  if($list = @$qstruct["AGG"]["GROUP"]) {
    foreach($list as $alias => $field) {
      if($res)
	$res .= ", ";
      $res .= $field; // no _db_realname() should be necessary
    }
  }
  return $res;
}

function _mysql_make_from($qstruct, &$joinconditions) {
  global $SCHEMA;
  global $ERROR;
  global $debug; if($debug) { echo "make_from: "; print_r($qstruct); echo"<br>\n";}
  $res = "";
  $joinconditions = "";
  if(!$list = @$qstruct["TABLE"]) {
    print_r($qstruct); echo "<br>\n";
    die("invalid TABLE information");
  }
  if(is_string($list))
    die("table '$list' has not been converted to array - internal homogenization error\n");
  $translate = array();
  $base_table = array();
  foreach($list as $alias => $tp_table) {
    if($res)
      $res .= ", ";
    if(is_array($tp_table)) {
      global $debug; if($debug) { echo "subquery: "; print_r($tp_table); echo"<br>\n";}
      $dummy = array();
      $subquery = mysql_make_query($dummy, $tp_table);
      if(!is_string($alias))
	die("an alias for a subtable is missing");
      $translate[$alias] = $alias;
      $res .= "($subquery) $alias";
    } else {
      $is_tp = _db_temporal($tp_table, $table);
      $realtable = _db_realname($table);
      if($is_tp) {
	$realtable = _db_2temporal($realtable);
      }
      if(!$realtable)
	die("no realtable set .... this should never happen\n");
      if(is_string($alias)) {
	$base_table[$alias] = $table;
	$translate[$alias] = $realtable;
	$res .= "$realtable $alias";
      } else {
	$base_table[$tp_table] = $table;
	$translate[$tp_table] = $realtable;
	$res .= "$realtable";
      }
    }
  }
  $all_joins = @$qstruct["JOIN_ON"];
  if(@$qstruct["JOIN_DEPENDANT"]) {
    $all_joins = array_merge($qstruct["JOIN_DEPENDANT"], $all_joins);
  }
  if($all_joins) {
    foreach($all_joins as $joinstring) {
      if($joinconditions)
	$joinconditions .= " and ";
      // translate $joinstring to the real names
      global $RAW_ID;
      if(!preg_match("/^($RAW_ID).($RAW_ID)([=<>]+)($RAW_ID).($RAW_ID)$/", $joinstring, $matches)) {
	die("bad joinstring '$joinstring'\n");
      }
      $realtable1 = $translate[$matches[1]];
      $realfield1 = _db_realname($base_table[$matches[1]], $matches[2]);
      $op = $matches[3];
      $realtable2 = $translate[$matches[4]];
      $realfield2 = _db_realname($base_table[$matches[4]], $matches[5]);
      $joinstring = "$realtable1.$realfield1 $op $realtable2.$realfield2";
      $joinconditions .= $joinstring;
    }
  }
  return $res;
}

function _mysql_make_boolean($table, $field, $value, $use_or) {
  global $RAW_DOTID;
  global $ERROR;
  global $debug;
  // check nested conditions
  if(!is_string($field) && is_array($value)) { // toggle and<->or
    $subres = _mysql_make_where($table, $value, !$use_or);
    return "($subres)";
  }

  // check unary operators
  $regex = "/^\\s*?(exists|not(?:\\s+exists)?)\\s*$/";
  if(preg_match($regex, $field, $matches)) {
    $op = $matches[1];
    if(preg_match("/exists/", $op)) {
      $dummy = array();
      $subres = mysql_make_query($dummy, $value);
    } else {
      $subres = _mysql_make_where($table, $value, $use_or);
    }
    return "$op ($subres)";
  }
  
  // check binary operators
  $op = "=";
  $regex = "/^($RAW_DOTID)?\\s*(=|<>|<|>|<=|>=|!|@|%| like| rlike| in| not in)?\\s*($RAW_DOTID)?$/";
  $old_field = $field;
  if(!preg_match($regex, $field, $matches)) {
    $ERROR = "bad field expression '$field'";
    return "/*bad field expression '$field'*/false";
  }
  $field = $matches[1];
  if(!$field) { // pure operator
    if(!@$matches[2]) {
      die("bad single operator");
    }
    $op = trim($matches[2]);
    $arg1 = $value[0];
    $arg2 = $value[1];
    $value1 = _db_homogenize($arg1);
    $value2 = _db_homogenize($arg2);
    $dummy = array();
    $subquery1 = mysql_make_query($dummy, $value1);
    $dummy = array();
    $subquery2 = mysql_make_query($dummy, $value2);
    $res = "($subquery1) $op ($subquery2)";
    return $res;
  }
  $realfield = _db_realname($table, $field);
  if(!$realfield)
    die("cannot find field '$field' in table '$table'\n");
  $sql_value = null;
  if(@$matches[3]) {
    $sql_value = $matches[3];
    $op = trim($matches[2]);
  } elseif(is_null($value)) {
    $op = "!";
  } else {
    if(@$matches[2]) {
      $op = trim($matches[2]);
      if(is_array($value) && $op != "in"&& $op != "not in") { // multiple conditions (indicated by presence of operator)
	$res = "";
	foreach($value as $item) {
	  if($res) {
	    if($use_or) {
	      $res .= " or ";
	    } else {
	      $res .= " and ";
	    }
	  }
	  // treat the same as if the operator had been repeated.
	  $res .= _mysql_make_boolean($table, $old_field, $item, $use_or);
	}
	return $res;
      }
    }
  
    if(is_array($value)) { // sub-sql statement (indicated by _absence_ of operator)
      if(!@$value["TABLE"] && !@$value["UNION"]) { // test against most sloppiness
	print_r($value);
	die("field '$old_field': badly formed sub-sql statement\n");
      }
      if($debug) { echo "start homogenizing: "; print_r($value); echo "<br>\n"; }
      $value = _db_homogenize($value);
      $dummy = array();
      $subquery = mysql_make_query($dummy, $value);
      $sql_value = "($subquery)";
    } else {
      $sql_value = db_esc_sql($value);
    }
  }

  if($op == "@") {
    $res = "$realfield is not null";
  } elseif($op == "%" || $op == "like") {
    $res = "$realfield like $sql_value";
  } elseif($op == "!") {
    $res = "$realfield is null";
  } else {
    $res = "$realfield $op $sql_value";
  }
  return $res;
}

function _mysql_make_where($table, $fields, $use_or = false) {
  $res = "";
  if($fields) {
    foreach($fields as $field => $value) {
      if($res) {
	if($use_or)
	  $res .= " or ";
	else
	  $res .= " and ";
      }
      $res .= _mysql_make_boolean($table, $field, $value, $use_or);
    }
  }
  return $res;
}

function mysql_make_query(&$subqs, $qstruct) {
  global $debug; if($debug) { echo "mysql_make_query: "; print_r($qstruct); echo"<br>\n";}
  if(($list = @$qstruct["UNION"])) {
    foreach($list as $alias => $subqstruct) {
      $subquery = mysql_make_query($subqs, $subqstruct);
      if($ERROR)
	return "";
      if($res)
	$res .= " union ";
      $res .= "$subquery $alias";
    }
    return $res;
  }
  $is_empty = isset($qstruct["COND"]["EMPTY"]);
  $select = _mysql_make_select($subqs, $qstruct, $is_empty);
  if($is_empty) {
    return "select $select";
  }
  if(@$qstruct["DISTINCT"]) {
    $select = "distinct $select";
  }
  $from = _mysql_make_from($qstruct, $join_where);
  $query = "select $select from $from";
  $where = _mysql_make_where($qstruct["TABLE"], @$qstruct["COND"], false);
  if($join_where) {
    if($where) {
      $where = "$join_where and ($where)";
    } else {
      $where = $join_where;
    }
  }
  if($where) {
    $query .= " where $where";
  }
  if($group = _mysql_make_group($qstruct)) {
    $query .= " group by $group"; 
  }
  if($order = @$qstruct["ORDER"]) {
    $order = implode(",", $order);
    $query .= " order by $order"; 
  }
  if($count = @$qstruct["COUNT"]) {
    if($start = @$qstruct["START"])
      $query .= " limit $start, $count";
    else
      $query .= " limit $count";
  }
  return $query;
}

////////////////////////////////////////////////////////////////////////

// generate mysql statements for referential integrity

function _mysql_make_idcond_base($qstruct, $row) {
  global $ERROR;
  $table = $qstruct["TABLE"];
  if(!($cond = @$qstruct["COND"])) {
    if(!$row && isset($qstruct["DATA"])) {
      $primary = _db_primary($table);
      $subcond = array();
      foreach($qstruct["DATA"] as $row) {
	$subsubcond = array();
	foreach(split(",", $primary) as $primfield) {
	  $subsubcond[$primfield] = $row[$primfield];
	}
	$subcond[] = $subsubcond;
      }
      $cond = array($subcond);
    } else {
      $cond = _db_make_idcond($table, $row);
    }
  }
  $idcond =
    array(
	  "TABLE" => array($table),
	  "FIELD" => array(),
	  "COND" => $cond,
	  "ORDER" => array(),
	  "START" => 0,
	  "COUNT" => 0,
	  );
  if($ERROR)
    $ERROR = "in table '$table': $ERROR";
  return $idcond;
}

function _mysql_check_allref(&$stack, $table, $field, $value, $mode, $idcond) {
  if(!_db_check_field($table, $field, $value))
    return false;
  if(!_db_check_ref($stack, $table, $field, $value))
    return false;
  $idcond["FIELD"] = array($field);
  $idcond["COND"]["$field <>"] = $value;
  if(!_db_check_xref($stack, $table, $field, $value, $mode, $idcond))
    return false;
  return true;
}

function _mysql_is_done(&$done, $qstruct, $test_mode = null) {
  global $debug;
  $is_done = false;
  if(!$test_mode)
    $test_mode = $qstruct["TEST_MODE"];
  if(is_array($test_mode)) {
    foreach($test_mode as $sub_mode) {
      $is_done |= _mysql_is_done($done, $qstruct, $sub_mode);
    }
    return $is_done;
  }
  $table = $qstruct["TABLE"];
  if(is_array($table))
    $table = @$table[key($table)];
  if(!$test_mode)
    $test_mode = $qstruct["TEST_MODE"];
  $fields = @$qstruct["FIELD"];
  if(is_string($fields))
    $fields = explode(",", $fields);
  if($fields) {
    foreach($fields as $alias => $field) {
      if(is_string($alias) && $alias == "test") {
	//echo "recurse $alias: "; print_r($field); echo"<br>\n";
	$sub_q = @$field["exists"];
	if(!$sub_q)
	  $sub_q = $field["not exists"];
	$is_done |= _mysql_is_done($done, $sub_q, $test_mode);
	continue;
      }
      $is_done |= @$done[$table][$field][$test_mode];
      if($debug) echo"is_done=$is_done $table $field $test_mode<br>\n";
      $done[$table][$field][$test_mode] = true;
    }
  }
  if(($test = @$qstruct["DATA"])) {
    // only examine the first record
    $rec = $test[key($test)];
    foreach($rec as $field => $dummy) {
      $is_done |= @$done[$table][$field][$test_mode];
      if($debug) echo"is_done=$is_done $table $field $test_mode<br>\n";
      $done[$table][$field][$test_mode] = true;
    }
  }
  return $is_done;
}

function _mysql_make_allref(&$stack, $first_qstruct, &$cb_list) {
  global $ERROR;
  global $debug; 
  $local_queue = array();
  $done = array();
  $res = "";
  // transitive closure algorithm: ensure that tests are coming first, under any circumstances!
  for(;;) {
    $qstruct = @array_shift($stack["TEST"]);
    if($qstruct) {
      if(!_mysql_is_done($done, $qstruct)) {
	if($res)
	  $res .="; ";
	$dummy = array();
	$res .= mysql_make_query($dummy, $qstruct);
	$cb_list[] = @$qstruct["CB"];
      }
      continue;
    }
    $qstruct = @array_shift($stack["UPDATE"]);
    if(!$qstruct)
      break;
    $qstruct = _db_mangle_update($qstruct);
    if(!$qstruct) {
      if(!$ERROR)
	$ERROR = "internal error: cannot process qstruct";
      return "";
    }
    if(!_mysql_is_done($done, $qstruct)) {
      // the following may update $stack once again, leading to transitive closure
      $sub_cb_list = array();
      if($debug) { echo "_mysql_make_update: "; print_r($qstruct); echo "<br>\n"; }
      $subres = _mysql_make_update($stack, $qstruct, $sub_cb_list);
      if($debug) { echo "_mysql_make_update subres: '$subres'<br>\n"; }
      // pushback to $local_queue because new tests might have been added
      $local_queue[] = array($subres, @$sub_cb_list);
    }
  }
  // finally, append the local_queue
  while(($pair = array_shift($local_queue))) {
    if($res)
      $res .="; ";
    $res .= $pair[0];
    $cb_list = array_merge($cb_list, $pair[1]);
  }
  if($debug) { echo "_mysql_make_update res: '$res'<br>\n"; }
  return $res;
}

////////////////////////////////////////////////////////////////////////

// generate mysql statements for writing

function _mysql_make_idwhere($qstruct, $row = null) {
  global $ERROR;
  $table = $qstruct["TABLE"];
  if($row && !@$qstruct["ALLOW_MANY"]) {
    $cond = _db_make_idcond($table, $row);
    if($ERROR) {
      $ERROR = "in table '$table': $ERROR";
      return "";
    }
    return _mysql_make_where($table, $cond);
  }
  if(($cond = @$qstruct["COND"])) {
    return _mysql_make_where($table, $cond);
  }
  $res = "";
  $count = 0;
  foreach($qstruct["DATA"] as $row) {
    if($count++)
      $res .=" or ";
    $res .= "(" . _mysql_make_idwhere($qstruct, $row) . ")";
  }
  if($res)
    return $res;
  // emergency: never trash the whole database, even if the user explicitly requests it by leaving COND an empty array
  global $ERROR;
  $ERROR .= " -- I will not trash the whole database, even if you requested it";
  return "false"; 
}

function _mysql_make_update_flat(&$stack, $qstruct, &$cb_list) {
  $table = $qstruct["TABLE"];
  $realtable = _db_realname($table);
  $autoinc = _db_autoinc($table);
  $mode = $qstruct["MODE"];
  if($mode == "DELETE") {
    $res = "delete from $realtable where ";
    $res .= _mysql_make_idwhere($qstruct);
    $cb_list[] = $qstruct["CB"];
    return $res;
  }
  $res = "$mode $realtable(";
  $res .= _db_realname($table, implode(", ", $qstruct["UPDATE_FIELDS"]));
  $res .= ") values ";
  $count = 0;
  foreach($qstruct["DATA"] as $row) {
    $idcond = _mysql_make_idcond_base($qstruct, $row);
    if($count++)
      $res .=", ";
    $res .= "(";
    $rowcount = 0;
    foreach($qstruct["UPDATE_FIELDS"] as $field) {
      $value = @$row[$field];
      if($mode == "INSERT" && $field == $autoinc) {
	$value = null;
      }
      if(!@$qstruct["RAW_MODE"] && !_mysql_check_allref($stack, $table, $field, $value, $mode, $idcond)) {
	return null;
      }
      if($rowcount++)
	$res .=", ";
      $res .= db_esc_sql($value);
    }
    $res .= ")";
  }
  $cb_list[] = $qstruct["CB"];
  return $res;
}

function _mysql_make_delete_tp(&$stack, $qstruct, &$cb_list) {
  global $USER;
  global $FROM;
  global $ERROR;
  $where = _mysql_make_idwhere($qstruct);
  if($ERROR) {
    return null;
  }
  $table = $qstruct["TABLE"];
  $realtable = _db_realname($table);
  $realtable_tp = _db_2temporal($realtable);

  $deleted = _db_extfield($table, "deleted");
  $version = _db_extfield($table, "version");
  $modified_from = _db_extfield($table, "modified_from");
  $modified_by = _db_extfield($table, "modified_by");

  $fields = implode(", ", _db_realname($table, $qstruct["ALL_FIELDS"]));
  $newdata = "";
  $rowcount = 0;
  foreach($qstruct["ALL_FIELDS"] as $field) {
    if($rowcount++)
      $newdata .=", ";
    if($field == $deleted) {
      $newdata .= "true";
    } elseif($field == $version) {
      $newdata .= "null";
    } elseif($field == $modified_from) {
      $newdata .= db_esc_sql($FROM);
    } elseif($field == $modified_by) {
      $newdata .= db_esc_sql($USER);
    } else {
      $newdata .= _db_realname($table, $field);
    }
    
    $idcond = _mysql_make_idcond_base($qstruct, null);
    $idcond["FIELD"] = array($field);
    if(!@$qstruct["RAW_MODE"] && !_db_check_xref($stack, $table, $field, null, "DELETE", $idcond)) {
      return null;
    }
    
  }
  $res = "Replace into $realtable_tp($fields) select $newdata from $realtable where $where";
  $cb_list[] = $qstruct["CB"];
  return $res;
}

function _mysql_make_update_tp(&$stack, $qstruct, &$cb_list) {
  global $ERROR;
  $mode = $qstruct["MODE"];
  if($mode == "DELETE") { // this need not depend on $qstruct["DATA"]
    return _mysql_make_delete_tp($stack, $qstruct, $cb_list);
  }
  $res = "";
  $table = $qstruct["TABLE"];
  $realtable = _db_realname($table);
  $realtable_tp = _db_2temporal($realtable);
  $autoinc = _db_autoinc($table);
  $deleted = _db_extfield($table, "deleted");
  $fields = implode(", ", _db_realname($table, $qstruct["ALL_FIELDS"]));
  $count = 0;
  foreach($qstruct["DATA"] as $row) {
    $idcond = _mysql_make_idcond_base($qstruct, $row);
    if($count++)
      $res .="; ";
    $res .= "replace into $realtable_tp($fields) ";

    $where = _mysql_make_idwhere($qstruct, $row);
    if($ERROR) {
      return null;
    }

    $newdata = "";
    $rowcount = 0;
    $oldcount = 0;
    foreach($qstruct["ALL_FIELDS"] as $field) {
      $realfield = _db_realname($table, $field);
      if($rowcount++)
	$newdata .=", ";
      if($field == $deleted) {
	$row[$field] = false;
      }
      if($mode == "INSERT" && $field == $autoinc) { // give AUTO_INCREMENT a chance
	$newdata .= "null";
      } elseif(array_key_exists($field, $row) && in_array($field, $qstruct["UPDATE_FIELDS"])) { // take new value
	$value = @$row[$field];
	if($field == $autoinc && $mode == "INSERT")
	  $value = null;
	if(!@$qstruct["RAW_MODE"] && !_mysql_check_allref($stack, $table, $field, $value, $mode, $idcond)) {
	  return null;
	}
	$newdata .= db_esc_sql($value);
      } elseif($mode == "REPLACE") { // caution: we cannot be sure that the data already exists, handle that case
	$newdata .= "case when exists(select $realfield from $realtable where $where) then (select max($realfield) from $realtable where $where) else null end";
      } else { // fallback to old value from the db
	$oldcount++;
	$newdata .= $realfield;
      }
    }
    if($mode == "REPLACE") {
      $res .= "select $newdata";
    } elseif(($oldcount || $mode == "UPDATE") && $mode != "INSERT") {
      $res .= "select $newdata from $realtable where $where";
    } else {
      $res .= "values ($newdata)";
    }
    $cb_list[] = $qstruct["CB"];
  }
  return $res;
}

function _mysql_make_update(&$stack, $qstruct, &$cb_list) {
  global $SCHEMA;
  $table = $qstruct["TABLE"];
  if(!$SCHEMA[$table]["TEMPORAL"]) {
    $res = _mysql_make_update_flat($stack, $qstruct, $cb_list);
  } else {
    $res = _mysql_make_update_tp($stack, $qstruct, $cb_list);
  }
  return $res;
}

function mysql_make_update($qstruct, &$cb_list) {
  // initialize $stack with first qstruct 
  $stack = array("UPDATE" => array($qstruct));
  $tp_table = $qstruct["TABLE"];
  if(_db_temporal($tp_table, $table)) {
    $qstruct["TABLE"] = $table;
  }
  $mode = $qstruct["MODE"];
  // add special checks/requests only for the _first_ qstruct
  foreach($qstruct["DATA"] as $row) {
    if(!@$qstruct["RAW_MODE"] && $mode == "INSERT") {
      _db_check_unique($stack, $table, $row);
    }
    foreach($row as $field => $value) {
      if(is_array($value)) {
	_db_push_subdata($stack, $table, $field, $value, $row);
      }
    }
  }

  // now do the transitive closure on everything
  $cb_list = array();
  $res = _mysql_make_allref($stack, $qstruct, $cb_list);
  return $res;
}

?>
