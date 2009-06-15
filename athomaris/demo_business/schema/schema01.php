<?php

$SCHEMA = 
    array(
	  "foos" =>
	  array("FIELDS" =>
		array(
		      "foo_name" =>
		      array("TYPE" => "varchar(16)",
			    "DEFAULT" => "''",
			    ),
		      "foo_state" =>
		      array("TYPE" => "int",
			    "DEFAULT" => "0",
			    ),
		      ),
		"UNIQUE" => array("foo_name"),
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
                    "foo_state" => 1,
                    ),
	      ),
        "bps" =>
        array(
              array(
                    "bp_name" => "process1",
                    "bp_statefield" => "foos.foo_state",
		    "bp_comment" => "demo business process",
		    ),
	      ),
        "rules" =>
        array(
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 1,
		    "rule_startvalue" => "=1",
		    "rule_firevalue" => "2",
		    "rule_action" => "script echo 'process @{bp_name} rule @{rule_prio} has fired on tuple @{foo_name}.'; sleep 3; echo 'done.'",
		    "rule_timeout" => 10,
		    "rule_comment" => "demo rule",
		    ),
	      ),
        "conts" =>
        array(
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 1,
		    "cont_prio" => 1,
		    "cont_match" => "/^STATUS 0$/",
		    "cont_endvalue" => "3",
		    "cont_comment" => "test for success",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 1,
		    "cont_prio" => 1,
		    "cont_match" => "/^STATUS ([0-9]+)/",
		    "cont_endvalue" => "-1",
		    "cont_comment" => "test for execution errors",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 1,
		    "cont_prio" => 1,
		    "cont_match" => "/^TIMEOUT ([0-9]+)/",
		    "cont_endvalue" => "-1",
		    "cont_comment" => "test for timeout",
		    ),
	      ),
	);
?>
