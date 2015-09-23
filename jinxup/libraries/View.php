<?php

	class JXP_View extends Jinxup
	{
		private static $_smarty    = null;
		private static $_config    = [];
		private static $_params    = [];
		private static $_regFilter = true;
		private static $_vars      = [];
		private static $_paths     = [
			'views'   => 'views',
			'compile' => '',
			'plugins' => 'views/plugins'
		];

		public static function __callStatic($name, $params)
		{
			self::_viewInit();

			$return = null;

			try
			{
				if (count($params) == 0)
					$return = self::$_smarty->{$name}();

				if (count($params) == 1)
					$return = self::$_smarty->{$name}($params[0]);

				if (count($params) == 2)
					$return = self::$_smarty->{$name}($params[0], $params[1]);

				if (count($params) == 3)
					$return = self::$_smarty->{$name}($params[0], $params[1], $params[2]);

				if (count($params) == 4)
					$return = self::$_smarty->{$name}($params[0], $params[1], $params[2], $params[3]);

			} catch (Exception $e) {

				$return = $e->getMessage();
			}

			return $return;
		}

		public static function setPath($key, $path)
		{
			self::$_paths[$key] = $path;
		}

		public static function setConfig($config, $app)
		{
			if (isset($app['views']))
				self::$_paths['views'] = $app['views'];

			self::$_config = $config;
		}

		private static function _viewInit($_vars = [], $smarty = true)
		{
			$vars = [];

			if (isset($_vars['app']))
				$vars['app'] = $_vars['app'];

			if (isset($_vars['jxp']))
				$vars['jxp'] = $_vars['jxp'];

			$_vars['app'] = [
				'name'       => JXP_Application::getActive(),
				'controller' => JXP_Routes::getController(),
				'action'     => JXP_Routes::getActionCall(),
				'assets'     => JXP_Application::getWebPath('assets'),
				'param'      => JXP_Routes::getParams(),
				'config'     => Jinxup::config()
			];

			$_vars['jxp'] = [
				'assets'  => '/jinxup/framework/assets',
				'session' => isset($_SESSION) ? $_SESSION : array(),
				'post'    => !empty($_POST) ? $_POST : array(),
				'routes'  => array('getURI' => JXP_Routes::getURI()),
				'tracker' => array('getIP' => JXP_Tracker::getIP())
			];

			$_vars = array_merge($_vars, $vars);

			self::$_vars = array_merge(self::$_vars, $vars);

			if (is_null(self::$_smarty) && $smarty == true)
			{
				$smartyPath = dirname(__DIR__) . DS . 'engines' . DS . 'smarty' . DS . 'Smarty.class.php';

				if (file_exists($smartyPath))
				{
					require_once($smartyPath);

					self::$_smarty                  = new Smarty();
					self::$_params['app']           = self::getApp();
					self::$_smarty->left_delimiter  = '{!';
					self::$_smarty->right_delimiter = '!}';

					if (isset(self::$_config['template']['delimiters']['left']))
						self::$_smarty->left_delimiter = self::$_config['template']['delimiters']['left'];

					if (isset(self::$_config['template']['delimiters']['right']))
						self::$_smarty->right_delimiter = self::$_config['template']['delimiters']['right'];

					self::$_smarty->assign('app', $_vars['app']);
					self::$_smarty->assign('jxp', $_vars['jxp']);
					self::$_smarty->setTemplateDir(self::$_paths['views']);
					self::$_smarty->addPluginsDir(self::$_paths['views'] . DS . 'plugins');
					self::$_smarty->registerResource('file', new RecompileFileResource());
					self::$_smarty->muteExpectedErrors();
				}
			}

			unset($_vars);
			unset($vars);
		}

		public static function set($key, $val)
		{
			self::_viewInit();

			self::$_vars[$key] = $val;

			self::$_smarty->assign($key, $val);
		}

		public static function render($tpl)
		{
			$_vars = [];

			if (strpos($tpl, 'app::') !== false)
			{
				preg_match('/app::(.*):(.*)/im', $tpl, $matches);

				if (count($matches) >= 3)
				{
					$tpl = $matches[2];

					$applicationPath = $_SERVER['DOCUMENT_ROOT'] . DS . JXP_Application::getDirectories('applications');

					self::$_paths['views'] = $applicationPath . DS . $matches[1] . DS . 'views';

					$_vars['app']['assets'] = '/' . JXP_Application::getDirectories('applications') . '/' . $matches[1] . '/' . 'assets';
				}

			} else {

				if (strpos($tpl, 'jinxup:') !== false)
				{
					preg_match('/jinxup:{1,}(.*)/im', $tpl, $matches);

					$tpl = $matches[1];

					self::$_paths['views'] = dirname(__DIR__) . DS . 'views' . DS . 'shared';
				}
			}

			self::_viewInit($_vars, false);

			if (substr($tpl, -4) == '.php')
			{
				self::_viewInit($_vars, false);

				foreach (self::$_vars as $key => $val)
					$$key = $val;

				self::$_vars = [];

				require_once(self::$_paths['views'] . DS . ltrim($tpl, '/'));

			} else {

				self::_viewInit($_vars, true);

				try
				{
					self::$_smarty->display(self::$_paths['views'] . DS . ltrim($tpl, '/'));

				} catch (SmartyException $e) {

					$error = $e->getMessage();

					if (preg_match('/Syntax error in template/im', $error, $match))
					{
						self::set('error', $e->getMessage());
						self::_exitWith('template_syntax', __LINE__);

					} else {

						echo $e->getMessage();
					}
				}
			}
		}

		public static function setFilterFlag($flag = true)
		{
			self::$_regFilter = $flag;
		}
	}

	$path = dirname(__DIR__) . DS . 'engines' . DS . 'smarty' . DS . 'sysplugins';

	require_once($path . DS . 'smarty_resource.php');
	require_once($path . DS . 'smarty_internal_resource_file.php');

	class RecompileFileResource extends Smarty_Internal_Resource_File
	{
		public function populate(Smarty_Template_Source $source, Smarty_Internal_Template $_template = null)
		{
			parent::populate($source, $_template);

			$source->recompiled = true;
		}
	}