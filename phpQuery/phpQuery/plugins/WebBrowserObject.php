<?php

namespace phpQuery\Plugin;

use phpQuery\phpQuery;
use phpQuery\Plugin\WebBrowser;
use phpQuery\Plugin\WebBrowserTools;



/**
 * WebBrowser plugin.
 *
 */
class WebBrowserObject
{

	/**
	 * Limit binded methods to specified ones.
	 *
	 * @var array
	 */
	public static $phpQueryMethods = null;

	
	/**
	 * Enter description here...
	 *
	 * @param phpQueryObject $self
	 * @todo support 'reset' event
	 */
	public static function WebBrowser($self, $callback = null, $location = null)
	{
		$self = $self->_clone()->toRoot();
			// TODO use document.location
		$location = $location ? $location : $self->document->xhr->getUri(true);
		// FIXME tmp
		$self->document->WebBrowserCallback = $callback;
		
		if (! $location) {
			throw new \Exception('Location needed to activate WebBrowser plugin !');

		} else {
			$self->bind('click', array($location, $callback), array(__CLASS__, 'hadleClick'));
			$self->bind('submit', array($location, $callback), array(__CLASS__, 'handleSubmit'));
		}
		
	}

	
	public static function browser($self, $callback = null, $location = null)
	{
		return $self->WebBrowser($callback, $location);
	}

	
	public static function downloadTo($self, $dir = null, $filename = null)
	{
		$url = null;
		if ($self->is('a[href]')) {
			$url = $self->attr('href');

		} elseif ($self->find('a')->length) {
			$url = $self->find('a')->attr('href');
		}

		if ($url) {
			$url = WebBrowserTools::resolve_url($self->document->location, $url);
			if (!$dir) {
				$dir = getcwd();
			}

			// TODO resolv name from response headers
			if (! $filename) {
				$matches = null;
				preg_match('@/([^/]+)$@', $url, $matches);
				$filename = $matches[1];
			}

			//print $url;
			$path = rtrim($dir, '/').'/'.$filename;
			phpQuery::debug("Requesting download of $url\n");
			// TODO use AJAX instead of file_get_contents
			file_put_contents($path, file_get_contents($url));
		}
		
		return $self;
	}

	
	/**
	 * Method changing browser location.
	 * Fires callback registered with WebBrowser(), if any.
	 * @param $self
	 * @param $url
	 * @return unknown_type
	 */
	public static function location($self, $url = null)
	{
		// TODO if ! $url return actual location ???
		$xhr = isset($self->document->xhr) ? $self->document->xhr : null;
		$xhr = phpQuery::ajax(array(
			'url' => $url,
		), $xhr);

		$return = false;

		if ($xhr->getLastResponse()->isSuccessful()) {
			$return = WebBrowser::browserReceive($xhr);
			if (isset($self->document->WebBrowserCallback))
				phpQuery::callbackRun(
					$self->document->WebBrowserCallback,
					array($return)
				);
		}

		return $return;
	}

	
}

