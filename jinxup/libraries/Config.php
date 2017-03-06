<?php

	class JXP_Config
	{
		private static $_config    = array();
		private static $_namespace = '\\';

		public static function load($config = '')
		{
		    if ($config != '') {

		        if (file_exists($config)) {

                    $contents = json_decode(self::_cleanCommentsFromJson($config), true);

                    if (is_array($contents))
                        self::$_config = self::_array_merge_recursive_distinct(self::$_config, $contents);
                }
            }

			return self::_translate(self::$_config);
		}

		private static function _array_merge_recursive_distinct(array &$array1, array &$array2)
        {
            $merged = $array1;

            foreach ($array2 as $key => &$value)
            {
                if (is_array($value) && isset($merged[$key]) && is_array($merged[$key]))
                {
                    if ($key == 'database') {

                        foreach ($value as $k => $v) {

                            $merged[$key] = self::_array_merge_recursive_distinct($merged[$key], $v);
                        }

                    } else {

                        $merged[$key] = self::_array_merge_recursive_distinct($merged[$key], $value);
                    }

                } else {

                    $merged[$key] = $value;
                }
            }

            return $merged;
        }

        public static function get($key = '') {

            return isset(self::$_config[$key]) ? self::$_config[$key] : self::$_config;
        }

		public static function setNamespace($ns)
		{
			self::$_namespace = '\\' . ltrim($ns, '\\');
		}

		public static function getNamespace()
		{
			return self::$_namespace == '\\' ? self::$_namespace : self::$_namespace . '\\';
		}

		private static function _cleanCommentsFromJson($file)
		{
			return preg_replace('@(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|((?<!:)//.*)|[\t\r\n]@i', '', file_get_contents($file));
		}

		private static function _translate($config)
		{
            foreach (self::$_config as $k => $v)
            {
                if (isset($v['import']))
                {
                    if (isset(self::$_config['settings']['setting'][$v['import']]))
                    {
                        self::$_config[$k];
                    }
                }
            }

			return $config;
		}

		private static function _array_change_key_case_recursive($arr, $case = CASE_LOWER)
		{
			return array_map(function($item) use($case) {

				if (is_array($item))
					$item = self::_array_change_key_case_recursive($item, $case);

				return $item;

			}, array_change_key_case($arr, $case));
		}
	}