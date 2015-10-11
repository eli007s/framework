<?php

	class JXP_Model
	{
		private static $_model = array();

		public static function using($name)
		{
			$class = $name . '_Model';

			if (class_exists($class))
			{
				if (!isset(self::$_model[$class]))
					self::$_model[$class] = new $class();

				self::$_model['name'] = $class;

			} else {

				$bt = debug_backtrace();

				$response  = 'Model ' . $name . ' was not found.<br /><br />';
				$response .= 'Called From: ' . $bt[1]['class'] . '<br />';
				$response .= 'Line: ' . $bt[0]['line'];

				JXP_Error::pushMessage(800, $response);

				JXP_Error::render(800);
			}

			return new self();
		}

		public static function __callStatic($name, $params)
		{
			$bt     = debug_backtrace();
			$class  = str_replace('_Controller', '_Model', $bt[2]['class']);
			$return = null;

			if (class_exists($class))
			{
				if (!isset(self::$_model[$class]))
					self::$_model[$class] = new $class();

				if (method_exists(self::$_model[$class], $name) && is_callable(array(self::$_model[$class], $name)))
				{
					$return = call_user_func_array(array(self::$_model[$class], $name), $params);

				} else {

					$bt = debug_backtrace();

					$response  = 'Model method ' . $name . ' was not found in ' . $class . '<br /><br />';
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
			$object = self::$_model[self::$_model['name']];

			if (method_exists($object, $name) && is_callable(array($object, $name)))
			{
				$return = call_user_func_array(array($object, $name), $params);

			} else {

				$bt = debug_backtrace();

				$response  = 'Model method ' . $name . ' was not found in ' . self::$_model['name'] . '<br /><br />';
				$response .= 'Called From: ' . $bt[2]['class'] . '<br />';
				$response .= 'Line: ' . $bt[1]['line'];

				JXP_Error::pushMessage(606, $response);

				JXP_Error::render(606);
			}

			return $return;
		}
	}