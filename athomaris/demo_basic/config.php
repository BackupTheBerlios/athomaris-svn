<?php

// General definitions (necessary for the framework itself)

$BASEDIR = dirname(__FILE__);
//$BASEDIR = "/www/athomaris/demo_basic"; // more secure alternative

$CONFIG =
  array(
	"USE_AUTH" => false,
	"CONNECTIONS" =>
	array(
	      "main" =>
	      array(
		    "MASTER" => "localhost",
		    "BASE" => "demo_basic",
		    ),
	      ),
	);

// App-specific definitions

// ...

?>
