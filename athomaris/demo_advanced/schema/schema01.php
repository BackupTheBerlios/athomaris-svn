<?php

$SCHEMA = 
    array(
	  "foos" =>
	  array("FIELDS" =>
		array(
		      "foo_name" =>
		      array("TYPE" => "varchar(16)",
			    "DEFAULT" => "''",
			    "REGEX" => "\A[A-Za-z0-9_]*\Z"
			    ),
		      "foo_data" =>
		      array("TYPE" => "varchar(32)",
			    "DEFAULT" => "'(not initialized)'",
			    ),
		      "foo_field" =>
		      array("TYPE" => "int",
			    "DEFAULT" => "0",
			    ),
		      ),
		"UNIQUE" => array("foo_name"),
		),
	  "bars" =>
	  array("FIELDS" =>
		array(
		      "bar_name" =>
		      array("TYPE" => "varchar(16)",
			    "DEFAULT" => "''",
			    ),
		      "bar_data" =>
		      array("TYPE" => "varchar(32)",
			    "DEFAULT" => "'(not initialized)'",
			    ),
		      ),
		"UNIQUE" => array("bar_name"),
		),
	  // n:m relation
	  "foo2bars" =>
	  array("FIELDS" =>
		array(
		      "foo_name" =>
		      array("TYPE" => "varchar(16)",
			    "DEFAULT" => "''",
			    "REFERENCES" => array("foos.foo_name" => array("on update cascade", "on delete cascade")),
			    ),
		      "bar_name" =>
		      array("TYPE" => "varchar(16)",
			    "DEFAULT" => "''",
			    "REFERENCES" => array("bars.bar_name" => array("on update cascade", "on delete cascade")),
			    ),
		      ),
		"UNIQUE" => array("foo_name,bar_name"),
		),
	  );

$EXTRA =
  array(
       );

$INITDATA =
  array(
	"foos" =>
	array(
	      array(
		    "foo_name" => "test1",
		    "foo_data" => "data1",
		    "foo_field" => 1,
		    ),
	      array(
		    "foo_name" => "test2",
		    "foo_data" => "data2",
		    "foo_field" => 2,
		    ),
	      array(
		    "foo_name" => "test3",
		    "foo_data" => "data3",
		    "foo_field" => 3,
		    ),
	      ),
	"bars" =>
	array(
	      array(
		    "bar_name" => "bar1",
		    "bar_data" => "data1",
		    ),
	      array(
		    "bar_name" => "bar2",
		    "bar_data" => "data2",
		    ),
	      ),
	"foo2bars" =>
	array(
	      array(
		    "foo_name" => "test1",
		    "bar_name" => "bar2",
		    ),
	      array(
		    "foo_name" => "test2",
		    "bar_name" => "bar1",
		    ),
	      array(
		    "foo_name" => "test2",
		    "bar_name" => "bar2",
		    ),
	      ),
	);
?>
