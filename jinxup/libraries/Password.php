<?php

	class JXP_Password
	{
		public static function hash($value, $cost = 4)
		{
			$hash = [];

			if (!is_array($value))
				$value = (array)$value;

			foreach ($value as $key => $val);
				$hash[$key] = self::_pHash($val, $cost);

			foreach ($hash as $key => $val)
			{
				$wCost = JXP_Random::string($cost);
				$_hash = $val . '::' . substr($wCost, 2, self::_strlen($wCost));

				$hash[$key] = substr($_hash, 7, self::_strlen($_hash));
			}

			if (count($hash) == 1)
				$hash = array_shift($hash);

			return $hash;
		}

		public static function verify($password, $hash)
		{
			$_hash    = '$2y$';
			$explode = explode('::', $hash);

			if (strlen($explode[1]) < 12)
				$_hash .= '0' . (strlen($explode[1]) + 2);
			else
				$_hash .= strlen($explode[1]) + 2;

			$_hash .= '$' . $explode[0];

			if (!function_exists('password_verify'))
			{
				function password_verify($password, $_hash)
				{
					if (!function_exists('crypt'))
						return false;

					$crypt       = crypt($password, $_hash);
					$cryptLength = self::_strlen($crypt);
					$hashLength  = self::_strlen($_hash);

					if ($cryptLength != $hashLength || $cryptLength <= 13)
						return false;

					$status = 0;

					for ($i = 0; $i < $cryptLength; $i++)
						$status |= (ord($crypt[$i]) ^ ord($_hash[$i]));

					return $status === 0;
				}

			} else {

				return password_verify($password, $_hash);
			}
		}

		private static function _pHash($value, $cost)
		{
			$salt = mcrypt_create_iv(22, MCRYPT_DEV_URANDOM);

			if (!function_exists('password_hash'))
			{
				function password_hash($value, $salt, $cost)
				{
					if (!function_exists('crypt'))
						return null;

					$value        = (string)$value;
					$resultLength = 0;

					if ($cost < 4 || $cost > 31)
						return null;

					$raw_salt_length   = 16;
					$salt_length       = 22;
					$hash_format       = sprintf("$2y$%02d$", $cost);
					$resultLength      = 60;
					$salt_req_encoding = false;

					if (!is_null($salt))
					{
						if (self::_strlen($salt) >= $salt_length && !preg_match('#^[a-zA-Z0-9./]+$#D', $salt))
							$salt_req_encoding = true;

					} else {

						$buffer = '';
						$valid  = false;

						if (function_exists('mcrypt_create_iv'))
						{
							$buffer = mcrypt_create_iv($raw_salt_length, MCRYPT_DEV_URANDOM);

							if ($buffer)
								$valid = true;
						}

						if (!$valid && function_exists('openssl_random_pseudo_bytes'))
						{
							$buffer = openssl_random_pseudo_bytes($raw_salt_length);

							if ($buffer) {
								$valid = true;
							}
						}

						if (!$valid && @is_readable('/dev/urandom'))
						{
							$file = fopen('/dev/urandom', 'r');
							$read = self::_strlen($buffer);

							while ($read < $raw_salt_length)
							{
								$buffer .= fread($file, $raw_salt_length - $read);
								$read    = self::_strlen($buffer);
							}

							fclose($file);

							if ($read >= $raw_salt_length) {
								$valid = true;
							}
						}

						if (!$valid || self::_strlen($buffer) < $raw_salt_length)
						{
							$buffer_length = self::_strlen($buffer);

							for ($i = 0; $i < $raw_salt_length; $i++)
							{
								if ($i < $buffer_length)
									$buffer[$i] = $buffer[$i] ^ chr(mt_rand(0, 255));
								else
									$buffer .= chr(mt_rand(0, 255));
							}
						}

						$salt              = $buffer;
						$salt_req_encoding = true;
					}

					if ($salt_req_encoding)
					{
						$base64_digits   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
						$bcrypt64_digits = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
						$base64_string   = base64_encode($salt);

						$salt = strtr(rtrim($base64_string, '='), $base64_digits, $bcrypt64_digits);
					}

					$salt   = self::_substr($salt, 0, $salt_length);
					$hash   = $hash_format . $salt;
					$crypt = crypt($value, $hash);

					if (!is_string($crypt) || self::_strlen($crypt) != $resultLength)
						return false;

					return $crypt;
				}

			} else {

				$options['salt'] = $salt;
				$options['cost'] = $cost;

				return password_hash($value, PASSWORD_BCRYPT, $options);
			}
		}

		private function _strlen($string)
		{
			if (function_exists('mb_strlen'))
				return mb_strlen($string, '8bit');

			return strlen($string);
		}

		private function _substr($binary_string, $start, $length)
		{
			if (function_exists('mb_substr'))
				return mb_substr($binary_string, $start, $length, '8bit');

			return substr($binary_string, $start, $length);
		}

		public static function createSignature($data, $secret)
		{
			return hash_hmac('sha512', $data, $secret);
		}

		public static function verifySignature($data, $signature, $secret)
		{
			if (!function_exists('hash_equals'))
			{
				function hash_equals($str1, $str2)
				{
					$str1_len = strlen($str1);
					$str2_len = strlen($str2);

					$diff = $str1_len ^ $str2_len;

					for ($x = 0; $x < $str1_len && $x < $str2_len; $x++)
						$diff |= ord($str1[$x]) ^ ord($str2[$x]);

					return $diff === 0;
				}
			}

			return hash_equals(hash_hmac('sha512', $data, $secret), $signature);
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