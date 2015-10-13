<?php

	class JXP_Autoloader
	{
		private static $_apps = array();

		public static function autoload($class)
		{
			$_c = explode('_', $class);
			$_c = array('name' => $_c[0], 'type' => $_c[1], 'path' => self::search($_c[0], $_c[1]));

			require_once $_c['path'];
		}

		public static function addApp($app)
		{
			self::$_apps[] = $app;
		}

		private static function search($name, $type)
		{
			$foundClassFilename = null;
			$potentialFileNames = array($name, $type[0] . $name, $name . '_' . $type); // index.php cIndex.php index_controller.php

			if (is_dir(end(self::$_apps)))
			{
				foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(end(self::$_apps))) as $dir)
				{
					if ($dir->isFile())
					{
						preg_match('#(' . str_replace('\\', '\\\\', $name) . ')#i', $dir->getBasename(), $match);

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