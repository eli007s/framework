<?php

	class JXP_Array
	{
		public static function array_key_case_change($array, $case = CASE_LOWER)
		{
			$return = array();

			foreach ($array as $k => $v)
			{
				if (is_array($v))
					$return[$k] = self::array_key_case_change($v);

				$return[($case == CASE_UPPER ? strtoupper($k) : strtolower($k))] = $v;
			}

			return $return;
		}
	}