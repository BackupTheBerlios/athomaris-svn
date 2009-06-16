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

$BASEDIR="."; require_once("db/schema.php");

/* find all associations which should go into the
 * attribute list of the parent tag. Remove them
 * from @data and return the attribute part.
 */
function _gen_attrs(&$data, $which_attrs) {
  if(!is_array($data))
    return "";
  $res = "";
  foreach($data as $key => $value) {
    if(is_string($value) && array_key_exists($key, $which_attrs)) {
      if(strstr($value, "\"") === false) {
	$res .= " $key=\"$value\"";
      } else {
	$res .= " $key='$value'";
      }
      unset($data[$key]);
    }
  }
  return $res;
}

/* generate output for a single XML node.
 */
function _gen_node(&$res, $which_attrs, $tag, $value, $blanks, $indent) {
  $at = _gen_attrs($value, $which_attrs);
  if(!$value) {
    $res .= "$blanks<$tag$at/>\n";
  } elseif(is_string($value) || is_int($value) || is_float($value)) {
    $res .= "$blanks<$tag$at>$value</$tag>\n";
  } elseif(is_array($value)) {
    $res .= "$blanks<$tag$at>\n";
    $res .= _gen_list($value, $which_attrs, $indent+2);
    $res .= "$blanks</$tag>\n";
  } else {
    $res .= "$blanks<!-- tag '$tag': bad type (should not happen) -->\n";
  }
}

/* generate output for a list of nodes.
 */
function _gen_list($data, $which_attrs, $indent) {
  $blanks = str_repeat(" ", $indent);
  // catch cases where "almost invalid" @data is supplied
  if(is_null($data)) {
    return "$blanks<!--null-->\n";
  }
  if(is_string($data) || is_int($data) || is_float($data)) {
    $data = preg_replace("/(\n)/m", "\n$blanks", $data);
    return "$blanks$data\n";
  }
  if(!is_array($data)) {
    return "$blanks<!-- bad data type -->\n";
  }
  if(!$data) {
    return "$blanks<!--array()-->\n";
  }
  /* now we know: $data is an array, where
   * unindexed items are plain text, and indexed
   * items are further subnodes.
   */
  $res = "";
  if(@$which_attrs["TAGNAME"] && @$data["TAGNAME"]) { // this is a DOM-like node
    $tag = $data["TAGNAME"];
    unset($data["TAGNAME"]);
    _gen_node($res, $which_attrs, $tag, $data, $blanks, $indent);
    return $res;
  }
  // otherwise an old-style node model is used
  foreach($data as $idx => $value) {
    $att = "";
    if(is_int($idx) || is_null($idx)) { // unindexed item -> text
      $res .= _gen_list($value, $which_attrs, $indent);
    } elseif(is_string($idx)) { // indexed item -> subnode
      $tag = $idx;
      _gen_node($res, $which_attrs, $tag, $value, $blanks, $indent);
    } else {
      $res .= "$blanks<!-- bad tag (should not happen) -->\n";
    }
  }
  return $res;
}

function php2xml($data, $root = "root", $which_attrs = array("TAGNAME" => true), $indent = 0) {
  $res = "<?xml version=\"1.0\" standalone=\"yes\"?>\n";
  if(@$which_attrs["TAGNAME"]) {
    $data["TAGNAME"] = $root;
  } else { // old-style node format
    $data = array($root => $data);
  }
  $res .= _gen_list($data, $which_attrs, $indent);
  return $res;
}

// testing

$test = array();

$test[] = "1";
$test["key1"] = "2";
$test[] = "3";
$test["key2"] = 4;
$test[] = 5;
$test[] = array();
$test[] = null;
$test[] = array(1.1);
$test["TAGNAME"] = "myroot";

echo php2xml($test);

//echo php2xml($SYNTAX_EXTRA);
echo php2xml($PROFILE_SCHEMA);
echo php2xml($PROFILE_SCHEMA, "root", array("TYPE" => true, "DEFAULT" => true));

/////////////////////////////////////////////////////////////////

// parser

$NameStartChar = "[:A-Z_a-z\xc0-\xd6\xd8-\xf6\xf8-\xff]";
$NameChar      = "[-.[0-9]\xb7:A-Z_a-z\xc0-\xd6\xd8-\xf6\xf8-\xff]";
$Name          = "(?:$NameStartChar$NameChar*)";
$Names         = "$Name(?:\s+$Name)*";
$Nmtoken       = "(?:$NameChar+)";
$Nmtokens      = "(?:$Nmtoken(?:\s+$Nmtoken)*)";

$EntityValue   = "(?:(?:\"[^%&\"]*)\")|(?:'[^%&']*'))"; // incomplete
$AttValue      = "(?:(?:\"[^<&\"]*)\")|(?:'[^<&']*'))"; // incomplete

$CharData      = "(?:[^<&]*)";

$Comment       = "(?:<!--(?:[^-]|-[^-])*-->)";
$PI            = "(?:<\?$Name\s+.*?\?>)";

$CDSect        = "<!\[CDATA\[(.*?)\]\]>";


function _parse_attribs(&$res, &$which_attrs, $tag, &$rest) {
  while(preg_match("/\A\s*([A-Za-z0-9_:])\s*=\s*(.*)/", $rest, $matches)) {
    $attr = $matches[1];
    $rest = $matches[2];
    $which_attrs[$attr] = true;
    if(preg_match("/\A\"([^\"]*)\"(.*)/", $rest, $matches)) {
      $res[$tag][$attr] = $matches[1];
      $rest = $matches[2];
      continue;
    }
    if(preg_match("/\A'([^']*)'(.*)/", $rest, $matches)) {
      $res[$tag][$attr] = $matches[1];
      $rest = $matches[2];
      continue;
    }
    // error: bad string syntax
    break;
  }
}

function _parse_sequence(&$text, &$which_attrs) {
  $res = array();
  // parse tags
  while(preg_match("/\A\s*(.*?)\s*(?:<([A-Za-z0-9_:]+))(.*)\Z/", $text, $matches)) {
    if($matches[1]) {
      $res[] = $matches[1];
    }
    $tag = $matches[2];
    $rest = $matches[3];
    $res[$tag] = array();
    // parse attributes
    _parse_attribs($res, $which_attrs, $tag, $rest);
    // tag closing
    if(preg_match("/\A\s*\/\s*>(.*)/", $rest, $matches)) { // tag is closed
      $text = $matches[2];
      continue;
    }
    if(preg_match("/\A\s*>(.*)/", $rest, $matches)) { // nested tag
      $rest = $matches[2];
      $subdata = _parse_sequence($rest);
      $res[$tag] = array_merge($res[$tag], $subdata);
      // expect correct nesting
      if(preg_match("/\A\s*<\s*\/([^>]*)>(.*)/", $text, $matches)) {
	$check = $matches[1];
	$text = $matches[2];
	if($check != $tag) { // bad tag nesting: assume missing tag and return
	  break;
	}
	continue;
      }
      // error: improperly nested tags
      $text = $rest;
      break;
    }
    // error: tag is improperly formed
    break;
  }
  // rest is text...
  if(preg_match("/\A\s*(.*?)\s*(<\/(.*?)>.*)\Z/", $text, $matches)) {
    if($matches[1]) { // rest of embedded test
      $res[] = $matches[1];
    }
    $text = $matches[2];
  } // else forgotten closing tags will not harm
  return $res;
}

function xml2php($text) {
  global $Comment, $PI, $Name;
  // remove XML declaration
  $text = preg_replace("/\A\s*<\?xml\s+.*?\?>\s*/", "", $text);
  // remove DOCTYPE declarations
  $text = preg_replace("/\A(\s|$Comment|$PI)*/", "", $text);
  $text = preg_replace("/\A<!DOCTYPE\s+($Name)\s*(?:\[([^\]*)]\]\s*)?>/", "", $text);
  $text = preg_replace("/\A(\s|$Comment|$PI)*/", "", $text);

  $res = _parse_sequence($text);
  return $res;
}

?>
