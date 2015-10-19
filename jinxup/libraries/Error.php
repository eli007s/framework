<?php

	class JXP_Error
	{
		private static $_error      = array();
		private static $_logType    = E_ALL;
		private static $_showErrors = true;
		private static $errorType   = array (
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parsing Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_STRICT            => 'Runtime Notice',
			E_RECOVERABLE_ERROR => 'Catchable Fatal Error',
			E_DEPRECATED        => 'Deprecated'
		);

		public static function register($logTypes = E_ALL)
		{
			error_reporting(0);

			self::$_logType = $logTypes;

			set_error_handler('JXP_Error::log', self::$_logType);
			register_shutdown_function('JXP_Error::shutdown');
		}

		public static function showErrors($bool = true)
		{
			self::$_showErrors = $bool;
		}

		public static function shutdown()
		{
			$error = error_get_last();

			if (!is_null($error))
				self::log($error['type'], $error['message'], $error['file'], $error['line'], array());

			if (!empty(self::$_error) && self::$_showErrors === true)
			{
				self::$_error['server']   = array_chunk($_SERVER, 9, true);
				self::$_error['sessions'] = $_SESSION;
				self::$_error['cookies']  = $_COOKIE;
				//self::$_error['vars']     = JXP_View::getTemplateVars();
				//self::$_error['queries']  = JXP_DB::log();

				//JXP_View::setPath('views', Jinxup::installPath() . DS . 'views');
				//JXP_View::set('debug', self::$_error);
				//JXP_View::render('debug.tpl');
			}
		}

		public static function log($errNo, $errMsg, $fileName, $lineNum, $vars)
		{
			self::$_error['exceptions'][] = array(
				'type'   => self::$errorType[$errNo],
				'time'   => date('Y-m-d H:i:s (T)'),
				'num'    => $errNo,
				'msg'    => $errMsg,
				'script' => array(
					'name' => $fileName,
					'line' => $lineNum
				)/*,
				'var' => $vars*/
			);

			echo '<pre>', print_r(self::$_error, true), '</pre>';
		}
	}