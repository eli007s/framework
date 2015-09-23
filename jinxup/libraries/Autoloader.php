<?php

	class JXP_Autoloader
	{
		private static $_init  = null;
		private static $_paths = array();

		private static function _init()
		{
			if (is_null(self::$_init))
				self::$_init = new self();

			return self::$_init;
		}

		public static function peekIn($paths, $alias = null)
		{
			if (!is_null($alias))
				self::$_paths[$alias] = $paths;
			else
				self::$_paths[] = $paths;

			return self::_init();
		}
		
		public static function removeFromPath($key)
		{
			if (array_key_exists($key, self::$_paths))
				unset(self::$_paths[$key]);
		}

		public static function autoload($class)
		{
			$file = null;

			if (strpos($class, 'JXP_') !== false)
			{
				$class = str_replace('JXP_', '', $class) . '.php';
				$file  = self::$_paths[0] . DS . $class;

			} else {

				if (strpos($class, '\\') !== false)
				{
					$namespace = explode('\\', $class);
					$_class[0] = array_pop($namespace);
					$path      = implode('\\', $namespace);

				} else {

					$pathKeys = array_keys(self::$_paths);

					if (isset($pathKeys[1]) && isset(self::$_paths[$pathKeys[1]]))
					{
						if ($class == 'bootstrap')
						{
							$_class[0] = $class;
							$_class[1] = 'controller';

						} else {

							$_class = explode('_', $class);

							if (count($_class) > 2)
							{
								$_type = array_pop($_class);

								$_class[0] = implode('_', $_class);
								$_class[1] = $_type;
							}
						}
					}

					$path = isset($_class[1]) ? self::$_paths[$pathKeys[1]] . DS . strtolower($_class[1]) . 's' : __DIR__;
				}

				if (isset($_class))
					$file = self::search($class, $_class, $path);
			}

			if (!is_null($file) && file_exists($file))
			{
				require_once($file);

			} else {

				// TODO: load error template for missing file
				// error out silently or halt app execution
			}
		}

		private static function search($class, $_class, $path)
		{
			$foundClassFilename = null;
			$potentialFileNames = [$_class[0], $class];

			if (isset($_class[1]))
				$potentialFileNames[] = $_class[1][0] . $_class[0];

			if (is_dir($path))
			{
				foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $dir)
				{
					if ($dir->isFile())
					{
						preg_match('#(' . str_replace('\\', '\\\\', $_class[0]) . ')#i', $dir->getBasename(), $match);

						$match = array_filter($match);

						if (!empty($match))
						{
							foreach ($potentialFileNames as $fileName)
							{
								if (strtolower($fileName . '.php') == strtolower($dir->getBasename()))
								{
									$foundClassFilename = $dir->getPathname();

									break;
								}
							}
						}
					}
				}
			}

			return $foundClassFilename;
		}
	}