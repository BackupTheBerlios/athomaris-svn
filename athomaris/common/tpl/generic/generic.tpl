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

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "dummy"}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "vspace"}
<br/>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "hspace"}
&nbsp;&nbsp;
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "header_download"}\
{HEADER "Content-Type: application/octet-stream"/}
{IF $FILENAME}
{HEADER "Content-Disposition: attachment; filename=".$FILENAME /}
{ELSE}
{HEADER "Content-Disposition: attachment;"/}
{/IF}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "styles"}\
  <link rel="stylesheet" type="text/css" href="styles/style.css" title="default_style" />
{/TEMPLATE}

{TEMPLATE "header"}\
{HEADER "Content-Type: text/html; charset=utf-8"/}
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de" lang="de">

<head>
  <meta http-equiv='Content-Encoding' content='UTF-8' />
  <title>{TEXT $TITLE/}</title>
  {TPL "styles"}
</head>

<body>

<form name="main" enctype="multipart/form-data" action="{$ACTION/}" method="post" onsubmit='
all = document.getElementsByTagName("select");
for(i = 0; i < all.length; i++) \{
  elem = all[i];
  if(elem.title == "full") \{
    //alert(elem.name);
    elem.multiple = true;
    opt = elem.options;
    for(j = 0; j < opt.length; j++) \{
      //alert(opt[j].text);
      opt[j].selected = true;
    \}
  \}
\}
'>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "footer"}
<br/>
</form>
</body>
</html>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "links"}
 <div class="links">
  {LOOP $DATA AS $category => $cat_def}
    {IF $cat_def}
      {TEXT $category/}
      {LOOP $cat_def AS $LINK => $VALUE}
        {IF !$SCHEMA->$LINK}
            <a href="{$VALUE}">{TEXT $LINK}</a>
        {ELSEIF $VALUE}
          {IF PERM $LINK "W"}
            <a href="{$VALUE}"><tt>{TEXT $LINK}</tt></a>
          {ELSEIF PERM $LINK "R"}
            <a href="{$VALUE}">({TEXT $LINK})</a>
          {/IF}
        {/IF}
      {/LOOP}
    <br/>
    {/IF}
  {/LOOP}
 </div>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_default"}\
 {VAR $FDEF = $SCHEMA->$TABLE->FIELDS->$FIELD/}\
 {IF $FDEF->REF_TABLE}\
  {VAR $REF_TABLE = $FDEF->REF_TABLE/}\
  {VAR $REF_FIELD = $FDEF->REF_FIELD/}\
  {VAR $REF_FIELDS = $FDEF->REF_FIELDS/}\
 {ELSE}\
  {VAR $REF_TABLE = $TABLE/}\
  {VAR $REF_FIELD = $FIELD/}\
 {/IF}\
 {IF $FDEF->TPL_DISPLAY}\
  {LOOP SPLIT "/\s*,\s*/" $FDEF->TPL_DISPLAY AS $CALL}\
   {TPL $CALL/}\
  {/LOOP}\
 {ELSEIF $FDEF->REF_TABLE}\
  {TPL "display_ref"/}\
 {ELSEIF isset($VALUE)}\
  {$VALUE/}\
 {ELSE} --- \
 {/IF}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_table"}
<table>
{IF $DATA->0}
  {IF $CAPTION}
   <caption>{TEXT $CAPTION}</caption>
  {/IF}
  <thead>
   <tr>
    {IF $EXTRAHEAD}{TPL $EXTRAHEAD}{/IF}
    {LOOP $SCHEMA->$TABLE->FIELDS AS $FIELD => $FDEF}
     {IF PERM $TABLE $FIELD "R"}
      <th><a href="{$ACTION}&order={$FIELD}">{TEXT $FIELD/}</a></th>
     {/IF}
    {/LOOP}
   </tr>
  </thead>
  <tbody>
   {LOOP $DATA AS $IDX => $ROW}
    {IF $ROW->deleted}{VAR $class = " class='deleted'"}\
    {ELSEIF $ROW->outdated}{VAR $class = " class='outdated'"}\
    {ELSE}{VAR $class = ""}\
    {/IF}
    <tr{RAW $class}>
     {IF $EXTRA}
       {LOOP $EXTRA AS $call => $id}
        {IF !$class || $call == "button_clone"}
	 <td{RAW $class}>{TPL $call "VALUE" => $ROW->$id/}</td>
        {ELSE}
         <td{RAW $class}></td>
        {/IF}
       {/LOOP}
     {/IF}
     {LOOP $SCHEMA->$TABLE->FIELDS AS $FIELD => $FDEF}
      {IF PERM $TABLE $FIELD "R"}
       {VAR $VALUE = @$ROW->$FIELD/}
       <td{RAW $class}>{TPL "display_default"}</td>
      {/IF}
     {/LOOP}
    </tr>
   {/LOOP}
  </tbody>
{ELSE}
 <tr><td>(table '{TEXT $TABLE}' is empty)</td></tr>
{/IF}
{IF $order}
  {TPL "input_hidden" "FIELD" => "order", "VALUE" => $order /}
{/IF}
</table>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

// recursively display a subtable.
{TEMPLATE "display_subtable"}\
{VAR $FDEF = $SCHEMA->$TABLE->FIELDS->$FIELD/}\
{VAR $TABLE = $FDEF->REF_TABLE/}\
{VAR $PRIMARY = $FDEF->REF_FIELD/}\
{VAR $DATA = db_selectfields($VALUE,explode(",",$FDEF->SHOW_FIELD)) /}\
{IF $DATA}
 <table>
  <tbody>
   {LOOP $DATA AS $IDX => $ROW}
    <tr>
     {LOOP $ROW AS $FIELD => $VALUE}
      {IF PERM $TABLE $FIELD "R"}
       <td>{TPL "display_default"}</td>
      {/IF}
     {/LOOP}
    </tr>
   {/LOOP}
  </tbody>
 </table>
{ELSE}
  (no data available)
{/IF}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_record"}
{VAR $ROW = $DATA->0}
{VAR $IDX = 0 /}
{IF $ROW}
 <table>
  {LOOP $SCHEMA->$TABLE->FIELDS AS $FIELD => $FDEF}
   {IF PERM $TABLE $FIELD "R"}
   {VAR $VALUE = $ROW->$FIELD/}
   <tr>
     <td>{TEXT $FIELD/}:</td>
     <td>{TPL "display_default"}</td>
   </tr>
   {/IF}
  {/LOOP}
 </table>
{ELSE}
  (no record exists)
{/IF}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

// display a list of references.
{TEMPLATE "display_reflist"}\
 {VAR $DATA = $VALUE /}\
 {VAR $FDEF = $SCHEMA->$TABLE->FIELDS->$FIELD/}\
 {VAR $REF_TABLE = $FDEF->REF_TABLE/}\
 {VAR $REF_FIELD = $FDEF->REF_FIELD/}\
 {VAR $REF_FIELDS = $FDEF->REF_FIELDS/}\
{IF $DATA}
 <ul>
  {LOOP $DATA AS $IDX => $ROW}
   <li>{TPL "display_ref"}</li>
  {/LOOP}
 </ul>
{ELSE}
  (list is empty)
{/IF}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_ref"}\
{IF $REF_FIELDS}\
 {LOOP $FDEF->EXTRA_FIELD AS $KEY}{IF $first++} | {/IF}{TEXT $KEY}:{$ROW->$KEY}{/LOOP}\
 <a href="{$ACTION_SELF}?table={PARAM $REF_TABLE}&primary={PARAM $REF_FIELD}{LOOP $REF_FIELDS AS $KEY}&{$KEY}={PARAM $ROW->$KEY}{/LOOP}">{LOOP $REF_FIELDS AS $KEY}{IF $first++} | {/IF}{TEXT $KEY}:{$ROW->$KEY}{/LOOP}</a>\
{ELSE}\
 <a href="{$ACTION_SELF}?table={PARAM $REF_TABLE}&primary={PARAM $REF_FIELD}&{$REF_FIELD}={PARAM $VALUE}">{$VALUE}</a>\
{/IF}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_download"}
<a href="{$ACTION_SELF}?table={$TABLE}&primary={$PRIMARY}&{$PRIMARY}={$DATA->$IDX->$PRIMARY}&download={$FIELD}&filename={$DATA->$IDX->$UNIQUE}">download: {$DATA->$IDX->$UNIQUE}</a>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_download_preview"}
{IF $VALUE}
{PREVIEW $VALUE}<br>
<a href="{$ACTION_SELF}?table={$TABLE}&primary={$PRIMARY}&{$PRIMARY}={$DATA->$IDX->$PRIMARY}&download={$FIELD}">view: {$DATA->$IDX->$UNIQUE}</a><br>
{TPL "display_download"/}
{ELSE}
(nothing to download)
{/IF}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_url"}
{LOOP SPLIT "/\s+/" $VALUE AS $URL}
<a href="{$URL}">{$URL}</a><br/>
{/LOOP}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_ascii"}\
<tt>{ASCII $VALUE}</tt>\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_text"}\
<tt>{ASCII $VALUE}</tt>\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_bool"}\
<tt>{IF $VALUE}true{ELSE}false{/IF}</tt>\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input"}\
 {IF PERM $TABLE $FIELD "W" || $FIELD == "hidden"}\
  {IF $IMAGE && !$TYPE}{VAR $TYPE = "image"/}{/IF}\
  <input{IF $TYPE} type="{$TYPE/}"{/IF}{IF $NAME} name="{$PREFIX}{$NAME}{$SUFFIX}"{/IF}{IF $ID} id="{$ID/}"{/IF}{IF $IMAGE} src="{$IMAGE/}" alt="{$ALT/}"{/IF}{IF $SIZE} size="{$SIZE/}"{/IF}{IF $MAXLEN} maxlength="{$MAXLEN/}"{/IF}{IF $CHECKED} checked="checked"{/IF}{IF isset($TEXT)} value="{TEXT $TEXT/}"{ELSEIF isset($VALUE)} value="{$VALUE/}"{/IF}{IF $CONFIRM} onclick="return confirm('{TEXT $CONFIRM}')"{/IF} />\
 {ELSEIF PERM $TABLE $FIELD "R"}\
  {IF isset($TEXT)}{TEXT $TEXT/}{ELSEIF isset($VALUE)}{$VALUE/}{ELSE}(null){/IF}\
 {ELSE}\
  (no access to table '{TEXT $TABLE}' column '{TEXT $FIELD}')\
 {/IF}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "button"}\
 {TPL "input" "IMAGE" => "images/".$NAME.".png", "ALT" => "button_".$NAME /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "button_add"}\
 {TPL "button" "NAME" => "add" /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "button_edit"}\
 {TPL "button" "NAME" => "edit" /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "button_clone"}\
 {TPL "button" "NAME" => "clone" /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "button_delete"}\
 {TPL "button" "NAME" => "delete" "CONFIRM" => "really_delete"/}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "button_submit"}\
 {TPL "button" "TYPE" => "submit" "TEXT" => $VALUE /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////

{TEMPLATE "extra_2buttons_head"}
 <th colspan="2">{TEXT "action"/}</th>
{/TEMPLATE}

{TEMPLATE "extra_3buttons_head"}
 <th colspan="3">{TEXT "action"/}</th>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_hidden"}\
  {TPL "input" "TYPE" => "hidden", "FIELD" => "hidden", "NAME" => $FIELD /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_hidden_display"}\
  {TPL "input_hidden"/}{$VALUE}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_upload"}\
  {TPL "input" "TYPE" => "file", "NAME" => $FIELD, "MAXLEN" => null /}\
{/TEMPLATE}
{TEMPLATE "input_upload_filename"}\
  {TPL "input" "TYPE" => "file", "NAME" => $FIELD, "MAXLEN" => null, "SUFFIX" => $SUFFIX.":use_filename" /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_string"}\
  {TPL "input" "TYPE" => "text", "NAME" => $FIELD /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_text"}\
  <textarea name="{$PREFIX}{$FIELD}{$SUFFIX}"{IF $SIZE} cols="{$SIZE}"{/IF}{IF $LINES} rows="{$LINES}"{/IF}>{$VALUE}</textarea>\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_int"}\
  {TPL "input_string" "NAME" => $FIELD /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_bool"}\
  {TPL "input" "TYPE" => "checkbox", "NAME" => $FIELD, "CHECKED" => $VALUE, "VALUE" => 1 /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_password"}\
  {TPL "input" "TYPE" => "password", "NAME" => $FIELD, "VALUE" => "" /}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_mode"}\
  {TPL "input" "TYPE" => "radio", "NAME" => $FIELD, "CHECKED" => ($VALUE==$code), "VALUE" => $code /}{IF $text}{TEXT $text}{ELSE}{TEXT $code}{/IF}\
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_modes"}\
  {TPL "input_mode" "code" => "n", "text" => "n=no_access"/}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\
  {TPL "input_mode" "code" => "r",  "text" => "r=read_database"/}&nbsp;\
  {TPL "input_mode" "code" => "R",  "text" => "R=Read_and_display"/}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\
  {TPL "input_mode" "code" => "w",  "text" => "w=write_database"/}&nbsp;\
  {TPL "input_mode" "code" => "W",  "text" => "W=Write_and_display"/}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\
  {TPL "input_mode" "code" => "A",  "text" => "A=Admin"/} \
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "input_table"}
 <table>
  {TPL "_input_table_loop" "SUFFIX" => ":0"/}
  <tr>
    <td class="noborder"></td>
    <td class="noborder">{TPL "button_submit" "NAME" => $MODE, "VALUE" => $MODE /}</td>
  </tr>
 </table>
{/TEMPLATE}
{TEMPLATE "_input_table_loop"}
  {LOOP $SCHEMA->$TABLE->FIELDS AS $FIELD => $FDEF}
    {VAR $VALUE = @$DATA->0->$FIELD/}
    {VAR $CALL = "input_".$FDEF->TYPE /}
    {IF $FDEF->TYPE == "hidden"}
     {TPL $CALL /}
    {ELSEIF PERM $TABLE $FIELD "W" /}
     {IF $IMMUTABLE->$FIELD}{VAR $CALL = $FDEF->IMMUTABLE /}{ELSEIF $FDEF->TPL_INPUT}{VAR $CALL = $FDEF->TPL_INPUT /}{/IF}
     <tr>
      <td>{TEXT $FIELD}:</td>
      <td>{TPL $CALL "SIZE" => $FDEF->SIZE, "LINES" => @$FDEF->LINES, "MAXLEN" => $FDEF->MAXLEN /}</td>
     </tr>
    {/IF}
  {/LOOP}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

// a simple selector. uses the pool-query.

{TEMPLATE "input_selector"}
 {VAR $POOL = $FIELD."_pool"/}
 {IF $DATA->0->$POOL}
  <select name="{$PREFIX}{$FIELD}{$SUFFIX}" size="{IF $SIZE}{$SIZE}{ELSE}5{/IF}">
  {LOOP $DATA->0->$POOL as $IDX => $REC}
    <option value="{$REC->$FIELD}"{IF $DATA->0->$FIELD == $REC->$FIELD} selected="selected"{/IF}>{TEXT $REC->$FIELD}</option>
  {/LOOP}
 </select>
 {ELSE}
  (no selection possible)
 {/IF}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

// produce two selectors, the first bearing the total set of items,
// the second the result list.
// produce buttons for moving around the items and for changing
// the order in the result list.

{TEMPLATE "input_sublist"}
 {VAR $POOL = $FIELD."_pool"/}
 {VAR $POOLNAME = $PREFIX.$FIELD."_pool".$SUFFIX /}
 {VAR $LISTNAME = $PREFIX.$FIELD.$SUFFIX.":decode[]" /}
 {VAR $SHOWFIELD = $SCHEMA->$TABLE->FIELDS->$FIELD->SHOW_FIELD /}
 SHOWFIELD='{$SHOWFIELD}'<br>
 {VAR $HASH = _tpl_make_hash(@$DATA->0->$FIELD,$SHOWFIELD) /}
{IF $DATA->0->$POOL}
<table>
 <tr>
  <td class="noborder">
   <select name="{$POOLNAME}" size="{IF $SIZE}{$SIZE}{ELSE}7{/IF}">
    {LOOP $DATA->0->$POOL as $IDX => $REC}
     {IF !$HASH[$REC->$SHOWFIELD]}
      <option value="{ROW $REC}">{$REC->$SHOWFIELD}</option>
     {/IF}
    {/LOOP}
   </select>
  </td>
  <td class="noborder">
   <img src="images/forward.png" alt="forward"
    onclick='
	a = document.getElementsByName("{$POOLNAME}")[0];
	b = document.getElementsByName("{$LISTNAME}")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	a.remove(idx);
	b.add(elem, null);
' />
  </td>
  <td class="noborder">
   <img src="images/go-up.png" alt="up"
    onclick='
	a = document.getElementsByName("{$LISTNAME}")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	other = a.options[idx-1];
	a.remove(idx);
	a.add(elem, other);
' /><br/>
   <img src="images/back.png" alt="backward"
    onclick='
	b = document.getElementsByName("{$POOLNAME}")[0];
	a = document.getElementsByName("{$LISTNAME}")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	a.remove(idx);
	b.add(elem, null);
' /><br/>
   <img src="images/down.png" alt="down"
    onclick='
	a = document.getElementsByName("{$LISTNAME}")[0];
	idx = a.selectedIndex;
	elem = a.options[idx];
	other = a.options[idx+2];
	a.remove(idx);
	a.add(elem, other);
' />
  </td>
  <td class="noborder">
   <select name="{$LISTNAME}" size="{IF $SIZE}{$SIZE}{ELSE}7{/IF}" title="full">
    {IF $DATA->0->$FIELD}
     {LOOP $DATA->0->$FIELD as $IDX => $REC}
      <option value="{ROW $REC}">{$REC->$SHOWFIELD}</option>
     {/LOOP}
    {/IF}
   </select>
  </td>
 </tr>
</table>
{ELSE}
 (no selections are possible)
{/IF}
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

// make an editable table, where each row can be selected/deselected

{TEMPLATE "input_subtable"}
 {VAR $POOL = $FIELD."_pool"/}
 {VAR $FDEF = $SCHEMA->$TABLE->FIELDS->$FIELD /}
 {VAR $SHOWFIELD = $FDEF->SHOW_FIELD /}
 SHOWFIELD='{$SHOWFIELD}'<br>
 {VAR $HASH = _tpl_make_hash($DATA->$IDX->$FIELD,$SHOWFIELD) /}

 {VAR $TABLE = $FDEF->$REF_TABLE /}
 {VAR $PRIMARY = $FDEF->$REF_FIELD /}
 {VAR $PREFIX = $PREFIX.$FIELD.":".$IDX."." /}
 {VAR $DATA = $DATA->$IDX->$POOL /}
<table>
 {LOOP $DATA as $IDX => $ROW}
  {VAR $SUFFIX = ":".$IDX /}
  {VAR $PRESENT = $HASH[$ROW->$SHOWFIELD] /}
  {TPL "_input_subtable_row" /}
 {/LOOP}
</table>
{/TEMPLATE}
{TEMPLATE "_input_subtable_row"}
  <tr id="">
   <td>
    <input type="checkbox" name="{$PREFIX}_present{$SUFFIX}" value="1"{IF $PRESENT} checked="checked"{/IF}>
   </td>
   {LOOP $SCHEMA->$TABLE->FIELDS AS $FIELD => $FDEF}
    {VAR $VALUE = @$ROW->$FIELD/}
    <td>
     {IF PERM $TABLE $FIELD "W" /}
      {TPL $FDEF->TPL_INPUT/}
     {ELSEIF PERM $TABLE $FIELD "R" /}
      {TPL $FDEF->TPL_DISPLAY/}
     {/IF}
    </td>
   {/LOOP}
  </tr>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////

// Generic Tools

{TEMPLATE "tool_search"}
{TEXT "search_column:"/}
<select name="{$PREFIX}tool_search_field">
{LOOP $SCHEMA->$TABLE->FIELDS AS $FIELD => $FDEF}
 {IF !$FDEF["VIRTUAL"]}
 {IF PERM $TABLE $FIELD "R"}
  <option value="{$FIELD}"{IF $tool_search_field==$FIELD} selected="selected"{/IF}>{TEXT $FIELD}</option>
 {/IF}
 {/IF}
{/LOOP}
</select>
<input type="text" name="{$PREFIX}tool_search" {IF $tool_search}value="{$tool_search}"{/IF}/>
<input type="submit" name="{$PREFIX}tool_search_submit" value="{TEXT "search"}"/>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "tool_page"}
{TEXT "Page:"}
<input type="text" name="{$PREFIX}tool_page_start" size="2" {IF $tool_page_start}value="{$tool_page_start}"{/IF}/>
{TEXT "Max:"}
<input type="text" name="{$PREFIX}tool_page_size" size="2" {IF $tool_page_size}value="{$tool_page_size}"{/IF}/>
<input type="submit" name="{$PREFIX}tool_page_submit" value="{TEXT "goto"}"/>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "tool_history"}
{TEXT "Show history:"} <input type="checkbox" name="{$PREFIX}tool_history" {IF $tool_history}checked="checked"{/IF}/>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "tool_level"}
{TEXT "Min-Level:"} <input type="text" name="{$PREFIX}tool_level" size="2" {IF $tool_level}value="{$tool_level}"{/IF}/>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////


/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////

{TEXTLIST}
really_delete = really delete this?
prepare = prepare record
new = create new record
clone = clone this record
change = change existing record

{/TEXTLIST}

/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////
/////////////////////////////////////////////////////////////////////////

// app specific templates

/////////////////////////////////////////////////////////////////////////

{TEMPLATE "display_record_xxx"}
Aha!<br>
{/TEMPLATE}

/////////////////////////////////////////////////////////////////////////
