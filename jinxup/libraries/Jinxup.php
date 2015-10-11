<?php

	class Jinxup
	{
		private $_route   = null;
		private $_routeTo = array();

		public function __construct()
		{
			$autoloaderPath = __DIR__ . DS . 'Autoloader.php';

			if (!file_exists($autoloaderPath))
			{
				// TODO: load error template
				exit('Missing autoloader');
			}

			require_once($autoloaderPath);

			if (function_exists('__autoload'))
				spl_autoload_register('__autoload');

			spl_autoload_register(array('JXP_Autoloader', 'autoload'));
		}

		public function route($route, $caller = null)
		{
			$route   = $route == '/' ? 'Index_Controller':'';
			$request = $_SERVER['REQUEST_URI'];

			if ($route == '*' || preg_match('#/' . $route . '/#', $request))
				$this->_route = $route;

			return $this;
		}

		public function to($controller = 'index', $action = array(), $arguments = array())
		{
			$controller = strtolower($controller);
			$controller = rtrim($controller, '_controller') . '_Controller';

			if ($controller == '_Controller')
			{
				throw new exception('invalid controller');

			} else {

				if (!empty($action))
				{
					$arguments = $action;
					$action    = 'indexAction';

				} else {

					if (!is_array($action))
					{
						$action = strtolower($action);
						$action = rtrim($action, 'action') . 'Action';
					}
				}

				$this->_routeTo = array(array($$controller, $action), $arguments);
			}

			return $this->go();
		}

		public function go()
		{
			if (!is_null($this->_route) && !empty($this->_routeTo))
			{
				echo 'all good';
			}

			return null;
		}
	}