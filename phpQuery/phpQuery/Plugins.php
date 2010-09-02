<?php

namespace phpQuery;

use phpQuery\phpQuery;



/**
 * Plugins static namespace class.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 * @todo move plugin methods here (as statics)
 */
class Plugins
{

	public function __call($method, $args)
	{
		if (isset(phpQuery::$extendStaticMethods[$method])) {
			$return = callback(phpQuery::$extendStaticMethods[$method])->invokeArgs($args);

		} elseif (isset(phpQuery::$pluginsStaticMethods[$method])) {
			$class = phpQuery::$pluginsStaticMethods[$method];

			callback("\\phpQuery\\Plugin\\$class", $method)->invokeArgs($args);

			return isset($return) ? $return : $this;

		} else {
			throw new \Exception("Method '{$method}' doesnt exist");
		}
	}
	
}