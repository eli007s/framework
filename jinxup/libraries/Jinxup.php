<?php

	class Jinxup
	{
		private $_app      = null;
		private $_route    = array();
		private $_registry = array();
		private $_routed   = false;

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
		}

		public function __toString()
		{
			return 'Jinxup-v1.2b';
		}

		public function __get($name)
		{
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
				// If the app hasn't been manually routed we continue with inferred app routing
				$_request = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'])));

				if (is_null($this->_app))
				{
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
				$this->_app = $app;

				JXP_Autoloader::register(getcwd() . DS . 'apps' . DS . $app);
				JXP_Config::load(getcwd() . DS . 'apps' . DS . $app . DS . 'config');

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
			if (!is_null($this->_app))
				$this->_route = array('string' => $route);
			else
				throw new exception ('no app loaded');

			return $this;
		}

		/**
		 * @param $controller string
		 * @param $action string|array
		 * @param $arguments array
		 * @return object
		 */
		public function to($controller = 'index', $action = 'index', $arguments = array())
		{
			if ($this->_routed === false && isset($this->_route['string']))
			{
				$controller = strtolower($controller);
				$continue   = false;

				if (strpos($this->_route['string'], '*') !== false)
				{
					if (preg_match('#(' . str_replace('*', '.*', $this->_route['string']) . ')#i', $_SERVER['REQUEST_URI']))
						$continue = true;

				} else {

					if ($this->_route['string'] == $_SERVER['REQUEST_URI'])
						$continue = true;
				}

				$this->_routed = $continue;

				if ($continue === true)
				{
					if (is_array($action))
					{
						$arguments = $action;
						$action    = 'index';
					}

					$route    = '/' . $controller . '/' . $action . '/' . implode('/', $arguments);
					$_route   = explode('/', $route);
					$params   = array_values(array_filter($_route));
					$_project = str_replace($_SERVER['DOCUMENT_ROOT'], '', getcwd());

					if ($params[0] == $_project)
						array_shift($params);

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

					$this->_route += $_r;

					$c = $this->_route['controller']['translated'];
					$n = '\\';

					$config = array_change_key_case(JXP_Config::get('apps'), CASE_LOWER);

					if (isset($config['::namespace']))
					{
						$n .= $config['::::namespace'] . '\\';

					} else {

						$app = strtolower($this->_app);

						if (isset($config[$app]['::namespace']))
						{
							$n .= $config[$app]['::namespace'] . '\\';

						} else {

							$controllers = array_change_key_case($config[$app]['controller'], CASE_LOWER);

							if (isset($controllers['::namespace']))
							{
								$n .= $controllers['::namespace'] . '\\';

							} else {

								if (isset($controllers[strtolower($this->_route['controller']['raw'])]['::namespace']))
									$n .= $controllers[strtolower($this->_route['controller']['raw'])]['::namespace'] . '\\';
							}
						}
					}

					$c = $n . $c;

					if (class_exists($c))
					{
						$c = new $c();
						$p = $this->_route['params'];
						$j = method_exists($c, $this->_route['action']['translated']);
						$i = is_callable(array($c, $this->_route['action']['translated']));
						$n = method_exists($c, '__call');
						$x = is_callable(array($c, '__call'));

						if (($j && $i) || ($n && $x))
						{
							if (count($p) == 3)
								$c->{$this->_route['action']['translated']}($p[0], $p[1], $p[2]);
							else if (count($p) == 2)
								$c->{$this->_route['action']['translated']}($p[0], $p[1]);
							else if (count($p) == 1)
								$c->{$this->_route['action']['translated']}($p[0]);
							else if (count($p) == 0)
								$c->{$this->_route['action']['translated']}();
							else
								call_user_func_array(array($c, $this->_route['action']['translated']), $p);

						} else {

							throw new exception('404');
						}

					} else {

						throw new exception('404');
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

		public function errors()
		{
			return $this->_errors;
		}

		public function timers()
		{
			return array();
		}
	}