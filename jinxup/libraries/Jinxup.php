<?php

	class Jinxup
	{
		private static $_app;
		private static $_config;
		private static $_exit       = false;
		private static $_init       = null;
		private static $_apps       = array();
		private static $_loadedApps = array();
		private static $_routes     = array('controller' => 'Index_Controller', 'action' => 'indexAction');

		public function __construct()
		{
			if (!defined('DS'))
				define('DS', DIRECTORY_SEPARATOR);

			self::_autoload();
		}

		public function init()
		{
			if (is_null(self::$_init))
			{
				self::_parseFrameworkConfig();
				self::_parseGlobalConfig();
				self::_sessions();
				self::_prepareURI();
				self::_findApplications();
				self::_setApplication();
				self::_runRoutes();

				self::$_init = 'loaded';
			}
		}
		
		public static function load($app)
		{
			if (!in_array($app, self::$_loadedApps))
			{
				if (array_key_exists($app, self::$_apps))
				{
					JXP_Autoloader::removeFromPath(JXP_Application::getActive());

					self::_setApplication($app);
					self::_autoload();
					self::_runRoutes();

				} else {

					self::_logExit('application');
				}

				exit;
			}
		}
		
		public static function stop()
		{
			exit;
		}

		public static function path($dir)
		{
			$path = JXP_Directory::scan(dirname(__DIR__));

			return is_dir($path[$dir]) ? $path[$dir] : null;
		}

		public function getInit()
		{
			echo self::$_init;
		}

		public static function getApp()
		{
			return self::$_app;
		}

		private static function _autoload()
		{
			$autoloaderPath = __DIR__ . DS . 'Autoloader.php';

			spl_autoload_unregister(array('JXP_Autoloader', 'autoload'));

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
			self::$_config = JXP_Config::translate(JXP_Config::loadFromPath(getcwd() . DS . 'config'));
		}

		private static function _sessions()
		{
			if (isset(self::$_config['session']) && JXP_Tracker::getIP() != '127.0.0.1')
			{
				$cfgSess = self::$_config['session'];
				$use     = isset($cfgSess['use']) ? $cfgSess['use'] : 'redis';
				$handler = null;

				if ($use == 'redis')
				{
					if (isset($cfgSess[$use]['host']))
					{
						require_once 'vendors' . DS . 'predis' . DS . 'Autoloader.php';

						Predis\Autoloader::register();

						$ttl  = isset($cfgSess[$use]['ttl']) ? $cfgSess[$use]['ttl'] : 3600;
						$port = isset($cfgSess[$use]['port']) ? $cfgSess[$use]['port'] : 6973;

						$client = new Predis\Client(array(
							'scheme' => 'tcp',
							'host'   => $cfgSess[$use]['host'],
							'port'   => $port
						));

						$handler = new JXP_Session($client, 'JINXUP_', $ttl);
					}
				}

				if (!is_null($handler))
				{
					session_set_save_handler(
						array($handler, 'open'),
						array($handler, 'close'),
						array($handler, 'read'),
						array($handler, 'write'),
						array($handler, 'destroy'),
						array($handler, 'gc')
					);
				}
			}

			session_start();
		}

		private static function _prepareURI()
		{
			self::$_routes['params'] = explode('/', JXP_Routes::getURI());

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

				$params = preg_replace('/\.php/im', '', $params);

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

			JXP_Routes::setRoutes(self::$_routes);
		}

		private static function _findApplications()
		{
			self::$_apps = JXP_Directory::scan(getcwd() . DS . JXP_Application::getDirectories('applications'));
		}

		private static function _setApplication($forceApp = null)
		{
			$activeApp = null;
			$app       = array();
			$config    = self::$_config;
			$dirs      = JXP_Application::getDirectories();

			if (isset($config['directories']))
			{
				$dirs = $config['directories'];

				if (isset($dirs['applications']))
				{
					if (is_dir(getcwd() . DS . $dirs['applications']))
						$dirs['applications'] = $dirs['applications'];

					unset(self::$_config['directories']);
				}

				JXP_Application::setDirectories($config['directories']);
			}

			if (empty(self::$_apps))
			{
				self::_logExit('welcome', 242);

			} else {

				if (count(self::$_apps) == 1)
				{
					$app       = array_keys(self::$_apps);
					$activeApp = $app[0];

				} else {

					if (isset($config['domains']))
					{
						// TODO: set activeApp from domain settings
						
						unset(self::$_config['domains']);

					} else {

						if (isset($config['active']) && !empty($config['active']))
						{
							if (array_key_exists($config['active'], self::$_apps))
								$activeApp = $config['active'];
							else
								self::_logExit('application',266);

							unset(self::$_config['active']);
						}

						if (!empty(self::$_routes['params']) && array_key_exists(self::$_routes['params'][0], self::$_apps))
							$activeApp = array_shift(self::$_routes['params']);

						if (!is_null($forceApp))
						{
							chdir(dirname(dirname(self::$_app['path'])));
							
							$activeApp = $forceApp;
						}

						if (is_null($activeApp))
							self::_logExit('active',282);
					}
				}

				$app['path']         = getcwd() . DS . $dirs['applications'] . DS . $activeApp;
				self::$_app          = $app;
				self::$_loadedApps[] = $activeApp;

				JXP_Application::setActive($activeApp);
				JXP_Application::setApps(self::$_apps);

				if (self::$_exit == false)
				{
					if (is_dir($app['path']))
					{
						if (self::_checkApplicationIntegrity($app['path']))
						{
							chdir($app['path']);

							self::$_app['paths'] = JXP_Directory::scan(getcwd());

							JXP_Application::setApp(self::$_app);

							if (isset(self::$_app['paths']['views']))
								JXP_View::setPath('views', self::$_app['paths']['views']);

							if (isset(self::$_app['paths']['config']))
								self::$_config = JXP_Config::translate(JXP_Config::loadFromPath(self::$_app['paths']['config']));

							JXP_Autoloader::peekIn(getcwd(), JXP_Application::getActive());

							if (isset(self::$_config['environment']))
							{
								if (self::$_config['environment'] == 'development')
								{
									error_reporting(E_ALL);
									ini_set('display_errors', 1);
									ini_set('auto_detect_line_endings', 1);
									set_time_limit(0);
								}

								unset(self::$_config['environment']);
							}

						} else {

							self::_logExit('integrity',328);
						}

					} else {

						self::_logExit('page',333);
					}
				}
			}
		}

		private static function _loadApplication()
		{
			if (self::$_exit == false)
			{
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

						self::_logExit('page',380);
					}

				} else {

					self::_logExit('page',385);
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

		protected static function _logExit($errorType = null,$line=0)
		{
			self::$_exit = true;

			chdir(dirname(__DIR__));

			$errorCode = 404;
			$errorTpl  = null;
			$errorPath = getcwd() . DS . 'views';

			ob_start();

			header('HTTP/1.0 404 Not Found');

			$errorTpl = $errorType . '.tpl';

			if ($errorCode == 404 || $errorCode == 500)
			{
				if (!empty(self::$_config['errors']) && isset(self::$_config['errors']['catch']))
				{
					if (isset(self::$_app['paths']['views']))
					{
						$errorPath = rtrim(self::$_app['paths']['views'], '/');

						if (array_key_exists('*', self::$_config['errors']['catch']) )
							$errorTpl = trim(self::$_config['errors']['catch']['*'], '/');

						if (array_key_exists('all', self::$_config['errors']['catch']) )
							$errorTpl = trim(self::$_config['errors']['catch']['all'], '/');

						if (array_key_exists($errorCode, self::$_config['errors']['catch']) )
							$errorTpl = trim(self::$_config['errors']['catch'][$errorCode], '/');
					}

				} else {

					$errorPath = getcwd() . DS . 'views';
				}

				if (!file_exists($errorPath . '/' . $errorTpl) || $errorType == strtolower('default'))
				{
					$errorPath = getcwd() . DS . 'views';
					$errorTpl  = $errorType . '.tpl';
				}
			}

			if (is_dir($errorPath))
			{
				JXP_Application::setApps(self::$_apps);
				JXP_View::setPath('views', $errorPath);

				JXP_View::render($errorTpl);
			}

			exit;
		}

		private static function _checkApplicationIntegrity($path)
		{
			$return = false;

			if (!is_null($path))
			{
				foreach (array('controllers') as $k)
					$return = (!is_dir($path . DS . $k)) ? false : true;
			}

			return $return;
		}
	}