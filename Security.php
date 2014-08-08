<?php

	class JXP_Security extends Jinxup
	{
		private static $_tokenMatch = false;

		public static function setTokenMatch($bool = false)
		{
			self::$_tokenMatch = $bool;
		}

		public static function hasTokenMatch()
		{
			return self::$_tokenMatch;
		}

		public static function sHash($value, $algo = 'sha512', $salt = null)
		{
			$hash   = array();
			$config = self::config('database');
			$config = $config['security'];

			$algo = is_null($algo) ? isset($config['hash']) ? $config['hash'] : 'md5' : $algo;
			$salt = is_null($salt) ? isset($config['salt']) ? $config['salt'] : null : $salt;

			foreach (hash_algos() as $_algo)
			{
				if ($algo == $_algo)
				{
					if (is_array($value))
					{
						foreach ($value as $key => $val)
							$hash[$key] = hash($algo, $val . $salt);

					} else {

						$hash[] = hash($algo, $value . $salt);
					}

					foreach ($hash as $key => $val)
					{
						$parts      = str_split($val, $split_length = 4);
						$hash[$key] = implode($parts, '-') . '::' . hash('adler32', $parts[1]);
					}

					if (count($hash) == 1)
						$hash = array_shift($hash);

					break;
				}
			}

			return $hash;
		}

		public static function hash($value, $algo = 'md5', $salt = 'secure')
		{
			$hash   = array();
			$config = Jinxup::application('config');

			if (isset($config['security']))
			{
				$config = $config['security'];

				$algo = $config['hash'];
				$salt = $config['salt'];
			}

			foreach (hash_algos() as $_algo)
			{
				if ($algo == $_algo)
				{
					if (is_array($value))
					{
						foreach ($value as $key => $val)
							$hash[$key] = hash_hmac($algo, $val, $salt);

					} else {

						$hash[] = hash_hmac($algo, $value, $salt);
					}

					if (count($hash) == 1)
						$hash = array_shift($hash);

					break;
				}
			}

			return $hash;
		}

		public static function encrypt($string, $key = null)
		{
			if (extension_loaded('mcrypt'))
			{
				return base64_encode(
					mcrypt_encrypt(
						MCRYPT_RIJNDAEL_256, md5($key), $string, MCRYPT_MODE_CBC, md5(md5($key))
					)
				);

			} else {

				return null;
			}
		}

		public static function decrypt($string, $key = null)
		{
			if (extension_loaded('mcrypt'))
			{
				return rtrim(
					mcrypt_decrypt(
						MCRYPT_RIJNDAEL_256, md5($key), base64_decode($string), MCRYPT_MODE_CBC, md5(md5($key))
					)
				, "\0");

			} else {

				return null;
			}
		}
	}