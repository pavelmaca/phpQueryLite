<?php
/**
 * phpQuery is a server-side, chainable, CSS3 selector driven
 * Document Object Model (DOM) API based on jQuery JavaScript Library.
 *
 * @version 0.9.5
 * @link http://code.google.com/p/phpquery/
 * @link http://phpquery-library.blogspot.com/
 * @link http://jquery.com/
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 * @package phpQuery
 */

use phpQuery\phpQuery;
use phpQuery\Plugins;


// class names for instanceof
// TODO move them as class constants into phpQuery

define('PHPQUERY_DIR', __DIR__);

require_once PHPQUERY_DIR . '/phpQuery.php';
require_once PHPQUERY_DIR . '/phpQuery/Object.php';
require_once PHPQUERY_DIR . '/phpQuery/Plugins.php';
require_once PHPQUERY_DIR . '/phpQuery/DOMDocumentWrapper.php';
require_once PHPQUERY_DIR . '/phpQuery/Callback.php';

/**
 * Shortcut to phpQuery::pq($arg1, $context)
 * Chainable.
 *
 * @see phpQuery::pq()
 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 */
function pq ($arg1, $context = null)
{
	$args = func_get_args();
	return callback('\\phpQuery\\phpQuery::pq')->invokeArgs($args);
}

// why ? no __call nor __get for statics in php...
// XXX __callStatic will be available in PHP 5.3
phpQuery::$plugins = new Plugins();

// include bootstrap file (personal library config)
if (file_exists(PHPQUERY_DIR . '/bootstrap.php')) {
	require_once PHPQUERY_DIR . '/bootstrap.php';
}

