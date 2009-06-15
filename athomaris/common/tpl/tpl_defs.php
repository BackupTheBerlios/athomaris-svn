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

  /* This file contains the template infrastructure for _runtime_.
   */

/*
 * Make output text HTML-safe.
 */
function _tpl_esc_html($text) {
  return htmlentities($text, ENT_QUOTES, "UTF-8");
}

function _tpl_esc_param($text) {
  $text = htmlentities($text, ENT_QUOTES, "UTF-8");
  return preg_replace("/[\?&=]/", "\\\$1", $text);
}

function _tpl_format_ascii($text) {
  return preg_replace(array("/ /", "/\\t/", "/\n/"), array("&nbsp;", "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;", "<br>\n"), _tpl_esc_html($text));
}

function _tpl_format_preview($text) {
  return _tpl_format_ascii(preg_replace("/\A((?:[^\n]*?\n){12})(?:.+)\Z/s", "\$1..................", $text));
}

function _tpl_encode_row($row) {
  $str = "";
  foreach($row as $key => $val) {
    //$code = html_entity_decode($val, ENT_QUOTES, "ISO-8859-1");
    $code = @htmlentities($val, ENT_QUOTES, "UTF-8");
    if($str)
      $str .= ",";
    $str .= "$key='$code'";
  }
  return $str;
}

function _tpl_decode_row($str) {
  $row = array();
  while(preg_match("/\A([^=]+)=(\\\\?')(.*?)\\2,?(.*)\Z/ms", $str, $matches)) {
    $key = $matches[1];
    $val = $matches[3];
    $str = $matches[4];
    $row[$key] = html_entity_decode($val, ENT_QUOTES, "UTF-8");
  }
  return $row;
}

function _tpl_make_hash($data, $field) {
  $hash = array();
  if($data) {
    foreach($data as $row) {
      $key = $row[$field];
      $hash[$key] = true;
    }
  }
  return $hash;
}

/* Lookup a text in the TEXTLIST table.
 */
function _tpl_text($key) {
  global $TEXTS;
  if(isset($TEXTS[$key])) {
    return $TEXTS[$key];
  } else {
    return _tpl_format_ascii($key);
  }
}

?>
