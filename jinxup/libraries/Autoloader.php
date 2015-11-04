<?php

	class JXP_Autoloader extends Jinxup
	{
		private static $_path     = null;
		private static $_registry = array();

		public static function register($app)
		{
			self::$_path = getcwd() . DS . 'apps' . DS . $app;
		}

		public static function autoload($class)
		{
			if (!in_array($class, self::$_registry))
			{
				$rawArray           = explode('_', $class);
				$foundClassFilename = null;

				if ($rawArray[0] == 'JXP')
				{
					$pathToFile = __DIR__;
					$fileNames  = array(
						ucfirst($rawArray[1])
					);

				} else {

					if (strpos($rawArray[0], '\\') !== false)
					{
						$ns = explode('\\', $rawArray[0]);

						$rawArray[0] = end($ns);
					}

					$pathToFile = self::$_path;
					$fileNames  = array(
						$rawArray[0],
						strtolower($rawArray[1][0]) . ucfirst($rawArray[0]),
						$rawArray[0] . '_' . strtolower($rawArray[1])
					);
				}

				if (is_dir($pathToFile))
				{
					foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pathToFile)) as $dir)
					{
						if ($dir->isFile())
						{
							foreach ($fileNames as $fileName)
							{
								if (strtolower($fileName . '.php') == strtolower($dir->getBasename()))
								{
									self::$_registry[] = $class;

									require_once $dir->getPathname();

									break;
								}
							}
						}
					}
				}
			}
		}
	}