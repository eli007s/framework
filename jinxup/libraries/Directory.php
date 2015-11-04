<?php

	class JXP_Directory
	{
		private static $_excludePattern = '\.DS_Store';

		public static function scan($path, $pattern = null)
		{
			$directories = array();

			if (is_dir($path))
			{
				foreach (new DirectoryIterator($path . '/') as $dir)
				{
					if (($dir->isDir() && !$dir->isDot()) || $dir->isFile())
					{
						$base  = $dir->getBasename();

						if (!preg_match('/' . self::$_excludePattern . '/im', $base))
						{
							if (!is_null($pattern))
							{
								if (preg_match('/' . $pattern . '/i', $base))
								{
									$_arr = array(
										'name' => $dir->getBasename(),
										'ext'  => $dir->getExtension(),
										'path' => $dir->getPathname(),
										'size' => filesize($dir->getPathname())
									);

									if (preg_match('(jpeg|jpg|png|gif)', $_arr['ext']))
									{
										$info = getimagesize($dir->getPathname());

										$_arr += array(
											'width'  => $info[0],
											'height' => $info[1]
										);
									}

									$directories[] = $_arr;
								}
							}
						}
					}
				}
			}

			return $directories;
		}
	}