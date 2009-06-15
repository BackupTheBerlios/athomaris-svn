<?php

// General definitions (necessary for the framework itself)

$BASEDIR = dirname(__FILE__);
//$BASEDIR = "/www/athomaris/demo_advanced"; // more secure alternative

$CONFIG =
  array(
	"USE_AUTH" => true,
	"CONNECTIONS" =>
	array(
	      "main" =>
	      array(
		    "MASTER" => "localhost",
		    "BASE" => "demo_advanced",
		    ),
	      ),
	);

// App-specific definitions

// ...

?>
