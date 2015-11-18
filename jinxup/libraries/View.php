<?php

	class JXP_View
	{
		private static $_engine = array('smarty');

		public static function render($view)
		{
			self::_engine();


		}

		private static function _engine()
		{
			$engine = JXP_Config::getView();

			if (!empty($engine))
				self::$_engine = $engine;

			//echo '<pre>', print_r(debug_backtrace(), true), '</pre>';

		}
	}