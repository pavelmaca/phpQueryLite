<?php

namespace phpQuery\Plugin;

use phpQuery\phpQuery;
use phpQuery\Plugin\Scripts;



/**
 * phpQuery plugin class extending phpQuery object.
 * Methods from this class are callable on every phpQuery object.
 *
 * Class name prefix 'phpQueryObjectPlugin_' must be preserved.
 */
abstract class ScriptsObject
{
	/**
	 * Limit binded methods.
	 *
	 * null means all public.
	 * array means only specified ones.
	 *
	 * @var array|null
	 */
	public static $phpQueryMethods = null;

	public static $config = array();


	/**
	 * Enter description here...
	 *
	 * @param phpQueryObject $self
	 */
	public static function script($self, $arg1)
	{
		$params = func_get_args();
		$params = array_slice($params, 2);
		$return = null;
		$config = self::$config;

		if (Scripts::$scriptMethods[$arg1]) {
			phpQuery::callbackRun(
				ScriptsObject::$scriptMethods[$arg1],
				array($self, $params, &$return, $config)
			);

		} elseif ($arg1 != '__config' && file_exists(dirname(__FILE__)."/Scripts/$arg1.php")) {
			phpQuery::debug("Loading script '$arg1'");
			require dirname(__FILE__)."/Scripts/$arg1.php";

		} else {
			phpQuery::debug("Requested script '$arg1' doesn't exist");
		}

		return $return ? $return : $self;
	}
}


