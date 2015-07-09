<?php

	class JXP_Security extends Jinxup
	{
		public static function sHash($value, $salt = null, $algo = 'sha512', $output = false)
		{
			$hash = self::hash($value, $salt, $algo);

			foreach ($hash as $key => $val)
			{
				$parts      = str_split($val, $split_length = 4);
				$hash[$key] = implode($parts, '-') . '::' . hash('adler32', $parts[1]);
			}

			if (count($hash) == 1)
				$hash = array_shift($hash);

			return $hash;
		}

		private static function _hash($algo, $data, $secret, $output = false, $blockSize = 64, $opad = 0x5c, $ipad = 0x36)
		{
			$secret    = str_pad($secret, $blockSize, chr(0x00), STR_PAD_RIGHT);
			$o_key_pad = $i_key_pad = '';

			for ($i = 0; $i < $blockSize; $i++)
			{
				$o_key_pad .= chr(ord(substr($secret, $i, 1)) ^ $opad);
				$i_key_pad .= chr(ord(substr($secret, $i, 1)) ^ $ipad);
			}

			$hash = hash_hmac($algo, $o_key_pad . hash_hmac($algo, $i_key_pad . $data, true), true);

			return self::_pbkdf2($algo, $hash, $secret, 1985, $blockSize, $output);
		}

		private static function _pbkdf2($algo, $data, $salt, $count, $length, $output)
		{
			if (function_exists('hash_pbkdf2'))
			{
				if (!$output)
					$length *= 2;

				return hash_pbkdf2($algo, $data, $salt, $count, $length, $output);
			}

			$hash_length = strlen(hash($algo, '', true));
			$block_count = ceil($length / $hash_length);

			$output = null;

			for ($i = 1; $i <= $block_count; $i++)
			{
				$last = $salt . pack('N', $i);
				$last = $xorsum = hash_hmac($algo, $last, $data, true);

				for ($j = 1; $j < $count; $j++)
					$xorsum ^= ($last = hash_hmac($algo, $last, $data, true));

				$output .= $xorsum;
			}

			return $output ? substr($output, 0, $length) : bin2hex(substr($output, 0, $length));
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

		public static function hash($value, $salt = null, $algo = 'md5')
		{
			$hash   = array();
			$config = JXP_Config::get('security');

			if (!empty($config))
			{
				$algo = is_null($algo) ? isset($config['hash']) ? $config['hash'] : 'md5' : $algo;
				$salt = is_null($salt) ? isset($config['salt']) ? $config['salt'] : null : $salt;
			}

			foreach (hash_algos() as $_algo)
			{
				if ($algo == $_algo)
				{
					if (is_array($value))
					{
						foreach ($value as $key => $val)
							$hash[$key] = self::_hash($algo, $val, $salt);

					} else {

						$hash[] = self::_hash($algo, $value, $salt);
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