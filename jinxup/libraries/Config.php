<?php

	class JXP_Config
	{
		private static $_config = array();

		public static function load($config)
		{
			if (is_dir($config) || is_file($config))
			{
				if (is_dir($config))
				{
					$scan = JXP_Directory::scan($config);

					foreach ($scan as $k => $v)
						$c[] = $v['path'];

				} else {

					$c[] = $config;
				}

				foreach ($c as $k => $v)
				{
					$contents = array();

					if (strpos($v, '.json') !== false || strpos($v, '.tell') !== false)
						$contents = json_decode(self::_cleanCommentsFromJson($v), true);

					if (strpos($v, '.php') !== false)
						$contents = file_get_contents($v);

					self::$_config = array_merge(self::$_config, $contents);
				}

			} else {

				self::$_config = array_merge(self::$_config, $config);
			}
		}

		public static function get($key)
		{
			return isset(self::$_config[$key]) ? self::$_config[$key] : array();
		}

		private static function _cleanCommentsFromJson($file)
		{
			return preg_replace('@(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|((?<!:)//.*)|[\t\r\n]@i', '', file_get_contents($file));
		}

		private function _translate($config)
		{

		}
	}