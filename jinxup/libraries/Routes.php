<?php

	class JXP_Routes
	{
		public static $_routes = array('controller' => 'Index_Controller', 'action' => 'indexAction');
		public static $prefix  = null;

		public static function setRoutes($routes = array())
		{
			self::$_routes = $routes;
		}

		public static function getRoutes()
		{
			return self::$_routes;
		}

		public static function setController($controller)
		{
			self::$_routes['controller'] = $controller;
		}

		public static function getController($friendly = false)
		{
			return $friendly === true ? self::$_routes['controller']['raw'] : self::$_routes['controller']['translated'];
		}

		public static function getActionCall($friendly = false)
		{
			return $friendly === true ? self::$_routes['action']['raw'] : self::$_routes['action']['translated'];
		}

		public static function getModel($friendly = false)
		{
			return $friendly === true ? self::$_routes['controller']['translated'] . '_Model' : self::getController();
		}

		public static function getDomain()
		{
			return parse_url(getenv('HTTP_HOST'), PHP_URL_PATH);
		}

		public static function getSubdomain($depth = 0, $tld = '.com')
		{
			$subdomain = array_filter(explode('.', str_replace($tld, '', self::getDomain())));

			unset($subdomain[(count($subdomain) - 1)]);

			return count($subdomain) >= 1 ? isset($subdomain[$depth]) ? $subdomain[$depth] : null : null;
		}

		public static function getDomainExt()
		{
			$host = self::getDomain();

			preg_match('/(.*?)((\.co)?.[a-z]{2,4})$/im', $host['host'], $m);

			return isset($m[2]) ? $m[2] : '';
		}

		public static function getURI()
		{
			$uri     = str_replace('index.php', '', getenv('PHP_SELF'));
			$uri     = str_replace($uri, '', getenv('REQUEST_URI'));
			$request = rawurldecode(trim(parse_url($uri, PHP_URL_PATH), '/'));

			return '/' . $request;
		}

		public static function getParamCount()
		{
			return isset(self::$_routes['params']) ? count(self::$_routes['params']) : 0;
		}

		public static function getSegment($index = 0)
		{
			$return = null;

			if ($index == 0)
				$return = self::getController();

			if ($index == 1)
				$return = self::getActionCall();

			if ($index > 1)
				$return = isset(self::$_routes['params'][$index]) ? self::$_routes['params'][$index] : array();

			return $return;
		}

		public static function getParams()
		{
			return isset(self::$_routes['params']) ? self::$_routes['params'] : array();
		}

		public static function addParam($name, $count = 1)
		{
			for ($i = 0; $i < $count; $i++)
				self::$_routes['params'][] = $name;
		}

		public static function addParams($params)
		{
			self::$_routes['params'] += $params;
		}

		public static function assocParams($params = array())
		{
			$parameters  = empty($params) ? self::getParams() : $params;
			$_parameters = array();

			if (count($parameters) > 1)
			{
				$i = 0;

				while (!empty($parameters))
				{
					if (isset($parameters[$i]))
					{
						$_parameters[$parameters[$i]] = isset($parameters[$i + 1]) ? $parameters[$i + 1] : null;

						unset($parameters[$i]);

						if (isset($parameters[$i + 1]))
							unset($parameters[$i + 1]);

						$i += 2;
					}
				}
			}

			return JXP_Format::trimSpaces($_parameters);
		}
	}