<?php

	class Jinxup
	{
		private $_route     = array();
		private $_registry  = array();
		private $_namespace = '\\';
		private $_routed    = false;

		public function __construct()
		{
			$autoloaderPath = __DIR__ . DS . 'Autoloader.php';

			if (file_exists($autoloaderPath))
			{
				require_once($autoloaderPath);

				if (function_exists('__autoload'))
					spl_autoload_register('__autoload');

				spl_autoload_register(array('JXP_Autoloader', 'autoload'));
			}

			JXP_Error::register();
			JXP_Config::load(getcwd() . DS . 'config');
			JXP_App::discover();
		}

		public function __toString()
		{
			return 'Jinxup-v2.0b';
		}

		public function __get($name)
		{
			echo $name;


			$return = null;
			$class  = 'JXP_' . $name;

			if (class_exists($class))
			{
				if (!array_key_exists($class, $this->_registry))
					$this->_registry[$class] = new $class;

				$return = $this->_registry[$class];

			} else {

				// TODO: error
			}

			return $return;
		}

		public function init()
		{
			if (!$this->_routed)
			{
				$_request = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'])));

				if (is_null(JXP_App::loaded()))
				{ echo 'app?';
					$config = JXP_Config::get('apps');

					if (isset($config['::default']))
						$this->app($config['::default']);
				}

				$this->route($_SERVER['REQUEST_URI']);

				if (count($_request) == 0)
					$this->to('index', 'index');

				if (count($_request) == 1)
					$this->to($_request[0] == '/' ? 'index' : $_request[0], 'index');

				if (count($_request) == 2)
					$this->to($_request[0], $_request[1]);

				if (count($_request) >= 3)
					$this->to(array_shift($_request), array_shift($_request), $_request);
			}
		}

		/**
		 * @param $app string
		 * @throws exception
		 * @return object
		 */
		public function app($app)
		{
			if (is_dir(getcwd() . DS . 'apps' . DS . $app))
			{
				JXP_App::set($app);

				JXP_Autoloader::register(getcwd() . DS . 'apps' . DS . $app);

			} else {

				throw new exception ('app does not exist');
			}

			return $this;
		}

		/**
		 * @param $route string
		 * @throws exception
		 * @return object
		 */
		public function route($route)
		{
			if (!is_null(JXP_App::loaded()))
				$this->_route = array('string' => $route);
			else
				throw new exception ('no app loaded');

			return $this;
		}

		/**
		 * @param $controller string
		 * @param $action string|array
		 * @param $arguments array
		 * @throws exception
		 * @return object
		 */
		public function to($controller = 'index', $action = 'index', $arguments = array())
		{
			if ($this->_routed === false && isset($this->_route['string']))
			{
				$controller = strtolower($controller);

				if (strpos($this->_route['string'], '*') !== false)
				{
					if (preg_match('#(' . str_replace('*', '.*', $this->_route['string']) . ')#i', $_SERVER['REQUEST_URI']))
						$this->_routed = true;

				} else {

					if ($this->_route['string'] == $_SERVER['REQUEST_URI'])
						$this->_routed = true;
				}

				if ($this->_routed === true)
				{
					$this->_route += $this->_route($controller, $action, $arguments);

					$stack  = array();
					$config = JXP_Config::load(getcwd() . DS . 'apps' . DS . JXP_App::loaded() . DS . 'config');

					if (!empty($config) && isset($config['apps'][JXP_App::loaded()]))
						$config = $config['apps'][JXP_App::loaded()];
					else
						$config = array();

					if (!empty($config))
					{
						if (isset($config['namespace']) && !empty($config['namespace']))
							$this->_namespace = $config['namespace'];

						if (isset($config['view']))
							JXP_Config::setView($config['view']);

						if (isset($config['init']))
						{
							$disabled = false;

							if (isset($config['init']['disabled']) && $config['init']['disabled'] == 1 || (string)$config['init']['disabled'] == 'true')
								$disabled = true;

							if ($disabled == false)
							{
								if (isset($config['init']['start']))
									$this->_init($config['init']['start']);
							}
						}

						if (isset($config['invoked']))
						{
							foreach ($config['invoked'] as $k => $v)
							{
								if (isset($v['controller']))
								{
									if (isset($v['controller']['name']))
									{
										if ($this->_route['controller']['raw'] == $v['controller']['name'] || $this->_route['controller']['translated'] == $v['controller']['name'])
										{
											if (isset($v['controller']['view']))
												JXP_Config::setView($v['controller']['view']);

											$namespace = isset($v['controller']['namespace']) && !empty($v['controller']['namespace']) ? $v['controller']['namespace'] : $this->_namespace;

											if (isset($v['controller']['init']))
											{
												$disabled = false;

												if (isset($v['controller']['init']['disabled']) && ($v['controller']['init']['disabled'] == 1 || (string)$v['controller']['init']['disabled'] == 'true'))
													$disabled = true;

												if ($disabled == false)
												{
													if (isset($v['controller']['init']))
														$stack['controller']['init'] = $v['controller']['init'];

													$stack['controller']['invoke'] = $namespace . '\\' . $this->_route['controller']['translated'];
												}
											}
										}
									}
								}

								if (isset($v['action']))
								{
									if (isset($v['action']['name']))
									{
										if ($this->_route['action']['raw'] == $v['action']['name'] || $this->_route['action']['translated'] == $v['action']['name'])
										{
											if (isset($v['action']['view']))
												JXP_Config::setView($v['action']['view']);

											if (isset($v['action']['init']))
											{
												$disabled = false;

												if (isset($v['action']['init']['disabled']) && ($v['action']['init']['disabled'] == 1 || (string)$v['action']['init']['disabled'] == 'true'))
													$disabled = true;

												if ($disabled == false)
												{
													if (isset($v['action']['init']))
														$stack['action']['init'] = $v['action']['init'];

													$stack['action']['invoke'] = '';
												}
											}
										}
									}
								}

								if (isset($stack['controller']) || isset($stack['action']))
									break;
							}
						}
					}

					if (!isset($stack['controller']))
						$stack['controller']['invoke'] = $this->_namespace . $this->_route['controller']['translated'];

					if (!isset($stack['action']))
						$stack['action']['invoke'] = $this->_route['action']['translated'];

					if (isset($stack['controller']['init']['start']))
						$this->_init($stack['controller']['init']['start']);

					$c = $stack['controller']['invoke'];

					if (class_exists($c))
					{
						$c = new $c();
						$m = $stack['action']['invoke'];
						$a = $this->_route['params'];

						if (isset($stack['action']['init']['start']))
							$this->_init($stack['action']['init']['start']);

						$j = method_exists($c, $m);
						$i = is_callable(array($c, $m));
						$n = method_exists($c, '__call');
						$x = is_callable(array($c, '__call'));

						if (($j && $i) || ($n && $x))
						{
							if (count($a) == 3)
								$c->$m($a[0], $a[1], $a[2]);
							else if (count($a) == 2)
								$c->$m($a[0], $a[1]);
							else if (count($a) == 1)
								$c->$m($a[0]);
							else if (count($a) == 0)
								$c->$m();
							else
								call_user_func_array(array($c, $m), $a);
						}

						if (isset($stack['action']['init']['end']))
							$this->_init($stack['action']['init']['end']);

					} else {

						echo 'error!';
					}

					if (isset($stack['controller']['init']['end']))
						$this->_init($stack['controller']['init']['end']);

					if (!empty($config))
					{
						if (isset($config['init']))
						{
							$disabled = false;

							if (isset($config['init']['disabled']) && $config['init']['disabled'] == 1 || (string)$config['init']['disabled'] == 'true')
								$disabled = true;

							if ($disabled == false)
							{
								if (isset($config['init']['end']))
									$this->_init($config['init']['end']);
							}
						}
					}
				}
			}

			return $this;
		}

		public function config($config)
		{
			JXP_Config::load($config);

			return $this;
		}

		private function _init($config)
		{
			if (!empty($config))
			{
				$disabled = false;

				if (isset($config['disabled']) && ($config['disabled'] == 1 || (string)$config['disabled'] == 'true'))
					$disabled = true;

				if ($disabled == false)
				{
					foreach ($config as $k => $v)
					{
						if ($k == 'redirect' && !empty($v))
						{
							header('Location: ' . $v);

							exit;
						}

						if ($k == 'route' || $k == 'call')
						{
							$namespace = $this->_namespace;

							if ($k == 'route' && !empty($v))
							{
								$route = $this->_route($v);

								$c = $route['controller']['translated'];
								$m = $route['action']['translated'];
								$a = $route['params'];
							}

							if ($k == 'call')
							{
								$namespace = isset($v['namespace']) ? $v['namespace'] : $this->_namespace;

								$c = isset($v['class']) && !empty($v['class']) ? $v['call'] : null;
								$m = isset($v['method']) && !empty($v['method']) ? $v['method'] : null;
								$a = isset($v['arguments']) && !empty($v['arguments']) ? $v['arguments'] : array();
							}

							if (!is_null($c))
							{
								$c = $namespace . $c;

								if (class_exists($c))
								{
									$c = new $c();

									$j = method_exists($c, $m);
									$i = is_callable(array($c, $m));
									$n = method_exists($c, '__call');
									$x = is_callable(array($c, '__call'));

									if (($j && $i) || ($n && $x))
									{
										if (count($a) == 3)
											$c->$m($a[0], $a[1], $a[2]);
										else if (count($a) == 2)
											$c->$m($a[0], $a[1]);
										else if (count($a) == 1)
											$c->$m($a[0]);
										else if (count($a) == 0)
											$c->$m();
										else
											call_user_func_array(array($c, $m), $a);
									}
								}
							}
						}

						if ($k == 'cmd')
							$this->_cmd($v);
					}
				}
			}
		}

		private function _cmd($cmd)
		{
			if (!is_array($cmd))
				$cmd = array($cmd);

			foreach ($cmd as $k => $v)
				eval($v);
		}

		private function _route($controller, $action = null, $arguments = array())
		{
			if (is_array($action))
			{
				$arguments = $action;
				$action    = 'index';
			}

			if (is_null($action) && empty($arguments))
				$route = '/' . ltrim($controller, '/');
			else
				$route    = '/' . $controller . '/' . $action . '/' . implode('/', $arguments);

			$_route   = explode('/', $route);
			$params   = array_values(array_filter($_route));
			$_project = str_replace($_SERVER['DOCUMENT_ROOT'], '', getcwd());

			if ($params[0] == $_project)
				array_shift($params);

			if (!empty($params) && JXP_App::exists($params[0]))
			{
				$config = JXP_Config::settings();

				if (isset($config['detect-app-from-url']) && (string)$config['detect-app-from-url'] == true)
				{
					$this->app($params[0]);

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

					$controller = array_shift($params);

					$_r['controller']['raw']        = $controller;
					$_r['controller']['translated'] = $prefix . str_replace('-', '_', $controller) . '_Controller';

				} else {

					array_shift($params);

					$_r['controller']['raw']        = 'index';
					$_r['controller']['translated'] = 'Index_Controller';
				}

			} else {

				$_r['controller'] = array('raw' => 'index', 'translated' => 'Index_Controller');
			}

			if (!empty($params))
			{
				if ($params[0] != '-')
				{
					$prefix = is_numeric($params[0][0]) ? 'n' : null;
					$prefix = $params[0][0] == '_' ? 'u' : $prefix;
					$prefix = $params[0][0] == '-' ? 'd' : $prefix;

					$action = array_shift($params);

					$_r['action']['raw']        = $action;
					$_r['action']['translated'] = $prefix . str_replace('-', '_', $action) . 'Action';

				} else {

					array_shift($params);

					$_r['action']['raw']        = 'index';
					$_r['action']['translated'] = 'indexAction';
				}

			} else {

				$_r['action'] = array('raw' => 'index', 'translated' => 'indexAction');
			}

			$_r['params'] = $params;

			return $_r;
		}
	}