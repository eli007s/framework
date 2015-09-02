<?php

	class Jinxup
	{
		private static $_app;
		private static $_config;
		private static $_exit       = false;
		private static $_init       = null;
		private static $_apps       = array();
		private static $_loadedApps = array();
		private static $_thisPath   = null;
		private static $_rootDir    = null;
		private static $_routes     = array('controller' => 'Index_Controller', 'action' => 'indexAction');

		public function __construct()
		{
			if (!defined('DS'))
				define('DS', DIRECTORY_SEPARATOR);

			self::$_thisPath = dirname(__DIR__);

			self::_autoload();

			JXP_Error::register(E_ALL);
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

					self::_prepareURI();
					self::_setApplication($app);
					self::_autoload();
					self::_runRoutes();

				} else {

					self::_logExit('application');
				}

				self::stop();
			}
		}

		public static function stop()
		{
			exit;
		}

		public static function installPath()
		{
			return self::$_thisPath;
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
			$ip = JXP_Tracker::getIP();

			if (isset(self::$_config['session']) && ($ip != '127.0.0.1' || $ip != '::1'))
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

		private static function _prepareURI($uri = null)
		{
			self::$_routes['params'] = explode('/', is_null($uri) ? JXP_Routes::getURI() : $uri);

			return self::$_routes['params'];
		}

		private static function _runRoutes()
		{
			$params = array();

			foreach (self::$_routes['params'] as $key => $value)
			{
				if (!is_null($value) || strlen($value) > 0)
					$params[] = $value;
			}

			if (isset($params[0]) && strtolower($params[0]) == 'assets')
			{
				$file = getcwd() . DS . implode($params, '/');

				if (is_file($file))
				{
					header('Content-type: ' . JXP_File::detectMime($file));

					include_once($file);

				} else {

					self::_logExit('file', __LINE__);
				}

			} else {

				$prefix = str_replace($_SERVER['DOCUMENT_ROOT'], '', getcwd());
				$prefix = explode('/', $prefix);

				JXP_Routes::$prefix = trim($prefix[0], DS);

				self::_prepareRoutes();
				self::_loadApplication();
			}
		}

		private static function _prepareRoutes($params = array())
		{
			$_params = array_filter(empty($params) ? self::$_routes['params'] : $params);

			foreach ($_params as $key => $value)
			{
				$value = preg_replace('/\.(php|php5|html|htm|shtml|jhtml)/im', '', $value);

				if (!is_null($value) || strlen($value) > 0)
					$params[] = $value;
			}

			if (!empty($params))
			{
				if ($params[0] == JXP_Routes::$prefix)
					array_shift($params);

				if ($params[0] != '-' && !empty($params))
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
				foreach ($config['directories'] as $key => $value)
				{
					if (is_dir(getcwd() . DS . $value))
						JXP_Application::setDirectory($key, $value);
				}
			}

			if (empty(self::$_apps))
			{
				self::_logExit('welcome', __LINE__);

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
								self::_logExit('application', __LINE__);

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
							self::_logExit('active', __LINE__);
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
						if (self::_checkAppIntegrity($app['path']))
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
								if (preg_match('/(dev)/mi', self::$_config['environment']))
									JXP_Error::showErrors(true);

								unset(self::$_config['environment']);
							}

						} else {

							self::_logExit('integrity', __LINE__);
						}

					} else {

						self::_logExit('page', __LINE__);
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

				$willThrow404 = class_exists($routes['controller']) ? false : true;

				JXP_Application::setWillThrow404($willThrow404);

				if (class_exists('__init__'))
				{
					$init = new __init__();

					if (method_exists($init, 'launch') && is_callable([$init, 'launch']))
						$init->launch();
				}

				if ($willThrow404 === false)
				{
					$c = new $routes['controller']();
					$p = $routes['params'];
					$j = method_exists($c, $routes['action']);
					$i = is_callable([$c, $routes['action']]);
					$n = method_exists($c, '__call');
					$x = is_callable([$c, '__call']);

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
							call_user_func_array([$c, $routes['action']], $p);

					} else {

						self::_logExit('page', __LINE__);
					}

				} else {

					self::_logExit('page', __LINE__);
				}

				if (!is_null($bootstrap) && is_callable([$bootstrap, 'onDestruct']))
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

		protected static function _logExit($errorType = null, $line = 0)
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
				$issetError = isset(self::$_config['errors']) ? 'errors' : 'error';

				if (!empty(self::$_config[$issetError]) && isset(self::$_config[$issetError]['catch']))
				{
					$catch = self::$_config[$issetError]['catch'];

					if (isset(self::$_app['paths']['views']))
					{
						$errorPath = rtrim(self::$_app['paths']['views'], '/');

						foreach ($catch as $key => $value)
						{
							$_c = $catch[$key];

							if (in_array($key, ['*', 'all', $errorCode]))
							{
								if (is_array($_c))
								{
									$errorKeys  = array_keys($_c);
									$breakError = false;

									foreach ($errorKeys as $k)
									{
										switch ($k)
										{
											case 'redirect':
											case 'location':

												$location = isset($_c['redirect']) ? $_c['redirect'] : $_c['location'];

												if (strlen($location) > 0)
												{
													$currentRoutes = self::$_routes;

													self::_prepareURI($location);
													self::_prepareRoutes();

													$locationRoutes = self::$_routes;
													$breakError     = true;

													$c1 = $currentRoutes['controller'];
													$c2 = $locationRoutes['controller'];
													$a1 = $currentRoutes['action'];
													$a2 = $locationRoutes['action'];

													if ($c1 != $c2 && $a1 != $a2)
														header('Location: ' . $location);

													break;
												}

											case 'file':

												$file = $_SERVER['DOCUMENT_ROOT'] . DS . ltrim($_c['file'], '/');

												if (strlen($file) > 0 && file_exists($file))
												{
													require_once $file;

													self::stop();

													break;
												}

											case 'load':

												$readyToLoad = false;

												if (!is_array($_c[$k]))
												{
													if (strlen($_c{$k}) > 0)
													{
														$readyToLoad = true;
														$errorType   = trim($_c[$k], '/');
													}

												} else {

													if (isset($_c[$k]['controller']) && strlen($_c[$k]['controller']) > 0)
													{
														$readyToLoad = true;

														JXP_Routes::setController($_c[$k]['controller'] . '_Controller');

														if (isset($_c[$k]['action']) && strlen($_c[$k]['action']) > 0)
														{
															$readyToLoad = true;

															JXP_Routes::setAction($_c[$k]['action'] . 'Action');

															if (isset($_c[$k]['params']) && count($_c[$k]['params']) > 0)
																JXP_Routes::addParams($_c[$k]['params']);

														} else {

															JXP_Routes::setAction('indexAction');
														}

														// TODO: load controller / action / params
													}
												}

												if ($readyToLoad === true)
												{
													$breakError = true;

													break;
												}

											default:

												$breakError = true;

												break;
										}

										if ($breakError === true)
											break;
									}

								} else {

									$errorTpl = trim($_c, '/');
								}
							}
						}
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

			self::stop();
		}

		private static function _checkAppIntegrity($path)
		{
			$return = false;

			if (!is_null($path))
			{
				foreach (['controllers'] as $k)
					$return = (!is_dir($path . DS . $k)) ? false : true;
			}

			return $return;
		}
	}