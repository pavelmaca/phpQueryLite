<?php

namespace phpQuery;

use phpQuery\Object;
use phpQuery\Plugins;
use phpQuery\DOMDocumentWrapper;



/**
 * Static namespace for phpQuery functions.
 *
 * @author Tobiasz Cudnik <tobiasz.cudnik/gmail.com>
 * @package phpQuery
 */
abstract class phpQuery
{
	

	/**
	 * XXX: Workaround for mbstring problems 
	 * 
	 * @var bool
	 */
	public static $mbstringSupport = true;

	public static $debug = false;

	public static $documents = array();

	public static $defaultDocumentID = null;


	/**
	 * Applies only to HTML.
	 *
	 * @var unknown_type
	 */
	public static $defaultDoctype = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN"
"http://www.w3.org/TR/html4/loose.dtd">';

	public static $defaultCharset = 'UTF-8';


	/**
	 * Static namespace for plugins.
	 *
	 * @var object
	 */
	public static $plugins = array();


	/**
	 * List of loaded plugins.
	 *
	 * @var unknown_type
	 */
	public static $pluginsLoaded = array();

	public static $pluginsMethods = array();

	public static $pluginsStaticMethods = array();

	public static $extendMethods = array();


	/**
	 * @TODO implement
	 */
	public static $extendStaticMethods = array();

	public static $lastModified = null;

	public static $active = 0;

	public static $dumpCount = 0;

	
	/**
	 * Multi-purpose function.
	 * Use pq() as shortcut.
	 *
	 * In below examples, $pq is any result of pq(); function.
	 *
	 * 1. Import markup into existing document (without any attaching):
	 * - Import into selected document:
	 *   pq('<div/>')				// DOESNT accept text nodes at beginning of input string !
	 * - Import into document with ID from $pq->getDocumentID():
	 *   pq('<div/>', $pq->getDocumentID())
	 * - Import into same document as DOMNode belongs to:
	 *   pq('<div/>', DOMNode)
	 * - Import into document from phpQuery object:
	 *   pq('<div/>', $pq)
	 *
	 * 2. Run query:
	 * - Run query on last selected document:
	 *   pq('div.myClass')
	 * - Run query on document with ID from $pq->getDocumentID():
	 *   pq('div.myClass', $pq->getDocumentID())
	 * - Run query on same document as DOMNode belongs to and use node(s)as root for query:
	 *   pq('div.myClass', DOMNode)
	 * - Run query on document from phpQuery object
	 *   and use object's stack as root node(s) for query:
	 *   pq('div.myClass', $pq)
	 *
	 * @param string|\DOMNode|\DOMNodeList|array	$arg1	HTML markup, CSS Selector, DOMNode or array of DOMNodes
	 * @param string|Object|\DOMNode	$context	DOM ID from $pq->getDocumentID(), phpQuery object (determines also query root) or DOMNode (determines also query root)
	 *
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery|QueryTemplatesPhpQuery|false
   * phpQuery object or false in case of error.
	 */
	public static function pq($arg1, $context = null)
	{
		if ($arg1 instanceof \DOMNode && ! isset($context)) {
			foreach (phpQuery::$documents AS $documentWrapper) {
				$compare = $arg1 instanceof \DOMDocument ? $arg1 : $arg1->ownerDocument;
				if ($documentWrapper->document->isSameNode($compare))
					$context = $documentWrapper->id;
			}
		}

		if (!$context) {
			$domId = self::$defaultDocumentID;
			if (!$domId){
				throw new \Exception("Can't use last created DOM, because there isn't any. Use phpQuery::newDocument() first.");
			}

//		} else if (is_object($context) && ($context instanceof PHPQUERY || is_subclass_of($context, 'phpQueryObject')))
		} elseif (is_object($context) && $context instanceof Object) {
			$domId = $context->getDocumentID();

		} elseif ($context instanceof \DOMDocument) {
			$domId = self::getDocumentID($context);
			if (!$domId) {
				//throw new Exception('Orphaned DOMDocument');
				$domId = self::newDocument($context)->getDocumentID();
			}

		} elseif ($context instanceof \DOMNode) {
			$domId = self::getDocumentID($context);
			if (! $domId) {
				throw new \Exception('Orphaned DOMNode');
//				$domId = self::newDocument($context->ownerDocument);
			}

		} else {
			$domId = $context;
		}

		if ($arg1 instanceof Object) {
//		if (is_object($arg1) && (get_class($arg1) == 'phpQueryObject' || $arg1 instanceof PHPQUERY || is_subclass_of($arg1, 'phpQueryObject'))) {
			/**
			 * Return $arg1 or import $arg1 stack if document differs:
			 * pq(pq('<div/>'))
			 */
			if ($arg1->getDocumentID() == $domId) {
				return $arg1;
			}

			$class = get_class($arg1);
			// support inheritance by passing old object to overloaded constructor
			$phpQuery = ($class != 'phpQuery' ? new $class($arg1, $domId) : new Object($domId));
			$phpQuery->elements = array();
			foreach($arg1->elements as $node) {
				$phpQuery->elements[] = $phpQuery->document->importNode($node, true);
			}

			return $phpQuery;

		} elseif ($arg1 instanceof \DOMNode || (is_array($arg1) && isset($arg1[0]) && $arg1[0] instanceof \DOMNode)) {
			/*
			 * Wrap DOM nodes with phpQuery object, import into document when needed:
			 * pq(array($domNode1, $domNode2))
			 */
			$phpQuery = new Object($domId);
			if (!($arg1 instanceof \DOMNodeList) && ! is_array($arg1)) {
				$arg1 = array($arg1);
			}

			$phpQuery->elements = array();
			foreach($arg1 as $node) {
				$sameDocument = ($node->ownerDocument instanceof \DOMDocument && ! $node->ownerDocument->isSameNode($phpQuery->document));
				$phpQuery->elements[] = $sameDocument ? $phpQuery->document->importNode($node, true) : $node;
			}

			return $phpQuery;

		} elseif (self::isMarkup($arg1)) {
			/**
			 * Import HTML:
			 * pq('<div/>')
			 */
			$phpQuery = new Object($domId);
			return $phpQuery->newInstance(
				$phpQuery->documentWrapper->import($arg1)
			);

		} else {
			/**
			 * Run CSS query:
			 * pq('div.myClass')
			 */
			$phpQuery = new Object($domId);
//			if ($context && ($context instanceof PHPQUERY || is_subclass_of($context, 'phpQueryObject')))
			if ($context && $context instanceof Object) {
				$phpQuery->elements = $context->elements;

			} elseif ($context && $context instanceof \DOMNodeList) {
				$phpQuery->elements = array();
				foreach ($context as $node) {
					$phpQuery->elements[] = $node;
				}

			} else if ($context && $context instanceof \DOMNode)
				$phpQuery->elements = array($context);

			return $phpQuery->find($arg1);
		}
	}

	
	/**
	 * Sets default document to $id. Document has to be loaded prior
	 * to using this method.
	 * $id can be retrived via getDocumentID() or getDocumentIDRef().
	 *
	 * @param unknown_type $id
	 */
	public static function selectDocument($id)
	{
		$id = self::getDocumentID($id);
		self::debug("Selecting document '$id' as default one");
		self::$defaultDocumentID = self::getDocumentID($id);
	}


	/**
	 * Returns document with id $id or last used as phpQueryObject.
	 * $id can be retrived via getDocumentID() or getDocumentIDRef().
	 * Chainable.
	 *
	 * @see phpQuery::selectDocument()
	 * @param unknown_type $id
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function getDocument($id = null)
	{
		if ($id) {
			phpQuery::selectDocument($id);

		} else {
			$id = phpQuery::$defaultDocumentID;
		}

		return new Object($id);
	}


	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocument($markup = null, $contentType = null)
	{
		if (! $markup) {
			$markup = '';
		}

		$documentID = phpQuery::createDocumentWrapper($markup, $contentType);
		return new Object($documentID);
	}
	

	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentHTML($markup = null, $charset = null)
	{
		$contentType = $charset ? ";charset=$charset" : '';
		return self::newDocument($markup, "text/html{$contentType}");
	}

	
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentXML($markup = null, $charset = null)
	{
		$contentType = $charset ? ";charset=$charset" : '';
		return self::newDocument($markup, "text/xml{$contentType}");
	}


	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentXHTML($markup = null, $charset = null)
	{
		$contentType = $charset ? ";charset=$charset" : '';
		return self::newDocument($markup, "application/xhtml+xml{$contentType}");
	}

	
	public static function phpToMarkup($php, $charset = 'utf-8')
	{
		$regexes = array(
			'@(<(?!\\?)(?:[^>]|\\?>)+\\w+\\s*=\\s*)(\')([^\']*)<'.'?php?(.*?)(?:\\?>)([^\']*)\'@s',
			'@(<(?!\\?)(?:[^>]|\\?>)+\\w+\\s*=\\s*)(")([^"]*)<'.'?php?(.*?)(?:\\?>)([^"]*)"@s',
		);

		foreach($regexes as $regex) {
			while (preg_match($regex, $php, $matches)) {
				$php = preg_replace_callback(
					$regex,
//					create_function('$m, $charset = "'.$charset.'"',
//						'return $m[1].$m[2]
//							.htmlspecialchars("<"."?php".$m[4]."?".">", ENT_QUOTES|ENT_NOQUOTES, $charset)
//							.$m[5].$m[2];'
//					),
					array(__CLASS__, '_phpToMarkupCallback'),
					$php
				);
			}
		}

		$regex = '@(^|>[^<]*)+?(<\?php(.*?)(\?>))@s';
		//preg_match_all($regex, $php, $matches);
		//var_dump($matches);
		$php = preg_replace($regex, '\\1<php><!-- \\3 --></php>', $php);
		return $php;
	}

	
	public static function _phpToMarkupCallback($php, $charset = 'utf-8')
	{
		return $m[1].$m[2]
			.htmlspecialchars("<"."?php".$m[4]."?".">", ENT_QUOTES|ENT_NOQUOTES, $charset)
			.$m[5].$m[2];
	}

	
	/**
	 * Creates new document from file $file.
	 * Chainable.
	 *
	 * @param string $file URLs allowed. See File wrapper page at php.net for more supported sources.
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFile($file, $contentType = null)
	{
		$documentID = self::createDocumentWrapper(
			file_get_contents($file), $contentType
		);

		return new Object($documentID);
	}

	
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFileHTML($file, $charset = null)
	{
		$contentType = $charset ? ";charset=$charset" : '';
		return self::newDocumentFile($file, "text/html{$contentType}");
	}


	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFileXML($file, $charset = null)
	{
		$contentType = $charset ? ";charset=$charset" : '';
		return self::newDocumentFile($file, "text/xml{$contentType}");
	}

	
	/**
	 * Creates new document from markup.
	 * Chainable.
	 *
	 * @param unknown_type $markup
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 */
	public static function newDocumentFileXHTML($file, $charset = null)
	{
		$contentType = $charset ? ";charset=$charset" : '';
		return self::newDocumentFile($file, "application/xhtml+xml{$contentType}");
	}

	
	/**
	 * Reuses existing DOMDocument object.
	 * Chainable.
	 *
	 * @param $document \DOMDocument
	 * @return phpQueryObject|QueryTemplatesSource|QueryTemplatesParse|QueryTemplatesSourceQuery
	 * @TODO support DOMDocument
	 */
	public static function loadDocument($document)
	{
		// TODO
		die('TODO loadDocument');
	}

	
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $html
	 * @param unknown_type $domId
	 * @return unknown New DOM ID
	 * @todo support PHP tags in input
	 * @todo support passing DOMDocument object from self::loadDocument
	 */
	protected static function createDocumentWrapper($html, $contentType = null, $documentID = null)
	{
		if (function_exists('domxml_open_mem')) {
			throw new \Exception("Old PHP4 DOM XML extension detected. phpQuery won't work until this extension is enabled.");
		}

//		$id = $documentID
//			? $documentID
//			: md5(microtime());
		$document = null;
		if ($html instanceof \DOMDocument) {
			if (self::getDocumentID($html)) {
				// document already exists in phpQuery::$documents, make a copy
				$document = clone $html;

			} else {
				// new document, add it to phpQuery::$documents
				$wrapper = new DOMDocumentWrapper($html, $contentType, $documentID);
			}

		} else {
			$wrapper = new DOMDocumentWrapper($html, $contentType, $documentID);
		}

//		$wrapper->id = $id;
		// bind document
		phpQuery::$documents[$wrapper->id] = $wrapper;
		// remember last loaded document
		phpQuery::selectDocument($wrapper->id);

		return $wrapper->id;
	}

	
	/**
	 * Extend class namespace.
	 *
	 * @param string|array $target
	 * @param array $source
	 * @TODO support string $source
	 * @return unknown_type
	 */
	public static function extend($target, $source)
	{
		switch($target) {
			case 'phpQueryObject':
				$targetRef = &self::$extendMethods;
				$targetRef2 = &self::$pluginsMethods;
				break;

			case 'phpQuery':
				$targetRef = &self::$extendStaticMethods;
				$targetRef2 = &self::$pluginsStaticMethods;
				break;
			
			default:
				throw new \Exception("Unsupported \$target type");
		}

		if (is_string($source)) {
			$source = array($source => $source);
		}

		foreach($source as $method => $callback) {
			if (isset($targetRef[$method])) {
//				throw new Exception
				self::debug("Duplicate method '{$method}', can\'t extend '{$target}'");
				continue;
			}

			if (isset($targetRef2[$method])) {
//				throw new Exception
				self::debug("Duplicate method '{$method}' from plugin '{$targetRef2[$method]}', can\'t extend '{$target}'");
				continue;
			}

			$targetRef[$method] = $callback;
		}

		return true;
	}

	
	/**
	 * Extend phpQuery with $class from $file.
	 *
	 * @param string $class Extending class name. Real class name can be prepended phpQuery_.
	 * @param array $file Filename to include. Defaults to "{$class}.php".
	 */
	public static function plugin($class, $file = null)
	{
		// TODO $class checked agains phpQuery_$class
//		if (strpos($class, 'phpQuery') === 0)
//			$class = substr($class, 8);
		if (in_array($class, self::$pluginsLoaded)) {
			return true;
		}

		if (!$file) {
			$file = PHPQUERY_DIR . '/phpQuery/plugins/' . $class . '.php';
		}

		$objectClass = '\\phpQuery\\Plugin\\'.$class.'Object';
		$staticClass = '\\phpQuery\\Plugin\\'.$class;

		$objectClassExists = class_exists($objectClass);
		$staticClassExists = class_exists($staticClass);

		if (! $objectClassExists && ! $staticClassExists) {
			require_once($file);
		}

		self::$pluginsLoaded[] = $class;

		// static methods
		if (class_exists($staticClass)) {
			$vars = get_class_vars($staticClass);
			$loop = isset($vars['phpQueryMethods']) && ! is_null($vars['phpQueryMethods'])
				? $vars['phpQueryMethods']
				: get_class_methods($staticClass);

			foreach($loop as $method) {
				if ($method == '__initialize') {
					continue;
				}

				if (! is_callable(array($staticClass, $method))) {
					continue;
				}

				try {
					callback($staticClass, $method);

				} catch( \InvalidArgumentException $e ){
					continue;
				}

				if (isset(self::$pluginsStaticMethods[$method])) {
					throw new \Exception("Duplicate method '{$method}' from plugin '{$c}' conflicts with same method from plugin '".self::$pluginsStaticMethods[$method]."'");
					return;
				}

				self::$pluginsStaticMethods[$method] = $class;
			}

			if (method_exists($staticClass, '__initialize')) {
				callback($staticClass, '__initialize')->invoke();
			}
		}

		// object methods
		if (class_exists($objectClass)) {
			$vars = get_class_vars($objectClass);
			$loop = isset($vars['phpQueryMethods']) && ! is_null($vars['phpQueryMethods'])
				? $vars['phpQueryMethods']
				: get_class_methods($objectClass);

			foreach($loop as $method) {
				if (! is_callable(array($objectClass, $method))) {
					continue;
				}

				if (isset(self::$pluginsMethods[$method])) {
					throw new \Exception("Duplicate method '{$method}' from plugin '{$c}' conflicts with same method from plugin '".self::$pluginsMethods[$method]."'");
					continue;
				}

				self::$pluginsMethods[$method] = $class;
			}
		}

		return true;
	}

	
	/**
	 * Unloades all or specified document from memory.
	 *
	 * @param mixed $documentID @see phpQuery::getDocumentID() for supported types.
	 */
	public static function unloadDocuments($id = null)
	{
		if (isset($id)) {
			if ($id = self::getDocumentID($id)) {
				unset(phpQuery::$documents[$id]);
			}

		} else {
			foreach(phpQuery::$documents as $k => $v) {
				unset(phpQuery::$documents[$k]);
			}
		}
	}

	
	public static function DOMNodeListToArray($DOMNodeList)
	{
		$array = array();
		if (! $DOMNodeList) {
			return $array;
		}
		foreach($DOMNodeList as $node) {
			$array[] = $node;
		}

		return $array;
	}

	
	/**
	 * Checks if $input is HTML string, which has to start with '<'.
	 *
	 * @deprecated
	 * @param String $input
	 * @return Bool
	 * @todo still used ?
	 */
	public static function isMarkup($input)
	{
		return ! is_array($input) && substr(trim($input), 0, 1) == '<';
	}

	
	public static function debug($text)
	{
		if (self::$debug) {
			print dump($text);
		}
	}

	
	/**
	 * Enter description here...
	 *
	 * @param array|phpQuery $data
	 *
	 */
	public static function param($data)
	{
		return http_build_query($data, null, '&');
	}


	/**
	 * Returns JSON representation of $data.
	 *
	 * @static
	 * @param mixed $data
	 * @return string
	 */
	public static function toJSON($data)
	{
		if (function_exists('json_encode')) {
			return json_encode($data);
		}

		die('TODO: implement nette encoder');
		//require_once('Zend/Json/Encoder.php');
		//return \Zend_Json_Encoder::encode($data);
	}

	
	/**
	 * Parses JSON into proper PHP type.
	 *
	 * @static
	 * @param string $json
	 * @return mixed
	 */
	public static function parseJSON($json)
	{
		if (function_exists('json_decode')) {
			$return = json_decode(trim($json), true);
			// json_decode and UTF8 issues
			if (isset($return))
				return $return;
		}

		die('TODO: implement nette decoder');
//		require_once('Zend/Json/Decoder.php');
//		return \Zend_Json_Decoder::decode($json);
	}

	
	/**
	 * Returns source's document ID.
	 *
	 * @param $source DOMNode|phpQueryObject
	 * @return string
	 */
	public static function getDocumentID($source)
	{
		if ($source instanceof \DOMDocument) {
			foreach(phpQuery::$documents as $id => $document) {
				if ($source->isSameNode($document->document))
					return $id;
			}

		} elseif ($source instanceof \DOMNode) {
			foreach(phpQuery::$documents as $id => $document) {
				if ($source->ownerDocument->isSameNode($document->document))
					return $id;
			}

		} elseif ($source instanceof Object) {
			return $source->getDocumentID();

		} elseif (is_string($source) && isset(phpQuery::$documents[$source])) {
			return $source;
		}
	}

	
	/**
	 * Get DOMDocument object related to $source.
	 * Returns null if such document doesn't exist.
	 *
	 * @param $source DOMNode|phpQueryObject|string
	 * @return string
	 */
	public static function getDOMDocument($source)
	{
		if ($source instanceof \DOMDocument) {
			return $source;
		}

		$source = self::getDocumentID($source);
		return $source ? self::$documents[$id]['document'] : null;
	}

	// UTILITIES
	// http://docs.jquery.com/Utilities

	/**
	 *
	 * @return unknown_type
	 * @link http://docs.jquery.com/Utilities/jQuery.makeArray
	 */
	public static function makeArray($obj)
	{
		$array = array();
		if (is_object($object) && $object instanceof \DOMNodeList) {
			foreach($object as $value) {
				$array[] = $value;
			}

		} elseif (is_object($object) && ! ($object instanceof \Iterator)) {
			foreach(get_object_vars($object) as $name => $value) {
				$array[0][$name] = $value;
			}

		} else {
			foreach($object as $name => $value) {
				$array[0][$name] = $value;
			}
		}

		return $array;
	}

	
	public static function inArray($value, $array)
	{
		return in_array($value, $array);
	}


	/**
	 *
	 * @param $object
	 * @param $callback
	 * @return unknown_type
	 * @link http://docs.jquery.com/Utilities/jQuery.each
	 */
	public static function each($object, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		$paramStructure = null;
		if (func_num_args() > 2) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 2);
		}

		if (is_object($object) && ! ($object instanceof Iterator)) {
			foreach(get_object_vars($object) as $name => $value) {
				phpQuery::callbackRun($callback, array($name, $value), $paramStructure);
			}

		} else {
			foreach($object as $name => $value) {
				phpQuery::callbackRun($callback, array($name, $value), $paramStructure);
			}
		}
	}

	
	/**
	 *
	 * @link http://docs.jquery.com/Utilities/jQuery.map
	 */
	public static function map($array, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		$result = array();
		$paramStructure = null;
		if (func_num_args() > 2) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 2);
		}

		foreach($array as $v) {
			$vv = phpQuery::callbackRun($callback, array($v), $paramStructure);
//			$callbackArgs = $args;
//			foreach($args as $i => $arg) {
//				$callbackArgs[$i] = $arg instanceof CallbackParam
//					? $v
//					: $arg;
//			}
//			$vv = call_user_func_array($callback, $callbackArgs);
			if (is_array($vv))  {
				foreach($vv as $vvv)
					$result[] = $vvv;

			} elseif ($vv !== null) {
				$result[] = $vv;
			}
		}

		return $result;
	}

	
	/**
	 *
	 * @param $callback Callback
	 * @param $params
	 * @param $paramStructure
	 * @return unknown_type
	 */
	public static function callbackRun($callback, $params = array(), $paramStructure = null)
	{
		if (! $callback) {
			return;
		}

		if ($callback instanceof \CallbackParameterToReference) {
			// TODO support ParamStructure to select which $param push to reference
			if (isset($params[0])) {
				$callback->callback = $params[0];
			}

			return true;
		}

		if ($callback instanceof Callback) {
			$paramStructure = $callback->params;
			$callback = $callback->callback;
		}

		if (! $paramStructure) {
			return call_user_func_array($callback, $params);
		}

		$p = 0;
		foreach($paramStructure as $i => $v) {
			$paramStructure[$i] = $v instanceof \CallbackParam ? $params[$p++] : $v;
		}

		return call_user_func_array($callback, $paramStructure);
	}

	
	/**
	 * Merge 2 phpQuery objects.
	 * @param array $one
	 * @param array $two
	 * @protected
	 * @todo node lists, phpQueryObject
	 */
	public static function merge($one, $two)
	{
		$elements = $one->elements;
		foreach($two->elements as $node) {
			$exists = false;
			foreach($elements as $node2) {
				if ($node2->isSameNode($node)) {
					$exists = true;
				}
			}
			if (! $exists) {
				$elements[] = $node;
			}
		}

		return $elements;
//		$one = $one->newInstance();
//		$one->elements = $elements;
//		return $one;
	}

	
	/**
	 *
	 * @param $array
	 * @param $callback
	 * @param $invert
	 * @return unknown_type
	 * @link http://docs.jquery.com/Utilities/jQuery.grep
	 */
	public static function grep($array, $callback, $invert = false)
	{
		$result = array();
		foreach($array as $k => $v) {
			$r = call_user_func_array($callback, array($v, $k));
			if ($r === !(bool)$invert) {
				$result[] = $v;
			}
		}

		return $result;
	}


	public static function unique($array)
	{
		return array_unique($array);
	}


	/**
	 *
	 * @param $function
	 * @return unknown_type
	 * @TODO there are problems with non-static methods, second parameter pass it
	 * 	but doesnt verify is method is really callable
	 */
	public static function isFunction($function)
	{
		return is_callable($function);
	}

	
	public static function trim($str)
	{
		return trim($str);
	}

	
	/* PLUGINS NAMESPACE */
	/**
	 *
	 * @param $url
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return phpQueryObject
	 */
	public static function browserGet($url, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		if (self::plugin('WebBrowser')) {
			$params = func_get_args();
			return self::callbackRun(array(self::$plugins, 'browserGet'), $params);

		} else {
			self::debug('WebBrowser plugin not available...');
		}
	}


	/**
	 *
	 * @param $url
	 * @param $data
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return phpQueryObject
	 */
	public static function browserPost($url, $data, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		if (self::plugin('WebBrowser')) {
			$params = func_get_args();
			return self::callbackRun(array(self::$plugins, 'browserPost'), $params);

		} else {
			self::debug('WebBrowser plugin not available...');
		}
	}


	/**
	 *
	 * @param $ajaxSettings
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return phpQueryObject
	 */
	public static function browser($ajaxSettings, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		if (self::plugin('WebBrowser')) {
			$params = func_get_args();
			return self::callbackRun(array(self::$plugins, 'browser'), $params);

		} else {
			self::debug('WebBrowser plugin not available...');
		}
	}

	
	/**
	 *
	 * @param $type
	 * @param $code
	 * @return string
	 */
	public static function code($type, $code)
	{
		return "<$type><!-- ".trim($code)." --></$type>";
	}

	public static function __callStatic($method, $params)
	{
		return call_user_func_array(
			array(phpQuery::$plugins, $method),
			$params
		);
	}

	
	protected static function dataSetupNode($node, $documentID)
	{
		// search are return if alredy exists
		foreach(phpQuery::$documents[$documentID]->dataNodes as $dataNode) {
			if ($node->isSameNode($dataNode)) {
				return $dataNode;
			}
		}
		// if doesn't, add it
		phpQuery::$documents[$documentID]->dataNodes[] = $node;
		return $node;
	}

	
	protected static function dataRemoveNode($node, $documentID)
	{
		// search are return if alredy exists
		foreach(phpQuery::$documents[$documentID]->dataNodes as $k => $dataNode) {
			if ($node->isSameNode($dataNode)) {
				unset(self::$documents[$documentID]->dataNodes[$k]);
				unset(self::$documents[$documentID]->data[ $dataNode->dataID ]);
			}
		}
	}

	
	public static function data($node, $name, $data, $documentID = null)
	{
		if (! $documentID) {
			// TODO check if this works
			$documentID = self::getDocumentID($node);
		}
		
		$document = phpQuery::$documents[$documentID];
		$node = self::dataSetupNode($node, $documentID);
		if (! isset($node->dataID)) {
			$node->dataID = ++phpQuery::$documents[$documentID]->uuid;
		}
		
		$id = $node->dataID;
		if (! isset($document->data[$id])) {
			$document->data[$id] = array();
		}

		if (! is_null($data)) {
			$document->data[$id][$name] = $data;
		}

		if ($name) {
			if (isset($document->data[$id][$name])) {
				return $document->data[$id][$name];
			}

		} else {
			return $id;
		}
	}

	public static function removeData($node, $name, $documentID)
	{
		if (! $documentID) {
			// TODO check if this works
			$documentID = self::getDocumentID($node);
		}

		$document = phpQuery::$documents[$documentID];
		$node = self::dataSetupNode($node, $documentID);
		$id = $node->dataID;

		if ($name) {
			if (isset($document->data[$id][$name])) {
				unset($document->data[$id][$name]);
			}
			
			$name = null;
			foreach($document->data[$id] as $name) {
				break;
			}

			if (! $name) {
				self::removeData($node, $name, $documentID);
			}

		} else {
			self::dataRemoveNode($node, $documentID);
		}
	}

}