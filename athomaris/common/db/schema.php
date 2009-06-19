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

if(!@$USER) {
  $USER = @$_SERVER["PHP_AUTH_USER"];
 }
if(!@$USER) {
  $USER = $argv[1];
 }
if(!@$PASSWD) {
  $PASSWD = @$_SERVER["PHP_AUTH_PW"];
 }
if(!@$PASSWD) {
  $PASSWD = $argv[2];
 }

////////////////////////////////////////////////////////////////////////

// general (missing in PHP)

/* The default array_merge_recursive() has the semantics that
 * conflicts on scalar values are resolved by _creating_
 * an array of both scalar values. Sometimes you don't want
 * that semantics, you just want that $a2 _always_ takes precendence
 * over $a1 (overwriting the former value), without changing
 * scalarness, similar to plain array_merge().
 * Here is a function doing that:
 */
function array_replace_recursive($a1, $a2) {
  if(!is_array($a1) || !is_array($a2)) {
    return $a2;
  }
  foreach($a2 as $idx => $value) {
    if(is_string($idx)) {
      $a1[$idx] = array_replace_recursive(@$a1[$idx], $value);
    } else {
      $a1[] = $value;
    }
  }
  return $a1;
}

////////////////////////////////////////////////////////////////////////

  /* Syntax checks.
   * This is only needed for precompiling.
   */

$SYNTAX_VALUE =
  array("|" =>
	false,
	0,
	"",
	null,
	);

$RAW_ID = "[A-Z_a-z][A-Z_a-z0-9]*";
$RAW_IDLIST = "${RAW_ID}(?:,${RAW_ID})*";
$RAW_DOTID = "$RAW_ID(?:\\.$RAW_ID)?";

$SYNTAX_ID = "/^${RAW_ID}$/";
$SYNTAX_DOTID = "/^${RAW_DOTID}$/";
$SYNTAX_IDLIST = "/^(?:${RAW_ID}(?:,${RAW_ID})*)?$/";
$SYNTAX_DOTIDLIST = "/^${RAW_DOTID}(?:,${RAW_DOTID})*$/";
$SYNTAX_EXPRLIST = "/^${RAW_ID}[(]${RAW_DOTID}[)](?:,${RAW_ID}[(]${RAW_DOTID}[)])*$/";
$SYNTAX_CONDFIELD = "/^${RAW_DOTID}(?:\\s*[<>=!@%]+\\s*(?:${RAW_DOTID})?)?$/";
$SYNTAX_JOIN_ON = "/^${RAW_ID}\.${RAW_ID}={RAW_ID}\.${RAW_ID}$/";


$SYNTAX_COND =
  array(
	$SYNTAX_CONDFIELD => $SYNTAX_VALUE,
	&$SYNTAX_COND,
	);

$SYNTAX_QUERY =
  array(
	"JOINFIELDS" => $SYNTAX_IDLIST,
	"JOIN_ON" => array($SYNTAX_JOIN_ON),
	"JOIN_DEPENDANT" => array($SYNTAX_JOIN_ON), // only for internal use
	"TABLE" =>
	array("|" =>
	      $SYNTAX_IDLIST,
	      array(
		    $SYNTAX_ID,
		    $SYNTAX_ID => &$SYNTAX_QUERY,
		    ),
	      ),
	"FIELD" =>
	array("|" =>
	      $SYNTAX_DOTIDLIST,
	      array(
		    $SYNTAX_DOTID,
		    $SYNTAX_ID => &$SYNTAX_QUERY,
		    ),
	      ),
	"AGG" =>
	array(
	      "FIELD" => $SYNTAX_EXPRLIST,
	      "GROUP" => $SYNTAX_IDLIST,
	      ),
	"COND" => $SYNTAX_COND,
	"ORDER" => $SYNTAX_IDLIST,
	"START" => 0,
	"COUNT" => 0,
	);

$SYNTAX_UPDATE =
  array(
	"TABLE" => $SYNTAX_ID,
	"MODE" =>
	array("|" =>
	      "INSERT",
	      "UPDATE",
	      "REPLACE",
	      "DELETE",
	      ),
	"DATA" => array(),
	"COND" => $SYNTAX_COND, // usually not set; setting this may trash large parts of your table!
	"CB" => "", // internal
	"TEST_MODE" => null, // internal
	"RAW_MODE" => null, // internal
	);


$SYNTAX_FIELD =
  array(
	"TYPE" => "",
	"OPTIONS" => $SYNTAX_IDLIST,
	"DEFAULT" => null,
	"CHANGE_FROM" => $SYNTAX_ID,
	//"BETWEEN" => null,
	"LENGTH" => array(0),
	"REGEX" => "",
	"REFERENCES" =>
	array(
	      "" =>
	      array(
		    "on delete cascade",
		    "on delete set null",
		    "on update cascade",
		    "on update set null"
		    ),
	      ),
	//"TARGET" => false, // for actual values, not in use
	"ACCESS" => "/^[nrRwW]$/",
	"REALNAME" => $SYNTAX_ID,

	"TPL_DISPLAY" => $SYNTAX_IDLIST,
	"TPL_INPUT" => $SYNTAX_ID,
	"ENCRYPT" => "",
	"NO_PROFILE" => false,

	"POOL_DATA" => $SYNTAX_QUERY,
	"SUB_DATA" => $SYNTAX_QUERY,
	"SUB_DELETE" => false, // allow deletions of SUB_DATA
	);

$SYNTAX_SCHEMA =
  array(
	"" =>
	array(
	      "VIEW" => $SYNTAX_QUERY,

	      "TEMPORAL" => true,
	      "FIELDNAME_ID" => $SYNTAX_ID,
	      "FIELDNAME_VERSION" => $SYNTAX_ID,
	      "FIELDNAME_DELETED" => $SYNTAX_ID,
	      "FIELDNAME_MODIFIED_FROM" => $SYNTAX_ID,
	      "FIELDNAME_MODIFIED_BY" => $SYNTAX_ID,

	      "FIELDS" =>
	      array(
		    "" => $SYNTAX_FIELD,
		    ),
	      "SINGULAR" => "",
	      "PRIMARY" => "",
	      "INDEX" => array(),
	      "UNIQUE" => array(),
	      "DB" => "",
	      "ENGINE" => "",
	      "ACCESS" => "/^[nrRwW]$/",
	      "REALNAME" => $SYNTAX_ID,

	      // the following is questionable and will be changed to a better systematics
	      "ADD_PROFILE_TABLE" => "",
	      "PROFILE_TABLE" => $SYNTAX_FIELD,
	      "ADD_PROFILE_FIELD" => "",
	      "PROFILE_FIELD" => $SYNTAX_FIELD,
	      "USER_RESTRICT" => "",
	      ),
	);

$SYNTAX_EXTRA_FIELD =
  array(
	"VIRTUAL" => false,
	"REF_LINKS" => "",
	"SIZE" => 0,
	"SHOW_FIELD" => "", // xxx bitte abschaffen! inkonsistent!
	"EXTRA_FIELD" => "",
	"DATA_SPLIT" => array(),
	"LINES" => 0,
	"UPLOAD" => false,
	"IMMUTABLE" => "",
	"FORCE" => "/USER/",
	"CB_DOWNLOAD" => $SYNTAX_ID,
	);

$SYNTAX_EXTRA =
  array(
	"" =>
	array(
	      "FIELDS" =>
	      array(
		    "" => $SYNTAX_EXTRA_FIELD,
		    ),
	      "TOOLS" => array(),
	      "CB_SUBMIT" => $SYNTAX_ID,
	      "CB_BEFORE" => $SYNTAX_ID,
	      "CB_BEFORE_INSERT" => $SYNTAX_ID,
	      "CB_BEFORE_UPDATE" => $SYNTAX_ID,
	      "CB_BEFORE_REPLACE" => $SYNTAX_ID,
	      "CB_BEFORE_DELETE" => $SYNTAX_ID,
	      "CB_AFTER" => $SYNTAX_ID,
	      "CB_AFTER_INSERT" => $SYNTAX_ID,
	      "CB_AFTER_UPDATE" => $SYNTAX_ID,
	      "CB_AFTER_REPLACE" => $SYNTAX_ID,
	      "CB_AFTER_DELETE" => $SYNTAX_ID,
	      ),
	);

$SYNTAX_SCHEMA = array_replace_recursive($SYNTAX_SCHEMA, $SYNTAX_EXTRA);

require_once($BASEDIR . "/../common/db/syntax.php");
require_once($BASEDIR . "/../common/db/infra.php");

////////////////////////////////////////////////////////////////////////

// default schema extensions

$PROFILE_SCHEMA =
  array(
	"profiles" =>
	array(
	      "ADD_PROFILE_TABLE" => "/.*/",
	      "PROFILE_TABLE" =>
	      array("TYPE" => "char(1)",
		    "DEFAULT" => "'n'",
		    "TPL_INPUT" => "input_modes",
		    ),
	      "ADD_PROFILE_FIELD" => "/\A(?!profiles)/",
	      "PROFILE_FIELD" =>
	      array("TYPE" => "char(1)",
		    "DEFAULT" => "'n'",
		    "TPL_INPUT" => "input_modes",
		    ),
	      "FIELDS" =>
	      array(
		    "profile_name" =>
		    array("TYPE" => "varchar(32)",
			  "DEFAULT" => "'UNKNOWN'",
			  ),
		    "profile_descr" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "''",
			  ),
		    ),
	      "UNIQUE" => array("profile_name"),
	      ),
	"languages" =>
	array(
	      "FIELDS" =>
	      array(
		    "language_name" =>
		    array("TYPE" => "varchar(16)",
			  "DEFAULT" => "'generic'",
			  ),
		    "language_template" =>
		    array("TYPE" => "varchar(80)",
			  "DEFAULT" => "'generic_generic.php'",
			  ),
		    "language_descr" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "''",
			  ),
		    ),
	      "UNIQUE" => array("language_name"),
	      ),
	);

$USER_SCHEMA =
    array(
	  "users" =>
	  array("USER_RESTRICT" => "user_name",
		"FIELDS" =>
		array(
		      "user_name" =>
		      array("TYPE" => "varchar(16)",
			    "DEFAULT" => "'guest'",
			    ),
		      "user_password" =>
		      array("TYPE" => "varchar(41)",
			    "DEFAULT" => null,
			    "ENCRYPT" => "password()",
			    "TPL_INPUT" => "input_password",
			    ),
		      "profile_name" =>
		      array("TYPE" => "varchar(32)",
			    "DEFAULT" => "'guest'",
			    "REFERENCES" => array("profiles.profile_name" => array("on update cascade", "on delete cascade")),
			    ),
		      "language_name" =>
		      array("TYPE" => "varchar(16)",
			    "DEFAULT" => "'generic'",
			    "REFERENCES" => array("languages.language_name" => array("on update cascade", "on delete cascade")),
			    ),
		      "user_descr" =>
		      array("TYPE" => "text",
			    "DEFAULT" => "''",
			    ),
		      ),
		"UNIQUE" => array("user_name"),
		),
	  );

$USER_EXTRA =
  array(
	"users" =>
	array(
	      "FIELDS" =>
	      array(
		    "profile_name" =>
		    array(
			  "REF_LINKS" => "profiles.profile_name",
			  "POOL_DATA" =>
			  array(
				"TABLE" => "profiles",
				"ORDER" => "profile_name",
				),
			  "SIZE" => 4,
			  "SHOW_FIELD" => "profile_name",
			  ),
		    "language_name" =>
		    array(
			  "REF_LINKS" => "languages.language_name",
			  "POOL_DATA" =>
				 array(
				       "TABLE" => "languages",
				       "ORDER" => "language_name",
				       ),
			  "SIZE" => 4,
			  "SHOW_FIELD" => "language_name",
			  ),
		    ),
	      ),
	);

$ENGINE_VALUE = "(=.*|%.*|\/.*\/)";

$ENGINE_SCHEMA =
  array(
	"bps" =>
	array(
	      "FIELDS" =>
	      array(
		    "bp_name" =>
		    array("TYPE" => "varchar(32)",
			  "DEFAULT" => "''",
			  "REGEX" => $RAW_ID,
			  ),
		    "bp_statefield" =>
		    array("TYPE" => "varchar(64)",
			  "DEFAULT" => "''",
			  "REGEX" => $RAW_DOTID,
			  ),
		    "bp_inputs" =>
		    array("TYPE" => "varchar(255)",
			  "DEFAULT" => "''",
			  "REGEX" => $RAW_IDLIST,
			  ),
		    "bp_outputs" =>
		    array("TYPE" => "varchar(255)",
			  "DEFAULT" => "''",
			  "REGEX" => $RAW_IDLIST,
			  ),
		    "bp_joinwith" =>
		    array("TYPE" => "varchar(64)",
			  "DEFAULT" => "''",
			  ),
		    "bp_comment" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "null",
			  ),
		    ),
	      "UNIQUE" => array("bp_name"),
	      ),
	"rules" =>
	array(
	      "FIELDS" =>
	      array(
		    "bp_name" =>
		    array("TYPE" => "varchar(32)",
			  "DEFAULT" => "''",
			  "REGEX" => $RAW_ID,
			  "REFERENCES" => array("bps.bp_name" => array("on delete cascade", "on update cascade")),
			  ),
		    "rule_prio" =>
		    array("TYPE" => "int",
			  "DEFAULT" => "0",
			  ),
		    "rule_startvalue" =>
		    array("TYPE" => "varchar(255)",
			  "DEFAULT" => "''",
			  "REGEX" => $ENGINE_VALUE,
			  ),
		    "rule_condition" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "''",
			  "REGEX" => "(|\?\s*$RAW_ID\s*$ENGINE_VALUE)",
			  ),
		    "rule_location" =>
		    array("TYPE" => "varchar(64)",
			  "DEFAULT" => "''",
			  "REGEX" => "(|$RAW_ID@$RAW_DOTID)",
			  ),
		    "rule_firevalue" =>
		    array("TYPE" => "varchar(255)",
			  "DEFAULT" => "''",
			  ),
		    "rule_action" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "''",
			  "REGEX" => "\A(?:script|url|insert)\s+.*",
			  ),
		    "rule_timeout" =>
		    array("TYPE" => "int",
			  "DEFAULT" => "0",
			  ),
		    "rule_comment" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "null",
			  ),
		    ),
	      "UNIQUE" => array("bp_name,rule_prio"),
	      ),
	"conts" =>
	array("FIELDS" =>
	      array(
		    "bp_name" =>
		    array("TYPE" => "varchar(32)",
			  "DEFAULT" => "''",
			  "REGEX" => $RAW_ID,
			  "REFERENCES" => array("rules.bp_name" => array("on delete cascade", "on update cascade")),
			  ),
		    "rule_prio" =>
		    array("TYPE" => "int",
			  "DEFAULT" => "0",
			  "REFERENCES" => array("rules.rule_prio" => array("on delete cascade", "on update cascade")),
			  ),
		    "cont_prio" =>
		    array("TYPE" => "int",
			  "DEFAULT" => "0",
			  ),
		    "cont_match" =>
		    array("TYPE" => "varchar(255)",
			  "DEFAULT" => "''",
			  "REGEX" => $ENGINE_VALUE,
			  ),
		    "cont_action" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "''",
			  "REGEX" => "\A(?:script|url|insert)\s+.*",
			  ),
		    "cont_endvalue" =>
		    array("TYPE" => "varchar(255)",
			  "DEFAULT" => "''",
			  ),
		    "cont_comment" =>
		    array("TYPE" => "text",
			  "DEFAULT" => "null",
			  ),
		    ),
	      "UNIQUE" => array("bp_name,rule_prio,cont_prio"),
	      ),
	);

$ENGINE_EXTRA =
  array(
       );

////////////////////////////////////////////////////////////////////////

// first pass: add temporal fields
function _db_pass_temporal($SCHEMA) {
  global $EXTENSIONS;
  foreach($SCHEMA as $table => $tdef) {
    if(@$tdef["VIEW"])
      continue;
    $singular = _db_singular($table, $SCHEMA);
    $vers = array();
    foreach($EXTENSIONS as $ext) {
      $field = "FIELDNAME_" . strtoupper($ext);
      $vers[$ext] = @$tdef[$field];
      if(!$vers[$ext]) {
	$vers[$ext] = $singular . "_$ext";
	$tdef[$field] = $vers[$ext];
      }
    }
    if(!isset($tdef["TEMPORAL"])) {
      // by default, all tables are temporal. If needed, explicitly disable it.
      $tdef["TEMPORAL"] = true;
    }
    if($tdef["TEMPORAL"]) {
      $new = array();
      $new[$vers["id"]] = array("TYPE" => "bigint", "OPTIONS" => "auto_increment", "AUTO_FIELD" => true, "ACCESS" => "w");
      $new[$vers["version"]] = array("TYPE" => "timestamp", "DEFAULT" => "current_timestamp", "AUTO_FIELD" => true, "ACCESS" => "w");
      $new[$vers["deleted"]] = array("TYPE" => "boolean", "DEFAULT" => "false", "NO_PROFILE" => true, "AUTO_FIELD" => true, "ACCESS" => "w");
      $new[$vers["modified_from"]] = array("TYPE" => "varchar(16)", "DEFAULT" => "null", "ACCESS" => "w");
      $new[$vers["modified_by"]] = array("TYPE" => "varchar(16)", "DEFAULT" => "null", "AUTO_FIELD" => true, "ACCESS" => "w");
      $tdef["FIELDS"] = array_merge($new, $tdef["FIELDS"]); // old settings take precedence
    }
    $SCHEMA[$table] = $tdef;
  }
  return $SCHEMA;
}

function _db_update_profiles($SCHEMA) {
  $RES = $SCHEMA;
  foreach($SCHEMA as $proftable => $profdef) {
    if($regex_table = @$profdef["ADD_PROFILE_TABLE"]) {
      //echo "AHA: $proftable<br>\n";
      $default_table = $profdef["PROFILE_TABLE"];
      if($default_table) {
	foreach($SCHEMA as $table => $tdef) {
	  if(preg_match($regex_table, $table)) {
	    $name = "t_$table";
	    //echo "  hmm: $name<br>\n";
	    $RES[$proftable]["FIELDS"][$name] = $default_table;
	  }
	}
      }
    }
    if($regex_field = @$profdef["ADD_PROFILE_FIELD"]) {
      $default_field = $profdef["PROFILE_FIELD"];
      if($default_field) {
	foreach($SCHEMA as $table => $tdef) {
	  foreach($tdef["FIELDS"] as $field => $fdef) {
	    if(@$fdef["NO_PROFILE"])
	      continue;
	    $name = $table . "_" . $field;
	    if(preg_match($regex_field, $name)) {
	      $name = "f_$name";
	      //echo "  hmm: $name<br>\n";
	      $RES[$proftable]["FIELDS"][$name] = $default_field;
	    }
	  }
	}
      }
    }
  }
  return $RES;
}

function _db_pass_main($MYSCHEMA) {
  // main pass
  $RES = array();
  $maindatabase = _db_maindatabase();
  foreach($MYSCHEMA as $table => $tdef) {
    $newtdef = $tdef;
    if(@$tdef["VIEW"]) {
      global $SCHEMA;
      $oldschema = $SCHEMA;
      $SCHEMA = $MYSCHEMA;
      $q2 = _db_mangle_query($databases, $tdef["VIEW"]);
      $SCHEMA = $oldschema;
      $tdef["FIELDS"] = $q2["SCHEMA_FIELDS"];
      $newtdef["ACCESS"] = "R";
      $newtdef["TEMPORAL"] = false;
      $newtdef["TOOLS"] = array("tool_search" => true, "tool_page" => true);
    }

    if(!@$tdef["REALNAME"]) {
      $newtdef["REALNAME"] = $table;
    }
    if(!$singular = @$tdef["SINGULAR"]) {
      $singular = _db_singular($table, $MYSCHEMA);
      $newtdef["SINGULAR"] = $singular;
    }
    if(!$primary = @$tdef["PRIMARY"]) {
      $primary = _db_primary($table, $MYSCHEMA);
      $newtdef["PRIMARY"] = $primary;
    }
    if(!@$tdef["DB"]) {
      $newtdef["DB"] = $maindatabase;
    }
    $newfields = array();
    foreach($tdef["FIELDS"] as $field => $fdef) {
      if(!@$fdef["REALNAME"]) {
	$fdef["REALNAME"] = $field;
      }
      if(isset($fdef["REFERENCES"])) {
	$assoc = $fdef["REFERENCES"];
	foreach($assoc as $foreign => $props) {
	  $all = preg_split("/\s*\.\s*/s", $foreign, 2);
	  $ftable = $all[0];
	  $ffield = $all[1];
	  if(!isset($MYSCHEMA[$ftable])) {
	    die("REFERENCES: foreign table '$ftable' does not exist");
	  }
	  if(!isset($MYSCHEMA[$ftable]["FIELDS"][$ffield])) {
	    die("REFERENCES: foreign field '$ffield' of table '$ftable' does not exist");
	  }
	  // the follwing will not work for cyclic references, deliberately
	  $RES[$ftable]["XREF"][$ffield][] = array($table, $field, $props); 
	}
      }
      $newfields[$field] = $fdef;
    }
    //echo "<br>newfields: "; print_r($newfields); echo "<br>\n";
    $newtdef["FIELDS"] = $newfields;
    $RES[$table] = $newtdef;
  }
  return $RES;
}

/* compute additional information for _presentation_.
 *
 */
function _db_pass_typeinfo($MYSCHEMA) {
  // augment with additional info
  $res = $MYSCHEMA;
  foreach($MYSCHEMA as $table => $tinfo) {
    // default links from the primary and secondary keys to SELF
    $primary = _db_primary($table, $MYSCHEMA);
    if($primary) {
      $res[$table]["FIELDS"][$primary]["TYPE"] = "hidden";
      $make_ref = array_merge(array($primary), @$tinfo["UNIQUE"]);
    }
    foreach($make_ref as $field) {
      if(!@$tinfo["FIELDS"][$field])
	continue;
      if(!@$res[$table]["FIELDS"][$field]["TPL_DISPLAY"])
	$res[$table]["FIELDS"][$field]["TPL_DISPLAY"] = "display_ref"; /* set to default */
    }
    if(!@$tinfo["TOOLS"]) {
      $res[$table]["TOOLS"] = array("tool_search" => true, "tool_history" => true, "tool_page" => true); /* set to default */
    }
    // check all FIELDS
    foreach($tinfo["FIELDS"] as $field => $finfo) {
      // add default REF_LINKS for the first possible REFERENCES
      if(@$finfo["REFERENCES"] && !array_key_exists("REF_LINKS", $finfo)) {
	foreach($finfo["REFERENCES"] as $link => $dummy) {
	  $split = preg_split("/\./", $link);
	  $ref_table = $split[0];
	  $ref_field = $split[1];
	  // only create a default runtime link when the reference identifies target tuples _uniquely_
	  if($ref_field == @$MYSCHEMA[$ref_table]["PRIMARY"] || array_search($ref_field, @$MYSCHEMA[$ref_table]["UNIQUE"]) !== false) {
	    $finfo["REF_LINKS"] = $link;
	    $res[$table]["FIELDS"][$field]["REF_LINKS"] = $link;
	    if(!@$finfo["POOL_DATA"]) {
	      $pool = array(
			    "TABLE" => $ref_table,
			    "ORDER" => $ref_field,
			    );
	      $finfo["POOL_DATA"] = $pool;
	      $res[$table]["FIELDS"][$field]["POOL_DATA"] = $pool;
	    }
	    if(!$finfo["SIZE"]) {
	      $finfo["SIZE"] = 4;
	      $res[$table]["FIELDS"][$field]["SIZE"] = 4;
	    }
	    //"SHOW_FIELD" => "profile_name",
	    break;
	  }
	}
      }
      // set some default values
      $res[$table]["FIELDS"][$field]["MINLEN"] = 0; /* set to dummy */
      $res[$table]["FIELDS"][$field]["MAXLEN"] = 999; /* set to dummy */
      $res[$table]["FIELDS"][$field]["SIZE"] = 40; /* set to default */
      $type = @$finfo["TYPE"];
      $res[$table]["FIELDS"][$field]["SQL_TYPE"] = $type;
      //echo "matching type '$type'\n";
      if($field == $primary) {
	$restype = "hidden";
      } elseif(preg_match("/^(?:date)?time(stamp)?$/", $type)) {
	$restype = "string";
      } elseif(preg_match("/^((?:small)?int([\(][0-9]+[\)])?|bigint)$/", $type)) {
	$restype = "int";
	$res[$table]["FIELDS"][$field]["SIZE"] = 10; /* set to default */
      } elseif(preg_match("/^bool(ean)?$/", $type)) {
	$restype = "bool";
	$res[$table]["FIELDS"][$field]["MAXLEN"] = null;
	$res[$table]["FIELDS"][$field]["SIZE"] = null;
	if(!@$res[$table]["FIELDS"][$field]["TPL_DISPLAY"])
	  $res[$table]["FIELDS"][$field]["TPL_DISPLAY"] = "display_bool";
      } elseif(preg_match("/^(var)?char[\(]([0-9]+)[\)]$/", $type, $matches)) {
	$restype = "string";
	if(!$matches[1]) {
	  $res[$table]["FIELDS"][$field]["MINLEN"] = $matches[2];
	}
	$res[$table]["FIELDS"][$field]["MAXLEN"] = $matches[2];
      } elseif(preg_match("/^(tiny|medium|long)?text$/", $type, $matches)) {
	$restype = "text";
	$res[$table]["FIELDS"][$field]["SIZE"] = 80; /* set to default */
	$res[$table]["FIELDS"][$field]["LINES"] = 4; /* set to default */
	$res[$table]["FIELDS"][$field]["MAXLEN"] = null;
	if(!@$res[$table]["FIELDS"][$field]["TPL_DISPLAY"])
	  $res[$table]["FIELDS"][$field]["TPL_DISPLAY"] = "display_text";
      } elseif($finfo) {
	$restype = "virtual";
      } else {
	die("ERROR in schema: cannot translate SQL type '$type'\n");
      }
      if(@($finfo["REF_LINKS"])) {
	//echo "Aha: table=$table field=$field<br>\n";
	$restype = "selector";
	$ref = $finfo["REF_LINKS"];
	$split = preg_split("/\./", $ref);
	//print_r($split); echo "<br>\n";
	$res[$table]["FIELDS"][$field]["REF_TABLE"] = $split[0];
	$res[$table]["FIELDS"][$field]["REF_FIELD"] = $split[1];
	$res[$table]["FIELDS"][$field]["REF_FIELDS"] = split(",", $split[1]);
      }
      $res[$table]["FIELDS"][$field]["TYPE"] = $restype;
      if(isset($finfo["LENGTH"])) {
	$res[$table]["FIELDS"][$field]["MINLEN"] = $finfo["LENGTH"][0];
	$res[$table]["FIELDS"][$field]["MAXLEN"] = $finfo["LENGTH"][1];
      }
      if(($fields = @$finfo["EXTRA_FIELD"])) {
	$res[$table]["FIELDS"][$field]["EXTRA_FIELD"] = split(",", $fields);
      } else {
	$res[$table]["FIELDS"][$field]["EXTRA_FIELD"] = array();
      }
      foreach(array("SUB_DATA", "POOL_DATA") as $qtype) {
	if(($sub_query = @$finfo[$qtype])) {
	  if(!@$sub_query["FIELD"]) {
	    // initialize with reasonable defaults: everything mentioned in JOINFIELDS and ORDER
	    $all = array();
	    if($add = @$sub_query["JOINFIELDS"]) {
	      $all = explode(",", $add);
	    }
	    if($add = @$sub_query["ORDER"]) {
	      $all = array_unique(array_merge(explode(",", $add), $all));
	    }
	    $res[$table]["FIELDS"][$field][$qtype]["FIELD"] = implode(",", $all);
	  }
	  if($qtype == "SUB_DATA") {
	    if(!isset($finfo["VIRTUAL"])) {
	      $res[$table]["FIELDS"][$field]["VIRTUAL"] = true;
	    }
	    if(!isset($finfo["TPL_DISPLAY"])) {
	      $res[$table]["FIELDS"][$field]["TPL_DISPLAY"] = "display_reflist";
	    }
	    if(!isset($finfo["TPL_INPUT"])) {
	      $res[$table]["FIELDS"][$field]["TPL_INPUT"] = "input_sublist";
	    }
	  }
	}
      }
      // user settings always take precedence
      foreach(array("SIZE", "SORT", "LINES", "TPL_INPUT", "TPL_DISPLAY") as $further) {
	if(@$finfo[$further])
	  $res[$table]["FIELDS"][$field][$further] = $finfo[$further];
      }
    }
  }
  return $res;
}

function db_mangle_schema($MYSCHEMA) {
  global $SYNTAX_SCHEMA;
  global $CONFIG;
  $error = db_check_syntax($MYSCHEMA, $SYNTAX_SCHEMA);
  if($error)
    die("bad syntax in schema: $error<br>\n");

  // adding components...

  if(@$CONFIG["USE_BUSINESS_ENGINE"]) {
    global $ENGINE_SCHEMA, $ENGINE_EXTRA;
    $tmp = array_replace_recursive($ENGINE_SCHEMA, $ENGINE_EXTRA);
    $MYSCHEMA = array_replace_recursive($tmp, $MYSCHEMA);
  }

  if(@$CONFIG["USE_AUTH"]) {
    global $USER_SCHEMA, $USER_EXTRA;
    $tmp = array_replace_recursive($USER_SCHEMA, $USER_EXTRA);
    $MYSCHEMA = array_replace_recursive($tmp, $MYSCHEMA);
    global $PROFILE_SCHEMA;
    $MYSCHEMA = array_replace_recursive($PROFILE_SCHEMA, $MYSCHEMA);
  }

  // mangling

  $MYSCHEMA = _db_pass_temporal($MYSCHEMA);

  $MYSCHEMA = _db_update_profiles($MYSCHEMA);

  $MYSCHEMA = _db_pass_main($MYSCHEMA);
  
  $MYSCHEMA = _db_pass_typeinfo($MYSCHEMA);

  return $MYSCHEMA;
}


////////////////////////////////////////////////////////////////////////


function db_data_to_code($data, $indent = 0) {
  $prefix = str_repeat(" ", $indent);
  if(is_null($data)) {
    return $prefix . "null";
  } elseif(is_array($data)) {
    if(!count($data)) {
      return $prefix . "array()";
    }
    $res = $prefix . "array(\n";
    foreach($data as $key => $val) {
      $res .= db_data_to_code($key, $indent+6) . " =>";
      if(is_array($val) && count($val)) {
	$res .= "\n";
	$res .= db_data_to_code($val, $indent+6) . ",\n";
      } else {
	$res .= " ";
	$res .= db_data_to_code($val, 0) . ",\n";
      }
    }
    $res .= $prefix . ")";
    return $res;
  } elseif(is_bool($data)) {
    $data = $data ? "true" : "false";
    return "$prefix$data";
  } elseif(is_int($data)) {
    return "$prefix$data";
  } elseif(is_string($data)) {
    $data = preg_replace(array("/([\$\"])/", "/\\\\/", "/\n/"), array("\\\\\$1", "\\\\", "\\\\n"), $data);
    return $prefix . "\"$data\"";
  } elseif($data) {
    return "$prefix$data";
  } else {
    return "${prefix}000";
  }
}

?>