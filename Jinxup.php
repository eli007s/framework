<?php

	class Jinxup
	{
		private static $_app;
		private static $_config;
		private static $_init   = null;
		private static $_exit   = false;
		private static $_routes = array('controller' => 'Index_Controller', 'action' => 'indexAction');

		public function init()
		{
			if (is_null(self::$_init))
			{
				self::_autoloadJinxup();
				self::_parseFrameworkConfig();
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
			self::$_config = self::_translateConfig(self::_loadConfig(dirname(__DIR__) . DS . 'config'));
		}

		private static function _sessions()
		{
			if (isset(self::$_config['session']))
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
			$app['ssl'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 1 : 0;

			$app      = array();
			$segments = explode('.', $_SERVER['HTTP_HOST']);

			if (count($segments) == 2)
			{
				$app['path'] = $_SERVER['DOCUMENT_ROOT'] . '/front';
				self::$_app  = $app;

				chdir($app['path']);

			} else {

				switch ($segments[count($segments) - 3])
				{
					case 'welcome':
					case 'cdn':
					case 'services':
					case 'login':
					case 'logout':
					case 'manage':
					case 'www':

						$activeApp = 'front';

						if ($segments[count($segments) - 3] != 'www')
						{
							if ($segments[0] == 'services')
							{
								if (isset(self::$_routes['params'][0]))
									$activeApp = array_shift(self::$_routes['params']);

								self::_prepareRoutes(self::$_routes['params']);

							} else {

								$activeApp = $segments[0];
							}
						}

						$app['path'] = $_SERVER['DOCUMENT_ROOT'] . '/' . $activeApp;
						self::$_app  = $app;

						chdir($app['path']);

						break;

					default:

						if ($segments[count($segments) - 2] != 'jinxup')
						{
							$bind['cname'] = $_SERVER['HTTP_HOST'];

							$cnameApp = JXP_DB::jinxup('
								SELECT
									cname_link_url,
									cname_url,
									account_id
								FROM
									cname_links
								WHERE
									cname_url = :cname
							', $bind);

							if (!empty($cnameApp))
							{
								$app['cname']   = $cnameApp[0]['cname_url'];
								$app['account'] = array('id' => $cnameApp[0]['cname_url']);

							} else {

								self::_logExit('cname');
							}

						} else {

							$segParts = explode('-', $segments[0]);

							if (count($segParts) > 1)
							{
								$app['app']['name'] = $segParts[0];
								$accAlias           = $segParts[1];

							} else {

								$accAlias = $segParts[0];
							}

							if (is_numeric($accAlias))
							{
								$bind  = array('alias' => $accAlias);
								$alias = JXP_DB::jinxup('SELECT `account_id`, `alias` FROM accounts WHERE account_id = :alias', $bind);

							} else {

								$bind  = array('alias' => $accAlias);
								$alias = JXP_DB::jinxup('SELECT `account_id`, `alias` FROM accounts WHERE alias = :alias', $bind);
							}

							if (!empty($alias))
								$app['account'] = array('id' => $alias[0]['account_id'], 'alias' => $alias[0]['alias']);
							else
								self::_logExit('page');
						}

						if (self::$_exit == false)
						{
							$bind['acc'] = $app['account']['id'];

							$account = JXP_DB::jinxup('
								SELECT
									active_application,
									node_id,
									status
								FROM
									accounts
								WHERE
									account_id = :acc
							', $bind);

							if (!empty($account))
							{
								$app['account']['status'] = $account[0]['status'];
								$app['node']['id'] = $account[0]['node_id'];

								if (isset($app['app']['name']))
								{
									$bind['app'] = $app['app']['name'];
									$bind['acc'] = $app['account']['id'];

									$appCheck = JXP_DB::jinxup('
										SELECT
											app_id
										FROM
											applications
										WHERE
											app_friendly = :app
										AND
											account_id = :acc
									', $bind);

									if (!empty($appCheck))
										$app['app']['id'] = $appCheck[0]['app_id'];

								} else {

									if ($account[0]['active_application'] > 0)
									{
										$bind['app'] = $account[0]['active_application'];

										$appCheck = JXP_DB::jinxup('
											SELECT
												account_id,
												app_friendly
											FROM
												applications
											WHERE
												app_id = :app
										', $bind);

										if (!empty($appCheck))
										{
											if ($app['account']['id'] == $appCheck[0]['account_id'])
											{
												$app['app']['id'] = $account[0]['active_application'];
												$app['app']['name'] = $appCheck[0]['app_friendly'];

											} else {

												self::_logExit('application');
											}

										} else {

											self::_logExit('application');
										}

									} else {

										if (!empty(self::$_routes['params'][0]))
										{
											$bind['app'] = self::$_routes['params'][0];
											$bind['acc'] = $app['account']['id'];

											$appCheck = JXP_DB::jinxup('
												SELECT
													app_id
												FROM
													applications
												WHERE
													app_friendly = :app
												AND
													account_id = :acc
											', $bind);

											if (!empty($appCheck))
											{
												$app['app']['id']   = $appCheck[0]['app_id'];
												$app['app']['name'] = self::$_routes['params'][0];

												array_shift(self::$_routes['params']);

												self::_prepareRoutes(self::$_routes['params']);

											} else {

												self::_logExit('application');
											}

										} else {

											self::_logExit('active-app');
										}
									}
								}

								if (self::$_exit == false)
								{
									$app['path'] = $_SERVER['DOCUMENT_ROOT'] . '/jinxup/storage/nodes/' . $app['node']['id'] . '/' . $app['account']['id'] . '/' . 'applications/' . $app['app']['id'];
									self::$_app  = $app;

									chdir($app['path']);
								}

							} else {

								self::_logExit('account');
							}
						}

						break;
				}
			}
		}

		private static function _translateConfig($config)
		{
			if (!empty($config))
			{
				foreach ($config as $a => $b)
				{
					if ($a != 'settings')
					{
						if (is_array($b))
						{
							foreach ($b as $c => $d)
							{
								if (isset($d['use']))
								{
									if (isset(self::$_config[$a][$c]))
									{
										if (isset($config['settings'][$a][$d['use']]))
											self::$_config[$a][$c] = $config['settings'][$a][$d['use']];

									} else {

										self::$_config[$a][$c] = $config[$a][$c];
									}

								} else {

									if (isset(self::$_config[$a]))
									{
										self::$_config[$a] = array_merge(
											self::$_config[$a],
											$config[$a]
										);

									} else {

										self::$_config[$a] = $config[$a];
									}
								}
							}

						} else {

							if ($b == 'use')
							{
								if (isset($config['settings'][$a][$config[$a][$b]]))
								{
									if (isset(self::$_config[$a][$config[$a][$b]]))
									{
										self::$_config[$a][$config[$a][$b]] = array_merge(
											self::$_config[$a][$config[$a][$b]],
											$config['settings'][$a][$config[$a][$b]]
										);

									} else {

										self::$_config[$a][$config[$a][$b]] = $config['settings'][$a][$config[$a][$b]];
									}
								}

							} else {

								if (isset(self::$_config[$a]))
								{
									self::$_config[$a] = array_merge(
										self::$_config[$a],
										$config[$a]
									);

								} else {

									self::$_config[$a] = $config[$a];
								}
							}
						}
					}
				}
			}

			return self::$_config;
		}

		private static function _loadConfig($path)
		{
			$return = null;

			foreach (JXP_Directory::scan($path, '.tell') as $config)
			{
				$contents   = file_get_contents($config);
				$configTell = preg_replace('/([?!http|ftp]\/\/|\/\*|#)(.*)(\*\/|\n|\r)/', '', $contents);
				$return     = json_decode($configTell, true);
			}

			return $return;
		}

		private static function _loadApplication()
		{
			if (self::$_exit == false)
			{
				JXP_Autoloader::peekIn(getcwd());

				self::$_app['paths'] = JXP_Directory::scan(getcwd());

				if (isset(self::$_app['paths']['config']))
					self::$_config = self::_translateConfig(self::_loadConfig(self::$_app['paths']['config']));

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

		private static function _logExit($type = null, $param1 = null)
		{
			self::$_exit = true;

			$path = null;

			chdir(dirname(__DIR__));

			$path = getcwd() . DS . 'views';

			$config = self::$_config;

			if (isset($config['error']) && !empty($config['error']))
			{

			}

			JXP_View::setTplPath($path);

			ob_start();

			switch ($type)
			{
				case 'app':

					echo 'app ' . $param1['name'] . ' not found';

					break;

				case 'app-status':

					if (isset(self::$_routes['params'][0]) && self::$_routes['params'][0] !== 'assets')
					{
						JXP_View::setTplPath(getcwd() . DS . 'views');

						ob_clean();

						header('HTTP/1.0 404 Not Found');

						JXP_View::render('app-status.tpl');
					}

					break;

				case 'app-list':

					echo '<pre>', print_r($param1, true), '</pre>';

					break;

				case 'page':

					if (isset(self::$_routes['params'][0]) && self::$_routes['params'][0] !== 'assets')
					{
						ob_clean();

						JXP_View::setTplPath(getcwd() . DS . 'views');

						header('HTTP/1.0 404 Not Found');

						JXP_View::render('page.tpl');
					}

					break;

				case 'cname':

					if (isset(self::$_routes['params'][0]) && self::$_routes['params'][0] !== 'assets')
					{
						JXP_View::setTplPath(getcwd() . DS . 'views');

						ob_clean();

						header('HTTP/1.0 404 Not Found');

						JXP_View::render('cname.tpl');
					}

					break;

				case 'account':

					if (isset(self::$_routes['params'][0]) && self::$_routes['params'][0] !== 'assets')
					{
						JXP_View::setTplPath(getcwd() . DS . 'views');

						ob_clean();

						header('HTTP/1.0 404 Not Found');

						JXP_View::render('account.tpl');
					}

					break;

				case 'account-status':

					if (isset(self::$_routes['params'][0]) && self::$_routes['params'][0] !== 'assets')
					{
						JXP_View::setTplPath(getcwd() . DS . 'views');

						//ob_end_clean();

						header('HTTP/1.0 404 Not Found');

						JXP_View::render('account-status.tpl');
					}

					break;

				case 'application':

					if (isset(self::$_routes['params'][0]) && self::$_routes['params'][0] !== 'assets')
					{
						JXP_View::setTplPath(getcwd() . DS . 'views');

						//ob_end_clean();

						header('HTTP/1.0 404 Not Found');

						JXP_View::render('application.tpl');
					}

					break;

				case 'active-app':

					if (isset(self::$_routes['params'][0]) && self::$_routes['params'][0] !== 'assets')
					{
						JXP_View::setTplPath(getcwd() . DS . 'views');

						//ob_end_clean();

						header('HTTP/1.0 404 Not Found');

						JXP_View::render('active-app.tpl');
					}

					break;

				case 'file':

					echo 'file not found';

					break;

				default:

					echo $type;

					break;
			}
		}

		public static function config()
		{
			return self::$_config;
		}

		public static function getApps()
		{
			$apps = array();

			if (!isset(self::$_app['name_friendly']))
			{
				$key  = self::$_app['account_id'] . '-apps';
				$apps = JXP_Cache_Redis::getKey($key);

				if (!$apps)
				{
					$bind['account_id'] = $_SESSION['user']['account_id'] ?: self::$_app['account_id'];

					$query = 'SELECT
							a1.node_id,
							a1.account_id,
							a1.`status`/*,
							a1.custom_home,
							a1.custom_home_app*/,
							a2.app_id,
							a2.app_friendly,
							a2.app_name
						FROM
							accounts a1
								JOIN
							applications a2 ON a1.account_id = a2.account_id
						WHERE
							a1.account_id = :account_id
					';

					$db = self::$_config['framework']['database']['jinxup'];

					JXP_DB::fuel('jinxup', $db['host'], $db['name'], $db['user'], $db['pass']);

					$apps = JXP_DB::jinxup($query, $bind);
				}
			}

			return $apps;
		}

		public static function getApp()
		{
			return self::$_app;
		}

		public static function getAppByName($friendly)
		{
			$key = self::$_app['account_id'] . '-app';
			$app = JXP_Cache_Redis::getKey($key);

			if (!$app)
			{
				$bind['friendly'] = $friendly;
				$bind['account_id'] = $_SESSION['user']['account']['id'] ?: self::$_app['account_id'];

				$query = 'SELECT
						a1.node_id,
						a1.account_id,
						a1.`status`/*,
						a1.custom_home,
						a1.custom_home_app*/,
						a2.app_id,
						a2.app_friendly,
						a2.app_name
					FROM
						accounts a1
							JOIN
						applications a2 ON a1.account_id = a2.account_id
					WHERE
						a1.account_id = :account_id
					AND
						a2.app_friendly = :friendly
				';

				$db = self::$_config['framework']['database']['jinxup'];

				JXP_DB::fuel('jinxup', $db['host'], $db['name'], $db['user'], $db['pass']);

				$app = JXP_DB::jinxup($query, $bind);
			}

			return $app;
		}

		public static function getActiveApp()
		{
			$key = self::$_app['account_id'] . '-app';
			$app = JXP_Cache_Redis::getKey($key);

			if (!$app)
			{
				$bind['account_id'] = $_SESSION['user']['account_id'] ?: self::$_app['account_id'];

				$query = 'SELECT
						a1.node_id,
						a1.account_id,
						a1.`status`/*,
						a1.custom_home,
						a1.custom_home_app*/,
						a2.app_id,
						a2.app_friendly,
						a2.app_name
					FROM
						accounts a1
							JOIN
						applications a2 ON a1.account_id = a2.account_id
					WHERE
						a1.account_id = :account_id
					AND
						a2.app_active = 1
				';

				$db = self::$_config['framework']['database']['jinxup'];

				JXP_DB::fuel('jinxup', $db['host'], $db['name'], $db['user'], $db['pass']);

				$app = JXP_DB::jinxup($query, $bind);
			}

			return $app;
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
	}