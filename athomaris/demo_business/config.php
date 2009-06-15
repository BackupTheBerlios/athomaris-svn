<?php

// General definitions (necessary for the framework itself)

$BASEDIR = dirname(__FILE__);
//$BASEDIR = "/www/athomaris/demo_business"; // more secure alternative

$CONFIG =
  array(
	"USE_AUTH" => true,
	"USE_BUSINESS_ENGINE" => true,
	"CONNECTIONS" =>
	array(
	      "main" =>
	      array(
		    "MASTER" => "localhost",
		    "BASE" => "demo_business",
		    ),
	      ),
	);

// App-specific definitions

// ...

?>
