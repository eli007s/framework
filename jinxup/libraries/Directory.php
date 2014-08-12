<?php

	class JXP_Directory
	{
		private static $_excludePattern = '\.DS_Store';

		public static function scan($path, $pattern = null)
		{
			$directories = array();

			if (is_dir($path))
			{
				foreach (new DirectoryIterator($path . '/') as $directory)
				{
					$match = true;

					if (($directory->isDir() && !$directory->isDot()) || $directory->isFile())
					{
						$base  = $directory->getBasename();

						if (!preg_match('/' . self::$_excludePattern . '/im', $base))
						{
							if (!is_null($pattern))
							{
								$match = false;

								if (preg_match('/' . $pattern . '/i', $base))
									$match = true;
							}

							if ($match === true)
								$directories[$directory->getBasename()] = $directory->getPathname();
						}
					}
				}
			}

			return $directories;
		}
	}