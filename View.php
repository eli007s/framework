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
				echo '<pre>', print_r($return, true), '</pre>';
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
					'paths'      => self::getWebPaths(),
					'param'      => self::getParams()
				);

				$_jxp = array(
					'session' => isset($_SESSION) ? $_SESSION : array(),
					'uri'     => array('getRequestURI' => self::getRequestURI()),
					'tracker' => array('getIP' => JXP_Tracker::getIP())
				);

				self::$_smarty->assign('app', $_app);
				self::$_smarty->assign('jxp', $_jxp);
				self::$_smarty->setTemplateDir(self::$_tplPath);
				self::$_smarty->setPluginsDir(self::$_tplPath . DS . 'plugins');
				self::$_smarty->registerFilter('pre', array(new self(), 'templateInjection'));
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

		public static function templateInjection($tpl_source, Smarty_Internal_Template $template)
		{
			preg_match_all('/(<form\s+([csfr]+)>)/im', $tpl_source, $matches);
			//echo '<pre>', print_r($matches, true), '</pre>';
			/*if (!empty($matches[1]))
			{
				$matches = JXP_Format::trimSpaces($matches[1]);

				foreach ($matches[1] as $key => $val)
				{
					$attrs = explode(' ', $matches);

					if (!empty($attrs))
					{
						foreach ($attrs as $attr)
						{
							$part = explode('=', $attr);

							if (!empty($part[1]))
							{
								if (strtolower($part[0]) == 'csfr')
								{
									if (strlen($part[1]) > 0)
									{
										$csfrName    = preg_replace('/\\\'|"/', '', $part[1]);
										$csfrToken   = null;
										$hiddenInput = $matches[0][$key] . '
											<input type="hidden" name="CSFRName" value="' . $csfrName . '" />
											<input type="hidden" name="CSFRToken" value="' . $csfrToken . '" />
										';

										$tpl_source = preg_replace('/<\\/form>/im', $hiddenInput, $tpl_source);
									}
								}
							}
						}
					}
				}
			}*/

			return $tpl_source;
		}

		public static function addPushCode($tpl_source)
		{
			$app    = self::_app();
			$pushJS = null;
			$uid    = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : time();

			if (isset($app['account_id']))
			{
				$pushJS = "
<script type='text/javascript' src='//cdn.pubnub.com/pubnub.min.js'></script>
<script type='text/javascript'>
	var pubnub = PUBNUB.init({

		windowing     : 1500,
		publish_key   : 'pub-c-aca8b9bf-46bc-416a-950e-0a4cb65d7a71',
		subscribe_key : 'sub-c-2e78c956-e9d3-11e3-92e7-02ee2ddab7fe',
		uuid          : '{$uid}'
	});

	pubnub.subscribe({

		channel : '{$app['app_id']}',
		message: function (message, env, channel) {}
	});
</script>";
			}

			$pushJS .= '</body>';

			return preg_replace('/<\\/body>/im', $pushJS, $tpl_source);
		}

		private static function _app()
		{
			$app = self::$_params['app'];

			return $app;
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