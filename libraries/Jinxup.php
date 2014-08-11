<?php

	class Jinxup
	{
		public static $exit = false;

		private static $_app;
		private static $_config;
		private static $_init   = null;
		private static $_dirs   = array('config' => 'config', 'applications' => 'applications');
		private static $_routes = array('controller' => 'Index_Controller', 'action' => 'indexAction');

		public function init()
		{
			if (is_null(self::$_init))
			{
				self::_autoloadJinxup();
				self::_parseFrameworkConfig();
				self::_parseGlobalConfig();
				self::_sessions();
				self::_prepareURI();
				self::_setApplication();
				self::_runRoutes();

				self::$_init = 'loaded';
			}
		}

		public function getInit()
		{
			echo self::$_init;
		}

		public static function getApp()
		{
			return self::$_app;
		}

		private static function _autoloadJinxup()
		{
			$autoloaderPath = __DIR__ . DS . 'Autoloader.php';

			if (!file_exists($autoloaderPath))
				exit('Missing autoloader');

			require_once($autoloaderPath);

			JXP_Autoloader::peekIn(__DIR__);

			if (function_exists('__autoload'))
				spl_autoload_register('__autoload');

			spl_autoload_register(array('JXP_Autoloader', 'autoload'));
		}

		private static function _parseFrameworkConfig()
		{
			self::$_config = JXP_Config::translate(JXP_Config::loadFromPath(dirname(__DIR__) . DS . 'config'));
		}

		private static function _parseGlobalConfig()
		{
			self::$_config = JXP_Config::translate(JXP_Config::loadFromPath(dirname(dirname(__DIR__)) . DS . 'config'));
		}

		private static function _sessions()
		{
			session_start();
		}

		private static function _prepareURI()
		{
			self::$_routes['params'] = explode('/', self::getRequestURI());

			return self::$_routes['params'];
		}

		private static function _runRoutes()
		{
			$params = array_filter(self::$_routes['params']);

			if (isset($params[0]) && strtolower($params[0]) == 'assets')
			{
				$file = getcwd() . DS . implode($params, '/');

				if (is_file($file))
				{
					header('Content-type: ' . JXP_File::detectMime($file));

					include_once($file);

				} else {

					self::_logExit('file');
				}

			} else {

				if (!empty($params[0]) && strtolower($params[0]) == 'index.php')
					$params[0] = 'index';

				self::_prepareRoutes($params);
				self::_loadApplication();
			}
		}

		private static function _prepareRoutes($params)
		{
			if (!empty($params))
			{
				if ($params[0] != '-')
				{
					$prefix = is_numeric($params[0][0]) ? 'n' : null;
					$prefix = $params[0][0] == '_' ? 'u' : $prefix;
					$prefix = $params[0][0] == '-' ? 'd' : $prefix;

					$controller = $prefix . str_replace('-', '_', array_shift($params)) . '_Controller';

					self::$_routes['controller'] = $controller;

				} else {

					array_shift($params);
				}
			}

			if (!empty($params))
			{
				if ($params[0] != '-')
				{
					$prefix = is_numeric($params[0][0]) ? 'n' : null;
					$prefix = $params[0][0] == '_' ? 'u' : $prefix;
					$prefix = $params[0][0] == '-' ? 'd' : $prefix;

					$action = $prefix . str_replace('-', '_', array_shift($params)) . 'Action';

					self::$_routes['action'] = $action;

				} else {

					array_shift($params);
				}
			}

			self::$_routes['params'] = $params;
		}

		private static function _setApplication()
		{
			$activeApp = null;
			$config    = self::$_config;

			if (isset($config['directories']['applications']))
			{
				if (is_dir(getcwd() . DS . $config['directories']['applications']))
					self::$_dirs['applications'] = $config['directories']['applications'];

				unset(self::$_config['directories']);
			}

			if (isset($config['domains']))
			{

				unset(self::$_config['domains']);

			} else {

				if (isset($config['active']))
				{
					$applications = JXP_Directory::scan(getcwd() . DS . self::$_dirs['applications']);

					if (array_key_exists($config['active'], $applications))
						$activeApp = $config['active'];
					else
						self::_logExit('application');

					unset(self::$_config['active']);
				}
			}

			$app['path'] = $_SERVER['DOCUMENT_ROOT'] . DS . self::$_dirs['applications'] . DS . $activeApp;
			self::$_app  = $app;

			if (is_dir($app['path']))
				chdir($app['path']);
			else
				self::_logExit('page');
		}

		private static function _loadApplication()
		{
			if (self::$exit == false)
			{
				JXP_Autoloader::peekIn(getcwd());

				self::$_app['paths'] = JXP_Directory::scan(getcwd());

				if (isset(self::$_app['paths']['config']))
					self::$_config = JXP_Config::translate(JXP_Config::loadFromPath(self::$_app['paths']['config']));

				$return    = null;
				$bootstrap = null;
				$routes    = self::$_routes;

				if (class_exists('Bootstrap_Controller'))
				{
					$bootstrap = new Bootstrap_Controller();

					if (method_exists($bootstrap, 'onConstruct') && is_callable(array($bootstrap, 'onConstruct')))
						$bootstrap->onConstruct();
				}

				if (class_exists($routes['controller']))
				{
					$c = new $routes['controller']();
					$p = $routes['params'];
					$j = method_exists($c, $routes['action']);
					$i = is_callable(array($c, $routes['action']));
					$n = method_exists($c, '__call');
					$x = is_callable(array($c, '__call'));

					if (($j && $i) || ($n && $x))
					{
						if (count($p) == 3)
							$c->{$routes['action']}($p[0], $p[1], $p[2]);
						else if (count($p) == 2)
							$c->{$routes['action']}($p[0], $p[1]);
						else if (count($p) == 1)
							$c->{$routes['action']}($p[0]);
						else if (count($p) == 0)
							$c->{$routes['action']}();
						else
							call_user_func_array(array($c, $routes['action']), $p);

					} else {

						self::_logExit('page');
					}

				} else {

					self::_logExit('page');
				}

				if (!is_null($bootstrap) && is_callable(array($bootstrap, 'onDestruct')))
					$bootstrap->onDestruct();

				unset($c);
				unset($p);
				unset($routes);
				unset($_bootstrap);
			}
		}

		public static function config()
		{
			return self::$_config;
		}

		public static function getWebPaths()
		{
			$paths = array();

			if (!empty(self::$_app['paths']))
			{
				foreach (self::$_app['paths'] as $key => $val)
					$paths[$key] = str_replace(DS, '/', str_replace(getcwd(), '', $val));
			}

			return $paths;
		}

		public static function getWebPath($key)
		{
			$path = null;

			if (!empty(self::$_app['paths']))
			{
				$app = self::$_app['paths'];

				if (isset($app[$key]))
					$path = str_replace(DS, '/', str_replace(getcwd(), '', $app[$key]));
			}

			return $path;
		}

		/**
		 * Directly alter invoked controller
		 *
		 * @param $controller
		 */
		public static function setController($controller)
		{
			self::$_routes['controller'] = $controller;
		}

		/**
		 * Directly alter invoked call action
		 *
		 * @param $action
		 */
		public static function setAction($action)
		{
			self::$_routes['action'] = $action;
		}

		/**
		 * @return mixed
		 */
		public static function getDomain()
		{
			return parse_url(getenv('SERVER_NAME'), PHP_URL_PATH);
		}

		/**
		 * @return array
		 */
		public static function getRoutes()
		{
			return self::$_routes;
		}

		public static function getActive()
		{
			return self::$_app['active'];
		}

		/**
		 * @param int $depth
		 * @return string
		 */
		public static function getSubdomain($depth = 0)
		{
			$subdomain = explode('.', rawurldecode(parse_url(getenv('HTTP_HOST'), PHP_URL_PATH)));

			return isset($subdomain[$depth]) ? $subdomain[$depth] : null;
		}

		/**
		 * @return string
		 */
		public static function getRequestURI()
		{
			$request = rawurldecode(trim(parse_url(getenv('REQUEST_URI'), PHP_URL_PATH), '/'));

			return $request;
		}

		/**
		 * @param bool $friendly
		 * @return string
		 */
		public static function getController($friendly = false)
		{
			return $friendly === true ? self::$_routes['controller'] : str_replace('_Controller', '', self::$_routes['controller']);
		}

		/**
		 * @param bool $friendly
		 * @return string
		 */
		public static function getModel($friendly = false)
		{
			return $friendly === true ? self::$_routes['controller'] . '_Model' : self::getController();
		}

		/**
		 * @param bool $friendly
		 * @return string
		 */
		public static function getActionCall($friendly = false)
		{
			return $friendly === true ? self::$_routes['action'] : str_replace('Action', '', self::$_routes['action']);
		}

		/**
		 * @return array
		 */
		public static function getParams()
		{
			return self::$_routes['params'];
		}

		/**
		 * @return int
		 */
		public static function getParamCount()
		{
			return count(self::$_routes['params']);
		}

		/**
		 * @param $name
		 * @param int $count
		 */
		public static function addParam($name, $count = 1)
		{
			for ($i = 0; $i < $count; $i++)
				self::$_routes['params'][] = $name;
		}

		/***
		 * @param $params
		 * @return array|string
		 */
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
						$_parameters[$parameters[$i]] = $parameters[$i + 1] ?: null;

						unset($parameters[$i]);

						if (isset($parameters[$i + 1]))
							unset($parameters[$i + 1]);

						$i += 2;
					}
				}
			}

			return JXP_Format::trimSpaces($_parameters);
		}

		private static function _logExit($errorType = null, $param1 = null)
		{
			Jinxup::$exit = true;

			chdir(dirname(__DIR__));

			$errorCode = 404;
			$errorTpl  = null;
			$errorPath = getcwd() . DS . 'views';

			ob_start();

			header('HTTP/1.0 404 Not Found');

			$errorTpl = $errorType . '.tpl';

			if ($errorCode == 404 || $errorCode == 500)
			{
				if (!empty(self::$_config['error']) && isset(self::$_config['error']['catch']))
				{
					if (isset(self::$_app['paths']['views']))
					{
						$errorPath = rtrim(self::$_app['paths']['views'], '/');

						if (array_key_exists('*', self::$_config['error']['catch']) )
							$errorTpl = trim(self::$_config['error']['catch']['*'], '/');

						if (array_key_exists('all', self::$_config['error']['catch']) )
							$errorTpl = trim(self::$_config['error']['catch']['all'], '/');

						if (array_key_exists($errorCode, self::$_config['error']['catch']) )
							$errorTpl = trim(self::$_config['error']['catch'][$errorCode], '/');
					}

				} else {

					$errorPath = getcwd() . DS . 'views';
				}

				if (!file_exists($errorPath . '/' . $errorTpl))
				{
					$errorPath = getcwd() . DS . 'views';
					$errorTpl  = $errorType . '.tpl';
				}
			}

			JXP_View::setTplPath($errorPath);
			JXP_View::render($errorTpl);
		}
	}