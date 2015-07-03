<?php

	class JXP_Vendor
	{
		private static $_vendor = null;

		public static function load($vendor)
		{
			$path = dirname(__DIR__) . DS . 'vendors' . DS;

			self::$_vendor = strtolower($vendor);

			if ($vendor == 'aws')
				$path .= 'aws' . DS . 'aws-autoloader.php';

			if ($vendor == 'pubnub')
				$path .= 'pubnub' . DS . 'Pubnub.php';

			require_once $path;

			return new self();
		}

		public static function using($credentials)
		{
			$client = new stdClass();
			$config = JXP_Config::get('vendors');

			if (is_array($credentials))
				$config = $credentials;
			else
				$config = $config[self::$_vendor][$credentials];

			if (self::$_vendor == 'aws')
			{
				$client = Aws\Common\Aws::factory(array(
					'key'    => $config['key'],
					'secret' => $config['secret'],
					'region' => $config['region']
				));
			}

			if (self::$_vendor == 'pubnub')
			{
				if (isset($config['publish_key']) && strlen($config['publish_key']) > 0)
					$publish_key = $config['publish_key'];
				else
					$publish_key = 'demo';

				if (isset($config['subscribe_key']) && strlen($config['subscribe_key']) > 0)
					$subscribe_key = $config['subscribe_key'];
				else
					$subscribe_key = 'demo';

				if (isset($config['secret_key']) && strlen($config['secret_key']) > 0)
					$secret_key = $config['secret_key'];
				else
					$secret_key = null;

				if (isset($config['cipher_key']) && strlen($config['cipher_key']) > 0)
					$cipher_key = $config['cipher_key'];
				else
					$cipher_key = false;

				if (isset($config['ssl_on']) && strlen($config['ssl_on']) > 0)
					$ssl_on = $config['ssl_on'];
				else
					$ssl_on = false;

				$client = new Pubnub($publish_key, $subscribe_key, $secret_key, $cipher_key, $ssl_on);
			}

			return $client;
		}
	}