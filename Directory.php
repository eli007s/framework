<?php

	class JXP_Directory
	{
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
						if (!is_null($pattern))
						{
							$match = false;

							if (preg_match('/' . $pattern . '/i', $directory->getBasename()))
								$match = true;
						}

						if ($match === true)
							$directories[$directory->getBasename()] = $directory->getPathname();
					}
				}
			}

			return $directories;
		}
	}