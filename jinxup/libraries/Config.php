<?php

	class JXP_Config
	{
		private static $_config = array();

		public static function load($config, $app = '__global__')
		{
			// if the config is from a file
			$c = array();

			if (is_dir($config) || is_file($config))
			{
				if (is_dir($config))
				{
					$c = JXP_Directory::scan($config);

					foreach ($c as $k => $v)
						$c[] = $v['path'];

				} else {

					$c[] = $config;
				}

				foreach ($c as $k => $v)
				{
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

		public function get($key, $app = '__global__')
		{
			return isset(self::$_config[$app][$key]) ? self::$_config[$app][$key] : array();
		}

		private static function _cleanCommentsFromJson($file)
		{
			return preg_replace('@(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|((?<!:)//.*)|[\t\r\n]@i', '', file_get_contents($file));
		}
	}