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

		public static function peekIn($paths)
		{
			self::$_paths[] = $paths;

			return self::_init();
		}

		public static function autoload($class)
		{
			$skip = array(
				'Smarty_Config',
				'Smarty_Internal_TemplateCompilerBase',
				'Smarty_Internal_Templatelexer',
				'Smarty_Internal_Templateparser',
				'Smarty_Internal_CompileBase'
			);

			if (!preg_match('/Predis\\\\/i', $class) && !in_array($class, $skip))
			{
				$file = null;

				if (strpos($class, 'JXP_') !== false)
				{
					$class = str_replace('JXP_', '', $class) . '.php';
					$file  = self::$_paths[0] . DS . $class;

				} else {

					if (isset(self::$_paths[1]))
					{
						$_class = explode('_', $class);

						if (isset($_class[1]))
						{
							$path = self::$_paths[1] . DS . strtolower($_class[1]) . 's';
							$file = self::search($class, $_class, $path);

						} else {

							$file = null;

							if ($_class[0] == 'onLoad')
							{

							} else {

								$file = self::search($class, $_class, __DIR__);
							}
						}
					}
				}

				if (!is_null($file) && file_exists($file))
					require_once($file);
			}
		}

		private static function search($class, $_class, $path)
		{
			$foundClassFilename   = null;
			$potentialFileNames[] = $_class[0];
			$potentialFileNames[] = $class;

			if (isset($_class[1]))
				$potentialFileNames[] = $_class[1][0] . $_class[0];

			if (is_dir($path))
			{
				foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $dir)
				{
					if ($dir->isFile())
					{
						preg_match('/(' . $_class[0] . ')/i', $dir->getBasename(), $match);

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
