<?php

	class JXP_Autoloader extends Jinxup
	{
		private static $_path     = null;
		private static $_registry = array();

		public static function register($path)
		{
			self::$_path = $path;
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

					if ($rawArray[0] !== 'Jinxup')
					{
						$fileNames  = array(
								$rawArray[0],
								strtolower($rawArray[1][0]) . ucfirst($rawArray[0]),
								$rawArray[0] . '_' . strtolower($rawArray[1])
						);

					} else {

						$fileNames = array();
					}
				}

				if (is_dir($pathToFile) && !empty($fileNames))
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