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

if(@$_SERVER["PHP_AUTH_USER"])
  $USER = $_SERVER["PHP_AUTH_USER"];
if(@$_SERVER["PHP_AUTH_PW"])
  $PASSWD = $_SERVER["PHP_AUTH_PW"];
if(!@$BASEDIR)
  $BASEDIR = dirname($_SERVER["SCRIPT_FILENAME"]);

/* 
 *  - uses no templates (emergency / resilience)
 */

# TODO: security checks

$debug = true;
require_once($BASEDIR . "/../common/db/db.php");

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n";
echo "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"de\" lang=\"de\">\n";
echo "<head>\n";
echo "<title>Initializing SERVERS</title>\n";
echo "</head>\n";

echo "<body>\n";


function __db_create_field($field, $value) {
  $query = "  $field " . $value["SQL_TYPE"];
  if(isset($value["OPTIONS"])) {
    $query .= " " . $value["OPTIONS"];
  }
  if(isset($value["DEFAULT"])) {
    $query .= " default " . $value["DEFAULT"];
  }
  /* sadly, this does not work with mysql at all..... :(
  $check = "";
  if(isset($value["BETWEEN"])) {
    $sub = $value["BETWEEN"];
    $check .= "$field between " . $sub[0] . " and " . $sub[1];
  }
  if(isset($value["LENGTH"])) {
    $sub = $value["LENGTH"];
    if($check) $check .= " and ";
    $check .= "char_length($field) between " . $sub[0] . " and " . $sub[1];
  }
  if(isset($value["REGEX"])) {
    $sub = $value["REGEX"];
    if($check) $check .= " and ";
    $check .= "$field rlike '$sub'";
  }
  if($check) $query .= " check $check";
  */
  return $query;
}

function __db_create_index($fields, $drop) {
  $name = preg_replace("/,/", "_", $fields);
  $res = "  index idx_$name";
  if(!isset($drop) || !$drop) $res .= "($fields)";
  return $res;
}

function __db_create_tpview($SCHEMA, $newtable, $restrict) {
  $id_field = $SCHEMA[$newtable]["FIELDNAME_ID"];
  $version_field = $SCHEMA[$newtable]["FIELDNAME_VERSION"];
  $deleted_field = $SCHEMA[$newtable]["FIELDNAME_DELETED"];
  $query = "";
  if($restrict) {
    $query .= "drop view if exists ${newtable}_tp;\n";
    $query .= "create view ${newtable}_tp as\n";
    $query .= "  select * from ${newtable}_unrestr_tp t0 where t0.$restrict = substring_index(user(), '@', 1) or substring_index(user(), '@', 1) = 'root';\n";
  }
  $query .= "drop view if exists $newtable;\n"; 
  $query .= "create view $newtable as\n";
  $query .= "  select * from ${newtable}_tp t1 where t1.$version_field in (\n";
  $query .= "    select max(t2.$version_field) from ${newtable}_tp t2\n";
  $query .= "    where t1.$id_field = t2.$id_field and not t1.$deleted_field\n";
  $query .= "  );\n\n";
  return $query;
}

function _db_gen_indices($SCHEMA, $def, $newtable, $secondary) {
  $indices = array();
  if($def["TEMPORAL"]) {
    $version_field = $SCHEMA[$newtable]["FIELDNAME_VERSION"];
    $deleted_field = $SCHEMA[$newtable]["FIELDNAME_DELETED"];
    $both = $deleted_field . "," . $version_field;
    $indices[$version_field] = $version_field;
    $indices[$both] = $both;
  }
  $auto_inc = @$SCHEMA[$newtable]["FIELDNAME_ID"];
  if($auto_inc)
    $indices[$auto_inc] = $auto_inc;
  $primary = _db_primary($newtable, $SCHEMA);
  $indices[$primary] = $primary; // often this overrides $auto_inc
  if(@$def[$secondary])
    $indices[$secondary] = $secondary;
  if(isset($def["INDEX"])) {
    foreach($def["INDEX"] as $index) {
      // eliminate duplicates
      $indices[$index] = $index;
    }
  }
  if($unis = @$def["UNIQUE"]) {
    foreach($unis as $uni) {
      foreach(explode(",", $uni) as $index) {
	// eliminate duplicates
	$indices[$index] = $index;
      }
    }
  }
  return $indices;
}

function _db_create_view($NEW, $alias, $qstruct) {
  global $SCHEMA;
  $oldschema = $SCHEMA;
  $SCHEMA = $NEW;
  $q2 = _db_mangle_query($databases, $qstruct);
  if(count($databases) == 1) {
    $database = key($databases);
    $query = _db_make_query($database, $subqs, $q2);
  } else {
    $SCHEMA = $oldschema;
    return "/* cannot create distributed view '$alias' */\n";
  }
  $SCHEMA = $oldschema;
  return "drop view if exists $alias;\ncreate view $alias as $query;\n\n";
}

function _db_create_tables($OLD, $NEW, $database, &$count) {
  $count = 0;
  $query = "";
  foreach($NEW as $newtable => $newdef) {
    if(@$newdef["VIEW"]) {
      $query .= _db_create_view($NEW, $newtable, $newdef["VIEW"]);
      continue;
    }
    if($newdef["DB"] != $database) {
      $query .= "/* skipping table '$newtable', not in database '$database' */\n";
      continue;
    }
    if(!db_access_table($newtable, "w")) {
      $query .= "/* skipping table '$newtable', no write access */\n";
      continue;
    }

    $singular = _db_singular($newtable, $NEW);
    $primary = _db_primary($newtable, $NEW);
    $secondary = $singular . "_name"; // !!! not generic!
    $restrict = @$newdef["USER_RESTRICT"];
    $tablename = $newtable;
    if($restrict)
      $tablename .= "_unrestr";
    $tablename .= "_tp";
    // completely new table or delta?
    if(!isset($OLD[$newtable])) {
      $count++;
      $index = "";
      $query .= "create table if not exists $tablename (\n";
      foreach($newdef["FIELDS"] as $field => $value) {
	if(@$value["VIRTUAL"]) {
	  $query .= "/* omitting VIRTUAL $field */\n";
	  continue;
	}
	$query .= __db_create_field($field, $value);
	$query .= ",\n";
      }
      $query .= "  primary key($primary";
      if($newdef["TEMPORAL"]) {
	$query .= ", " . $NEW[$newtable]["FIELDNAME_VERSION"];
      }
      $query .= "),\n";

      $indices = _db_gen_indices($NEW, $newdef, $newtable, $secondary);
      foreach($indices as $dummy => $index) {
	$query .= __db_create_index($index, false) . ",\n";
      }

      $engine = isset($DEF[$newtable]["ENGINE"]) ? $DEF[$newtable]["ENGINE"] : "";
      if(!$engine) $engine = "myisam";
      $query = preg_replace("/,\s*\Z/m", "\n", $query);
      $query .= ") engine=$engine;\n";
      $query .= __db_create_tpview($NEW, $newtable, $restrict);
    } else { // isset($OLD[$newtable]) => use "alter table"
      $olddef = $OLD[$newtable];
      $flag = 0;
      $flag_col = 0;
      $after = "";
      foreach ($newdef["FIELDS"] as $field => $value) {
	if(@$value["VIRTUAL"]) {
	  //$query .= "/* omitting VIRTUAL $field */\n";
	  continue;
	}
	if(isset($value["CHANGE_FROM"])) {
	  $count++;
	  $oldfield = $value["CHANGE_FROM"];
	  $query .= "alter table $tablename\n";
	  $query .= "  change column " . $oldfield . " " . __db_create_field($field, $value) . " $after;\n";
	  unset($olddef["FIELDS"][$oldfield]);
	  $flag++;
	  $flag_col++;
	} elseif(isset($olddef["FIELDS"][$field])) {
	  $oldvalue = $olddef["FIELDS"][$field];
	  $diff = false;
	  foreach(array("SQL_TYPE", "DEFAULT", "BETWEEN", "LENGTH", "REGEX") as $test) {
	    if(isset($value[$test]) &&
	       (!isset($oldvalue[$test]) ||
		$value[$test] != $oldvalue[$test])) {
	      $diff = true;
	    }
	  }
	  if($diff) {
	    $count++;
	    $query .= "alter table $tablename\n";
	    $query .= "  modify column " . __db_create_field($field, $value) . " $after;\n";
	    $flag++;
	    $flag_col++;
	  }
	  if(!isset($value["DEFAULT"]) && isset($oldvalue["DEFAULT"])) {
	    $query .= "alter table $tablename\n";
	    $query .= "  alter column " . $field . " drop default;\n";
	    $flag++;
	    $flag_col++;
	  }
	} else { // create new column
	  $count++;
	  $query .= "alter table $tablename\n";
	  $query .= "  add column" . __db_create_field($field, $value) . " $after;\n";
	  $flag++;
	  $flag_col++;
	}
	$after = "after $field";
      }

      $oldindices = _db_gen_indices($OLD, $olddef, $newtable, $secondary);
      $newindices = _db_gen_indices($NEW, $newdef, $newtable, $secondary);
      foreach ($newindices as $index) {
	if(!in_array($index, $oldindices)) {
	  $count++;
	  $query .= "alter table $tablename\n";
	  $query .= "  add" . __db_create_index($index, false) . ";\n";
	  $flag++;
	}
      }
      foreach ($oldindices as $index) {
	if(!in_array($index, $newindices)) {
	  $count++;
	  $query .= "alter table $tablename\n";
	  $query .= "  drop" . __db_create_index($index, true) . ";\n";
	  $flag++;
	}
      }

      foreach ($olddef["FIELDS"] as $field => $value) {
	if(@$value["VIRTUAL"]) {
	  //$query .= "/* omitting OLD VIRTUAL $field */\n";
	  continue;
	}
	if(!isset($newdef["FIELDS"][$field])) {
	  $count++;
	  $query .= "alter table $tablename\n";
	  $query .= "  drop column $field;\n";
	  $flag++;
	  $flag_col++;
	}
      }
      if($flag_col) {
	// whenever the *_tp table changes, mysql seems to require
	// recreation of the view (otherwise the old definition would remain)
	$count++;
	$query .= __db_create_tpview($NEW, $newtable, $restrict);
      } elseif($flag) $query .= "\n";
    }
  }
  foreach($OLD as $oldtable => $olddef) {
    if(!isset($NEW[$newtable])) {
      $count++;
      $query .= "drop table " . $oldtable . "_tp\n";
      $count++;
      $query .= "drop view " . $oldtable . "\n\n";
    }
  }
  return $query;
}


function _query($database, $query) {
  global $ERROR;
  $env = array("DB" => $database);
  $res = _db_multiquery($env, true, $query, array("_db_cb_update"));
  if($ERROR) {
    die("Query error:<br>\n" . $query . "<br>\nError: " . $ERROR);
  }
  return $res;
}

$list = array();
$dir = opendir($BASEDIR . "/schema/");
while(($name = readdir($dir)) != false) {
  if(preg_match("/^schema-?[0-9]+\.php$/", $name)) {
    echo "Found schema: $name<br>\n";
    $list[] = $name;
  }
 }
closedir($dir);

rsort($list);

if(!isset($list[0])) {
  die("Error: no schema found!<br>\n");
 }

if(isset($list[1])) {
  $old_schema = "$BASEDIR/schema/" . $list[1];
  echo "Old schema: '$old_schema'<br>\n";
  require_once($old_schema);
  if(@$EXTRA) $SCHEMA = array_merge_recursive($SCHEMA, $EXTRA);
  $check = db_check_syntax($SCHEMA, $SYNTAX);
  if($check) {
    die("bad syntax in OLD: $check");
  }
  $OLD = db_mangle_schema($SCHEMA);
 } else {
  echo "No old schema exists => generate code for creation of complete database<br>\n";
  $OLD = array();
 }

$new_schema = "$BASEDIR/schema/" . $list[0];
echo "New schema: '$new_schema'<br>\n";
unset($SCHEMA); unset($EXTRA);
require_once($new_schema);
if(@$EXTRA) $SCHEMA = array_merge_recursive($SCHEMA, $EXTRA);
$check = db_check_syntax($SCHEMA, $SYNTAX);
if($check) {
  die("bad syntax in NEW: $check\n");
 }
$NEW = db_mangle_schema($SCHEMA);

$outname = "$BASEDIR/compiled/schema.php";
$tmpname = "$outname.tmp";
echo "writing compiled schema => temporary '$tmpname' ........<br>\n";
$fp = fopen($tmpname, "w");
if(!$fp) {
  die("cannot create file '$outname'\n");
 }
$text = "<?php // this file was automatically generated by the schema precompiler from '$new_schema'.\n\n// ====> DO NOT EDIT! <===\n\n";
$text .= "\$SCHEMA =\n" . db_data_to_code($NEW);
$text .= ";\n?>\n";

if(fwrite($fp, $text) != strlen($text)) {
  die("cannot write output file '$tmpname'\n");
 }
fclose($fp);
echo "done.<br>\n";
echo "renaming '$tmpname' => '$outname'<br>\n";
rename($tmpname, $outname);
echo "done.<br>\n";

$SCHEMA = $NEW;

echo "<br>---------------------------------------------------<br><br>\n";

foreach($CONFIG["CONNECTIONS"] as $database => $ddef) {
  $DB_NAME = $ddef["BASE"];
  echo "-----> Database '$database' name='$DB_NAME' <------<br><br>\n";
  $connection = _db_open($database, true, true);
  if(!$connection) {
    die("Cannot connect to database '$database' (fix connection and/or sourcecode in config.php<br>\n");
  }

  $query = "";
  if(!$OLD) {
    $query .= "drop database if exists $DB_NAME;\n";
    $query .= "create database if not exists $DB_NAME;\n\n";
  }

  $query .= "use $DB_NAME;\n\n";

  $query .= _db_create_tables($OLD, $NEW, $database, $count);
  if(!$count) {
    echo "// nothing to do with this database -> skipping<br/><br/>\n";
    continue;
  }

  $QUERIES[$database] = $query;
  echo "<tt>\n";
  echo preg_replace(array("/ /", "/\n/"), array("&nbsp;", "<br>\n"), $query);
  echo "</tt>\n";
  
  echo "<br>---------------------------------------------------<br><br>\n";

  echo "<form action='" . $_SERVER['PHP_SELF'] . "' method='get'>\n";
  if($OLD) {
    echo "Update schema for database: ";
  } else {
    echo "CREATE / OVERWRITE database: ";
  }
  echo "<input name='ok' type='submit' value='$database'/>\n";
  echo "<br/><br/>=======================================================<br/><br/>\n";
  echo "</form>\n";
}

$data_profiles =
  array(
	array(
	      "profile_name" => "root",
	      "profile_descr" => "Root Profile (virtual)",
	      ),
	array(
	      "profile_name" => "guest",
	      "profile_descr" => "Guest Profile",
	      ),
	array(
	      "profile_name" => "admin",
	      "profile_descr" => "Admin Profile",
	      ),
	array(
	      "profile_name" => "user",
	      "profile_descr" => "Default User Profile",
	      ),
	);

$data_languages =
  array(
	array(
	      "language_name" => "generic",
	      "language_template" => "generic_generic.php",
	      "language_descr" => "This is the default generic template library (English)",
	      ),
	);

function create_data($table, $data) {
  global $ERROR;
  $ERROR = "";
  if(!db_insert($table, $data)) {
    echo "create \$INITDATA for table '$table': ERROR $ERROR<br>\n";
  }
}

function create_profiles() {
  global $SCHEMA;
  global $INITDATA;
  global $data_profiles;

  if(!($data = @$INITDATA["profiles"])) {
    $data = $data_profiles;
  }

  foreach($SCHEMA["profiles"]["FIELDS"] as $field => $val) {
    if(preg_match("/\At_/", $field)) {
      $data[0][$field] = "W";
      $data[2][$field] = "W";
      $data[3][$field] = "R";
    }
    if(preg_match("/\At_users/", $field)) {
      $data[3][$field] = "W";
    }
    if(preg_match("/\At_profiles/", $field)) {
      $data[2][$field] = "r";
      $data[3][$field] = "r";
    }
    if(preg_match("/\Af_/", $field)) {
      $data[0][$field] = "W";
      $data[2][$field] = "W";
      $data[3][$field] = "R";
    }
    if(preg_match("/\Af_users_/", $field)) {
      $data[2][$field] = "R";
    }
    if(preg_match("/\Af_users_user_password/", $field)) {
      $data[2][$field] = "W";
      $data[3][$field] = "W";
    }
    if(preg_match("/\Af_downloads_/", $field)) {
      $data[3][$field] = "W";
    }
    if(preg_match("/_(id|version|deleted|modified_from|modified_by)\Z/", $field)) {
      $data[3][$field] = "r";
    }
  }
  foreach($SCHEMA as $table => $tdef) {
    if(preg_match("/2/", $table)) {
      $data[3]["t_".$table] = "r";
    }
    if(preg_match("/patch2kernels/", $table)) {
      $data[3]["t_".$table] = "R";
    }
  }
  $data[3]["t_downloads"] = "W";
  $INITDATA["profiles"] = $data;
}

function create_languages() {
  global $data_languages;
  global $INITDATA;

  if(!($data = @$INITDATA["languages"])) {
    $data = $data_languages;
    $INITDATA["languages"] = $data;
  }
}

function create_users() {
  global $SCHEMA;
  global $data_profiles;
  global $INITDATA;

  if(@$INITDATA["users"]) {
    return;
  }

  if(!($data = @$INITDATA["profiles"])) {
    $data = $data_profiles;
  }

  foreach($data as $idx => $rec) {
    $rec["user_name"] = $rec["profile_name"];
    $rec["user_password"] = $rec["profile_name"];
    unset($rec["profile_descr"]);
    $data[$idx] = $rec;
  }
  $INITDATA["users"] = $data;
}

$database = @$_REQUEST["ok"];
if($database) {
  global $CONFIG;
  $query = $QUERIES[$database];
  echo "starting query on database '$database'........<br>\n";
  //global $debug; $debug = true;
  $res = _query($database, $query);
  if($res) {
    echo "success.<br>\n";
  } else {
    die("query failure ($ERROR)<br>\n");
  }
  if(@$CONFIG["USE_AUTH"]) {
    global $OLD;
    if($OLD) {
      echo "Keeping profiles.<br>\n";
    } else {
      echo "creating bootstrap data for profiles / languages / users ........<br>\n";
      create_profiles();
      create_languages();
      create_users();
      echo "done.<br>\n";
    }
  }
  //$debug = true;
  foreach($INITDATA as $table => $data) {
    echo "INIT: writing \$INITDATA['$table']......<br>\n";
    create_data($table, $INITDATA[$table]);
  }
  echo "ready.<br>\n";
 }

?>
</body>
</html>
