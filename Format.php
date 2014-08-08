<?php

	class JXP_Format
	{
		public static function phoneNumber($number)
		{
			$return = $number;

			if (preg_match('~(\d{3})[^\d]*(\d{3})[^\d]*(\d{4})$~', $number, $match))
				$return = '(' . $match[1] . ') ' . $match[2] . '-' . $match[3];

			return $return;
		}

		public static function isEmail($email)
		{
			return filter_var($email, FILTER_VALIDATE_EMAIL);
		}

		public static function extractDigits($string)
		{
			return preg_replace('/[^0-9]/', '', $string);
		}

		public static function trimSpaces($string)
		{
			$array = array();

			if (is_array($string))
			{
				foreach ($string as $str => $val)
					$array[$str] = is_array($val) ? self::trimSpaces($val) : trim($val);

			} else {

				$array = trim($string);
			}

			return $array;
		}
	}