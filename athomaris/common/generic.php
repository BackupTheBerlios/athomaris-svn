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

if(!$BASEDIR)
  die("script improperly called, no \$BASEDIR set\n");

$download = @$_REQUEST["download"];
if($download)
  $debug = false;

if(!@$debug)
  $debug = false; // shutup php warnings

require_once($BASEDIR . "/../common/db/db.php");

require_once($BASEDIR . "/../common/app.php");

app_get_templates();

///////////////////////////////////////////////////////////////////////////

// call the "app stuff" for generic table management

if(!$download) {
  $data = array("TITLE" => "inspect_$table", "ACTION" => $_SERVER["PHP_SELF"] . "?table=$table");
  tpl_header($data);
  tpl_body_start($data);

  app_links();

  if(@$debug) { print_r($_REQUEST); echo "<br>\n"; }
 }

if(isset($_REQUEST["primary"])) {
  $cond = app_get_id($table, $_REQUEST["primary"]);
  if($download) {
    app_display_download($table, $cond, $download, @$_REQUEST["filename"]);
  } else {
    app_display_record($table, $cond);
  }
 } else {
  app_input_record($table);
  app_display_table($tp_table);
 }

if(!$download) {
  tpl_body_end(null);
  tpl_footer(null);
 }

?>
