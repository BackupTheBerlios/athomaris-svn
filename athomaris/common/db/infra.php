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

$EXTENSIONS =
  array(
	"id",
	"version",
	"deleted",
	"modified_from",
	"modified_by",
	);


////////////////////////////////////////////////////////////////////////

// infrastructure

/* Escaping: prevent SQL code injection
 */
function db_esc_sql($value) {
  if(is_array($value)) $value = implode(";", $value);
  if(is_null($value)) {
    return "NULL";
  } elseif(is_string($value)) {
    return "'" . addslashes($value) . "'";
  } elseif(is_int($value)) {
    return addslashes($value);
  } elseif(is_bool($value)) {
    return $value ? "true" : "false";
  }
  return "ERROR bad type '$value'";
}

/* Get the singular form of a tablename
 */
function _db_singular($table, $MYSCHEMA = null) {
  global $SCHEMA;
  if(!$MYSCHEMA)
    $MYSCHEMA = $SCHEMA;
  $singular = preg_replace("/s$/", "", $table);
  if(@$MYSCHEMA[$table]["SINGULAR"]) {
    $singular = $MYSCHEMA[$table]["SINGULAR"];
  }
  return $singular;
}

/* Get artificial fieldnames from extension
 */
function _db_extfield($table, $extension, $MYSCHEMA = null) { // return name of extension field
  global $SCHEMA;
  if(!$MYSCHEMA)
    $MYSCHEMA = $SCHEMA;
  
  return $MYSCHEMA[$table]["FIELDNAME_" . strtoupper($extension)];
}

/* return name of primary key
 */
function _db_primary($table, $MYSCHEMA = null) {
  if(@$MYSCHEMA[$table]["PRIMARY"])
    return $MYSCHEMA[$table]["PRIMARY"];
  return _db_extfield($table, "id", $MYSCHEMA);
}

/* return name of first UNIQUE key
 */
function _db_unique($table, $MYSCHEMA = null) {
  global $SCHEMA;
  if(!$MYSCHEMA)
    $MYSCHEMA = $SCHEMA;
  return @$MYSCHEMA[$table]["UNIQUE"][0];
}

/* return name of autoincrement
 */
function _db_autoinc($table, $MYSCHEMA = null) {
  if(@$MYSCHEMA[$table]["FIELDNAME_ID"])
    return $MYSCHEMA[$table]["FIELDNAME_ID"];
  return _db_extfield($table, "id", $MYSCHEMA);
}

/* Currently the suffix "_tp" cannot be overridden
 */
function _db_2temporal($table) {
  return "${table}_tp";
}

function _db_temporal($tp_table, &$table) {
  $table = $tp_table;
  $is_tp = preg_match("/^(.+)_tp$/", $tp_table, $matches);
  if($is_tp) {
    $table = $matches[1];
  }
  return $is_tp;
}

/* return the logical connection of a table.
 * FIXME: troughout the system, terminology should clearly differentiate
 * between "connection" and "database". Currently mixed up :(
 */
function _db_database($table) {
  global $SCHEMA;
  $host = $SCHEMA[$table]["DB"];
  return $host;
}

function _db_maindatabase() {
  global $CONFIG;
  $databases = array_keys(@$CONFIG["CONNECTIONS"]);
  $maindatabase = $databases[0];
  if(!$maindatabase) {
    die("bad config: no main database is defined<br>\n");
  }
  return $maindatabase;
}

/* Check whether a given table / field is member in a
 * given database or set of databases.
 * When &$database or &$table is an array, search for
 * a matching member and return it by reference.
 */
function _db_check(&$database, &$table, $field = null) {
  if(!$database) { // find one
    global $CONFIG;
    $database = array_keys($CONFIG);
  }
  if(is_array($database)) {
    foreach($database as $item) {
      if(_db_check($item, $table, $field)) {
	$database = $item;
	return true;
      }
    }
    return false;
  }
  if(!$table) { // find one
    global $SCHEMA;
    $table = array_keys($SCHEMA);
  }
  if(is_array($table)) {
    foreach($table as $item) {
      if(_db_check($database, $item, $field)) {
	$table = $item;
	return true;
      }
    }
    return false;
  }
  // now we know everything is flat....
  global $SCHEMA;
  if($field) {
    return ($SCHEMA[$table]["FIELDS"][$field] && $SCHEMA[$table]["DB"] == $database);
  }
  return ($SCHEMA[$table]["DB"] == $database);
}

/* Get the REALNAME.
 * Also works for $field arrays.
 * When $table is an array, search for a matching table.
 */
function _db_realname($tp_table, $field = null) {
  global $SCHEMA;
  global $RAW_ID;
  if(is_array($field)) { // structured case
    $res = array();
    foreach($field as $item) {
      $res[] = _db_realname($tp_table, $item);
    }
    return $res;
  }
  if(is_string($field) && preg_match("/^($RAW_ID)\.$RAW_ID$/", $field, $matches)) {
    return $matches[1];
  }
  if(is_array($tp_table)) { // no _exact_ table given -> search for one
    $tlist = "";
    $res = "";
    $res2 = "";
    $count = 0; // number of matches
    foreach($tp_table as $alias => $test) {
      if(is_array($test))
	$test = $alias;
      $tlist .= "[$test]";
      $test2 = _db_realname($test, $field);
      if($test2) {
	$count++;
	if(!$res) {
	  $res = _db_realname($test);
	  $res2 = $test2;
	}
      }
    }
    if($res2) {
      if($count > 1 && $field) { // resolve ambiguity by prepending
	return "$res.$res2";
      }
      return $res2;
    }
    die("no table found for field '$field' ($tlist)\n");
  }
  // normal case
  _db_temporal($tp_table, $table);
  if(!$table || !@$SCHEMA[$table]) { // cannot translate
    return "";
  }
  if($field) {
    return @$SCHEMA[$table]["FIELDS"][$field]["REALNAME"];
  }
  return @$SCHEMA[$table]["REALNAME"];
}

//////////////////////////////////////////////////////////////////////

// access permissions

$SCORE =
  array(
	"n" => 0,
	"r" => 1,
	"R" => 2,
	"w" => 3,
	"W" => 4,
	"A" => 5,
	);

function db_access_table($table, $mode) {
  global $SCHEMA;
  global $PERM;
  global $USER;
  global $SCORE;
  if(($gcode = @$SCHEMA[$table]["ACCESS"])) {
    $score_mode = $SCORE[$mode];
    $score_code = $SCORE[$gcode];
    if($score_mode > $score_code)
      return false;
  }
  if($USER == "root") { // superuser can do almost anything
    return true;
  }
  if(!@$PERM) {
    return false;
  }
  $name = "t_$table";
  if(!$code = @$PERM[$name]) {
    return false;
  }
  $score_mode = $SCORE[$mode];
  $score_code = $SCORE[$code];
  return $score_mode <= $score_code;
}

function db_access_field($table, $field, $mode) {
  global $SCHEMA;
  global $PERM;
  global $USER;
  global $SCORE;
  $score_mode = $SCORE[$mode];
  // schema restrictions take precedence
  //echo "db_access_field mode='$mode' table='$table' field='$field'<br>\n";
  if($code = @$SCHEMA[$table]["FIELDS"][$field]["ACCESS"]) {
    //echo "code='$code' table='$table' field='$field'<br>\n";
    $score_code = $SCORE[$code];
    if($score_mode > $score_code) {
      return false;
    }
  }
  if($USER == "root") { // superuser can do almost anything
    return true;
  }
  // never exceed table permissions
  if(!db_access_table($table, $mode)) {
    return false;
  }
  $name = "f_${table}_$field";
  if(!$code = @$PERM[$name]) { // the field does not exist => fallback to table permissions
    return true;
  }
  $score_code = $SCORE[$code];
  return $score_mode <= $score_code;
}

///////////////////////////////////////////////////////

/* DB helper functions for templates
 */

/* return an "empty" record
 */
function db_getemptyrec($table) {
  return db_read($table, "", array("EMPTY" => true), "", 0, 0);
}

function db_force_data($table, $data) {
  global $SCHEMA;
  global $USER;
  //echo "pre: "; print_r($data); echo "<br>\n";
  if($data) {
    foreach($SCHEMA[$table]["FIELDS"] as $field => $fdef) {
      if(($init = @$fdef["FORCE"]) && $init == "USER") {
	//echo "force-field: '$field'<br>\n";
	foreach($data as $idx => $rec) {
	  if($USER != "root" || !@$rec[$field]) { // superuser is only forced on empty fields
	    //echo "forced init: '$field' = '$USER'<br>\n";
	    $rec[$field] = $USER;
	    $data[$idx] = $rec;
	  }
	}
      }
    }
  }
  return $data;
}

/* select certain fields from data
 */
function db_selectfields($data, $fields) {
  if(!@$fields) return $data;
  $newdata = array();
  if(@$data) {
    foreach($data as $rec) {
      $newrec = array();
      foreach($rec as $field => $value) {
	if(in_array($field, $fields)) {
	  $newrec[$field] = $value;
	}
      }
      $newdata[] = $newrec;
    }
  }
  return $newdata;
}

/* interpret an xml-like string representing nested php structures.
 * restriction: structure must not have recursively nested tags
 * with the same tag name.
 * this is intended for flat database tables, not full-flegded xml.
 */
function db_parse_dbxml($text) {
  $data = array();
  while(preg_match("/<db_([a-z_0-9]+)>(.*?)\n*<\/db_\\1>(.*)\Z/s", $text, $matches)) {
    $key = $matches[1];
    $val = $matches[2];
    $text = $matches[3];
    if(substr($val, 0, 4) == "<db_" && substr($val, -1, 1) == ">") {
      $val = db_parse_dbxml($val);
    }
    //else echo "'$key' => '$val'\n";
    $data[$key] = $val;
  }
  return $data;
}

/* the "reverse operation" of parse_dbxml()
 */
function db_convert_dbxml($data) {
  $text = "";
  foreach($data as $key => $val) {
    $text .= "<db_$key>";
    if(is_array($val)) {
      $text .= db_convert_dbxml($val);
    } else {
      $text .= $val;
    }
    $text .= "</db_$key>\n";
  }
  return $text;
}


?>
