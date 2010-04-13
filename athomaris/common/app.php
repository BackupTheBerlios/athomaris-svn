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

require_once($BASEDIR . "/compiled/schema.php");
require_once($BASEDIR . "/../common/db/db.php");

////////////////////////////////////////////////////////////////////

// authentication

if(@$_SERVER["PHP_AUTH_USER"])
  $USER = $_SERVER["PHP_AUTH_USER"];
if(@$_SERVER["PHP_AUTH_PW"])
  $PASSWD = $_SERVER["PHP_AUTH_PW"];

//echo "user='$USER' pass='$PASSWD'<br>\n";

function app_auth($USER, $PASSWD) {
  //echo "user='$USER' pass='$PASSWD'<br>\n";
  $user = db_esc_sql($USER);
  $pass = db_esc_sql($PASSWD);
  $query = "select * from users u join profiles p using(profile_name) where user_name = $user and (user_password = password($pass) or ($user = 'root') and password($pass) in (select Password from mysql.user where User='root'))";
  //echo "$query<br>\n";
  $data = _db_do_query("", $query);
  //print_r($data); echo "<br>\n";
  return @$data[0];
}

if(@$CONFIG["USE_AUTH"]) {
  $PERM = app_auth($USER, $PASSWD);
  if($PERM || $USER == "root") {
    if($debug) {
      echo "authenticated user: $USER <br>\n";
    }
  } else {
    die("no access permissions<br>\n");
  }
 }

////////////////////////////////////////////////////////////////////

// parameter handling

if($debug) {
  foreach($_REQUEST as $field => $val) {
    echo "_REQUEST field='$field' val='"; print_r($val); echo "'<br>\n";
  }
 }

// get generic parameters
$table = @$_REQUEST["table"];
if(!@$table) {
  foreach($SCHEMA as $candidate => $tdef) {
    if(db_access_table($candidate, "R")) {
      $table = $candidate;
      break;
    }
  }
 }

// generic tool handling

$TOOL_NAMES = array("order", "tool_page_start", "tool_page_size", "tool_search", "tool_search_field", "tool_history", "tool_level");
$TOOL = array();
foreach($TOOL_NAMES as $tool) {
  $TOOL[$tool] = @$_REQUEST[$tool];
  if($debug) echo "TOOL: $tool='" . $TOOL[$tool] . "'<br>\n";
}
if(!$TOOL["order"]) {
  $TOOL["order"] = _db_primary($table);
 }
if(!$TOOL["tool_page_start"]) {
  $TOOL["tool_page_start"] = 1;
 }
if(!$TOOL["tool_page_size"]) {
  $TOOL["tool_page_size"] = 100;
 }
if(!isset($_REQUEST["tool_level"])) {
  $TOOL["tool_level"] = 1;
 }
$tp_table = $table;
if(@$_REQUEST["tool_history"]) {
  $tp_table = _db_2temporal($table);
  $version = _db_extfield($table, "version");
  $TOOL["order"] .= ",$version";
 }

////////////////////////////////////////////////////////////////////


function app_get_templates() {
  global $BASEDIR;
  global $CONFIG;
  global $USER;
  global $PERM;
  global $debug;
  global $LANGUAGE;
  if(!@$LANGUAGE) {
    $LANGUAGE = "generic_generic.php";
  }
  $lang = $LANGUAGE;
  $data = null;
  if(@$CONFIG["USE_AUTH"]) { // use the language table
    $table = "languages";
    $field = "language_template";
    $lang = @$PERM["language_name"];
    if(!$lang)
      $lang = "generic";
    $cond = array("language_name" => $lang);
    $data = db_read($table, $field, $cond, $field, 0, 1);
  }
  $list = array();
  if($data) {
    $list[] = $data[0][$field];
  }
  $list[] = $LANGUAGE;
  foreach($list as $try) {
    foreach(array("compiled", "../common/tpl/compiled") as $dir) {
      $path = "$BASEDIR/$dir/$try";
      if($debug) echo "stat('$path')<br>\n";
      if(stat($path)) {
	require_once($path);
	if($debug) echo "loaded template '$path'.<br>\n";
	return;
      }
    }
  }
  die("cannot get templates for user='$USER' language='$lang'\n");
}

////////////////////////////////////////////////////////////////////

// links management

function app_links() {
  global $LINKS;
  tpl_links(array("DATA" => @$LINKS));
}

/* create some defaults if $LINKS is omitted
 */
foreach($SCHEMA as $test_table => $dummy) {
  if(!@$LINKS["Tables:"][$test_table] && @$LINKS["Tables:"] !== "") {
    $string = dirname($_SERVER["PHP_SELF"]) . "/index.php?table=$test_table";
    $LINKS["Tables:"][$test_table] = $string;
  }
}

////////////////////////////////////////////////////////////////////

// default error displaying

function app_check_error() {
  global $ERROR;
  if($ERROR) {
    echo "<br/>DATABASE ERROR: $ERROR<br/>\n";
  }
}

////////////////////////////////////////////////////////////////////

/* get input data from the user
 */

function _app_get_value($rawfield, $rawvalue, $attrs) {
  // check if it is an upload field
  if($fi = @$_FILES[$rawfield]) {
    echo "FILE_INFO '$rawfield':"; print_r($fi); echo "<br>\n";
    if(is_array($attrs) && in_array("use_filename", $attrs)) { // use the filename
      return $fi["tmp_name"];
    } else { // use the _content_ of the file
      $fp = fopen($fi["tmp_name"], "r");
      echo "AHA:"; print_r($fp); echo "<br>\n";
      if($fp) {
	$sub = "";
	while($add = fread($fp, 1024*1024)) {
	  $sub .= $add;
	}
	fclose($fp);
	return $sub;
      }
    }
  }
  return $rawvalue;
}

function _app_get_field(&$res, $rawfield, $rawvalue) {
  //echo "RAWFIELD: $rawfield<br>\n";
  $ptr = &$res;
  foreach(split("\.", $rawfield) as $rawcomponent) {
    $attrs = split(":", $rawcomponent);
    $field = array_shift($attrs);
    $idx = array_shift($attrs);
    //echo "field='$field' idx='$idx'<br>\n";
    if(!@$ptr[$idx]) {
      $ptr[$idx] = array();
    }
    if(!@$ptr[$idx][$field]) {
      $ptr[$idx][$field] = array();
    }
    $ptr = &$ptr[$idx][$field];
  }
  $ptr = _app_get_value($rawfield, $rawvalue, $attrs);
  while($attr = array_shift($attrs)) {
    switch($attr) {
    case "decode":
      //echo "decoding '"; print_r($ptr); echo "'<br>\n";
      if(is_array($ptr)) {
	$sub = array();
	foreach($ptr as $item) {
	  $sub[] = _tpl_decode_row($item);
	}
	$ptr = $sub;
      } else {
	$ptr = array(_tpl_decode_row($ptr));
      }
      break;
    case "split":
      $delim = array_shift($attrs);
      $field = array_shift($attrs);
      $text = $ptr;
      $ptr = array();
      foreach(split($delim, $text) as $item) {
	$ptr[][$field] = $item;
      }
      break;
    case "split_decode":
      $delim = array_shift($attrs);
      $text = $ptr;
      $ptr = array();
      foreach(split($delim, $text) as $item) {
	$ptr[] = _tpl_decode_row($item);
      }
      break;
    default: ; // nothing
    }
  }
}

function _app_get_data($table) {
  global $SCHEMA;
  $res = array();
  // make recursive data structure out of encoded fieldnames
  foreach($_REQUEST as $rawfield => $rawvalue) {
    if(!strpos($rawfield, ":"))
       continue;
    _app_get_field($res, $rawfield, $rawvalue);
  }
  foreach($_FILES as $rawfield => $rawvalue) {
    if(!strpos($rawfield, ":"))
       continue;
    _app_get_field($res, $rawfield, $rawvalue);
  }
  // handle callback
  if($cb = @$SCHEMA[$table]["CB_SUBMIT"]) {
    $res = $cb($table, $res);
  }
  // handle forced fields (security)
  $res = db_force_data($table, $res);
  global $debug;
  if($debug) {
    echo "constructed newrec: \n"; print_r($res); echo "<br>\n";
  }
  return $res;
}

////////////////////////////////////////////////////////////////////

// Tools

function app_tool_search($table, $select) {
  $DATA = _app_prepare_data($table, null, "search");
  $DATA["SELECT"] = $select;
  tpl_tool_search($DATA);
}

function app_tool_page($table, $select) {
  $DATA = _app_prepare_data($table, null, "page");
  tpl_tool_page($DATA);
}

function app_tool_history($table, $select) {
  $DATA = _app_prepare_data($table, null, "history");
  tpl_tool_history($DATA);
}

function app_tool_level($table, $select) {
  $DATA = _app_prepare_data($table, null, "level");
  $DATA["SELECT"] = $select;
  tpl_tool_level($DATA);
}

function app_tools($table) {
  global $SCHEMA;
  $tools = @$SCHEMA[$table]["TOOLS"];
  tpl_vspace(null);
  $count = 0;
  foreach($tools as $name => $select) {
    if($count)
      tpl_hspace(null);
    $call = "app_$name";
    $call($table, $select);
    $count++;
  }
  tpl_vspace(null);
  tpl_vspace(null);
}

////////////////////////////////////////////////////////////////////

function _app_prepare_data($table, $querydata, $mode) {
  global $SCHEMA;
  global $PERM;
  global $TOOL, $TOOL_NAMES;
  $singular = _db_singular($table);
  $data = $TOOL;
  $data["DATA"] = db_force_data($table, $querydata);
  $data["TABLE"] = $table;
  $data["PRIMARY"] = _db_primary($table);
  $data["PRIMARIES"] = split(",", $data["PRIMARY"]);
  $data["UNIQUE"] = _db_unique($table);
  $data["UNIQUES"] = split(",", $data["UNIQUE"]);
  $data["PREFIX"] = "";  $data["SUFFIX"] = "";
  $data["PERM"] = $PERM;
  $data["SCHEMA"] = $SCHEMA;
  $data["ACTION_SELF"] = $_SERVER["PHP_SELF"];
  $action = dirname($_SERVER["PHP_SELF"]) . "/index.php?table=$table";
  $data["ACTION_BASE"] = $action;
  // generate link parameters reflecting current tools
  foreach($TOOL_NAMES as $tool) {
    if(@$TOOL[$tool]) {
      $action .= "&$tool=" . htmlspecialchars($TOOL[$tool]);
    }
  }
  $data["ACTION"] = $action;
  if($mode == "insert") {
    // check if immutable values are missing
    $immutable = array();
    $oldrec = $data["DATA"][0];
    $done = 0;
    foreach($SCHEMA[$table]["FIELDS"] as $field => $info) {
      if(@$info["IMMUTABLE"]) {
	if(@$oldrec[$field])
	  $done++;
	//echo "immutable: $field<br>\n";
	$immutable[$field] = true;
	$oldrec[$field] = @$oldrec[$field]; // ensure the key exists
      }
    }
    //echo "done: $done<br>\n";
    if($immutable && $done < count($immutable)) { // bail out any other fields
      $mode = "prepare";
      $data["FIELDS"] = array();
      $data["DATA"] = array();
      foreach($oldrec as $field => $value) {
	if(@$immutable[$field]) {
	  //echo "transferring: $field<br>\n";
	  $data["FIELDS"][$field] = $field;
	  $data["DATA"][0][$field] = $value;
	}
      }
    } else {
      $immutable = array();
    }
  }
  $data["MODE"] = $mode;
  $data["IMMUTABLE"] = @$immutable;
  return $data;
}

/* Get the identifying field values as denoted by
 * the primary key. Returns a condition suitable for query.
 */
function app_get_id($tp_table, $primary = null, $data = null) {
  if(!$data) {
    $data = $_REQUEST;
  }
  if(!$primary) {
    _db_temporal($tp_table, $table);
    $primary = _db_primary($table);
  }
  if(is_string($primary)) {
    $primary = split(",", $primary);
  }
  $cond = array();
  foreach($primary as $key) {
    if(!isset($data[$key])) {
      die("parameter for primary key '$key' is missing\n");
    }
    $cond[$key] = $data[$key];
  }
  return $cond;
}

/* output the inputs for a record: this contains all input fields.
 * and do all the user-requested actions associated with that.
 */
function app_input_record($tp_table) {
  global $SCHEMA;
  global $ERROR;

  _db_temporal($tp_table, $table);

  if(!db_access_table($table, "w")) {
    return;
  }
  // execute user actions on data
  if(isset($_REQUEST["insert"]) || isset($_REQUEST["new_clone"])) { /* user tries to insert a new record */
    $newdata = _app_get_data($table);
    //echo "db_inserting: "; print_r($newdata); echo "<br>\n";
    db_insert($table, $newdata);
    app_check_error();
  } elseif(isset($_REQUEST["change"])) { /* user tries to update a record */
    $newdata = _app_get_data($table);
    db_update($table, $newdata);
    app_check_error();
  }
  // execute user actions on buttons
  $primary = _db_primary($table); // TODO: allow combined primary keys (currently goes wrong because no arrays can be submitted as values)
  if(isset($_REQUEST["delete"])) { /* user has clicked on small delete button */
    $olddata = array(app_get_id($table));
    db_delete($table, $olddata);
    app_check_error();
  }
  if(isset($_REQUEST["edit"])) { /* user has clicked on small edit button */
    $cond = app_get_id($table);
    $olddata = db_read($table, null, $cond, null, 0, 1);
    //echo "edit olddata: "; print_r($olddata); echo "<br>\n";
    $mode = "change";
    $data = _app_prepare_data($table, $olddata, $mode);
  } elseif(isset($_REQUEST["clone"])) { /* user has clicked on cloning button */
    $cond = app_get_id($table);
    $olddata = db_read($table, null, $cond, null, 0, 1);
    //echo "edit olddata: "; print_r($olddata); echo "<br>\n";
    $mode = "new_clone";
    $data = _app_prepare_data($table, $olddata, $mode);
  } else { /* no preselection => present empty input fields */
    $mode = "insert";
    $olddata = db_getemptyrec($table);
    if(@$ERROR && @$newdata) {
      // exception: the user does not want to loose bad input
      $olddata = $newdata;
    }
    if(isset($_REQUEST["prepare"])) { /* user has supplied immutable data */
      $olddata = array($_REQUEST);
    }
    $data = _app_prepare_data($table, $olddata, $mode);
  }
  tpl_input_record($data);
}

function app_display_table($tp_table) {
  global $SCHEMA;
  global $TOOL;
  global $ERROR;
  global $debug;

  _db_temporal($tp_table, $table);

  if(!db_access_table($table, "r")) {
    tpl_error_permission_denied(null);
    return;
  }
  app_tools($table);
  $cond = array();
  if(isset($SCHEMA[$table]["TOOLS"]["tool_search"]) &&
     ($str = trim($TOOL["tool_search"])) && ($field = $TOOL["tool_search_field"])) {
    if($debug) echo "adding search condition '$field'<br>\n";
    $cond["$field%"] = "%$str%";
  }
  $page_size = @$TOOL["tool_page_size"];
  if(!$page_size || $page_size < 0)
    $page_size = 1;
  $page_start = @$TOOL["tool_page_start"];
  if(!$page_start || $page_start < 0)
    $page_start = 1;
  if(!isset($SCHEMA[$table]["TOOLS"]["tool_page"]))
    $page_size = 0;
  // xxx not generic!!!
  if(@$SCHEMA[$table]["TOOLS"]["tool_level"]) {
    $prio = @$TOOL["tool_level"];
    //$cond["#class_name in (select class_name from classes where class_prio >= $prio)"] = true;
  }

  $tmp = db_read($tp_table, null, $cond, @$TOOL["order"], ($page_start - 1) * $page_size, $page_size);
  if($ERROR) {
    tpl_error(array("ERROR" => $ERROR));
    return;
  }

  $data = _app_prepare_data($table, $tmp, "invalid");

  if(db_access_table($table, "w")) {
    $data["EXTRAHEAD"] = "extra_3buttons_head";
    $primary = _db_primary($table);
    $data["EXTRA"]["button_edit"] = $primary;
    $data["EXTRA"]["button_clone"] = $primary;
    $data["EXTRA"]["button_delete"] = $primary;
  }
  tpl_display_table($data);
}

function _app_getdata($table, $cond) {
  $order = implode(",", array_keys($cond));
  $tmp = db_read($table, null, $cond, $order, 0, 1);
  $data = _app_prepare_data($table, $tmp, "change");
  return $data;
}

function app_display_record($tp_table, $cond) {
  global $TEMPLATE;
  _db_temporal($tp_table, $table);
  $data = _app_getdata($table, $cond);
  $call = "display_record_$table";
  if(@$TEMPLATE[$call]) {
    $call = "tpl_$call";
    $call($data);
  } else { // fallback
    tpl_display_record($data);
    if(db_access_table($table, "W")) {
      tpl_vspace(null);
      tpl_input_record($data);
    }
  }
}

function app_display_download($tp_table, $cond, $download, $filename) {
  global $SCHEMA;
  $tp = _db_temporal($tp_table, $table);
  $data = _app_getdata($table, $cond);

  $data["FILENAME"] = $filename;

  if($filename) {
    if($cb = @$SCHEMA[$table]["FIELDS"][$download]["CB_DOWNLOAD"]) {
      $data = $cb($data);
    }
    tpl_header_download($data);
    print $data["DATA"][0][$download];
    exit(0);
  } else {
    tpl_header($data);
    tpl_body_start($data);
    print "<tt>"._tpl_format_ascii($data["DATA"][0][$download])."</tt>";
    tpl_body_end($data);
    tpl_footer($data);
  }
}

function _app_shorten_len($text, $maxlen) {
  $len = strlen($text);
  if($len <= $maxlen) {
    return $text;
  }
  $small = $maxlen / 2 - 3;
  return substr($text, 0, $small) . " ... " . substr($text, $len-$small, $small);
}

?>
