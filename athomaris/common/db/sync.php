<?php

  /* Copyright (C) 2009 Thomas Schoebel-Theuer (ts@athomux.net)
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

require_once("$BASEDIR/../common/db/db.php");

include_once("$BASEDIR/compiled/sync_status.php"); // variable $SYNC_STATUS

function _sync_cb_table(&$env, $oldrow) {
  global $SYNC_STATUS;
  global $debug;
  $dst = $env["DST"];
  $transl = $env["TRANSL"];
  $modes = $env["MODES"];
  $newrow = array();
  foreach($oldrow as $oldkey => $value) {
    $newkey = $transl ? @$transl[$oldkey] : $oldkey;
    if($newkey)
      $newrow[$newkey] = $value;
  }
  if($debug) { echo "sync_data: "; print_r($newrow); echo "<br>\n"; }
  $data = array($newrow);

  $qstruct =
    array(
	  "TABLE" => $dst,
	  "MODE" => "REPLACE",
	  "DATA" => $data,
	  "CB" => "_db_cb_update",
	  "TEST_MODE" => array("EXISTS", "DO_UPDATE"),
	  "RAW_MODE" => true,
	  );
  $ok = _db_update($qstruct);
  if($ok) {
    $old_stamp = $env["MAXTIME"];
    $new_stamp = $oldrow[$env["version"]];
    $old = strtotime($old_stamp);
    $new = strtotime($new_stamp);
    echo "old = $old_stamp ($old) new = $new_stamp ($new)\n";
    if($new > $old) {
      $env["MAXTIME"] = $new_stamp;
    }
  }
  return null; // don't aggregate results in the specific driver
}

function sync_table($src, $dst, $transl, $modes) {
  global $SYNC_STATUS;
  global $SCHEMA;
  global $ERROR;
  global $debug;

  $tdef = $SCHEMA[$src];
  $version = _db_extfield($src, "version");
  $limit = @$SYNC_STATUS[$src][$dst];
  $cond = $limit ? array("$version >" => $limit) : array();
  $qstruct = 
    array(
	  "TABLE" => $src,
	  "FIELD" => array(),
	  "COND" => $cond,
	  "ORDER" => "",
	  "START" => 0,
	  "COUNT" => $debug ? 10 : 0,
	  );

  $q2 = _db_mangle_query($databases, $qstruct);
  // currently only 1 database supported
  $database = key($databases);
  $query = _db_make_query($database, $subqs, $q2);

  $env =
    array(
	  "DB" => $database,
	  "ARG" => $subqs,
	  "CB_PROCESS" => "_sync_cb_table",
	  "DST" => $dst,
	  "TRANSL" => $transl,
	  "MODES" => $modes,
	  "version" => $version,
	  "MAXTIME" => $limit,
	  );

  $ok = _db_multiquery($env, false, $query, array("_db_cb_process_data"));
  if(!$ok) {
    if(!$ERROR)
      $ERROR = "unknown sync error";
    if($debug) echo "sync oops............................ $ERROR <br>\n";
    return false;
  }
  $SYNC_STATUS[$src][$dst] = $env["MAXTIME"];
  return true;
}

function write_syncstatus() {
  global $SYNC_STATUS;
  global $BASEDIR;
  $tmp_name = "$BASEDIR/compiled/sync_status.tmp";
  $fin_name = "$BASEDIR/compiled/sync_status.php";
  $fp = fopen($tmp_name, "w");
  if(!$fp) {
    die("cannot open syncstatus file\n");
  }
  fwrite($fp, "<?php // this file is automatically generated by the DB syncing system.\n\n// ====> DO NOT EDIT unless you EXACTLY know what you are doing! <===\n\n");
  fwrite($fp, "\$SYNC_STATUS =\n" . db_data_to_code($SYNC_STATUS));
  fwrite($fp, ";\n?>\n");
  fclose($fp);
  rename($tmp_name, $fin_name);
}

// testing...
//$debug = true;
sync_table("host", "myhosts", null, null);

?>
