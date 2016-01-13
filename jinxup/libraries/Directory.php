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
						$match = true;

						if (!preg_match('/' . self::$_excludePattern . '/im', $dir->getBasename()))
						{
							if (!is_null($pattern))
							{
								$match = false;

								if (preg_match('/' . $pattern . '/i', $dir->getBasename()))
									$match = true;
							}

							if ($match === true)
							{
								$_arr['name'] = $dir->getBasename();
								$_arr['path'] = $dir->getPathname();

								if ($dir->isFile())
								{
									$_arr['ext']  = $dir->getExtension();
									$_arr['size'] = filesize($dir->getPathname());

									if (preg_match('(jpeg|jpg|png|gif)', $_arr['ext']))
									{
										$info = getimagesize($dir->getPathname());

										$_arr += array(
											'width'  => $info[0],
											'height' => $info[1]
										);
									}
								}

								$directories[] = $_arr;
							}
						}
					}
				}
			}

			return $directories;
		}
	}