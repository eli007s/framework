<?php

	class JXP_Helper
	{
		private static $_helper = array();

		public static function using($name)
		{
			$class  = $name . '_Helper';
			$return = null;

			if (class_exists($class))
			{
				$return = new $class();

			} else {

				$bt = debug_backtrace();

				$response  = 'Helper ' . $name . ' was not found.<br /><br />';
				$response .= 'Called From: ' . $bt[1]['class'] . '<br />';
				$response .= 'Line: ' . $bt[0]['line'];

				echo $response;
			}

			return $return;
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

				$response  = 'Helper ' . $name . ' was not found.<br /><br />';
				$response .= 'Called From: ' . $bt[1]['class'] . '<br />';
				$response .= 'Line: ' . $bt[0]['line'];

				echo $response;
			}

			return $return;
		}
	}