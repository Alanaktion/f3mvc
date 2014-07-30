<?php

namespace Controller;

class Index extends \Controller {

	public function index($fw, $params) {
		echo \App::render("index/index.html");
	}

}
