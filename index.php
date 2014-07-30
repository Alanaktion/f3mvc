<?php

// Initialize core
$fw = require("lib/init.php");

// Load configuration
if(is_file("config-base.ini")) {
	$fw->config("config-base.ini");
}
if(is_file("config-base.ini")) {
	$fw->config("config.ini");
}

// Load routes
if(is_file("app/routes.ini")) {
	$fw->config("app/routes.ini");
}

// Set up error handling
$fw->set("ONERROR", function($fw) {
	switch($fw->get("ERROR.code")) {
		case 404:
			$fw->set("title", "Not Found");
			$fw->set("ESCAPE", false);
			echo Template::instance()->render("error/404.html");
			break;
		default:
			return false;
	}
});

// Minify static resources
// Cache for 1 week
$fw->route("GET @minify: /minify/@type/@files", function($fw, $args) {
	$fw->set("UI", $args["type"] . "/");
	echo Web::instance()->minify($args["files"]);
}, 3600 * 24 * 7);

$fw->run();
