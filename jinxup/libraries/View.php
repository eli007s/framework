<?php

	class JXP_View extends Jinxup
	{
		private static $_smarty    = null;
		private static $_tplPath   = 'views';
		private static $_config    = array();
		private static $_params    = array();
		private static $_regFilter = true;

		public static function __callStatic($name, $params)
		{
			self::viewInit();

			$return = null;

			try
			{
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

				echo $return;
			}

			return $return;
		}

		public static function setTplPath($path)
		{
			self::$_tplPath = $path;
		}

		public static function setConfig($config, $app)
		{
			if (isset($app['views']))
				self::$_tplPath = $app['views'];

			self::$_config = $config;
		}

		public static function viewInit()
		{
			if (is_null(self::$_smarty))
			{
				require_once(dirname(__DIR__) . DS . 'engines' . DS . 'smarty' . DS . 'Smarty.class.php');

				self::$_smarty = new Smarty();

				self::$_params['app'] = self::getApp();

				self::$_smarty->left_delimiter  = '{!';
				self::$_smarty->right_delimiter = '!}';

				if (isset(self::$_config['template']['delimiters']['left']))
					self::$_smarty->left_delimiter = self::$_config['template']['delimiters']['left'];

				if (isset(self::$_config['template']['delimiters']['right']))
					self::$_smarty->right_delimiter = self::$_config['template']['delimiters']['right'];

				$_app = array(
					//'active'     => self::getActiveApp(),
					'action'     => self::getActionCall(),
					'controller' => self::getController(),
					'path'       => self::getWebPaths(),
					'param'      => self::getParams()
				);

				$_jxp = array(
					'session' => isset($_SESSION) ? $_SESSION : array(),
					'post'    => !empty($_POST) ? $_POST : array(),
					'uri'     => array('getRequestURI' => self::getRequestURI()),
					'tracker' => array('getIP' => JXP_Tracker::getIP())
				);

				self::$_smarty->assign('app', $_app);
				self::$_smarty->assign('jxp', $_jxp);
				self::$_smarty->setTemplateDir(self::$_tplPath);
				self::$_smarty->setPluginsDir(self::$_tplPath . DS . 'plugins');
				self::$_smarty->registerResource('file', new RecompileFileResource());
			}

			return new self();
		}

		public static function set($key, $val)
		{
			self::viewInit();

			self::$_smarty->assign($key, $val);
		}

		public static function render($tpl)
		{
			self::viewInit();

			self::$_smarty->display(self::$_tplPath . DS . $tpl);
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