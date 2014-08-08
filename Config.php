<?php

	class JXP_Config
	{
		public static function get($key = null)
		{
			$config = Jinxup::config();

			return is_null($config) ? $config : isset($config[$key]) ? $config[$key] : null;
		}
	}