<?php

	class JXP_Controller
	{
		private static $_controller = array();

		public static function using($name)
		{
			$class = $name . '_Controller';

			if (class_exists($class))
			{
				if (!isset(self::$_controller[$class]))
					self::$_controller[$class] = new $class();

				self::$_controller['name'] = $class;

			} else {

				$bt = debug_backtrace();

				$response  = 'Controller ' . $name . ' was not found.<br /><br />';
				$response .= 'Called From: ' . $bt[1]['class'] . '<br />';
				$response .= 'Line: ' . $bt[0]['line'];

				JXP_Error::pushMessage(800, $response);

				JXP_Error::render(800);
			}

			return self::$_controller['name'];
		}

		public static function __callStatic($name, $params)
		{
			$bt     = debug_backtrace();
			$class  = $bt[2]['class'];
			$return = null;

			JXP_Error::addBackTrace($bt, ucfirst($class));

			if (class_exists($class))
			{
				if (!isset(self::$_controller[$class]))
					self::$_controller[$class] = new $class();

				$_class = self::$_controller[$class];

				if (method_exists($_class, $name) && is_callable(array($_class, $name)))
				{
					$return = call_user_func_array(array($_class, $name), $params);

				} else {

					$bt = debug_backtrace();

					$response  = 'Helper method ' . $name . ' was not found in ' . $class . '<br /><br />';
					$response .= 'Called From: ' . $bt[2]['class'] . '<br />';
					$response .= 'Line: ' . $bt[1]['line'];

					JXP_Error::pushMessage(606, $response);

					JXP_Error::render(606);
				}

			} else {

				$response  = 'Model ' . $name . ' was not found.<br /><br />';
				$response .= 'Called From: ' . $bt[1]['class'] . '<br />';
				$response .= 'Line: ' . $bt[0]['line'];

				JXP_Error::pushMessage(800, $response);

				JXP_Error::render(800);
			}

			return $return;
		}

		public function __call($name, $params)
		{
			$return = null;
			$object = self::$_controller[self::$_controller['name']];

			if (method_exists($object, $name) && is_callable(array($object, $name)))
			{
				$return = call_user_func_array(array($object, $name), $params);

			} else {

				$bt = debug_backtrace();

				$response  = 'Controller method ' . $name . ' was not found in ' . self::$_controller['name'] . '<br /><br />';
				$response .= 'Called From: ' . $bt[2]['class'] . '<br />';
				$response .= 'Line: ' . $bt[1]['line'];

				JXP_Error::pushMessage(606, $response);

				JXP_Error::render(606);
			}

			return $return;
		}
	}