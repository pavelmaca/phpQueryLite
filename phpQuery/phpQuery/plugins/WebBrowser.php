<?php

namespace phpQuery\Plugin;

use phpQuery\phpQuery;
use phpQuery\Plugin\WebBrowserObject;
use phpQuery\Plugin\WebBrowserTools;

require_once __DIR__ . '/WebBrowserObject.php';
require_once __DIR__ . '/WebBrowserTools.php';



class WebBrowser
{
	
	/**
	 *
	 * @param $url
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return Zend_Http_Client
	 */
	public static function browserGet($url, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		phpQuery::debug("[WebBrowser] GET: $url");
		self::authorizeHost($url);
		$xhr = phpQuery::ajax(array(
			'type' => 'GET',
			'url' => $url,
			'dataType' => 'html',
		));

		$paramStructure = null;
		if (func_num_args() > 2) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 2);
		}

		if ($xhr->getLastResponse()->isSuccessful()) {
			phpQuery::callbackRun($callback,
				array(self::browserReceive($xhr)->WebBrowser()),
				$paramStructure
			);
//			phpQuery::callbackRun($callback, array(
//				self::browserReceive($xhr)//->WebBrowser($callback)
//			));
			return $xhr;

		} else {
			throw new \Exception("[WebBrowser] GET request failed; url: $url");
			return false;
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
	 * @return Zend_Http_Client
	 */
	public static function browserPost($url, $data, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		self::authorizeHost($url);
		$xhr = phpQuery::ajax(array(
			'type' => 'POST',
			'url' => $url,
			'dataType' => 'html',
			'data' => $data,
		));

		$paramStructure = null;
		if (func_num_args() > 3) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 3);
		}
		
		if ($xhr->getLastResponse()->isSuccessful()) {
			phpQuery::callbackRun($callback,
				array(self::browserReceive($xhr)->WebBrowser()),
				$paramStructure
			);
//			phpQuery::callbackRun($callback, array(
//				self::browserReceive($xhr)//->WebBrowser($callback)
//			));
			return $xhr;

		} else {
			return false;
		}
	}

	
	/**
	 *
	 * @param $ajaxSettings
	 * @param $callback
	 * @param $param1
	 * @param $param2
	 * @param $param3
	 * @return Zend_Http_Client
	 */
	public static function browser($ajaxSettings, $callback, $param1 = null, $param2 = null, $param3 = null)
	{
		self::authorizeHost($ajaxSettings['url']);
		$xhr = phpQuery::ajax(
			self::ajaxSettingsPrepare($ajaxSettings)
		);

		$paramStructure = null;
		if (func_num_args() > 2) {
			$paramStructure = func_get_args();
			$paramStructure = array_slice($paramStructure, 2);
		}

		if ($xhr->getLastResponse()->isSuccessful()) {
			phpQuery::callbackRun($callback,
				array(self::browserReceive($xhr)->WebBrowser()),
				$paramStructure
			);
//			phpQuery::callbackRun($callback, array(
//				self::browserReceive($xhr)//->WebBrowser($callback)
//			));
			return $xhr;

		} else {
			return false;
		}
	}

	
	protected static function authorizeHost($url)
	{
		$host = parse_url($url, PHP_URL_HOST);
		if ($host) {
			phpQuery::ajaxAllowHost($host);
		}
	}

	
	protected static function ajaxSettingsPrepare($settings)
	{
		unset($settings['success']);
		unset($settings['error']);
		return $settings;
	}

	
	/**
	 * @param Zend_Http_Client $xhr
	 */
	public static function browserReceive($xhr)
	{
		phpQuery::debug("[WebBrowser] Received from ".$xhr->getUri(true));
		// TODO handle meta redirects
		$body = $xhr->getLastResponse()->getBody();

		// XXX error ???
		if (strpos($body, '<!doctype html>') !== false) {
			$body = '<html>'
				.str_replace('<!doctype html>', '', $body)
				.'</html>';
		}

		$pq = phpQuery::newDocument($body);
		$pq->document->xhr = $xhr;
		$pq->document->location = $xhr->getUri(true);
		$refresh = $pq->find('meta[http-equiv=refresh]')->add('meta[http-equiv=Refresh]');

		if ($refresh->size()) {
//			print htmlspecialchars(var_export($xhr->getCookieJar()->getAllCookies(), true));
//			print htmlspecialchars(var_export($xhr->getLastResponse()->getHeader('Set-Cookie'), true));
			phpQuery::debug("Meta redirect... '{$refresh->attr('content')}'\n");
			// there is a refresh, so get the new url
			$content = $refresh->attr('content');
			$urlRefresh = substr($content, strpos($content, '=')+1);
			$urlRefresh = trim($urlRefresh, '\'"');
			// XXX not secure ?!
			phpQuery::ajaxAllowURL($urlRefresh);
//			$urlRefresh = urldecode($urlRefresh);
			// make ajax call, passing last $xhr object to preserve important stuff
			$xhr = phpQuery::ajax(array(
				'type' => 'GET',
				'url' => $urlRefresh,
				'dataType' => 'html',
			), $xhr);

			if ($xhr->getLastResponse()->isSuccessful()) {
				// if all is ok, repeat this method...
				return call_user_func_array(
					array(__CLASS__, 'browserReceive'), array($xhr)
				);
			}

		} else {
			return $pq;
		}
	}

	
	/**
	 *
	 * @param $e
	 * @param $callback
	 * @return unknown_type
	 */
	public static function hadleClick($e, $callback = null)
	{
		$node = phpQuery::pq($e->target);
		$type = null;
		if ($node->is('a[href]')) {
			// TODO document.location
			$xhr = isset($node->document->xhr) ? $node->document->xhr : null;
			$xhr = phpQuery::ajax(array(
				'url' => WebBrowserTools::resolve_url($e->data[0], $node->attr('href')),
				'referer' => $node->document->location,
			), $xhr);

			if ((! $callback || !($callback instanceof Callback)) && $e->data[1]) {
				$callback = $e->data[1];
			}

			if ($xhr->getLastResponse()->isSuccessful() && $callback) {
				phpQuery::callbackRun($callback, array(
					self::browserReceive($xhr)
				));
			}

		} elseif ($node->is(':submit') && $node->parents('form')->size()) {
			$node->parents('form')->trigger('submit', array($e));
		}
	}

	
	/**
	 * Enter description here...
	 *
	 * @param unknown_type $e
	 * @TODO trigger submit for form after form's  submit button has a click event
	 */
	public static function handleSubmit($e, $callback = null)
	{
		$node = phpQuery::pq($e->target);
		if (!$node->is('form') || !$node->is('[action]')) {
			return;
		}

		// TODO document.location
		$xhr = isset($node->document->xhr) ? $node->document->xhr : null;
		$submit = pq($e->relatedTarget)->is(':submit') ? $e->relatedTarget
				// will this work ?
//			: $node->find(':submit:first')->get(0);
			: $node->find('*:submit:first')->get(0);

		$data = array();
		foreach($node->serializeArray($submit) as $r) {
		// XXXt.c maybe $node->not(':submit')->add($sumit) would be better ?
//		foreach($node->serializeArray($submit) as $r)
			$data[ $r['name'] ] = $r['value'];
		}

		$options = array(
			'type' => $node->attr('method')
				? $node->attr('method')
				: 'GET',
			'url' => WebBrowserTools::resolve_url($e->data[0], $node->attr('action')),
			'data' => $data,
			'referer' => $node->document->location,
//			'success' => $e->data[1],
		);

		if ($node->attr('enctype')) {
			$options['contentType'] = $node->attr('enctype');
		}

		$xhr = phpQuery::ajax($options, $xhr);
		if ((! $callback || !($callback instanceof Callback)) && $e->data[1]) {
			$callback = $e->data[1];
		}

		if ($xhr->getLastResponse()->isSuccessful() && $callback) {
			phpQuery::callbackRun($callback, array(
				self::browserReceive($xhr)
			));
		}
	}
}