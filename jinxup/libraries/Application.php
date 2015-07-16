<?php

	class JXP_Application
	{
		private static $_active = null;
		private static $_app    = array();
		private static $_apps   = array();
		private static $_using  = null;
		private static $_dirs   = array('config' => 'config', 'applications' => 'applications', 'views' => 'views');

		public static function setActive($active = null)
		{
			self::$_active = $active;
		}

		public static function using($app)
		{
			self::$_using = $app;

			return new self();
		}

		public static function setDirectories($dirs = array())
		{
			self::$_dirs = $dirs;
		}

		public static function setDirectory($key, $value)
		{
			if (array_key_exists($key, self::$_dirs))
				self::$_dirs[$key] = $value;
		}

		public static function getDirectories($key = null)
		{
			$return = self::$_dirs;

			if (!is_null($key) && isset(self::$_dirs[$key]))
				$return = self::$_dirs[$key];

			return $return;
		}

		public static function getActive()
		{
			return self::$_active;
		}

		public static function getPaths()
		{
			return self::$_app['paths'];
		}

		public static function getPath($path)
		{
			return isset(self::$_app['paths'][$path]) ? self::$_app['paths'][$path] : null;
		}

		public static function setApp($app = array())
		{
			self::$_app = $app;
		}

		public static function getApp()
		{
			return self::$_app;
		}

		public static function setApps($apps = array())
		{
			self::$_apps = $apps;
		}

		public static function getApps()
		{
			return self::$_apps;
		}

		public static function getWebPaths()
		{
			$paths = array();

			if (!empty(self::$_app['paths']))
			{
				foreach (self::$_app['paths'] as $key => $val)
					$paths[$key] = self::getWebPath($key);
			}

			return $paths;
		}

		public static function getWebPath($key)
		{
			$path = null;

			if (!empty(self::$_app['paths']))
			{
				$app = self::$_app['paths'];

				if (isset($app[$key]))
					$path = DS . ltrim(JXP_Routes::$prefix . str_replace($_SERVER['DOCUMENT_ROOT'] . DS . JXP_Routes::$prefix, '', getcwd()), DS) . DS . $key;
			}

			return str_replace(DS, '/', $path);
		}
	}
