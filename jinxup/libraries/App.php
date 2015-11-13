<?php

	class JXP_App
	{
		private static $_app = null;

		public static function set($app)
		{
			self::$_app = $app;
		}

		public static function loaded()
		{
			return self::$_app;
		}
	}