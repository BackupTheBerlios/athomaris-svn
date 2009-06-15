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

if(!@$BASEDIR)
  $BASEDIR = dirname($_SERVER["SCRIPT_FILENAME"]);
if($BASEDIR == ".") 
  $BASEDIR = getcwd();

function _tpl_skipcomments(&$text) {
  if(preg_match("/^(\s|^[#][^\n]*\n|[\/][\/][^\n]*\n|([\/][\*]([^\*]|[\*][^\/])*[\*][\/]))+(.*)$/s", $text, $matches)) {
    $text = $matches[4];
    return true;
  }
  return false;
}

/* Split some input into tokens
 */
function _tpl_token(&$cmd) {
  _tpl_skipcomments($cmd);
  $all = preg_split("/(\s+|\s*,\s+)/s", $cmd, 2);
  //echo "'$cmd' => '".$all[0]."' . '".$all[1]."'\n";
  //echo " token '".$all[0]."'\n";
  if(isset($all[1])) {
    $cmd = $all[1];
  } else {
    $cmd = "";
  }
  return $all[0];
}

/* Translate an argument to its php representation.
 * Substitute $-replacements.
 */
function _tpl_var($name) {
  $name = trim($name);
  //echo "{ _tpl_var '$name'\n";
  $res = "";
  while(preg_match("/^([^\$]*)[\$]([A-Za-z0-9_]+)(.*)$/s", $name, $matches)) {
    $res .= $matches[1];
    $match = $matches[2];
    $name = $matches[3];
    $index = _tpl_var($match);
    if(substr($index, 0, 1) != "\$") {
      $index = "\"$index\"";
    }
    $res .= "\$data[" . $index . "]";
    while(preg_match("/^->([\$]*[A-Za-z0-9_]*)(.*)$/s", $name, $matches)) {
      $match = $matches[1];
      $name = $matches[2];
      if(substr($match, 0, 1) == "\$") {
	$match = _tpl_var($match);
      } else {
	$match = "\"$match\"";
      }
      $res .= "[$match]";
    }
  }
  $res .= $name;
  //echo "} res='$res'\n";
  return $res;
}

/* Translate a bunch of templates into php-variables $TEMPLATES and $TEXTS.
 * The intermediate step of variables ist needed
 * because php cannot redefine ordinary functions.
 * Redefinition is essentially carried out by overwriting
 * previous values in $TEMPLATES and $TEXTS.
 */
function tpl_translate(&$TEMPLATES, &$TEXTS, $text) {
  $parsed = "";
  $count = 1;
  $mode = false;
  while($text) {
    if(!$parsed && _tpl_skipcomments($text)) {
      continue;
    }
    // look for special commands in braces.
    if(preg_match("/\A((?:[^\{\\\\]|[\\\\][\{\}\n])*?)\{([^\}]*?)[\/]?\}(.*)/s", $text, $matches)) {
      if($mode) {
	$list = $matches[1];
	while($list) {
	  if(_tpl_skipcomments($list)) {
	    continue;
	  }
	  if(preg_match("/^([A-Za-z0-9_]+) *= *([^\n]*)\n(.*)/s", $list, $listmatches)) {
	    $key = $listmatches[1];
	    $val = trim($listmatches[2]);
	    $list = $listmatches[3];
	    $TEXTS[$key] = $val;
	    continue;
	  }
	  die("unparsable TEXTLIST\n");
	}
      } else { // normal case
	$html = preg_replace(array("/\\\\\{/", "/\\\\\}/"), array("{", "}"), $matches[1]);
	if($html) {
	  $parsed .= "?>$html<?php ";
	}
      }
      $stmt = $matches[2];
      $text = $matches[3];
      $trailslash = false;
      if(substr($stmt, -1, 1) == "/") {
	$stmt = substr($stmt, 0, -1);
	$trailslash = true;
      }
      $orig_cmd = _tpl_token($stmt);
      $cmd = strtolower($orig_cmd);
      switch($cmd) {
      case "include":
	eval("\$filename = " . $stmt . "\n;");
	global $BASEDIR;
	if(substr($filename, 0, 1) != "/") $filename = $BASEDIR . "/" . $filename;
	echo "including '$filename'<br>\n";
	$text = _tpl_read($filename) . $text;
	$res = "";
	break;
      case "template":
	eval("\$tpl_name = " . $stmt . "\n;");
	$res =  "function tpl_$tpl_name(\$data) {\n";
	$res .=  "  global \$TEXTS;\n";
	//$res .=  "  global \$TEMPLATES;\n";
	// add automatic hooks: any templates may be extended
	$res .=  "  if(function_exists(\"tpl_before_$tpl_name\")) tpl_before_$tpl_name(\$data);\n";
	$parsed = "";
	break;
      case "/template":
	$parsed .=  "\n  if(function_exists(\"tpl_after_$tpl_name\")) tpl_after_$tpl_name(\$data);\n";
	$parsed .= "}\n";
	// remove backslash-newlines
        $parsed = preg_replace("/\\\\\n\s*/m", "", $parsed);
	// optimize some silly patterns
        $parsed = preg_replace("/\?>(\s*)<\?php/s", "\$1", $parsed);
        $parsed = preg_replace("/<\?php\s*\?>/", "", $parsed);
	// remove whitespace around standalone php statements
        $parsed = preg_replace("/^\s*((?:<\?php.*?\?>\s*?)+)\s*$/ms", "\$1", $parsed);
	// remove blank lines
        $parsed = preg_replace("/^\s*$\n/m", "", $parsed);
	$TEMPLATES[$tpl_name] = $parsed;
	$parsed = "";
	$res = "";
	break;
      case "tpl":
      case "hook":
	//eval("\$name = " . _tpl_token($stmt) . "\n;");
	$name = _tpl_var(_tpl_token($stmt));
	$data = "\$data";
	$merge = "";
	while($stmt) {
	  if($merge) $merge .= ", ";
	  $key = _tpl_token($stmt);
	  if(substr($key, 0, 1) == "(" && substr($key, -1, 1) == ")") {
	    $data = _tpl_var($key);
	  } else {
	    $token = _tpl_token($stmt);
	    if($token != "=>") {
	      die("expected '=>' after key '$key', but received '$token' rest='$stmt'\n");
	    }
	    $val = _tpl_var(_tpl_token($stmt));
	    $merge .= "$key => $val";
	  }
	}
	if($merge)  $data = "array_merge($data, array($merge))";
	$res =  "\$call = \"tpl_\" . $name; ";
	if($cmd == "hook") { // only try to call defined templates
	  $res .= "if(function_exists(\$call)) ";
	} else { // die when undefined templates are to be called
	  $res .= "if(!function_exists(\$call)) die(\"\\nUndefined template: '\".$name.\"'\\n\"); ";
	}
	$res .=  "\$call($data); ";
	// dont indent _stand-alone_ template calls
	$parsed = preg_replace("/^\s+\Z/m", "", $parsed);
	break;
      case "loop":
	$var = _tpl_token($stmt);
	if(strtolower($var) == "split") {
	  $pattern = _tpl_token($stmt);
	  $var = _tpl_token($stmt);
	  $var = "preg_split($pattern, $var)";
	}
	if(strtolower($var) == "as") {
	  $var = "\$data";
	} else {
	  $var = _tpl_var($var);
	  $next = _tpl_token($stmt);
	  if(strtolower($next) != "as") {
	    die("LOOP: missing keyword 'AS'\n");
	  }
	}
	$key = _tpl_token($stmt);
	$tmp = "\$tmp_" . $count;
	$res =  "\$data_old_$count = \$data; $tmp = $var; if($tmp && is_array($tmp)) foreach($tmp as " . _tpl_var($key);
	$after = "";
	if(substr($var, 0, 1) == "\$")
	  $after = "$var = $tmp; ";
	$after .= "\$data = \$data_old_$count;";
	$STACK[] = $after;
	$count++;
	$next = _tpl_token($stmt);
	if($next) {
	  if($next != "=>") {
	    die("LOOP: missing keyword '=>'\n");
	  }
	  $val = _tpl_token($stmt);
	  $res .=  " => " . _tpl_var($val);
	}
	$res .= ") { ";
	break;
      case "/loop":
	$pop = array_pop($STACK);
	$res = "} $pop ";
	break;
      case "elseif":
	$cmd = "} elseif";
	// fallthrough
      case "if":
	$cond = _tpl_var($stmt);
	$test = _tpl_token($stmt);
	if(preg_match("/\Aperm\Z/i", $test)) {
	  $v1 = _tpl_var(_tpl_token($stmt));
	  $v2 = _tpl_var(_tpl_token($stmt));
	  $v3 = _tpl_var(_tpl_token($stmt));
	  if($v3) {
	    $cond = "db_access_field($v1, $v2, $v3)";
	  } else {
	    $cond = "db_access_table($v1, $v2)";
	  }
	  $cond .= _tpl_var($stmt);
	}
	$cmd = preg_replace("/perm/", "", $cmd);
	$res =  "$cmd(@($cond)) { ";
	break;
      case "else":
	$res =  "} else { ";
	break;
      case "/if":
	$res = "} ";
	break;
      case "php":
	$stmt = _tpl_var($stmt);
	$res =  "$stmt ";
	break;
      case "var":
	$var = _tpl_var(_tpl_token($stmt));
	$next = _tpl_token($stmt);
	if($next != "=") {
	  die("VAR: expected '='\n");
	}
	$expr = _tpl_var($stmt);
	$res = "$var = $expr; ";
	break;
      case "unset":
	$var = _tpl_var(_tpl_token($stmt));
	$res = "unset($var); ";
	break;
      case "textlist":
	$mode = true;
	break;
      case "/textlist":
	$mode = false;
	break;
      case "text":
	$var = _tpl_var($stmt);
	$res = "echo _tpl_text($var); ";
	break;
      case "raw": // dangerous!
	$var = _tpl_var($stmt);
	$res = "echo $var; ";
	break;
      case "param":
	$var = _tpl_var($stmt);
	$res = "echo _tpl_esc_param($var); ";
	break;
      case "ascii":
	$var = _tpl_var($stmt);
	$res = "echo _tpl_format_ascii($var); ";
	break;
      case "preview":
	$var = _tpl_var($stmt);
	$res = "echo _tpl_format_preview($var); ";
	break;
      case "header":
	$var = _tpl_var($stmt);
	$res = "header($var); ";
	break;
      case "row":
	$var = _tpl_var($stmt);
	$res = "echo _tpl_encode_row($var); ";
	break;
      case "printf":
	$pattern = _tpl_var(_tpl_token($stmt));
	while($stmt) {
	  $pattern .= ", " ._tpl_var(_tpl_token($stmt));
	}
	$res = "echo _tpl_esc_html(sprintf($pattern)); ";
	break;
      default:
	$var = _tpl_var($orig_cmd); /* preserve case */
	$res = "echo _tpl_esc_html($var); ";
      }
      $parsed .= $res;
      continue;
    }
    die("unparsable input text: '$text'\n");
  }
}

function _tpl_read($filename) {
  $fp = fopen($filename, "r");
  if(!$fp) return null;
  $text = fread($fp, 4096*1024);
  fclose($fp);
  return $text;
}

function tpl_output($TEMPLATES, $TEXTS, $filename) {
  $code = "<?php // This file is generated from $filename\n\n//    =====> do not edit! <=====\n\ninclude_once(\"\$BASEDIR/../common/tpl/tpl_defs.php\");\n\n";
  foreach($TEMPLATES as $key => $res) {
    $code .= "// ------------------------------------- template: $key ------------------\n\n$res\n\$TEMPLATES['$key'] = true;\n\n";
  }
  $code .= "\n// ----------------------------------------- LISTS ---------------------\n\n";
  foreach($TEXTS as $key => $res) {
    $code .= "\$TEXTS['$key'] = '$res';\n";
  }
  $code .= "?>\n";

  $tmpname = "$filename.tmp";
  $fp = fopen($tmpname, "w");
  $len = fwrite($fp, $code);
  if($len != strlen($code)) {
    die("cannot write file '$tmpname'\n");
  }
  fclose($fp);
  rename($tmpname, $filename);
}

function tpl_translate_all($dirname) {
  $dir1 = opendir($dirname);
  while(($name1 = readdir($dir1)) != false) {
    if(preg_match("/^[a-zA-Z_]/", $name1)) {
      echo "found directory '$name1'<br>\n";
      $dir2 = opendir("${dirname}/$name1");
      while(($name2 = readdir($dir2)) != false) {
	if(preg_match("/\.tpl$/", $name2)) {
	  $src = "${dirname}/$name1/$name2";
	  echo "compiling '$src' ...<br>\n";
	  $TEMPLATES = array();
	  $TEXTS = array();
	  $text =  _tpl_read($src);
	  if($text) {
	    tpl_translate($TEMPLATES, $TEXTS, $text);
	    $dst = "${dirname}/../compiled/".$name1."_".$name2;
	    $dst = preg_replace("/\.tpl$/", ".php", $dst);
	    echo "    writing $dst ... <br>";
	    tpl_output($TEMPLATES, $TEXTS, $dst);
	    echo "done.<br><br>\n";
	  }
	}
      }
    }
  }
}

tpl_translate_all("${BASEDIR}/lang");

?>
