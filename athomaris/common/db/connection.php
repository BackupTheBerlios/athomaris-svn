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

$SYNTAX_CONFIG =
    array(
	  "USE_AUTH" => true,
	  "USE_BUSINESS_ENGINE" => false,
	  "CONNECTIONS" =>
	  array(
		"" =>
		array(
		      "DRIVER" => "",
		      "MASTER" => "",
		      "SLAVES" => array(),
		      "BASENAME" => "",
		      "BASE" => "",
		      "USER" => "",
		      "PASSWD" => "",
		      ),
		),
	  );

require_once("$BASEDIR/../common/db/syntax.php");

function _db_get_driver($database) {
  global $CONFIG;
  global $BASEDIR;
  $driver = @$CONFIG["CONNECTIONS"][$database]["DRIVER"];
  if(!$driver)
    $driver = "mysql";
  require_once("$BASEDIR/../common/db/$driver/driver.php");
  return $driver;
}

////////////////////////////////////////////////////////////////////////

/* try to connect to one of the mirror servers first if not writing.
 */
function _db_open(&$database, $write, $do_init = false) {
  global $CONN_CACHE;
  if(!$database)
    $database = _db_maindatabase();

  // use dynamic programming for connections
  if(isset($CONN_CACHE[$database][$write])) {
    return $CONN_CACHE[$database][$write];
  }

  global $BASEDIR;
  global $SYNTAX_CONFIG;
  global $CONFIG;
  global $BASENAME;
  global $USER;
  global $PASSWD;
  global $ERROR;
  global $debug;

  if(!@$CONFIG["CONNECTIONS"]) { // use some reasonable default
    if(!$BASENAME)
      $BASENAME = "main";
    $CONFIG["CONNECTIONS"] = 
      array("DEFAULT" =>
	    array("MASTER" => "localhost",
		  "BASENAME" => $BASENAME,
		  )
	    );
  }

  $ERROR = db_check_syntax($CONFIG, $SYNTAX_CONFIG);
  if($ERROR) {
    print_r($CONFIG); echo "<br>\n";
    die("_db_open syntax error: $ERROR");
    return null;
  }


  $driver = _db_get_driver($database);
  $host = @$CONFIG["CONNECTIONS"][$database]["MASTER"];
  if(!$host)
    $host = "localhost";
  $basename = @$CONFIG["CONNECTIONS"][$database]["BASENAME"];
  if(!$basename)
    $basename = @$CONFIG["CONNECTIONS"][$database]["BASE"];
  if(!$basename)
    $basename = $BASENAME;
  if($do_init)
    $basename = "";
  $user = @$CONFIG["CONNECTIONS"][$database]["USER"];
  if(!$user)
    $user = $USER;
  $passwd = @$CONFIG["CONNECTIONS"][$database]["PASSWD"];
  if(!$passwd)
    $passwd = $PASSWD;
  if(!$write && @$CONFIG["CONNECTIONS"][$database]["SLAVES"]) {
    // randomly determine a mirror for reading
    $max = count($CONFIG["CONNECTIONS"][$database]["SLAVES"]);
    $idx = crc32($USER) % $max;
    $host = $CONFIG["CONNECTIONS"][$database]["SLAVES"][$idx];
  }

  if($debug) echo"_db_open driver=$driver host=$host, user=$user, passwd=$passwd, basename=$basename<br>\n";

  $call = "${driver}_do_open";
  $connection = $call($host, $user, $passwd, $basename);

  if(!$connection) {
    return null;
  }

  $CONN_CACHE[$database][$write] = $connection;
  return $connection;
}

function _db_close($database = null) {
  global $CONN_CACHE;
  if(is_string($database)) {
    $call =  _db_get_driver($database) . "_do_close";
    if(($test = $CONN_CACHE[$database][false])) {
      $call($test);
    }
    if(($test = $CONN_CACHE[$database][true])) {
      $call($test);
    }
    
    unset($CONN_CACHE[$database][false]);
    unset($CONN_CACHE[$database][true]);
  }
  // otherwise we have to search in the cache
  $to_kill = $database;
  foreach($CONN_CACHE as $database => $sub) {
    foreach($sub as $idx => $connection) {
      if($connection == $to_kill || is_null($to_kill)) {
	$call =  _db_get_driver($database) . "_do_close";
	$call($connection);
	unset($CONN_CACHE[$database][$idx]);
      }
    }
  }
}

////////////////////////////////////////////////////////////////////////

// raw query interface


function _db_raw_query($database, $write, $query) {
  global $ERROR;
  $ERROR = "";
  global $debug; if(@$debug) echo "raw_query: $query<br/>\n";

  $connection = _db_open($database, $write);
  $call =  _db_get_driver($database) . "_raw_query";
  $res = $call($connection, $query);
  return $res;
}

////////////////////////////////////////////////////////////////////////

// data queries

function _db_do_query($database, $query) {
  global $ERROR;
  $ERROR = "";
  global $debug; if(@$debug) echo "query: $query<br/>\n";
  
  $connection = _db_open($database, false);
  if(!$connection) {
    return null;
  }
  $call = _db_get_driver($database) . "_do_query";
  $res = $call($connection, $query);
  return $res;
}

function _db_multiquery(&$env, $write, $query, $cb_list) {
  global $ERROR;
  $ERROR = "";
  global $debug; if(@$debug) echo "multiquery: $query<br/>\n";

  $database = $env["DB"];
  $connection = _db_open($database, $write);
  if(!$connection) {
    if($debug) die("no connection\n");
    return null;
  }
  $call = _db_get_driver($database) . "_multiquery";
  //if($debug) echo "calling '$call'<br>\n";
  $res = $call($env, $connection, $query, $cb_list);
  return $res;
}

////////////////////////////////////////////////////////////////////////

// virtualized callbacks

function _db_cb_process_data(&$env, $resultset) {
  $database = $env["DB"];
  $call = _db_get_driver($database) . "_cb_process_data";
  $res = $call($env, $resultset);
  return $res;
}

////////////////////////////////////////////////////////////////////////

// generate sql statements

function _db_make_query($database, &$subqs, $qstruct) {
  $call = _db_get_driver($database) . "_make_query";
  $res = $call($subqs, $qstruct);
  return $res;
}

function _db_make_update($database, $qstruct, &$cb_list) {
  $call = _db_get_driver($database) . "_make_update";
  $res = $call($qstruct, $cb_list);
  return $res;
}

?>
