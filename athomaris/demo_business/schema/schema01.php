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
		      "foo_progress" =>
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
                    "foo_name" => "foo1",
                    "foo_state" => 1,
                    ),
              array(
                    "foo_name" => "foo2",
                    "foo_state" => 1,
                    ),
	      ),
        "bps" =>
        array(
              array(
                    "bp_name" => "process1",
                    "bp_statefield" => "foos.foo_state",
		    "bp_comment" => "demo business process #1",
		    ),
              array(
                    "bp_name" => "process2",
                    "bp_statefield" => "foos.foo_state",
		    "bp_comment" => "abstract business process #2",
		    ),
	      ),
        "rules" =>
        array(
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 10,
		    "rule_startvalue" => "=1",
		    "rule_firevalue" => "2",
		    "rule_action" => "script echo 'process @{bp_name} rule @{rule_prio} has fired on tuple @{foo_name}.'; sleep 3; echo 'done.'",
		    "rule_timeout" => 10,
		    "rule_comment" => "demo rule: first step",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 20,
		    "rule_startvalue" => "=10",
		    "rule_firevalue" => "11",
		    "rule_action" => "script echo 'process @{bp_name} rule @{rule_prio} has fired on tuple @{foo_name}.'; sleep 3; echo 'done.'",
		    "rule_timeout" => 10,
		    "rule_comment" => "demo rule: second step",
		    ),
	      ),
        "conts" =>
        array(
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 10,
		    "cont_prio" => 100,
		    "cont_match" => "/^STATUS 0$/",
		    "cont_action" => "update foo_progress='50'",
		    "cont_endvalue" => "10",
		    "cont_comment" => "test for success",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 10,
		    "cont_prio" => 200,
		    "cont_match" => "/^STATUS ([0-9]+)/",
		    "cont_endvalue" => "-1",
		    "cont_comment" => "test for execution errors",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 10,
		    "cont_prio" => 300,
		    "cont_match" => "/^TIMEOUT ([0-9]+)/",
		    "cont_endvalue" => "-2",
		    "cont_comment" => "test for timeout",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 20,
		    "cont_prio" => 100,
		    "cont_match" => "/^STATUS 0$/",
		    "cont_action" => "update foo_progress='100'",
		    "cont_endvalue" => "20",
		    "cont_comment" => "test for success",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 20,
		    "cont_prio" => 200,
		    "cont_match" => "/^STATUS ([0-9]+)/",
		    "cont_endvalue" => "-1",
		    "cont_comment" => "test for execution errors",
		    ),
              array(
                    "bp_name" => "process1",
		    "rule_prio" => 20,
		    "cont_prio" => 300,
		    "cont_match" => "/^TIMEOUT ([0-9]+)/",
		    "cont_endvalue" => "-2",
		    "cont_comment" => "test for timeout",
		    ),
	      ),
	);
?>
