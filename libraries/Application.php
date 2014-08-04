<?php

	class JXP_Application
	{
		public function getController()
		{
			$routes = Jinxup::getRoutes();

			return str_replace('_Controller', '', $routes['controller']);
		}

		public static function getModel()
		{
			return self::getController();
		}
	}