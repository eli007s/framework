<?php

	class JXP_Autoloader
	{
		public static function autoload($class)
		{
			echo $class;
			echo '<br />';
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