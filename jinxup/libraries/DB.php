<?php

	class JXP_DB
	{
		/**
		 * @var array
		 * @access private
		 */
		private static $_database = array();

		/**
		 * @var null
		 * @access private
		 */
		private static $_alias = null;

		/**
		 * @var null
		 * @access public
		 */
		public $alias = 1;

		/**
		 * Return the alias used for this instance
		 */
		public function __construct()
		{
			$this->alias = self::$_alias;
		}

		/**
		 * @param string $alias
		 * @param string $host
		 * @param string $name
		 * @param string $user
		 * @param string $pass
		 * @param int $port
		 * @return object
		 */
		public static function fuel($alias, $host = null, $name = null, $user = null, $pass = null, $port = 3306)
		{
			$fuel = array('host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass, 'port' => $port);

			return self::ignite($alias, $fuel);
		}

		public static function sqlite($storage = null, $alias = null)
		{
			$fuel = array('storage' => $storage, 'driver' => 'sqlite');

			return self::ignite($alias, $fuel);
		}

		public static function using($alias)
		{
			return self::ignite($alias);
		}

		/**
		 * @param string $alias
		 * @param array $fuel
		 */
		public static function ignite($alias = null, $fuel = array())
		{
			$config  = !empty($fuel) ? array($alias => $fuel) : JXP_Config::get('database');
			$host    = '';
			$name    = '';
			$user    = '';
			$pass    = '';
			$file    = '';
			$port    = 3306;
			$storage = '';
			$driver  = 'PDO';
			$_alias  = null;
			$_driver = null;

			if (isset($config[$alias]))
				extract($config[$alias]);

			switch (strtolower($driver))
			{
				case 'mssql':

					$_driver = "mssql:server=" . $host . ";database=" . $name;

					break;

				case 'sqlite':

					if (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite'))
					{
						$file    .= !empty($storage) || !is_null($storage) ? getcwd() . DS . trim($storage, '/') : ':memory:';
						$_driver  = "sqlite:{$file}";

					} else {

						echo 'SQLite is not loaded';
					}

					break;

				case 'pdo':
				default:

					$_driver = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $name;

					break;
			}

			self::$_database[$alias] = !is_null($_driver) ? new JXP_DB_PDO($alias, $_driver, $user, $pass) : array();

			return self::$_database[$alias];
		}

		public static function connections()
		{
			return self::$_database;
		}

		public static function connection($alias = null)
		{
			$return = null;

			if (!is_null($alias))
				$return = self::$_database[$alias];

			return $return;
		}

		/**
		 * @param string $alias
		 * @param array $params
		 * @return object
		 */
		public static function __callStatic($alias, $params)
		{
			self::$_alias = $alias;

			if (!isset(self::$_database[$alias]))
				self::ignite($alias);

			$return = null;
			$dbObj  = self::$_database[$alias];

			if (method_exists($dbObj, $alias))
			{
				if (count($params) == 4)
					$return = $dbObj->{$alias}($params[0], $params[1], $params[2], $params[3]);
				else if (count($params) == 3)
					$return = $dbObj->{$alias}($params[0], $params[1], $params[2]);
				else if (count($params) == 2)
					$return = $dbObj->{$alias}($params[0], $params[1]);
				else if (count($params) == 1)
					$return = $dbObj->{$alias}($params[0]);
				else
					$return = $dbObj->{$alias}();

			} else {

				if (method_exists($dbObj->getConnection(), $alias))
				{
					$dbObj = $dbObj->getConnection();

					if (count($params) == 4)
						$return = $dbObj->{$alias}($params[0], $params[1], $params[2], $params[3]);
					else if (count($params) == 3)
						$return = $dbObj->{$alias}($params[0], $params[1], $params[2]);
					else if (count($params) == 2)
						$return = $dbObj->{$alias}($params[0], $params[1]);
					else if (count($params) == 1)
						$return = $dbObj->{$alias}($params[0]);
					else
						$return = $dbObj->{$alias}();

				} else {

					if (!empty($params))
					{
						if (count($params) == 4)
							$return = $dbObj->query($params[0], $params[1], $params[2], $params[3]);
						else if (count($params) == 3)
							$return = $dbObj->query($params[0], $params[1], $params[2]);
						else if (count($params) == 2)
							$return = $dbObj->query($params[0], $params[1]);
						else if (count($params) == 1)
							$return = $dbObj->query($params[0]);
						else
							$return = $dbObj->query();

					} else {

						$return = new self();
					}
				}
			}

			return $return;
		}

		/**
		 * @param string $alias
		 * @param array $params
		 * @return object
		 */
		public function __call($alias, $params)
		{
			$dbObj  = self::$_database[self::$_alias];
			$return = null;

			if (method_exists($dbObj, $alias))
			{
				if (count($params) == 4)
					$return = $dbObj->{$alias}($params[0], $params[1], $params[2], $params[3]);
				else if (count($params) == 3)
					$return = $dbObj->{$alias}($params[0], $params[1], $params[2]);
				else if (count($params) == 2)
					$return = $dbObj->{$alias}($params[0], $params[1]);
				else if (count($params) == 1)
					$return = $dbObj->{$alias}($params[0]);
				else
					$return = $dbObj->{$alias}();

			} else {

				if (method_exists($dbObj->getConnection(), $alias))
				{
					$dbObj = $dbObj->getConnection();

					if (count($params) == 4)
						$return = $dbObj->{$alias}($params[0], $params[1], $params[2], $params[3]);
					else if (count($params) == 3)
						$return = $dbObj->{$alias}($params[0], $params[1], $params[2]);
					else if (count($params) == 2)
						$return = $dbObj->{$alias}($params[0], $params[1]);
					else if (count($params) == 1)
						$return = $dbObj->{$alias}($params[0]);
					else
						$return = $dbObj->{$alias}();

				} else {

					$return = $dbObj;
				}
			}

			return $return;
		}

		/**
		 * @desc Destroy connection
		 * @param string $alias
		 */
		public static function destroy($alias = null)
		{
			if (is_null($alias))
				unset(self::$_database);
			else
				unset(self::$_database[$alias]);
		}
	}