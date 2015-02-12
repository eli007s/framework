<?php

	class JXP_Vendor
	{
		private static $_vendor = null;

		public static function load($vendor)
		{
			$path = dirname(__DIR__) . DS . 'vendors' . DS;

			self::$_vendor = $vendor;

			if (preg_match('/aws/im', $vendor))
				$path .= 'aws' . DS . 'aws-autoloader.php';

			require_once $path;

			return new self();
		}

		public static function using($credentials)
		{
			$config = JXP_Config::get('vendors');

			if (is_array($credentials))
				$config = $credentials;
			else
				$config = $config[self::$_vendor][$credentials];

			if (preg_match('/aws/im', self::$_vendor))
			{
				$client = Aws\Common\Aws::factory(array(
					'key'    => $config['key'],
					'secret' => $config['secret'],
					'region' => $config['region']
				));
			}

			return $client;
		}
	}