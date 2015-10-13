<?php

	class Jinxup
	{
		private $_routes  = array();
		private $_invoked = array();

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
		}

		/*
		 * @param $route string
		 * /:controller/:action
		 */
		public function route($route)
		{
			if (!in_array(__FUNCTION__, $this->_invoked))
				$this->_invoked[] = __FUNCTION__;

			$this->_routes[] = $route;

			return $this;
		}

		/*
		 * @param $controller string
		 * @param $action string|array
		 * @param @arguments array
		 */
		public function to($controller = 'index', $action = null, $arguments = array())
		{
			if (!in_array('route', $this->_invoked))
			{
				throw new exception('please specify a route first');

			} else {

				if (!in_array(__FUNCTION__, $this->_invoked))
					$this->_invoked[] = __FUNCTION__;

				$controller = strtolower($controller);

				if (is_array($action))
				{
					$arguments = $action;
					$action    = 'index';

				} else {

					$action = strtolower($action);
				}

				$index  = count($this->_routes) > 0 ? count($this->_routes) - 1 : 0;
				$_route = $this->_route('/' . $controller . '/' . $action . '/' . implode('/', $arguments));

				$this->_routes[$index] = array('string' => $this->_routes[$index]) + $_route;
			}

			return $this;
		}

		public function go()
		{
			if (in_array('route', $this->_invoked) && in_array('to', $this->_invoked))
			{
				$request = preg_replace('/\.(php|php5|html|htm|shtml|jhtml)$/im', '', $_SERVER['REQUEST_URI']);

				if (!empty($this->_routes))
				{
					foreach ($this->_routes as $route)
					{
						if (preg_match('$' . str_replace(array('*'), array('(.*)'), $route['string']) . '$', $request))
						{
							call_user_func_array(array($route['controller']['translated'], $route['action']['translated']), $route['params']);

							break;
						}
					}
				}

				// empty the routes array in case its invoked twice we don't want to run the routes twice
				$this->_routes = array();
			}

			return null;
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
					if ($params[0] == '*')
					{
						$_r['controller']['raw']        = '*';
						$_r['controller']['translated'] = '*';

						array_shift($params);

					} else {

						$prefix = is_numeric($params[0][0]) ? 'n' : null;
						$prefix = $params[0][0] == '_' ? 'u' : $prefix;
						$prefix = $params[0][0] == '-' ? 'd' : $prefix;

						$controller = array_shift($params);

						$_r['controller']['raw']        = $controller;
						$_r['controller']['translated'] = $prefix . str_replace('-', '_', $controller) . '_Controller';
					}

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
					if ($params[0] == '*')
					{
						$_r['action']['raw']        = '*';
						$_r['action']['translated'] = '*';

					} else {

						$prefix = is_numeric($params[0][0]) ? 'n' : null;
						$prefix = $params[0][0] == '_' ? 'u' : $prefix;
						$prefix = $params[0][0] == '-' ? 'd' : $prefix;

						$action = array_shift($params);

						$_r['action']['raw']        = $action;
						$_r['action']['translated'] = $prefix . str_replace('-', '_', $action) . 'Action';
					}

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