<?php

if ((float)PCRE_VERSION < 7.9)
	trigger_error('PCRE version is out of date');

// Get framework instance
$fw = require("base.php");

// Connect to configured database
if($fw->get("db.host")) {
	$fw->set("db.instance", new DB\SQL(
		"mysql:host=" . $fw->get("db.host") . ";port=3306;dbname=" . $fw->get("db.name"),
		$fw->get("db.user"),
		$fw->get("db.pass")
	));
}

class App {

	/**
	 * Get framework instance
	 * @return Base
	 */
	public static function fw() {
		return Base::instance();
	}

	/**
	 * Instantiate a model, can pass arguments to the constructor
	 * @param  string $name
	 * @param  array  $args
	 * @return object
	 */
	public static function model($name, array $args = array()) {
		$reflector = new ReflectionClass("\\Model\\" . str_replace("/", "\\", $name));
		return $reflector->newInstanceArgs($args);
	}

	/**
	 * Get an existing instance of a model if available, otherwise instantiate it
	 * @param  string $name
	 * @param  array  $args
	 * @return object
	 */
	public static function singleton($name, array $args = array()) {
		$fw = self::fw();
		$key = "SINGLETON." . str_replace(array("/", "\\"), "_");
		if($fw->exists($key)) {
			return $fw->get($key);
		} else {
			$instance = self::model($name, $args);
			$fw->set($key, $instance);
			return $instance;
		}
	}

	/**
	 * Instantiate a model, can pass arguments to the constructor
	 * @param  string $name
	 * @param  array  $args
	 * @return object
	 */
	public static function helper($name, array $args = array()) {
		$reflector = new ReflectionClass("\\Helper\\" . str_replace("/", "\\", $name));
		return $reflector->newInstanceArgs($args);
	}

	/**
	 * Shorthand to render a template
	 * @param string $view
	 */
	public static function render($view) {
		\Template::instance()->render($view);
	}

	/**
	 * Get a URL relative to the root
	 * @param  string $path
	 * @return string
	 */
	public static function url($path) {
		$fw = self::fw();
		return $fw->rel($path);
	}

}

abstract class Model extends \DB\SQL\Mapper {

	protected $fields = array();

	/**
	 * Create a new instance of Model class, optionally specifying a table to attach to
	 * @param string $table_name
	 */
	function __construct($table_name = null) {
		$fw = \Base::instance();

		if(empty($this->_table_name)) {
			if(empty($table_name)) {
				$fw->error(500, "Model instance does not have a table name specified.");
			} else {
				$this->table_name = $table_name;
			}
		}

		parent::__construct($fw->get("db.instance"), $this->_table_name, null, $fw->get("cache_expire.db"));
		return $this;
	}

	/**
	 * Set object created datetime if possible
	 * @return Model
	 */
	function save() {
		if(array_key_exists("created", $this->fields) && !$this->query) {
			$this->set("created", now());
		}
		return parent::save();
	}

	/**
	 * Safely delete object if possible, otherwise erase the record
	 * @return Model
	 */
	function delete() {
		if(array_key_exists("deleted", $this->fields)) {
			$this->set("deleted", now());
			return $this->save();
		} else {
			return $this->erase();
		}
	}

	/**
	 * Load by ID directly, ignoring records with a deleted datetime
	 * @param  string|array  $filter
	 * @param  array         $options
	 * @param  int           $ttl
	 * @return Model
	 */
	function load($filter=NULL, array $options=NULL, $ttl=0) {
		if(is_numeric($filter)) {
			if(array_key_exists("deleted", $this->fields)) {
				return parent::load(array("id = ? AND deleted IS NULL", $filter), $options, $ttl);
			} else {
				return parent::load(array("id = ?", $filter), $options, $ttl);
			}
		} else {
			return parent::load($filter, $options, $ttl);
		}
	}

	/**
	 * Get most recent value of field
	 * @param  string $key
	 * @return mixed
	 */
	protected function get_prev($key) {
		if(!$this->query) {
			return null;
		}
		$prev_fields = $this->query[count($this->query) - 1]->fields;
		return array_key_exists($key, $prev_fields) ? $prev_fields[$key]["value"] : null;
	}

}

abstract class Base {

	/**
	 * Require a user to be logged in. Redirects to /login if a session is not found.
	 * @return bool|int
	 */
	protected function _requireLogin() {
		$fw = \Base::instance();
		if($id = $fw->get("user_id")) {
			return $id;
		} else {
			if(empty($_GET)) {
				$fw->reroute("/login?to=" . urlencode($fw->get("PATH")));
			} else {
				$fw->reroute("/login?to=" . urlencode($fw->get("PATH")) . urlencode("?" . http_build_query($_GET)));
			}
			$fw->unload();
			return false;
		}
	}

	/**
	 * Require a user to be an administrator. Throws HTTP 403 if logged in, but not an admin.
	 * @return bool|int
	 */
	protected function _requireAdmin() {
		$id = $this->_requireLogin();

		$fw = \Base::instance();
		$user = $fw->get("user");
		if($user->role == "admin") {
			return $id;
		} else {
			$fw->error(403);
			$fw->unload();
			return false;
		}
	}

	/**
	 * Trigger error and optionally redirect for non-AJAX requests
	 * @param  string      $message
	 * @param  bool|string $redirect  A value of TRUE redirects to referrer
	 */
	protected function _error($message, $redirect = false) {
		$fw = \Base::instance();
		if($fw->get("AJAX")) {
			print_json(array("error" => $message));
			die();
		} else {
			if($redirect) {
				if($redirect === true) {
					$fw->reroute($fw->get("SERVER.HTTP_REFERER"));
				} else {
					$fw->reroute($redirect);
				}
			} else {
				$fw->set("error", $message);
			}
		}
	}

}

return $fw;
