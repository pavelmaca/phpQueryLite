<?php

namespace phpQuery\Plugin;

use phpQuery\Plugin\ScriptsObject;

require_once __DIR__ . '/ScriptsObject.php';



abstract class Scripts
{
	
	public static $scriptMethods = array();


	public static function __initialize()
	{
		if (file_exists(dirname(__FILE__)."/Scripts/__config.php")) {
			include dirname(__FILE__)."/Scripts/__config.php";
			ScriptsObject::$config = $config;
		}
	}


	/**
	 * Extend scripts' namespace with $name related with $callback.
	 *
	 * Callback parameter order looks like this:
	 * - $this
	 * - $params
	 * - &$return
	 * - $config
	 *
	 * @param $name
	 * @param $callback
	 * @return bool
	 */
	public static function script($name, $callback)
	{
		if (Scripts::$scriptMethods[$name]) {
			throw new \Exception("Script name conflict - '$name'");
		}

		Scripts::$scriptMethods[$name] = $callback;
	}
}

