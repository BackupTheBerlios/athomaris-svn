<?php // This file is generated from /home/schoebel/athomaris/demo_business/lang/../compiled/generic_generic.php

//    =====> do not edit! <=====

include_once("$BASEDIR/../common/tpl/tpl_defs.php");

// ------------------------------------- template: dummy ------------------

function tpl_dummy($data) {
  global $TEXTS;
  if(function_exists("tpl_before_dummy")) tpl_before_dummy($data);
  if(function_exists("tpl_after_dummy")) tpl_after_dummy($data);
}

$TEMPLATES['dummy'] = true;

// ------------------------------------- template: vspace ------------------

function tpl_vspace($data) {
  global $TEXTS;
  if(function_exists("tpl_before_vspace")) tpl_before_vspace($data);
?>
<br/>
<?php 
  if(function_exists("tpl_after_vspace")) tpl_after_vspace($data);
}

$TEMPLATES['vspace'] = true;

// ------------------------------------- template: hspace ------------------

function tpl_hspace($data) {
  global $TEXTS;
  if(function_exists("tpl_before_hspace")) tpl_before_hspace($data);
?>
&nbsp;&nbsp;
<?php 
  if(function_exists("tpl_after_hspace")) tpl_after_hspace($data);
}

$TEMPLATES['hspace'] = true;

// ------------------------------------- template: header_download ------------------

function tpl_header_download($data) {
  global $TEXTS;
  if(function_exists("tpl_before_header_download")) tpl_before_header_download($data);
 header("Content-Type: application/octet-stream"); 
 if(@($data["FILENAME"])) { 
 header("Content-Disposition: attachment; filename=".$data["FILENAME"]); 
 } else { 
 header("Content-Disposition: attachment;"); 
 } 
  if(function_exists("tpl_after_header_download")) tpl_after_header_download($data);
}

$TEMPLATES['header_download'] = true;

// ------------------------------------- template: styles ------------------

function tpl_styles($data) {
  global $TEXTS;
  if(function_exists("tpl_before_styles")) tpl_before_styles($data);
?><link rel="stylesheet" type="text/css" href="styles/style.css" title="default_style" />
<?php 
  if(function_exists("tpl_after_styles")) tpl_after_styles($data);
}

$TEMPLATES['styles'] = true;

// ------------------------------------- template: header ------------------

function tpl_header($data) {
  global $TEXTS;
  if(function_exists("tpl_before_header")) tpl_before_header($data);
 header("Content-Type: text/html; charset=utf-8"); ?>
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">
<head>
  <meta http-equiv='Content-Encoding' content='UTF-8' />
  <title><?php echo _tpl_text($data["TITLE"]); ?></title>
<?php $call = "tpl_" . "styles"; if(!function_exists($call)) die("\nUndefined template: '"."styles"."'\n"); $call($data); ?>
</head>
<body>
<form name="main" enctype="multipart/form-data" action="<?php echo _tpl_esc_html($data["ACTION"]); ?>" method="post" onsubmit='
all = document.getElementsByTagName("select");
for(i = 0; i < all.length; i++) {
  elem = all[i];
  if(elem.title == "full") {
    //alert(elem.name);
    elem.multiple = true;
    opt = elem.options;
    for(j = 0; j < opt.length; j++) {
      //alert(opt[j].text);
      opt[j].selected = true;
    }
  }
}
'>
<?php 
  if(function_exists("tpl_after_header")) tpl_after_header($data);
}

$TEMPLATES['header'] = true;

// ------------------------------------- template: footer ------------------

function tpl_footer($data) {
  global $TEXTS;
  if(function_exists("tpl_before_footer")) tpl_before_footer($data);
?>
<br/>
</form>
</body>
</html>
<?php 
  if(function_exists("tpl_after_footer")) tpl_after_footer($data);
}

$TEMPLATES['footer'] = true;

// ------------------------------------- template: links ------------------

function tpl_links($data) {
  global $TEXTS;
  if(function_exists("tpl_before_links")) tpl_before_links($data);
?>
 <div class="links">
<?php $data_old_1 = $data; $tmp_1 = $data["DATA"]; if($tmp_1 && is_array($tmp_1)) foreach($tmp_1 as $data["category"] => $data["cat_def"]) { 
     if(@($data["cat_def"])) { 
       echo _tpl_text($data["category"]); 
       $data_old_2 = $data; $tmp_2 = $data["cat_def"]; if($tmp_2 && is_array($tmp_2)) foreach($tmp_2 as $data["FIELD"] => $data["VALUE"]) { 
         if(@($data["VALUE"])) { 
           if(@(db_access_table($data["FIELD"], "W"))) { ?>
            <a href="<?php echo _tpl_esc_html($data["VALUE"]); ?>"><strong><?php echo _tpl_text($data["FIELD"]); ?></strong></a>
<?php } elseif(@(db_access_table($data["FIELD"], "R"))) { ?>
            <a href="<?php echo _tpl_esc_html($data["VALUE"]); ?>">(<?php echo _tpl_text($data["FIELD"]); ?>)</a>
<?php } 
         } 
       } $data["cat_def"] = $tmp_2; $data = $data_old_2; ?>
    <br/>
<?php } 
   } $data["DATA"] = $tmp_1; $data = $data_old_1; ?>
 </div>
<?php 
  if(function_exists("tpl_after_links")) tpl_after_links($data);
}

$TEMPLATES['links'] = true;

// ------------------------------------- template: display_default ------------------

function tpl_display_default($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_default")) tpl_before_display_default($data);
 $data["FDEF"] = $data["SCHEMA"][$data["TABLE"]]["FIELDS"][$data["FIELD"]];  if(@($data["FDEF"]["REF_TABLE"])) {  $data["REF_TABLE"] = $data["FDEF"]["REF_TABLE"];  $data["REF_FIELD"] = $data["FDEF"]["REF_FIELD"];  $data["REF_FIELDS"] = $data["FDEF"]["REF_FIELDS"];  } else {  $data["REF_TABLE"] = $data["TABLE"];  $data["REF_FIELD"] = $data["FIELD"];  }  if(@($data["FDEF"]["TPL_DISPLAY"])) {  $data_old_3 = $data; $tmp_3 = preg_split("/\s*,\s*/", $data["FDEF"]["TPL_DISPLAY"]); if($tmp_3 && is_array($tmp_3)) foreach($tmp_3 as $data["CALL"]) {  $call = "tpl_" . $data["CALL"]; if(!function_exists($call)) die("\nUndefined template: '".$data["CALL"]."'\n"); $call($data);  } $data = $data_old_3;  } elseif(@($data["FDEF"]["REF_TABLE"])) {  $call = "tpl_" . "display_ref"; if(!function_exists($call)) die("\nUndefined template: '"."display_ref"."'\n"); $call($data);  } elseif(@(isset($data["VALUE"]))) {  echo _tpl_esc_html($data["VALUE"]);  } else { ?> --- <?php }  
  if(function_exists("tpl_after_display_default")) tpl_after_display_default($data);
}

$TEMPLATES['display_default'] = true;

// ------------------------------------- template: display_table ------------------

function tpl_display_table($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_table")) tpl_before_display_table($data);
?>
<table>
<?php if(@($data["DATA"]["0"])) { 
   if(@($data["CAPTION"])) { ?>
   <caption><?php echo _tpl_text($data["CAPTION"]); ?></caption>
<?php } ?>
  <thead>
   <tr>
<?php if(@($data["EXTRAHEAD"])) { $call = "tpl_" . $data["EXTRAHEAD"]; if(!function_exists($call)) die("\nUndefined template: '".$data["EXTRAHEAD"]."'\n"); $call($data); } 
     $data_old_4 = $data; $tmp_4 = $data["SCHEMA"][$data["TABLE"]]["FIELDS"]; if($tmp_4 && is_array($tmp_4)) foreach($tmp_4 as $data["FIELD"] => $data["FDEF"]) { 
      if(@(db_access_field($data["TABLE"], $data["FIELD"], "R"))) { ?>
      <th><a href="<?php echo _tpl_esc_html($data["ACTION"]); ?>&order=<?php echo _tpl_esc_html($data["FIELD"]); ?>"><?php echo _tpl_text($data["FIELD"]); ?></a></th>
<?php } 
     } $data["SCHEMA"][$data["TABLE"]]["FIELDS"] = $tmp_4; $data = $data_old_4; ?>
   </tr>
  </thead>
  <tbody>
<?php $data_old_5 = $data; $tmp_5 = $data["DATA"]; if($tmp_5 && is_array($tmp_5)) foreach($tmp_5 as $data["IDX"] => $data["ROW"]) { 
     if(@($data["ROW"]["deleted"])) { $data["class"] = " class='deleted'";  } elseif(@($data["ROW"]["outdated"])) { $data["class"] = " class='outdated'";  } else { $data["class"] = "";  } ?>
    <tr<?php echo $data["class"]; ?>>
<?php if(@($data["EXTRA"])) { 
        $data_old_6 = $data; $tmp_6 = $data["EXTRA"]; if($tmp_6 && is_array($tmp_6)) foreach($tmp_6 as $data["call"] => $data["id"]) { 
         if(@(!$data["class"] || $data["call"] == "button_clone")) { ?>
	 <td<?php echo $data["class"]; ?>><?php $call = "tpl_" . $data["call"]; if(!function_exists($call)) die("\nUndefined template: '".$data["call"]."'\n"); $call(array_merge($data, array("VALUE" => $data["ROW"][$data["id"]]))); ?></td>
<?php } else { ?>
         <td<?php echo $data["class"]; ?>></td>
<?php } 
        } $data["EXTRA"] = $tmp_6; $data = $data_old_6; 
      } 
      $data_old_7 = $data; $tmp_7 = $data["SCHEMA"][$data["TABLE"]]["FIELDS"]; if($tmp_7 && is_array($tmp_7)) foreach($tmp_7 as $data["FIELD"] => $data["FDEF"]) { 
       if(@(db_access_field($data["TABLE"], $data["FIELD"], "R"))) { 
        $data["VALUE"] = @$data["ROW"][$data["FIELD"]]; ?>
       <td<?php echo $data["class"]; ?>><?php $call = "tpl_" . "display_default"; if(!function_exists($call)) die("\nUndefined template: '"."display_default"."'\n"); $call($data); ?></td>
<?php } 
      } $data["SCHEMA"][$data["TABLE"]]["FIELDS"] = $tmp_7; $data = $data_old_7; ?>
    </tr>
<?php } $data["DATA"] = $tmp_5; $data = $data_old_5; ?>
  </tbody>
<?php } else { ?>
 <tr><td>(table '<?php echo _tpl_text($data["TABLE"]); ?>' is empty)</td></tr>
<?php } 
 if(@($data["order"])) { 
   $call = "tpl_" . "input_hidden"; if(!function_exists($call)) die("\nUndefined template: '"."input_hidden"."'\n"); $call(array_merge($data, array("FIELD" => "order", "VALUE" => $data["order"]))); 
 } ?>
</table>
<?php 
  if(function_exists("tpl_after_display_table")) tpl_after_display_table($data);
}

$TEMPLATES['display_table'] = true;

// ------------------------------------- template: display_subtable ------------------

function tpl_display_subtable($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_subtable")) tpl_before_display_subtable($data);
 $data["FDEF"] = $data["SCHEMA"][$data["TABLE"]]["FIELDS"][$data["FIELD"]];  $data["TABLE"] = $data["FDEF"]["REF_TABLE"];  $data["PRIMARY"] = $data["FDEF"]["REF_FIELD"];  $data["DATA"] = db_selectfields($data["VALUE"],explode(",",$data["FDEF"]["SHOW_FIELD"]));  if(@($data["DATA"])) { ?>
 <table>
  <tbody>
<?php $data_old_8 = $data; $tmp_8 = $data["DATA"]; if($tmp_8 && is_array($tmp_8)) foreach($tmp_8 as $data["IDX"] => $data["ROW"]) { ?>
    <tr>
<?php $data_old_9 = $data; $tmp_9 = $data["ROW"]; if($tmp_9 && is_array($tmp_9)) foreach($tmp_9 as $data["FIELD"] => $data["VALUE"]) { 
       if(@(db_access_field($data["TABLE"], $data["FIELD"], "R"))) { ?>
       <td><?php $call = "tpl_" . "display_default"; if(!function_exists($call)) die("\nUndefined template: '"."display_default"."'\n"); $call($data); ?></td>
<?php } 
      } $data["ROW"] = $tmp_9; $data = $data_old_9; ?>
    </tr>
<?php } $data["DATA"] = $tmp_8; $data = $data_old_8; ?>
  </tbody>
 </table>
<?php } else { ?>
  (no data available)
<?php } 
  if(function_exists("tpl_after_display_subtable")) tpl_after_display_subtable($data);
}

$TEMPLATES['display_subtable'] = true;

// ------------------------------------- template: display_record ------------------

function tpl_display_record($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_record")) tpl_before_display_record($data);
 $data["ROW"] = $data["DATA"]["0"]; 
 $data["IDX"] = 0; 
 if(@($data["ROW"])) { ?>
 <table>
<?php $data_old_10 = $data; $tmp_10 = $data["SCHEMA"][$data["TABLE"]]["FIELDS"]; if($tmp_10 && is_array($tmp_10)) foreach($tmp_10 as $data["FIELD"] => $data["FDEF"]) { 
    if(@(db_access_field($data["TABLE"], $data["FIELD"], "R"))) { 
    $data["VALUE"] = $data["ROW"][$data["FIELD"]]; ?>
   <tr>
     <td><?php echo _tpl_text($data["FIELD"]); ?>:</td>
     <td><?php $call = "tpl_" . "display_default"; if(!function_exists($call)) die("\nUndefined template: '"."display_default"."'\n"); $call($data); ?></td>
   </tr>
<?php } 
   } $data["SCHEMA"][$data["TABLE"]]["FIELDS"] = $tmp_10; $data = $data_old_10; ?>
 </table>
<?php } else { ?>
  (no record exists)
<?php } 
  if(function_exists("tpl_after_display_record")) tpl_after_display_record($data);
}

$TEMPLATES['display_record'] = true;

// ------------------------------------- template: display_reflist ------------------

function tpl_display_reflist($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_reflist")) tpl_before_display_reflist($data);
 $data["DATA"] = $data["VALUE"];  $data["FDEF"] = $data["SCHEMA"][$data["TABLE"]]["FIELDS"][$data["FIELD"]];  $data["REF_TABLE"] = $data["FDEF"]["REF_TABLE"];  $data["REF_FIELD"] = $data["FDEF"]["REF_FIELD"];  $data["REF_FIELDS"] = $data["FDEF"]["REF_FIELDS"];  if(@($data["DATA"])) { ?>
 <ul>
<?php $data_old_11 = $data; $tmp_11 = $data["DATA"]; if($tmp_11 && is_array($tmp_11)) foreach($tmp_11 as $data["IDX"] => $data["ROW"]) { ?>
   <li><?php $call = "tpl_" . "display_ref"; if(!function_exists($call)) die("\nUndefined template: '"."display_ref"."'\n"); $call($data); ?></li>
<?php } $data["DATA"] = $tmp_11; $data = $data_old_11; ?>
 </ul>
<?php } else { ?>
  (list is empty)
<?php } 
  if(function_exists("tpl_after_display_reflist")) tpl_after_display_reflist($data);
}

$TEMPLATES['display_reflist'] = true;

// ------------------------------------- template: display_ref ------------------

function tpl_display_ref($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_ref")) tpl_before_display_ref($data);
 if(@($data["REF_FIELDS"])) {  $data_old_12 = $data; $tmp_12 = $data["FDEF"]["EXTRA_FIELD"]; if($tmp_12 && is_array($tmp_12)) foreach($tmp_12 as $data["KEY"]) { if(@($data["first"]++)) { ?> | <?php } echo _tpl_text($data["KEY"]); ?>:<?php echo _tpl_esc_html($data["ROW"][$data["KEY"]]); } $data["FDEF"]["EXTRA_FIELD"] = $tmp_12; $data = $data_old_12; ?><a href="<?php echo _tpl_esc_html($data["ACTION_SELF"]); ?>?table=<?php echo _tpl_esc_param($data["REF_TABLE"]); ?>&primary=<?php echo _tpl_esc_param($data["REF_FIELD"]); $data_old_13 = $data; $tmp_13 = $data["REF_FIELDS"]; if($tmp_13 && is_array($tmp_13)) foreach($tmp_13 as $data["KEY"]) { ?>&<?php echo _tpl_esc_html($data["KEY"]); ?>=<?php echo _tpl_esc_param($data["ROW"][$data["KEY"]]); } $data["REF_FIELDS"] = $tmp_13; $data = $data_old_13; ?>"><?php $data_old_14 = $data; $tmp_14 = $data["REF_FIELDS"]; if($tmp_14 && is_array($tmp_14)) foreach($tmp_14 as $data["KEY"]) { if(@($data["first"]++)) { ?> | <?php } echo _tpl_text($data["KEY"]); ?>:<?php echo _tpl_esc_html($data["ROW"][$data["KEY"]]); } $data["REF_FIELDS"] = $tmp_14; $data = $data_old_14; ?></a><?php } else { ?><a href="<?php echo _tpl_esc_html($data["ACTION_SELF"]); ?>?table=<?php echo _tpl_esc_param($data["REF_TABLE"]); ?>&primary=<?php echo _tpl_esc_param($data["REF_FIELD"]); ?>&<?php echo _tpl_esc_html($data["REF_FIELD"]); ?>=<?php echo _tpl_esc_param($data["VALUE"]); ?>"><?php echo _tpl_esc_html($data["VALUE"]); ?></a><?php }  
  if(function_exists("tpl_after_display_ref")) tpl_after_display_ref($data);
}

$TEMPLATES['display_ref'] = true;

// ------------------------------------- template: display_download ------------------

function tpl_display_download($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_download")) tpl_before_display_download($data);
?>
<a href="<?php echo _tpl_esc_html($data["ACTION_SELF"]); ?>?table=<?php echo _tpl_esc_html($data["TABLE"]); ?>&primary=<?php echo _tpl_esc_html($data["PRIMARY"]); ?>&<?php echo _tpl_esc_html($data["PRIMARY"]); ?>=<?php echo _tpl_esc_html($data["DATA"][$data["IDX"]][$data["PRIMARY"]]); ?>&download=<?php echo _tpl_esc_html($data["FIELD"]); ?>&filename=<?php echo _tpl_esc_html($data["DATA"][$data["IDX"]][$data["UNIQUE"]]); ?>">download: <?php echo _tpl_esc_html($data["DATA"][$data["IDX"]][$data["UNIQUE"]]); ?></a>
<?php 
  if(function_exists("tpl_after_display_download")) tpl_after_display_download($data);
}

$TEMPLATES['display_download'] = true;

// ------------------------------------- template: display_download_preview ------------------

function tpl_display_download_preview($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_download_preview")) tpl_before_display_download_preview($data);
 if(@($data["VALUE"])) { 
 echo _tpl_format_preview($data["VALUE"]); ?><br>
<a href="<?php echo _tpl_esc_html($data["ACTION_SELF"]); ?>?table=<?php echo _tpl_esc_html($data["TABLE"]); ?>&primary=<?php echo _tpl_esc_html($data["PRIMARY"]); ?>&<?php echo _tpl_esc_html($data["PRIMARY"]); ?>=<?php echo _tpl_esc_html($data["DATA"][$data["IDX"]][$data["PRIMARY"]]); ?>&download=<?php echo _tpl_esc_html($data["FIELD"]); ?>">view: <?php echo _tpl_esc_html($data["DATA"][$data["IDX"]][$data["UNIQUE"]]); ?></a><br>
<?php $call = "tpl_" . "display_download"; if(!function_exists($call)) die("\nUndefined template: '"."display_download"."'\n"); $call($data); 
 } else { ?>
(nothing to download)
<?php } 
  if(function_exists("tpl_after_display_download_preview")) tpl_after_display_download_preview($data);
}

$TEMPLATES['display_download_preview'] = true;

// ------------------------------------- template: display_url ------------------

function tpl_display_url($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_url")) tpl_before_display_url($data);
 $data_old_15 = $data; $tmp_15 = preg_split("/\s+/", $data["VALUE"]); if($tmp_15 && is_array($tmp_15)) foreach($tmp_15 as $data["URL"]) { ?>
<a href="<?php echo _tpl_esc_html($data["URL"]); ?>"><?php echo _tpl_esc_html($data["URL"]); ?></a><br/>
<?php } $data = $data_old_15; 
  if(function_exists("tpl_after_display_url")) tpl_after_display_url($data);
}

$TEMPLATES['display_url'] = true;

// ------------------------------------- template: display_ascii ------------------

function tpl_display_ascii($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_ascii")) tpl_before_display_ascii($data);
?><tt><?php echo _tpl_format_ascii($data["VALUE"]); ?></tt><?php 
  if(function_exists("tpl_after_display_ascii")) tpl_after_display_ascii($data);
}

$TEMPLATES['display_ascii'] = true;

// ------------------------------------- template: display_text ------------------

function tpl_display_text($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_text")) tpl_before_display_text($data);
?><tt><?php echo _tpl_format_ascii($data["VALUE"]); ?></tt><?php 
  if(function_exists("tpl_after_display_text")) tpl_after_display_text($data);
}

$TEMPLATES['display_text'] = true;

// ------------------------------------- template: display_bool ------------------

function tpl_display_bool($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_bool")) tpl_before_display_bool($data);
?><tt><?php if(@($data["VALUE"])) { ?>true<?php } else { ?>false<?php } ?></tt><?php 
  if(function_exists("tpl_after_display_bool")) tpl_after_display_bool($data);
}

$TEMPLATES['display_bool'] = true;

// ------------------------------------- template: input ------------------

function tpl_input($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input")) tpl_before_input($data);
 if(@(db_access_field($data["TABLE"], $data["FIELD"], "W")|| $data["FIELD"] == "hidden")) {  if(@($data["IMAGE"] && !$data["TYPE"])) { $data["TYPE"] = "image"; } ?><input<?php if(@($data["TYPE"])) { ?> type="<?php echo _tpl_esc_html($data["TYPE"]); ?>"<?php } if(@($data["NAME"])) { ?> name="<?php echo _tpl_esc_html($data["PREFIX"]); echo _tpl_esc_html($data["NAME"]); echo _tpl_esc_html($data["SUFFIX"]); ?>"<?php } if(@($data["ID"])) { ?> id="<?php echo _tpl_esc_html($data["ID"]); ?>"<?php } if(@($data["IMAGE"])) { ?> src="<?php echo _tpl_esc_html($data["IMAGE"]); ?>" alt="<?php echo _tpl_esc_html($data["ALT"]); ?>"<?php } if(@($data["SIZE"])) { ?> size="<?php echo _tpl_esc_html($data["SIZE"]); ?>"<?php } if(@($data["MAXLEN"])) { ?> maxlength="<?php echo _tpl_esc_html($data["MAXLEN"]); ?>"<?php } if(@($data["CHECKED"])) { ?> checked="checked"<?php } if(@(isset($data["TEXT"]))) { ?> value="<?php echo _tpl_text($data["TEXT"]); ?>"<?php } elseif(@(isset($data["VALUE"]))) { ?> value="<?php echo _tpl_esc_html($data["VALUE"]); ?>"<?php } if(@($data["CONFIRM"])) { ?> onclick="return confirm('<?php echo _tpl_text($data["CONFIRM"]); ?>')"<?php } ?> /><?php } elseif(@(db_access_field($data["TABLE"], $data["FIELD"], "R"))) {  if(@(isset($data["TEXT"]))) { echo _tpl_text($data["TEXT"]); } elseif(@(isset($data["VALUE"]))) { echo _tpl_esc_html($data["VALUE"]); } else { ?>(null)<?php }  } else { ?>(no access to table '<?php echo _tpl_text($data["TABLE"]); ?>' column '<?php echo _tpl_text($data["FIELD"]); ?>')<?php }  
  if(function_exists("tpl_after_input")) tpl_after_input($data);
}

$TEMPLATES['input'] = true;

// ------------------------------------- template: button ------------------

function tpl_button($data) {
  global $TEXTS;
  if(function_exists("tpl_before_button")) tpl_before_button($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("IMAGE" => "images/".$data["NAME"].".png", "ALT" => "button_".$data["NAME"])));  
  if(function_exists("tpl_after_button")) tpl_after_button($data);
}

$TEMPLATES['button'] = true;

// ------------------------------------- template: button_add ------------------

function tpl_button_add($data) {
  global $TEXTS;
  if(function_exists("tpl_before_button_add")) tpl_before_button_add($data);
 $call = "tpl_" . "button"; if(!function_exists($call)) die("\nUndefined template: '"."button"."'\n"); $call(array_merge($data, array("NAME" => "add")));  
  if(function_exists("tpl_after_button_add")) tpl_after_button_add($data);
}

$TEMPLATES['button_add'] = true;

// ------------------------------------- template: button_edit ------------------

function tpl_button_edit($data) {
  global $TEXTS;
  if(function_exists("tpl_before_button_edit")) tpl_before_button_edit($data);
 $call = "tpl_" . "button"; if(!function_exists($call)) die("\nUndefined template: '"."button"."'\n"); $call(array_merge($data, array("NAME" => "edit")));  
  if(function_exists("tpl_after_button_edit")) tpl_after_button_edit($data);
}

$TEMPLATES['button_edit'] = true;

// ------------------------------------- template: button_clone ------------------

function tpl_button_clone($data) {
  global $TEXTS;
  if(function_exists("tpl_before_button_clone")) tpl_before_button_clone($data);
 $call = "tpl_" . "button"; if(!function_exists($call)) die("\nUndefined template: '"."button"."'\n"); $call(array_merge($data, array("NAME" => "clone")));  
  if(function_exists("tpl_after_button_clone")) tpl_after_button_clone($data);
}

$TEMPLATES['button_clone'] = true;

// ------------------------------------- template: button_delete ------------------

function tpl_button_delete($data) {
  global $TEXTS;
  if(function_exists("tpl_before_button_delete")) tpl_before_button_delete($data);
 $call = "tpl_" . "button"; if(!function_exists($call)) die("\nUndefined template: '"."button"."'\n"); $call(array_merge($data, array("NAME" => "delete", "CONFIRM" => "really_delete")));  
  if(function_exists("tpl_after_button_delete")) tpl_after_button_delete($data);
}

$TEMPLATES['button_delete'] = true;

// ------------------------------------- template: button_submit ------------------

function tpl_button_submit($data) {
  global $TEXTS;
  if(function_exists("tpl_before_button_submit")) tpl_before_button_submit($data);
 $call = "tpl_" . "button"; if(!function_exists($call)) die("\nUndefined template: '"."button"."'\n"); $call(array_merge($data, array("TYPE" => "submit", "TEXT" => $data["VALUE"])));  
  if(function_exists("tpl_after_button_submit")) tpl_after_button_submit($data);
}

$TEMPLATES['button_submit'] = true;

// ------------------------------------- template: extra_2buttons_head ------------------

function tpl_extra_2buttons_head($data) {
  global $TEXTS;
  if(function_exists("tpl_before_extra_2buttons_head")) tpl_before_extra_2buttons_head($data);
?>
 <th colspan="2"><?php echo _tpl_text("action"); ?></th>
<?php 
  if(function_exists("tpl_after_extra_2buttons_head")) tpl_after_extra_2buttons_head($data);
}

$TEMPLATES['extra_2buttons_head'] = true;

// ------------------------------------- template: extra_3buttons_head ------------------

function tpl_extra_3buttons_head($data) {
  global $TEXTS;
  if(function_exists("tpl_before_extra_3buttons_head")) tpl_before_extra_3buttons_head($data);
?>
 <th colspan="3"><?php echo _tpl_text("action"); ?></th>
<?php 
  if(function_exists("tpl_after_extra_3buttons_head")) tpl_after_extra_3buttons_head($data);
}

$TEMPLATES['extra_3buttons_head'] = true;

// ------------------------------------- template: input_hidden ------------------

function tpl_input_hidden($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_hidden")) tpl_before_input_hidden($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("TYPE" => "hidden", "FIELD" => "hidden", "NAME" => $data["FIELD"])));  
  if(function_exists("tpl_after_input_hidden")) tpl_after_input_hidden($data);
}

$TEMPLATES['input_hidden'] = true;

// ------------------------------------- template: input_hidden_display ------------------

function tpl_input_hidden_display($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_hidden_display")) tpl_before_input_hidden_display($data);
 $call = "tpl_" . "input_hidden"; if(!function_exists($call)) die("\nUndefined template: '"."input_hidden"."'\n"); $call($data); echo _tpl_esc_html($data["VALUE"]);  
  if(function_exists("tpl_after_input_hidden_display")) tpl_after_input_hidden_display($data);
}

$TEMPLATES['input_hidden_display'] = true;

// ------------------------------------- template: input_upload ------------------

function tpl_input_upload($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_upload")) tpl_before_input_upload($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("TYPE" => "file", "NAME" => $data["FIELD"], "MAXLEN" => null)));  
  if(function_exists("tpl_after_input_upload")) tpl_after_input_upload($data);
}

$TEMPLATES['input_upload'] = true;

// ------------------------------------- template: input_upload_filename ------------------

function tpl_input_upload_filename($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_upload_filename")) tpl_before_input_upload_filename($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("TYPE" => "file", "NAME" => $data["FIELD"], "MAXLEN" => null, "SUFFIX" => $data["SUFFIX"].":use_filename")));  
  if(function_exists("tpl_after_input_upload_filename")) tpl_after_input_upload_filename($data);
}

$TEMPLATES['input_upload_filename'] = true;

// ------------------------------------- template: input_string ------------------

function tpl_input_string($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_string")) tpl_before_input_string($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("TYPE" => "text", "NAME" => $data["FIELD"])));  
  if(function_exists("tpl_after_input_string")) tpl_after_input_string($data);
}

$TEMPLATES['input_string'] = true;

// ------------------------------------- template: input_text ------------------

function tpl_input_text($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_text")) tpl_before_input_text($data);
?><textarea name="<?php echo _tpl_esc_html($data["PREFIX"]); echo _tpl_esc_html($data["FIELD"]); echo _tpl_esc_html($data["SUFFIX"]); ?>"<?php if(@($data["SIZE"])) { ?> cols="<?php echo _tpl_esc_html($data["SIZE"]); ?>"<?php } if(@($data["LINES"])) { ?> rows="<?php echo _tpl_esc_html($data["LINES"]); ?>"<?php } ?>><?php echo _tpl_esc_html($data["VALUE"]); ?></textarea><?php 
  if(function_exists("tpl_after_input_text")) tpl_after_input_text($data);
}

$TEMPLATES['input_text'] = true;

// ------------------------------------- template: input_int ------------------

function tpl_input_int($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_int")) tpl_before_input_int($data);
 $call = "tpl_" . "input_string"; if(!function_exists($call)) die("\nUndefined template: '"."input_string"."'\n"); $call(array_merge($data, array("NAME" => $data["FIELD"])));  
  if(function_exists("tpl_after_input_int")) tpl_after_input_int($data);
}

$TEMPLATES['input_int'] = true;

// ------------------------------------- template: input_bool ------------------

function tpl_input_bool($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_bool")) tpl_before_input_bool($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("TYPE" => "checkbox", "NAME" => $data["FIELD"], "CHECKED" => $data["VALUE"], "VALUE" => 1)));  
  if(function_exists("tpl_after_input_bool")) tpl_after_input_bool($data);
}

$TEMPLATES['input_bool'] = true;

// ------------------------------------- template: input_password ------------------

function tpl_input_password($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_password")) tpl_before_input_password($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("TYPE" => "password", "NAME" => $data["FIELD"], "VALUE" => "")));  
  if(function_exists("tpl_after_input_password")) tpl_after_input_password($data);
}

$TEMPLATES['input_password'] = true;

// ------------------------------------- template: input_mode ------------------

function tpl_input_mode($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_mode")) tpl_before_input_mode($data);
 $call = "tpl_" . "input"; if(!function_exists($call)) die("\nUndefined template: '"."input"."'\n"); $call(array_merge($data, array("TYPE" => "radio", "NAME" => $data["FIELD"], "CHECKED" => ($data["VALUE"]==$data["code"]), "VALUE" => $data["code"]))); if(@($data["text"])) { echo _tpl_text($data["text"]); } else { echo _tpl_text($data["code"]); }  
  if(function_exists("tpl_after_input_mode")) tpl_after_input_mode($data);
}

$TEMPLATES['input_mode'] = true;

// ------------------------------------- template: input_modes ------------------

function tpl_input_modes($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_modes")) tpl_before_input_modes($data);
 $call = "tpl_" . "input_mode"; if(!function_exists($call)) die("\nUndefined template: '"."input_mode"."'\n"); $call(array_merge($data, array("code" => "n", "text" => "n=no_access"))); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php $call = "tpl_" . "input_mode"; if(!function_exists($call)) die("\nUndefined template: '"."input_mode"."'\n"); $call(array_merge($data, array("code" => "r", "text" => "r=read_database"))); ?>&nbsp;<?php $call = "tpl_" . "input_mode"; if(!function_exists($call)) die("\nUndefined template: '"."input_mode"."'\n"); $call(array_merge($data, array("code" => "R", "text" => "R=Read_and_display"))); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php $call = "tpl_" . "input_mode"; if(!function_exists($call)) die("\nUndefined template: '"."input_mode"."'\n"); $call(array_merge($data, array("code" => "w", "text" => "w=write_database"))); ?>&nbsp;<?php $call = "tpl_" . "input_mode"; if(!function_exists($call)) die("\nUndefined template: '"."input_mode"."'\n"); $call(array_merge($data, array("code" => "W", "text" => "W=Write_and_display"))); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<?php $call = "tpl_" . "input_mode"; if(!function_exists($call)) die("\nUndefined template: '"."input_mode"."'\n"); $call(array_merge($data, array("code" => "A", "text" => "A=Admin")));   
  if(function_exists("tpl_after_input_modes")) tpl_after_input_modes($data);
}

$TEMPLATES['input_modes'] = true;

// ------------------------------------- template: input_table ------------------

function tpl_input_table($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_table")) tpl_before_input_table($data);
?>
 <table>
<?php $call = "tpl_" . "_input_table_loop"; if(!function_exists($call)) die("\nUndefined template: '"."_input_table_loop"."'\n"); $call(array_merge($data, array("SUFFIX" => ":0"))); ?>
  <tr>
    <td class="noborder"></td>
    <td class="noborder"><?php $call = "tpl_" . "button_submit"; if(!function_exists($call)) die("\nUndefined template: '"."button_submit"."'\n"); $call(array_merge($data, array("NAME" => $data["MODE"], "VALUE" => $data["MODE"]))); ?></td>
  </tr>
 </table>
<?php 
  if(function_exists("tpl_after_input_table")) tpl_after_input_table($data);
}

$TEMPLATES['input_table'] = true;

// ------------------------------------- template: _input_table_loop ------------------

function tpl__input_table_loop($data) {
  global $TEXTS;
  if(function_exists("tpl_before__input_table_loop")) tpl_before__input_table_loop($data);
   $data_old_16 = $data; $tmp_16 = $data["SCHEMA"][$data["TABLE"]]["FIELDS"]; if($tmp_16 && is_array($tmp_16)) foreach($tmp_16 as $data["FIELD"] => $data["FDEF"]) { 
     $data["VALUE"] = @$data["DATA"]["0"][$data["FIELD"]]; 
     $data["CALL"] = "input_".$data["FDEF"]["TYPE"]; 
     if(@($data["FDEF"]["TYPE"] == "hidden")) { 
      $call = "tpl_" . $data["CALL"]; if(!function_exists($call)) die("\nUndefined template: '".$data["CALL"]."'\n"); $call($data); 
     } elseif(@(db_access_field($data["TABLE"], $data["FIELD"], "W"))) { 
      if(@($data["IMMUTABLE"][$data["FIELD"]])) { $data["CALL"] = $data["FDEF"]["IMMUTABLE"]; } elseif(@($data["FDEF"]["TPL_INPUT"])) { $data["CALL"] = $data["FDEF"]["TPL_INPUT"]; } ?>
     <tr>
      <td><?php echo _tpl_text($data["FIELD"]); ?>:</td>
      <td><?php $call = "tpl_" . $data["CALL"]; if(!function_exists($call)) die("\nUndefined template: '".$data["CALL"]."'\n"); $call(array_merge($data, array("SIZE" => $data["FDEF"]["SIZE"], "LINES" => @$data["FDEF"]["LINES"], "MAXLEN" => $data["FDEF"]["MAXLEN"]))); ?></td>
     </tr>
    <?php } 
   } $data["SCHEMA"][$data["TABLE"]]["FIELDS"] = $tmp_16; $data = $data_old_16; 
  if(function_exists("tpl_after__input_table_loop")) tpl_after__input_table_loop($data);
}

$TEMPLATES['_input_table_loop'] = true;

// ------------------------------------- template: input_selector ------------------

function tpl_input_selector($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_selector")) tpl_before_input_selector($data);
  $data["POOL"] = $data["FIELD"]."_pool"; 
  if(@($data["DATA"]["0"][$data["POOL"]])) { ?>
  <select name="<?php echo _tpl_esc_html($data["PREFIX"]); echo _tpl_esc_html($data["FIELD"]); echo _tpl_esc_html($data["SUFFIX"]); ?>" size="<?php if(@($data["SIZE"])) { echo _tpl_esc_html($data["SIZE"]); } else { ?>5<?php } ?>">
<?php $data_old_17 = $data; $tmp_17 = $data["DATA"]["0"][$data["POOL"]]; if($tmp_17 && is_array($tmp_17)) foreach($tmp_17 as $data["IDX"] => $data["REC"]) { ?>
    <option value="<?php echo _tpl_esc_html($data["REC"][$data["FIELD"]]); ?>"<?php if(@($data["DATA"]["0"][$data["FIELD"]] == $data["REC"][$data["FIELD"]])) { ?> selected="selected"<?php } ?>><?php echo _tpl_text($data["REC"][$data["FIELD"]]); ?></option>
<?php } $data["DATA"]["0"][$data["POOL"]] = $tmp_17; $data = $data_old_17; ?>
 </select>
<?php } else { ?>
  (no selection possible)
 <?php } 
  if(function_exists("tpl_after_input_selector")) tpl_after_input_selector($data);
}

$TEMPLATES['input_selector'] = true;

// ------------------------------------- template: input_sublist ------------------

function tpl_input_sublist($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_sublist")) tpl_before_input_sublist($data);
  $data["POOL"] = $data["FIELD"]."_pool"; 
  $data["POOLNAME"] = $data["PREFIX"].$data["FIELD"]."_pool".$data["SUFFIX"]; 
  $data["LISTNAME"] = $data["PREFIX"].$data["FIELD"].$data["SUFFIX"].":decode[]"; 
  $data["SHOWFIELD"] = $data["SCHEMA"][$data["TABLE"]]["FIELDS"][$data["FIELD"]]["SHOW_FIELD"]; ?>
 SHOWFIELD='<?php echo _tpl_esc_html($data["SHOWFIELD"]); ?>'<br>
<?php $data["HASH"] = _tpl_make_hash(@$data["DATA"]["0"][$data["FIELD"]],$data["SHOWFIELD"]); 
 if(@($data["DATA"]["0"][$data["POOL"]])) { ?>
<table>
 <tr>
  <td class="noborder">
   <select name="<?php echo _tpl_esc_html($data["POOLNAME"]); ?>" size="<?php if(@($data["SIZE"])) { echo _tpl_esc_html($data["SIZE"]); } else { ?>7<?php } ?>">
<?php $data_old_18 = $data; $tmp_18 = $data["DATA"]["0"][$data["POOL"]]; if($tmp_18 && is_array($tmp_18)) foreach($tmp_18 as $data["IDX"] => $data["REC"]) { 
      if(@(!$data["HASH"][$data["REC"][$data["SHOWFIELD"]]])) { ?>
      <option value="<?php echo _tpl_encode_row($data["REC"]); ?>"><?php echo _tpl_esc_html($data["REC"][$data["SHOWFIELD"]]); ?></option>
<?php } 
     } $data["DATA"]["0"][$data["POOL"]] = $tmp_18; $data = $data_old_18; ?>
   </select>
  </td>
  <td class="noborder">
   <img src="images/forward.png" alt="forward"
    onclick='
	a = document.getElementsByName("<?php echo _tpl_esc_html($data["POOLNAME"]); ?>")[0];
	b = document.getElementsByName("<?php echo _tpl_esc_html($data["LISTNAME"]); ?>")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	a.remove(idx);
	b.add(elem, null);
' />
  </td>
  <td class="noborder">
   <img src="images/go-up.png" alt="up"
    onclick='
	a = document.getElementsByName("<?php echo _tpl_esc_html($data["LISTNAME"]); ?>")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	other = a.options[idx-1];
	a.remove(idx);
	a.add(elem, other);
' /><br/>
   <img src="images/back.png" alt="backward"
    onclick='
	b = document.getElementsByName("<?php echo _tpl_esc_html($data["POOLNAME"]); ?>")[0];
	a = document.getElementsByName("<?php echo _tpl_esc_html($data["LISTNAME"]); ?>")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	a.remove(idx);
	b.add(elem, null);
' /><br/>
   <img src="images/down.png" alt="down"
    onclick='
	a = document.getElementsByName("<?php echo _tpl_esc_html($data["LISTNAME"]); ?>")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	other = a.options[idx+2];
	a.remove(idx);
	a.add(elem, other);
' />
  </td>
  <td class="noborder">
   <select name="<?php echo _tpl_esc_html($data["LISTNAME"]); ?>" size="<?php if(@($data["SIZE"])) { echo _tpl_esc_html($data["SIZE"]); } else { ?>7<?php } ?>" title="full">
<?php if(@($data["DATA"]["0"][$data["FIELD"]])) { 
      $data_old_19 = $data; $tmp_19 = $data["DATA"]["0"][$data["FIELD"]]; if($tmp_19 && is_array($tmp_19)) foreach($tmp_19 as $data["IDX"] => $data["REC"]) { ?>
      <option value="<?php echo _tpl_encode_row($data["REC"]); ?>"><?php echo _tpl_esc_html($data["REC"][$data["SHOWFIELD"]]); ?></option>
<?php } $data["DATA"]["0"][$data["FIELD"]] = $tmp_19; $data = $data_old_19; 
     } ?>
   </select>
  </td>
 </tr>
</table>
<?php } else { ?>
 (no selections are possible)
<?php } 
  if(function_exists("tpl_after_input_sublist")) tpl_after_input_sublist($data);
}

$TEMPLATES['input_sublist'] = true;

// ------------------------------------- template: input_subtable ------------------

function tpl_input_subtable($data) {
  global $TEXTS;
  if(function_exists("tpl_before_input_subtable")) tpl_before_input_subtable($data);
  $data["POOL"] = $data["FIELD"]."_pool"; 
  $data["FDEF"] = $data["SCHEMA"][$data["TABLE"]]["FIELDS"][$data["FIELD"]]; 
  $data["SHOWFIELD"] = $data["FDEF"]["SHOW_FIELD"]; ?>
 SHOWFIELD='<?php echo _tpl_esc_html($data["SHOWFIELD"]); ?>'<br>
<?php $data["HASH"] = _tpl_make_hash($data["DATA"][$data["IDX"]][$data["FIELD"]],$data["SHOWFIELD"]); 
  $data["TABLE"] = $data["FDEF"][$data["REF_TABLE"]]; 
  $data["PRIMARY"] = $data["FDEF"][$data["REF_FIELD"]]; 
  $data["PREFIX"] = $data["PREFIX"].$data["FIELD"].":".$data["IDX"]."."; 
  $data["DATA"] = $data["DATA"][$data["IDX"]][$data["POOL"]]; ?>
<table>
<?php $data_old_20 = $data; $tmp_20 = $data["DATA"]; if($tmp_20 && is_array($tmp_20)) foreach($tmp_20 as $data["IDX"] => $data["ROW"]) { 
   $data["SUFFIX"] = ":".$data["IDX"]; 
   $data["PRESENT"] = $data["HASH"][$data["ROW"][$data["SHOWFIELD"]]]; 
   $call = "tpl_" . "_input_subtable_row"; if(!function_exists($call)) die("\nUndefined template: '"."_input_subtable_row"."'\n"); $call($data); 
  } $data["DATA"] = $tmp_20; $data = $data_old_20; ?>
</table>
<?php 
  if(function_exists("tpl_after_input_subtable")) tpl_after_input_subtable($data);
}

$TEMPLATES['input_subtable'] = true;

// ------------------------------------- template: _input_subtable_row ------------------

function tpl__input_subtable_row($data) {
  global $TEXTS;
  if(function_exists("tpl_before__input_subtable_row")) tpl_before__input_subtable_row($data);
?>
  <tr id="">
   <td>
    <input type="checkbox" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>_present<?php echo _tpl_esc_html($data["SUFFIX"]); ?>" value="1"<?php if(@($data["PRESENT"])) { ?> checked="checked"<?php } ?>>
   </td>
<?php $data_old_21 = $data; $tmp_21 = $data["SCHEMA"][$data["TABLE"]]["FIELDS"]; if($tmp_21 && is_array($tmp_21)) foreach($tmp_21 as $data["FIELD"] => $data["FDEF"]) { 
     $data["VALUE"] = @$data["ROW"][$data["FIELD"]]; ?>
    <td>
<?php if(@(db_access_field($data["TABLE"], $data["FIELD"], "W"))) { 
       $call = "tpl_" . $data["FDEF"]["TPL_INPUT"]; if(!function_exists($call)) die("\nUndefined template: '".$data["FDEF"]["TPL_INPUT"]."'\n"); $call($data); 
      } elseif(@(db_access_field($data["TABLE"], $data["FIELD"], "R"))) { 
       $call = "tpl_" . $data["FDEF"]["TPL_DISPLAY"]; if(!function_exists($call)) die("\nUndefined template: '".$data["FDEF"]["TPL_DISPLAY"]."'\n"); $call($data); 
      } ?>
    </td>
<?php } $data["SCHEMA"][$data["TABLE"]]["FIELDS"] = $tmp_21; $data = $data_old_21; ?>
  </tr>
<?php 
  if(function_exists("tpl_after__input_subtable_row")) tpl_after__input_subtable_row($data);
}

$TEMPLATES['_input_subtable_row'] = true;

// ------------------------------------- template: tool_search ------------------

function tpl_tool_search($data) {
  global $TEXTS;
  if(function_exists("tpl_before_tool_search")) tpl_before_tool_search($data);
 echo _tpl_text("search_column:"); ?>
<select name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_search_field">
<?php $data_old_22 = $data; $tmp_22 = $data["SCHEMA"][$data["TABLE"]]["FIELDS"]; if($tmp_22 && is_array($tmp_22)) foreach($tmp_22 as $data["FIELD"] => $data["FDEF"]) { 
  if(@(!$data["FDEF"]["VIRTUAL"])) { 
  if(@(db_access_field($data["TABLE"], $data["FIELD"], "R"))) { ?>
  <option value="<?php echo _tpl_esc_html($data["FIELD"]); ?>"<?php if(@($data["tool_search_field"]==$data["FIELD"])) { ?> selected="selected"<?php } ?>><?php echo _tpl_text($data["FIELD"]); ?></option>
<?php } 
  } 
 } $data["SCHEMA"][$data["TABLE"]]["FIELDS"] = $tmp_22; $data = $data_old_22; ?>
</select>
<input type="text" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_search" <?php if(@($data["tool_search"])) { ?>value="<?php echo _tpl_esc_html($data["tool_search"]); ?>"<?php } ?>/>
<input type="submit" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_search_submit" value="<?php echo _tpl_text("search"); ?>"/>
<?php 
  if(function_exists("tpl_after_tool_search")) tpl_after_tool_search($data);
}

$TEMPLATES['tool_search'] = true;

// ------------------------------------- template: tool_page ------------------

function tpl_tool_page($data) {
  global $TEXTS;
  if(function_exists("tpl_before_tool_page")) tpl_before_tool_page($data);
?>
Page
<input type="text" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_page_start" size="2" <?php if(@($data["tool_page_start"])) { ?>value="<?php echo _tpl_esc_html($data["tool_page_start"]); ?>"<?php } ?>/>
Size
<input type="text" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_page_size" size="2" <?php if(@($data["tool_page_size"])) { ?>value="<?php echo _tpl_esc_html($data["tool_page_size"]); ?>"<?php } ?>/>
<input type="submit" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_page_submit" value="<?php echo _tpl_text("goto"); ?>"/>
<?php 
  if(function_exists("tpl_after_tool_page")) tpl_after_tool_page($data);
}

$TEMPLATES['tool_page'] = true;

// ------------------------------------- template: tool_history ------------------

function tpl_tool_history($data) {
  global $TEXTS;
  if(function_exists("tpl_before_tool_history")) tpl_before_tool_history($data);
?>
Show history: <input type="checkbox" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_history" <?php if(@($data["tool_history"])) { ?>checked="checked"<?php } ?>/>
<?php 
  if(function_exists("tpl_after_tool_history")) tpl_after_tool_history($data);
}

$TEMPLATES['tool_history'] = true;

// ------------------------------------- template: tool_level ------------------

function tpl_tool_level($data) {
  global $TEXTS;
  if(function_exists("tpl_before_tool_level")) tpl_before_tool_level($data);
?>
Min-Level:<input type="text" name="<?php echo _tpl_esc_html($data["PREFIX"]); ?>tool_level" size="2" <?php if(@($data["tool_level"])) { ?>value="<?php echo _tpl_esc_html($data["tool_level"]); ?>"<?php } ?>/>
<?php 
  if(function_exists("tpl_after_tool_level")) tpl_after_tool_level($data);
}

$TEMPLATES['tool_level'] = true;

// ------------------------------------- template: display_record_xxx ------------------

function tpl_display_record_xxx($data) {
  global $TEXTS;
  if(function_exists("tpl_before_display_record_xxx")) tpl_before_display_record_xxx($data);
?>
Aha!<br>
<?php 
  if(function_exists("tpl_after_display_record_xxx")) tpl_after_display_record_xxx($data);
}

$TEMPLATES['display_record_xxx'] = true;


// ----------------------------------------- LISTS ---------------------

$TEXTS['really_delete'] = 'really delete this?';
$TEXTS['prepare'] = 'prepare record';
$TEXTS['new'] = 'create new record';
$TEXTS['change'] = 'change existing record';
?>
