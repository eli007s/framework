<?php

	class JXP_Redis
	{
		private static $_redis = null;

		public static function init()
		{
			if (is_null(self::$_redis))
			{
				$cfg = JXP_Config::get('sessions');

				if (!empty($cfg))
				{
					if (isset($cfg['use']) && strtolower($cfg['use']) == 'redis')
					{
						$use = $cfg['use'];
						$cfg = $cfg[$use] ?: array();

						if (isset($cfg['host']))
						{
							if ($use == 'redis')
							{
								require_once 'vendors' . DS . 'predis' . DS . 'Autoloader.php';

								Predis\Autoloader::register();

								$ttl  = $cfg['ttl'] ?: 3600;
								$port = $cfg['port'] ?: 6973;

								self::$_redis = new Predis\Client(array(
									'scheme' => 'tcp',
									'host'   => $cfg['host'],
									'port'   => $port
								));
							}
						}
					}
				}
			}

			return self::$_redis;
		}

		public static function getKey($key)
		{
			$redis = self::init();
			$value = $redis->get($key);
			$json  = json_decode($value, true);

			return json_last_error() == JSON_ERROR_NONE ? $json : $value;
		}

		public static function setKey($key, $value)
		{
			$redis = self::init();

			$value = is_array($value) ? json_encode($value) : $value;

			return $redis->set($key, $value);
		}

		public static function delKey($key)
		{
			$redis = self::init();

			$redis->del($key);
		}
	}