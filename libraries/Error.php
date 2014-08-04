<?php

	class JXP_Error
	{
		private static function render($errorType = null, $param1 = null)
		{
			Jinxup::$exit = true;

			chdir(dirname(__DIR__));

			$errorCode = 0;
			$errorTpl  = null;
			$errorPath = getcwd() . DS . 'views';

			ob_start();

			header('HTTP/1.0 404 Not Found');

			switch ($errorType)
			{
				case 'app':

					echo 'app ' . $param1['name'] . ' not found';

					break;

				case 'app-list':

					echo '<pre>', print_r($param1, true), '</pre>';

					break;

				case 'page':

					$errorCode = 404;

					break;

				case 'file':

					echo 'file not found';

					break;

				default:

					echo $errorType;

					break;
			}

			$errorTpl = $errorType . '.tpl';

			if ($errorCode == 404 || $errorCode == 500)
			{
				if (!empty(self::$_config['error']) && isset(self::$_config['error']['catch']))
				{
					if (isset(self::$_app['paths']['views']))
					{
						$errorPath = rtrim(self::$_app['paths']['views'], '/');

						if (array_key_exists('*', self::$_config['error']['catch']) )
							$errorTpl = trim(self::$_config['error']['catch']['*'], '/');

						if (array_key_exists('all', self::$_config['error']['catch']) )
							$errorTpl = trim(self::$_config['error']['catch']['all'], '/');

						if (array_key_exists($errorCode, self::$_config['error']['catch']) )
							$errorTpl = trim(self::$_config['error']['catch'][$errorCode], '/');
					}

				} else {

					$errorPath = getcwd() . DS . 'views';
				}

				if (!file_exists($errorPath . '/' . $errorTpl))
				{
					$errorPath = getcwd() . DS . 'views';
					$errorTpl  = $errorType . '.tpl';
				}
			}

			JXP_View::setTplPath($errorPath);
			JXP_View::render($errorTpl);
		}
	}