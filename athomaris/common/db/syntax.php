<?php

/*
 * Check syntax of $STRUCTURE against $SYNTAX-definition
 */
function db_check_syntax($STRUCTURE, $SYNTAX) {
  if(is_null($SYNTAX)) { // no further checks
    return "";
  }
  if(is_array($SYNTAX)) {
    if(array_key_exists("|", $SYNTAX)) { // check alternatives
      $res = "";
      foreach($SYNTAX as $dummy => $subsyntax) {
	$error = db_check_syntax($STRUCTURE, $subsyntax);
	if(!$error)
	  return "";
	if($res)
	  $res .= " | ";
	$res .= $error;
      }
      return "ALTERNATIVES: ($res)";
    }
    if(!is_array($STRUCTURE)) {
      return "must be an array";
    }
    if(!count($SYNTAX)) { // empty array -> don't check anything
      return "";
    }
    // check the array indexes
    foreach($STRUCTURE as $subidx => $substruct) {
      $ok = array();
      $err = array();
      $res = "";
      foreach($SYNTAX as $key => $subsyntax) {
	if(is_string($key)) {
	  if($key == "" && !is_string($subidx)) {
	    $err[$key] = "index '$subidx' should be a string";
	    if($res)
	      $res .= " | ";
	    $res .= $err[$key];
	    continue;
	  }
	  if($subidx != $key) {
	    $subres = db_check_syntax($subidx, $key);
	    if($subres) {
	      $err[$key] = "subcheck key '$subidx': $subres";
	      if($res)
		$res .= " | ";
	      $res .= $err[$key];
	      continue;
	    }
	  }
	}
	$ok[$key] = true;
      }
      $keyok = count($ok);
      if(!$keyok)
	return "no key is matching: ($res)";
      // now check the subtree
      $subres = "";
      $allok = 0;
      foreach($SYNTAX as $key => $subsyntax) {
	$suberr[$key] = "";
	if(@$ok[$key] || is_int($key)) {
	  $lastkey = $key;
	  $suberr[$key] = db_check_syntax($substruct, $subsyntax);
	  if($suberr[$key]) {
	    if($subres)
	      $subres .= " | ";
	    $subres .= "key '$subidx' substructure problem: $suberr[$key]";
	  } else {
	    $allok++;
	    break;
	  }
	}
      }
      if(!$allok) {
	if($keyok == 1) { // don't tell all the other silly alternatives
	  return "single key '$subidx': " . $suberr[$lastkey];
	}
	return $subres;
      }
    }
    return "";
  }
  if(is_string($SYNTAX)) {
    if(!is_string($STRUCTURE)) {
      return "must be string";
    }
    if($regex = $SYNTAX) {
      if(substr($regex, 0, 1) != "/")
	$regex = "/^$regex$/";
      if(!preg_match($regex, $STRUCTURE)) {
	return "string '$STRUCTURE' does not match regex '$SYNTAX'";
      }
    }
    return "";
  }
  if(is_int($SYNTAX)) {
    if(!is_int($STRUCTURE)) {
      return "must be int";
    }
    return "";
  }
  if(is_bool($SYNTAX)) {
    if(!is_bool($STRUCTURE)) {
      return "must be bool";
    }
    return "";
  }
  return "unknown part cannot be checked";
}

function db_enforce_syntax($STRUCTURE, $SYNTAX) {
  global $debug;
  if(@$debug) {
    $err = db_check_syntax($STRUCTURE, $SYNTAX);
    if($err) {
      die("BAD SYNTAX: $err");
    }
  }
}

?>
