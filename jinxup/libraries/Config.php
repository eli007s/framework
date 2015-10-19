<?php

	class JXP_Config
	{
		private static $_config = array();

		public static function load($config, $app = '__global__')
		{
			// if the config is from a file
			if (!is_array($config) && is_file($config))
			{
				// TODO:
			}

			// if config not empty
			if (!empty($config))
			{
				self::$_config[$app] = $config;
			}
		}

		public function get($key, $app = '__global__')
		{
			return isset(self::$_config[$app][$key]) ? self::$_config[$app][$key] : array();
		}
	}