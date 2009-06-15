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
    _db_temporal($tp_table, $table);
    if($table != $tp_table) {
      if(is_int($alias))
	$alias = $tp_table;
      if($add1) {
	$add1 .= " or ";
	$add2 .= " or ";
      }
      $id = _db_extfield($table, "id");
      $version = _db_extfield($table, "version");
      $add1 .= "$version < (select max($version) from ${table}_tp old where old.$id = $alias.$id)";
      $add2 .= _db_extfield($table, "deleted");
    }
  }
  if($add1) {
    return ", $add1 as outdated, $add2 as deleted";
  }
  return "";
}

function _mysql_make_select(&$subqs, $qstruct, $is_empty) {
  global $SCHEMA;
  $res = "";
  if(($pair = @$qstruct["AGG"])) { // treat aggregate functions
    if(($list = @$pair["FIELD"])) {
      foreach($list as $alias => $field) {
	if($res)
	  $res .= ", ";
	$res .= $field;
	if(is_string($alias)) {
	  $res .= " as $alias";
	}
      }
    }
    if($list = @$pair["GROUP"]) {
      foreach($list as $alias => $field) {
	if($res)
	  $res .= ", ";
	$res .= $field;
	if(is_string($alias)) {
	  $res .= " as $alias";
	}
      }
    }
  } elseif(($list = @$qstruct["FIELD"])) { // treat ordinary subqueries
    foreach($list as $alias => $field) {
      if(is_string($field)) {
	if($res)
	  $res .= ", ";
	if($is_empty) {
	  $init = null;
	  foreach($qstruct["BASE_TABLE"] as $table) {
	    if(($init = @$SCHEMA[$table]["FIELDS"][$field]["DEFAULT"]))
	      break;
	  }
	  if(is_null($init))
	    $init = "null";
	  $res .= $init;
	  if(!$alias || !is_string($alias))
	    $alias = $field;
	} else { // normal case
	  $res .= $field;
	}
      } elseif(!@$field["TABLE"]) { // we have a boolean sub-expression
	$subexpr = _mysql_make_where($field);
	$res .= "($subexpr)";
      } else { // we have a sub-expression
	global $debug; if($debug) { echo "subquery: "; print_r($field); echo"<br>\n";}
	$sub_homo = _db_homogenize($field);
	$dummy = array();
	$subquery = mysql_make_query($dummy, $sub_homo);
	if(@$sub_homo["AGG"]) { // we can embed it as ordinary subquery
	  if($res)
	    $res .= ", ";
	  $res .= "($subquery)";
	} else { // we have a true subtable which cannot be executed in a single query
	  $joinfields = @$sub_homo["JOINFIELDS"];
	  if($debug) { echo "pushback subquery: alias='$alias' joinfields='$joinfields'<br>\n";}
	  $subqs[$alias] = array($joinfields, $subquery, $alias);
	  continue;
	}
      }
      if($is_empty || (is_string($alias) && is_string($field) && $alias != $field)) {
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
      $res .= $field;
    }
  }
  return $res;
}

function _mysql_make_from($qstruct) {
  global $SCHEMA;
  $res = "";
  if($list = @$qstruct["TABLE"]) {
    $candidate_fields = array();
    foreach($list as $alias => $tp_table) {
      if(is_array($tp_table)) {
	global $debug; if($debug) { echo "subquery: "; print_r($tp_table); echo"<br>\n";}
	$homo = _db_homogenize($tp_table);
	$dummy = array();
	$subquery = mysql_make_query($dummy, $homo);
	if(!is_string($alias))
	  die("an alias for a subtable is missing");
	$res .= "($subquery) $alias";
      } else {
	_db_temporal($tp_table, $table);
	if(!$fields = @$SCHEMA[$table]["FIELDS"])
	  die("table $table does not exist");
	$myfields = array_keys($fields);
	if(!is_string($alias))
	  $alias = "";
	if($res) {
	  $joinfields = array_intersect($candidate_fields, $myfields);
	  $using = implode(",", $joinfields);
	  $res .= " join $tp_table $alias using($using)";
	} else {
	  $res = "$tp_table $alias";
	}
	$candidate_fields = array_merge($candidate_fields, $myfields);
      }
    }
  }
  if(!$res) {
    //print_r(apd_callstack()); echo "<br>\n";
    print_r($qstruct); echo "<br>\n";
    die("invalid TABLE information");
  }
  return $res;
}

function _mysql_make_boolean($field, $value, $use_or) {
  global $RAW_DOTID;
  // check nested conditions
  if(is_int($field) && is_array($value)) { // toggle and<->or
    $subres = _mysql_make_where($value, !$use_or);
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
      $subres = _mysql_make_where($value, $use_or);
    }
    return "$op ($subres)";
  }
  
  // check binary operators
  $op = "=";
  $regex = "/^($RAW_DOTID)\\s*?(=|<>|<|>|<=|>=|!|@|%| rlike| in)?\\s*($RAW_DOTID)?$/";
  if(!preg_match($regex, $field, $matches)) {
    global $ERROR;
    $ERROR = "bad field expression '$field'";
    return "false";
  }
  $field = $matches[1];
  if(@$matches[2]) {
    $op = $matches[2];
    if(is_array($value)) { // multiple conditions (indicated by presence of operator)
      $res = "";
      foreach($value as $item) {
	if($res) {
	  if($use_or)
	    $res .= " or ";
	  else
	    $res .= " and ";
	}
	$res .= _mysql_make_boolean($field, $item, $use_or);
      }
      return $res;
    }
  }
  
  if(is_array($value)) { // sub-sql statement (indicated by _absence_ of operator)
    $dummy = array();
    $sql_value = "(" . mysql_make_query($dummy, $value) . ")";
  } else {
    $sql_value = db_esc_sql($value);
  }
  if(@$matches[3]) {
    $sql_value = $matches[3];
  } elseif(is_null($value)) {
    $op = "!";
  }
  
  if($op == "@") {
    $res = "$field is not null";
  } elseif($op == "%") {
    $res = "$field like $sql_value";
  } elseif($op == "!") {
    $res = "$field is null";
  } else {
    $res = "$field $op $sql_value";
  }
  return $res;
}

function _mysql_make_where($fields, $use_or = false) {
  $res = "";
  if($fields) {
    foreach($fields as $field => $value) {
      if($res) {
	if($use_or)
	  $res .= " or ";
	else
	  $res .= " and ";
      }
      $res .= _mysql_make_boolean($field, $value, $use_or);
    }
  }
  return $res;
}

function mysql_make_query(&$subqs, $qstruct) {
  //global $debug; if($debug) { echo "make_query: "; print_r($qstruct); echo"<br>\n";}
  $is_empty = isset($qstruct["COND"]["EMPTY"]);
  $select = _mysql_make_select($subqs, $qstruct, $is_empty);
  if($is_empty) {
    return "select $select";
  }
  $from = _mysql_make_from($qstruct);
  $query = "select $select from $from";
  $where = _mysql_make_where(@$qstruct["COND"], false);
  if($where)
    $query .= " where $where";
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
  $table = $qstruct["TABLE"];
  if(!($cond = @$qstruct["COND"])) {
    if(!$row && isset($qstruct["DATA"])) {
      $primary = _db_primary($table);
      $subcond = array();
      foreach($qstruct["DATA"] as $row) {
	$subcond[] = array($primary => $row[$primary]);
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
      $subres = _mysql_make_update($stack, $qstruct, $sub_cb_list);
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
  return $res;
}

////////////////////////////////////////////////////////////////////////

// generate mysql statements for writing

function _mysql_make_idwhere($qstruct, $row = null) {
  $table = $qstruct["TABLE"];
  if($row) {
    $cond = _db_make_idcond($table, $row);
    return _mysql_make_where($cond);
  }
  if(($cond = @$qstruct["COND"])) {
    return _mysql_make_where($cond);
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
  $primary = _db_primary($table);
  $mode = $qstruct["MODE"];
  if($mode == "DELETE") {
    $res = "delete from $table where ";
    $res .= _mysql_make_idwhere($qstruct);
    $cb_list[] = $qstruct["CB"];
    return $res;
  }
  $res = "$mode $table(";
  $res .= implode(", ", $qstruct["UPDATE_FIELDS"]);
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
      if($field == $primary && $mode == "INSERT") {
	$value = null;
      }
      if(!_mysql_check_allref($stack, $table, $field, $value, $mode, $idcond)) {
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

  $deleted = _db_extfield($table, "deleted");
  $version = _db_extfield($table, "version");
  $modified_from = _db_extfield($table, "modified_from");
  $modified_by = _db_extfield($table, "modified_by");

  $fields = implode(", ", $qstruct["ALL_FIELDS"]);
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
      $newdata .= $field;
    }
    
    $idcond = _mysql_make_idcond_base($qstruct, null);
    $idcond["FIELD"] = array($field);
    if(!_db_check_xref($stack, $table, $field, null, "DELETE", $idcond)) {
      return null;
    }
    
  }
  $res = "Replace into ${table}_tp($fields) select $newdata from $table where $where";
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
  $primary = _db_primary($table);
  $deleted = _db_extfield($table, "deleted");
  $fields = implode(", ", $qstruct["ALL_FIELDS"]);
  $count = 0;
  foreach($qstruct["DATA"] as $row) {
    $idcond = _mysql_make_idcond_base($qstruct, $row);
    if($count++)
      $res .="; ";
    $res .= "replace into ${table}_tp($fields) ";

    $where = _mysql_make_idwhere($qstruct, $row);
    if($ERROR) {
      return null;
    }

    $newdata = "";
    $rowcount = 0;
    $oldcount = 0;
    foreach($qstruct["ALL_FIELDS"] as $field) {
      if($rowcount++)
	$newdata .=", ";
      if($field == $deleted) {
	$row[$field] = false;
      }
      if($field == $primary && $mode == "INSERT") { // give AUTO_INCREMENT a chance
	$newdata .= "null";
      } elseif(array_key_exists($field, $row) && in_array($field, $qstruct["UPDATE_FIELDS"])) { // take new value
	$value = @$row[$field];
	if($field == $primary && $mode == "INSERT")
	  $value = null;
	if(!_mysql_check_allref($stack, $table, $field, $value, $mode, $idcond)) {
	  return null;
	}
	$newdata .= db_esc_sql($value);
      } elseif($mode == "REPLACE") { // caution: we cannot be sure that the data already exists, handle that case
	$newdata .= "case when exists(select $field from $table where $where) then (select max($field) from $table where $where) else null end";
      } else { // fallback to old value from the db
	$oldcount++;
	$newdata .= $field;
      }
    }
    if($mode == "REPLACE") {
      $res .= "select $newdata";
    } elseif(($oldcount || $mode == "UPDATE") && $mode != "INSERT") {
      $res .= "select $newdata from $table where $where";
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
  $table = $qstruct["TABLE"];
  $mode = $qstruct["MODE"];
  // add special checks/requests only for the _first_ qstruct
  foreach($qstruct["DATA"] as $row) {
    if($mode == "INSERT") {
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