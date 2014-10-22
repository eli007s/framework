<?php

	class JXP_Random
	{
		private static $_letters = array(
			'const' => array(
				'b', 'c', 'd', 'f', 'g', 'h', 'j', 'k', 'l', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'z', 'th', 'st'
			),
			'vowel' =>	array(
				'a', 'e', 'i', 'o', 'u', 'y', 'ee', 'ie', 'oo', 'ou'
			)
		);

		public static function word($length = 6)
		{
			$word    = null;

			for ($i = 0; $i < $length; $i++)
			{
				foreach(array('const', 'vowel') as $key)
					$word .= self::$_letters[$key][self::number(0, count(self::$_letters[$key]) - 1)];
			}

			return substr($word, 0, $length);
		}

		public static function letter()
		{
			$const = self::$_letters['const'];

			return substr(strtoupper($const[self::number(0, count($const) - 1)]), 0, 1);
		}

		public static function password($length = 8)
		{
			$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789=!@#$%';
			$str   = null;
			$max   = strlen($chars) - 1;

			for ($i = 0; $i < $length; $i++)
				$str .= $chars[self::number(0, $max)];

			return str_replace(' ', '', $str);
		}

		public static function number($min = 0, $max = 9999999, $seed = 0)
		{
			mt_srand($seed === 0 ? microtime() * 1000000 : $seed);

			return mt_rand($min, $max);
		}
	}