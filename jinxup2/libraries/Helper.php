<?php

	class JXP_Helper
	{
		private static $_helper = array();

		public static function using($name)
		{
			$class = $name . '_Helper';

			if (class_exists($class))
			{
				if (!isset(self::$_helper[$class]))
					self::$_helper[$name] = new $class();

			} else {

				$bt = debug_backtrace();

				$helper = array(
					'name'   => $name,
					'caller' => $bt[1]['class'],
					'line'   => $bt[0]['line']
				);

				$errorPath = Jinxup::installPath() . DS . 'views';
				$errorTpl  = 'helper_error.tpl';

				JXP_View::setPath('views', $errorPath);
				JXP_View::set('helper', $helper);
				JXP_View::render($errorTpl);
			}

			return self::$_helper[$name];
		}

		public static function __callStatic($name, $params)
		{
			$bt     = debug_backtrace();
			$class  = $name . '_Helper';
			$return = null;

			if (class_exists($class))
			{
				if (!isset(self::$_helper['obj'][$class]))
					$return = !empty($params) ? call_user_func_array(array($class, '__construct'), $params) : new $class();

			} else {

				$helper = array(
					'name'   => $name,
					'caller' => $bt[1]['class'],
					'line'   => $bt[0]['line']
				);

				$errorPath = Jinxup::installPath() . DS . 'views';
				$errorTpl  = 'helper_error.tpl';

				JXP_View::setPath('views', $errorPath);
				JXP_View::set('helper', $helper);
				JXP_View::render($errorTpl);
			}

			return $return;
		}
	}