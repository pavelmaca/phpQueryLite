<?php


namespace phpQuery\Plugin;


final class WebBrowserTools
{

	/**
	 *
	 * @param unknown_type $parsed
	 * @return unknown
	 * @link http://www.php.net/manual/en/function.parse-url.php
	 * @author stevenlewis at hotmail dot com
	 */
	public static function glue_url($parsed)
	{
		if (!is_array($parsed)){
			return false;
		}

		$uri = isset($parsed['scheme']) ? $parsed['scheme'].':'.((strtolower($parsed['scheme']) == 'mailto') ? '':'//'): '';
		$uri .= isset($parsed['user']) ? $parsed['user'].($parsed['pass']? ':'.$parsed['pass']:'').'@':'';
		$uri .= isset($parsed['host']) ? $parsed['host'] : '';
		$uri .= isset($parsed['port']) ? ':'.$parsed['port'] : '';

		if(isset($parsed['path'])) {
			$uri .= (substr($parsed['path'],0,1) == '/')?$parsed['path']:'/'.$parsed['path'];
		}

		$uri .= isset($parsed['query']) ? '?'.$parsed['query'] : '';
		$uri .= isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

		return $uri;
	}


	/**
	 * Enter description here...
	 *
	 * @param unknown_type $base
	 * @param unknown_type $url
	 * @return unknown
	 * @author adrian-php at sixfingeredman dot net
	 */
	public static function resolve_url($base, $url)
	{
		if (!strlen($base)) {
			return $url;
		}
		// Step 2
		if (!strlen($url)) {
			return $base;
		}
		// Step 3
		if (preg_match('!^[a-z]+:!i', $url)) {
			return $url;
		}
		$base = parse_url($base);

		if ($url{0} == "#") {
			// Step 2 (fragment)
			$base['fragment'] = substr($url, 1);
			return unparse_url($base);
		}

		unset($base['fragment']);
		unset($base['query']);
		if (substr($url, 0, 2) == "//") {
			// Step 4
			return unparse_url(array(
					'scheme'=>$base['scheme'],
					'path'=>substr($url,2),
			));

		} elseif ($url{0} == "/") {
			// Step 5
			$base['path'] = $url;

		} else {
				// Step 6
			$path = explode('/', $base['path']);
			$url_path = explode('/', $url);
			// Step 6a: drop file from base
			array_pop($path);
			// Step 6b, 6c, 6e: append url while removing "." and ".." from
			// the directory portion
			$end = array_pop($url_path);
			foreach ($url_path as $segment) {
					if ($segment == '.') {
							// skip

					} elseif ($segment == '..' && $path && $path[sizeof($path)-1] != '..') {
							array_pop($path);

					} else {
							$path[] = $segment;
					}
			}

			// Step 6d, 6f: remove "." and ".." from file portion
			if ($end == '.') {
					$path[] = '';

			} elseif ($end == '..' && $path && $path[sizeof($path)-1] != '..') {
					$path[sizeof($path)-1] = '';

			} else {
					$path[] = $end;
			}

			// Step 6h
			$base['path'] = join('/', $path);

		}

		// Step 7
		return self::glue_url($base);
	}
	
}