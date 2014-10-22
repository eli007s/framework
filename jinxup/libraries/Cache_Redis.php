<?php

	class JXP_Cache_Redis
	{
		private static $_redis = null;
		private static $_mode  = null;

		public static function init()
		{
			if (is_null(self::$_redis))
			{
				$cfg = JXP_Config::get('cache');
				$cfg = isset($cfg['redis']) ? $cfg['redis'] : array();

				self::$_mode = isset($cfg['mode']) ? $cfg['mode'] : 'production';

				if (isset($cfg['host']))
				{
					$ip = JXP_Tracker::getIP();

					if ($ip != '::1' && $ip != '127.0.0.1' && self::$_mode == 'production')
					{
						require_once dirname(__DIR__) . DS . 'vendors' . DS . 'predis' . DS . 'Autoloader.php';

						Predis\Autoloader::register();

						self::$_redis = new Predis\Client(array(
							'scheme' => 'tcp',
							'host'   => $cfg['host'],
							'port'   => isset($cfg['port']) ? $cfg['port'] : 6973
						));
					}
				}
			}

			return new self();
		}

		public static function getKey($key)
		{
			self::init();

			$return = null;

			if (!is_null(self::$_redis))
			{
				$value = self::$_redis->get($key);
			$json  = json_decode($value, true);

				$return = json_last_error() == JSON_ERROR_NONE ? $json : $value;
			}

			return $return;
		}

		public static function setKey($key, $value)
		{
			self::init();

			$return = null;

			if (!is_null(self::$_redis))
			{
			$value = is_array($value) ? json_encode($value) : $value;

				$return = self::$_redis->set($key, $value);
			}

			return $return;
		}

		public static function delKey($key)
		{
			self::init();

			if (!is_null(self::$_redis))
				self::$_redis->del($key);
		}
	}