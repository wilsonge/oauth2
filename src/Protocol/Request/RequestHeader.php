<?php
/**
 * Part of the Joomla Framework OAuth2 Package
 *
 * @copyright  Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

namespace Joomla\OAuth2\Protocol\Request;

use Joomla\Application\AbstractApplication;
use Joomla\Input\Input;
use Joomla\Uri\Uri;
use Joomla\OAuth2\Protocol\Request;
use Joomla\CMS\Factory;

/**
 * Header class
 *
 * @package  Joomla.Framework
 * @since    1.0
 */
class RequestHeader
{
	/**
	 * @var    Input  The Joomla Input Object.
	 * @since  1.0
	 */
	private $input;

	/**
	 * Object constructor.
	 *
	 * @param   Input  $input  The Joomla Input Object
	 *
	 * @since   1.0
	 */
	public function __construct(Input $input)
	{
		// Setup the database object.
		$this->input = $input;
	}

	/**
	 * Get the HTTP Request headers.  Header names have been normalized, stripping
	 * the leading 'HTTP_' if present, and capitalizing only the first letter
	 * of each word.
	 *
	 * @return  string  The Authorization header if it has been set or false if is not present
	 */
	public function fetchAuthorizationHeader()
	{
		// The simplest case is if the apache_request_headers() function exists.
		if (function_exists('apache_request_headers'))
		{
			$headers = apache_request_headers();

			if (isset($headers['Authorization']))
			{
				return trim($headers['Authorization']);
			}
		}

		// Otherwise we need to look in the $_SERVER superglobal.
		elseif ($this->input->server->getString('HTTP_AUTHORIZATION', false))
		{
			return trim($this->input->server->getString('HTTP_AUTHORIZATION'));
		}

		elseif ($this->input->server->getString('HTTP_AUTH_USER', false))
		{
			return trim($this->input->server->getString('HTTP_AUTH_USER'));
		}

		elseif ($this->input->server->getString('HTTP_USER', false))
		{
			return trim($this->input->server->getString('HTTP_USER'));
		}

		return false;
	}

	/**
	 * Parse an OAuth authorization header and set any found OAuth parameters.
	 *
	 * @param   string  $header  Authorization header.
	 *
	 * @return  mixed  Array of OAuth 1.2 parameters if found or boolean false otherwise.
	 *
	 * @since   1.0
	 */
	public function processAuthorizationHeader($header)
	{
		// Initialise variables.
		$parameters = array();

		$server = $_SERVER;

		$headers = array();

		foreach ($server as $key => $value)
		{
			if (0 === strpos($key, 'HTTP_'))
			{
				$headers[substr($key, 5)] = $value;
			}

			// CONTENT_* are not prefixed with HTTP_
			elseif (in_array($key, array('CONTENT_LENGTH', 'CONTENT_MD5', 'CONTENT_TYPE'))) {
				$headers[strtolower($key)] = $value;
			}
		}

		if (isset($server['PHP_AUTH_USER']))
		{
			$headers['PHP_AUTH_USER'] = $server['PHP_AUTH_USER'];
			$headers['PHP_AUTH_PW'] = isset($server['PHP_AUTH_PW']) ? $server['PHP_AUTH_PW'] : '';
		}
		else
		{
			/*
			* php-cgi under Apache does not pass HTTP Basic user/pass to PHP by default
			* For this workaround to work, add this line to your .htaccess file:
			* RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
			*
			* A sample .htaccess file:
			* RewriteEngine On
			* RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
			* RewriteCond %{REQUEST_FILENAME} !-f
			* RewriteRule ^(.*)$ app.php [QSA,L]
			*/
			$authorizationHeader = null;

			if (isset($server['HTTP_AUTHORIZATION']))
			{
				$authorizationHeader = $server['HTTP_AUTHORIZATION'];
			}
			elseif (isset($server['REDIRECT_HTTP_AUTHORIZATION']))
			{
				$authorizationHeader = $server['REDIRECT_HTTP_AUTHORIZATION'];
			}
			elseif (function_exists('apache_request_headers'))
			{
				$requestHeaders = apache_request_headers();

				// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
				$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));

				if (isset($requestHeaders['Authorization']))
				{
					$authorizationHeader = trim($requestHeaders['Authorization']);
				}
			}

			if (null !== $authorizationHeader)
			{
				$headers['AUTHORIZATION'] = $authorizationHeader;

				// Decode AUTHORIZATION header into PHP_AUTH_USER and PHP_AUTH_PW when authorization header is basic
				if (0 === stripos($authorizationHeader, 'basic'))
				{
					$exploded = explode(':', base64_decode(substr($authorizationHeader, 6)));

					if (count($exploded) == 2)
					{
						list($headers['PHP_AUTH_USER'], $headers['PHP_AUTH_PW']) = $exploded;
					}
				}
			}
		}

		// PHP_AUTH_USER/PHP_AUTH_PW
		if (isset($headers['PHP_AUTH_USER']))
		{
			$headers['AUTHORIZATION'] = 'Basic ' . base64_encode($headers['PHP_AUTH_USER'] . ':' . $headers['PHP_AUTH_PW']);
		}

		// PHP_USER/PHP_PW
		if (isset($headers['PHP_USER']) && empty($headers['AUTHORIZATION']) )
		{
			$headers['AUTHORIZATION'] = 'Basic ' . base64_encode($headers['PHP_USER'] . ':' . $headers['PHP_PW']);
		}

		// Iterate over the reserved parameters and look for them in the POST variables.
		foreach (Request::getReservedParameters() as $k)
		{
			$name = 'HTTP_OAUTH_' . strtoupper($k);

			if ( isset($server[$name]) )
			{
				$headers[$name] = trim($server[$name]);
			}
		}

		// If we didn't find anything return false.
		if (empty($headers))
		{
			return false;
		}

		return $headers;
	}
}
