<?php

	class JXP_Config
	{
		private static $_config = array();

		public static function get($key = null)
		{
			$config = Jinxup::config();

			return is_null($config) ? $config : isset($config[$key]) ? $config[$key] : null;
		}

		public static function loadFromPath($path)
		{
			$return = null;

			foreach (JXP_Directory::scan($path, '.tell') as $config)
			{
				$contents   = file_get_contents($config);
				$configTell = self::_filter($contents);
				$return     = json_decode($configTell, true);
			}

			return $return;
		}

		public static function loadFromFile($file)
		{
			$contents   = file_get_contents($file);
			$configTell = self::_filter($contents);
			$return     = json_decode($configTell, true);

			return $return;
		}

		public static function translate($config)
		{
			if (!empty($config))
			{
				foreach ($config as $a => $b)
				{
					if ($a != 'settings')
					{
						if (is_array($b))
						{
							foreach ($b as $c => $d)
							{
								if (isset($d['use']))
								{
									if (isset(self::$_config[$a][$c]))
									{
										if (isset($config['settings'][$a][$d['use']]))
											self::$_config[$a][$c] = $config['settings'][$a][$d['use']];

									} else {

										self::$_config[$a][$c] = $config[$a][$c];
									}

								} else {

									if (isset(self::$_config[$a]))
									{
										self::$_config[$a] = array_merge(
											self::$_config[$a],
											$config[$a]
										);

									} else {

										self::$_config[$a] = $config[$a];
									}
								}
							}

						} else {

							if ($b == 'use')
							{
								if (isset($config['settings'][$a][$config[$a][$b]]))
								{
									if (isset(self::$_config[$a][$config[$a][$b]]))
									{
										self::$_config[$a][$config[$a][$b]] = array_merge(
											self::$_config[$a][$config[$a][$b]],
											$config['settings'][$a][$config[$a][$b]]
										);

									} else {

										self::$_config[$a][$config[$a][$b]] = $config['settings'][$a][$config[$a][$b]];
									}
								}

							} else {

								if (isset(self::$_config[$a]))
								{
									self::$_config[$a] = array_merge(
										self::$_config[$a],
										$config[$a]
									);

								} else {

									self::$_config[$a] = $config[$a];
								}
							}
						}
					}
				}
			}

			return self::$_config;
		}

		private static function _filter($string)
		{
			return preg_replace('/([?!http|ftp]\/\/|\/\*|#)(.*)(\*\/|\n|\r)/', '', $string);
		}
	}