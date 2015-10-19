<?php

	class Jinxup
	{
		private $_app      = null;
		private $_route    = array();
		private $_invoked  = array();
		private $_request  = null;
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

			$this->_request = preg_replace('/\.(.*)$/im', '', $_SERVER['REQUEST_URI']);
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

		public function __destruct()
		{
			if (!$this->_routed)
			{
				// If the app hasn't been manually routed we continue with inferred app routing
				$_request = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'])));

				// TODO: get app from config
				if (is_null($this->_app))
					$this->app('v3');

				$this->route($_SERVER['REQUEST_URI']);

				if (count($_request) == 0)
					$this->to('index', 'index');

				if (count($_request) == 1)
					$this->to($_request[0], 'index');

				if (count($_request) == 2)
					$this->to($_request[0], $_request[1]);

				if (count($_request) >= 3)
					$this->to(array_shift($_request), array_shift($_request), $_request);
			}
		}

		/*
		 * @param $app the app that should handle the routing
		 */
		public function app($app)
		{
			if (!in_array(__FUNCTION__, $this->_invoked))
				$this->_invoked[] = __FUNCTION__;

			$this->_app = $app;

			if (!in_array('simulate', $this->_invoked))
			{
				JXP_Autoloader::$path = getcwd() . DS . 'apps' . DS . $app;

			} else {

				throw new exception('cannot run app while simulation in progress');
			}

			return $this;
		}

		/*
		 * @param string $app the app that will be simulated
		 */
		public function simulate($app)
		{
			if (!in_array(__FUNCTION__, $this->_invoked))
				$this->_invoked[] = __FUNCTION__;

			if (!in_array('app', $this->_invoked))
			{
				// TODO: simulate

			} else {

				throw new exception('cannot run simulation while an app has already been specified');
			}
		}

		/*
		 * @param $route string
		 * /:controller/:action
		 */
		public function route($route)
		{
			if (in_array('app', $this->_invoked))
			{
				if (!in_array(__FUNCTION__, $this->_invoked))
					$this->_invoked[] = __FUNCTION__;

				$this->_route = array('string' => $route);

			} else {

				throw new exception('please specify an app first');
			}

			return $this;
		}

		/*
		 * @param $controller string
		 * @param $action string|array
		 * @param @arguments array
		 */
		public function to($controller = 'index', $action = null, $arguments = array(), $test = null)
		{
			if (in_array('route', $this->_invoked))
			{
				if (!in_array(__FUNCTION__, $this->_invoked))
					$this->_invoked[] = __FUNCTION__;

				$controller = strtolower($controller);
				$continue   = false;

				if (strpos($this->_route['string'], '*') !== false)
				{
					if (preg_match('$(' . str_replace('*', '.*', $this->_route['string']) . ')$', $this->_request))
						$continue = true;

				} else {

					if ($this->_route['string'] == $this->_request)
						$continue = true;
				}

				if ($continue === true)
				{
					$this->_routed = $continue;

					if (is_array($action))
					{
						$arguments = $action;
						$action    = 'index';

					} else {

						$action = strtolower($action);
					}

					$route = '/' . $controller . '/' . $action . '/' . implode('/', $arguments);

					$_r       = array();
					$_route   = explode('/', $route);
					$_params  = array_values(array_filter($_route));
					$_project = trim(str_replace($_SERVER['DOCUMENT_ROOT'], '', getcwd()), '/');

					if (count($_params) > 0 && $_params[0] == $_project)
						array_shift($_params);

					$params = array();

					foreach ($_params as $key => $value)
					{
						if (!is_null($value) && strlen($value) > 0)
							$params[] = $value;
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

					$this->_route += $_r;

					$c = $this->_route['controller']['translated'];
					$n = '';
					$c = $n . '\\' . $c;

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

							JXP_Error::event('Action has not been defined in the requested controller', __FILE__, __LINE__);
						}

					} else {

						JXP_Error::event('Controller has not been defined in the app', __FILE__, __LINE__);
					}

				} else {

					$this->_route = array();
				}

			} else {

				throw new exception('please specify a route first');
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

		private function _route($route)
		{
			$_r       = array();
			$_route   = explode('/', $route);
			$_params  = array_values(array_filter($_route));
			$_project = trim(str_replace($_SERVER['DOCUMENT_ROOT'], '', getcwd()), '/');

			if (count($_params) > 0 && $_params[0] == $_project)
				array_shift($_params);

			$params = array();

			foreach ($_params as $key => $value)
			{
				if (!is_null($value) && strlen($value) > 0)
					$params[] = $value;
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
				}

			} else {

				$_r['action'] = array('raw' => 'index', 'translated' => 'indexAction');
			}

			$_r['params'] = $params;

			return $_r;
		}
	}