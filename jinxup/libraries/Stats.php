<?php

	class JXP_Stats
	{
		private static $_app  = null;
		private static $_time = null;
		private static $_stats = array();

		public function __construct()
		{
			self::$_time = microtime(true);
		}

		public static function app($app)
		{
			self::$_app = $app;

			return new self();
		}

		public static function report()
		{
			/*self::$_stats['main'] = array(
							'app'        => $this->_app,
							'route'      => $this->_route['string'],
							'controller' => $this->_route['controller'],
							'action'     => $this->_route['action'],
							'params'     => $this->_route['params'],
							'timers'     => $this->_timers,
							'errors'     => $this->_errors
						);*/

			return self::$_stats[self::$_app];
		}

		protected static function log($key, $time, $memory)
		{
			if (!is_null(self::$_app))
			{
				if (!array_key_exists($key, self::$_stats[self::$_app]))
					self::$_stats[self::$_app][$key] = array();

				self::$_stats[self::$_app][$key] += array('time' => $time, 'memory' => $memory);

			} else {

				throw new exception('Missing app for stats');
			}
		}

		public function __destruct()
		{
			echo '<pre>', print_r(self::$_stats, true), '</pre>';
			return self::$_stats;
		}
	}