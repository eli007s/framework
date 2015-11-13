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
				$_request = array_values(array_filter(explode('/', $_SERVER['REQUEST_URI'])));

				if (is_null($this->app->loaded()))
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
				$this->app->set($app);

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
			if (!is_null($this->app->loaded()))
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

					$config  = $this->config->app($this->app->loaded());
					$invoked = array();

					foreach ($config as $k => $v)
					{
						$init = array();

						if ($k == '::namespace')
							$this->config->setNamespace($v);

						if ($k == 'view')
						{
							if (isset($v['::default']) && isset($v[$v['::default']]))
								$this->config->setView($v['::default'], $v[$v['::default']]);
						}

						if ($k == 'init')
							$this->_init('start', $v);

						if ($k == 'controller')
						{
							if (isset($v['view']))
							{
								if (isset($v['view']['::use']) && isset($v['view'][$v['view']['::use']]))
								{
									$view = array_merge_recursive($this->config->getView(), $v['view'][$v['view']['::use']]);
								}
									$this->config->setView($v['view']['::use'], $view);
							}

							if (isset($v['init']))
								$this->_init('start', $v['init']);

							if (isset($v[$this->_route['controller']['raw']]))
							{
								if (isset($v[$this->_route['controller']['raw']]['init']))
									$init = $v[$this->_route['controller']['raw']]['init'];

								$invoked['controller'] = $this->_callController($this->_route, $init);
							}

							if (isset($v['init']))
								$this->_init('end', $v['init']);
						}

						if (!isset($invoked['controller']))
							$invoked['controller'] = $this->_callController($this->_route);

						if ($k == 'action')
						{
							if (isset($v['view']))
							{
								if (isset($v['view']['::use']) && isset($v['view'][$v['view']['::use']]))
								{
									$view = array_merge_recursive($this->config->getView(), $v['view'][$v['view']['::use']]);
								}
								$this->config->setView($v['view']['::use'], $view);
							}

							if (isset($v['init']))
								$this->_init('start', $v['init']);

							if (isset($v[$this->_route['action']['raw']]))
							{
								$invoked['action'] = true;

								if (isset($v[$this->_route['action']['raw']]['init']))
									$init = $v[$this->_route['action']['raw']]['init'];

								if (isset($invoked['controller']))
								{
									$invoked['action'] = true;

									$this->_callAction($invoked['controller'], $this->_route, $init);
								}
							}

							if (isset($v['init']))
								$this->_init('end', $v['init']);
						}

						if (!isset($invoked['action']))
							$this->_callAction($invoked['controller'], $this->_route);

						if ($k == 'init')
							$this->_init('end', $v);
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

		private function _init($cursor, $config)
		{
			foreach ($config as $k => $v)
			{
				if ($k == 'cmd')
					$this->_cmd($v);

				if ($k == $cursor)
				{
					if (isset($v['::namespace']))
						$this->config->setNamespace($v['::namespace']);

					if (isset($v['cmd']))
						$this->_cmd($v['cmd']);

					if (isset($v['call']))
					{
						if (!isset($v['call']['disabled']) || ($v['call']['disabled'] == 0 || (string)$v['call']['disabled'] == 'false'))
						{
							if (isset($v['call']['::namespace']))
								$this->config->setNamespace($v['::namespace']);

							$c = isset($v['call']['controller']) && !empty($v['call']['controller']) ? $v['call']['controller'] : 'index';
							$a = isset($v['call']['action']) && !empty($v['call']['action']) ? $v['call']['action'] : 'index';
							$p = isset($v['call']['params']) ? $v['call']['params'] : array();

							$route = $this->_route($c, $a, $p);

							$this->_callAction($this->_callController($this->_route($c, $a, $p)), $route);
						}
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

		private function _callController($route, $init = array())
		{
			$c = $route['controller']['translated'];
			$c = $this->config->getNamespace() . $c;

			if (class_exists($c))
			{
				if (!empty($init))
					$this->_init('start', $init);

				$c = new $c();

				if (!empty($init))
					$this->_init('end', $init);
			}

			return $c;
		}

		private function _callAction($c, $route, $init = array())
		{
			$p = $route['params'];
			$j = method_exists($c, $route['action']['translated']);
			$i = is_callable(array($c, $route['action']['translated']));
			$n = method_exists($c, '__call');
			$x = is_callable(array($c, '__call'));

			if (($j && $i) || ($n && $x))
			{
				if (!empty($init))
					$this->_init('start', $init);

				if (count($p) == 3)
					$c->{$route['action']['translated']}($p[0], $p[1], $p[2]);
				else if (count($p) == 2)
					$c->{$route['action']['translated']}($p[0], $p[1]);
				else if (count($p) == 1)
					$c->{$route['action']['translated']}($p[0]);
				else if (count($p) == 0)
					$c->{$route['action']['translated']}();
				else
					call_user_func_array(array($c, $route['action']['translated']), $p);

				if (!empty($init))
					$this->_init('end', $init);
			}
		}

		private function _route($controller, $action, $arguments)
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

			return $_r;
		}
	}