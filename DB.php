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
		 * $param string $alias
		 * @param string $host
		 * @param string $name
		 * @param string $user
		 * @param string $pass
		 * @param int $port
		 * @return mixed
		 */
		public static function fuel($alias, $host = null, $name = null, $user = null, $pass = null, $port = 3306)
		{
			$fuel = array('host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass, 'port' => $port);

			self::ignite($alias, $fuel);

			return self::$_database[self::$_alias];
		}

		public static function sqlite($storage = null, $alias = null)
		{
			$fuel = array('storage' => $storage, 'driver' => 'sqlite');

			self::ignite($alias, $fuel);

			return self::$_database[self::$_alias];
		}

		/**
		 * @param string $alias
		 * @param array $fuel
		 */
		public static function ignite($alias = null, $fuel = array())
		{
			if (!isset(self::$_database[self::$_alias]))
			{
				if (!method_exists('JXP_DB_PDO', $alias))
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
								if (!empty($storage) || !is_null($storage))
									$file .= getcwd() . DS . trim($storage, '/');
								else
									$file .= ':memory:';

								$_driver = "sqlite:{$file}";

							} else {

								echo 'SQLite is not loaded';
							}

							break;

						case 'pdo':
						default:

							$_driver = "mysql:host=" . $host . ";port=" . $port . ";dbname=" . $name;

							break;
					}

					if (!is_null($_driver))
						self::$_database[self::$_alias] = new JXP_DB_PDO($_driver, $user, $pass);
				}
			}
		}

		/**
		 * @param string $name
		 * @param array $params
		 * @return object
		 */
		public static function __callStatic($name, $params)
		{
			self::ignite($name);

			$return = null;

			if (isset(self::$_database[self::$_alias]))
			{
				$dbObj = self::$_database[self::$_alias];

				if (method_exists($dbObj, $name))
				{
					switch (count($params))
					{
						case 1:

							$return = $dbObj->{$name}($params[0]);

							break;

						case 2:

							$return = $dbObj->{$name}($params[0], $params[1]);

							break;

						case 3:

							$return = $dbObj->{$name}($params[0], $params[1], $params[2]);

							break;

						case 4:

							$return = $dbObj->{$name}($params[0], $params[1], $params[2], $params[3]);

							break;

						default:

							$return = $dbObj->{$name}();

							break;
					}

				} else {

					if (method_exists($dbObj->getObj(), $name))
					{
						switch (count($params))
						{
							case 1:

								$return = $dbObj->getObj->{$name}($params[0]);

								break;

							case 2:

								$return = $dbObj->getObj->{$name}($params[0], $params[1]);

								break;

							case 3:

								$return = $dbObj->getObj->{$name}($params[0], $params[1], $params[2]);

								break;

							case 4:

								$return = $dbObj->getObj->{$name}($params[0], $params[1], $params[2], $params[3]);

								break;

							default:

								$return = $dbObj->getObj->{$name}();

								break;
						}

					} else {

						if (!empty($params))
						{
							switch (count($params))
							{
								case 1:

									$return = $dbObj->query($params[0]);

									break;

								case 2:

									$return = $dbObj->query($params[0], $params[1]);

									break;

								case 3:

									$return = $dbObj->query($params[0], $params[1], $params[2]);

									break;

								case 4:

									$return = $dbObj->query($params[0], $params[1], $params[2], $params[3]);

									break;

								default:

									$return = $dbObj->query();

									break;
							}

						} else {

							$return = new self();
						}
					}
				}
			}

			return $return;
		}

		/**
		 * @param string $name
		 * @param array $params
		 * @return object
		 */
		public function __call($name, $params)
		{
			$dbObj  = self::$_database[self::$_alias];
			$return = null;

			if (method_exists($dbObj, $name))
			{
				switch (count($params))
				{
					case 1:

						$return = $dbObj->{$name}($params[0]);

						break;

					case 2:

						$return = $dbObj->{$name}($params[0], $params[1]);

						break;

					case 3:

						$return = $dbObj->{$name}($params[0], $params[1], $params[2]);

						break;

					case 4:

						$return = $dbObj->{$name}($params[0], $params[1], $params[2], $params[3]);

						break;

					default:

						$return = $dbObj->{$name}();

						break;
				}

			} else {

				if (method_exists($dbObj->getObj(), $name))
				{
					switch (count($params))
					{
						case 1:

							$return = $dbObj->getObj()->{$name}($params[0]);

							break;

						case 2:

							$return = $dbObj->getObj()->{$name}($params[0], $params[1]);

							break;

						case 3:

							$return = $dbObj->getObj()->{$name}($params[0], $params[1], $params[2]);

							break;

						case 4:

							$return = $dbObj->getObj()->{$name}($params[0], $params[1], $params[2], $params[3]);

							break;

						default:

							$return = $dbObj->getObj()->{$name}();

							break;
					}

				} else {

					$return = $dbObj;
				}
			}

			return $return;
		}

		/**
		 * @desc Destroy connection
		 */
		public static function destroy()
		{
			unset(self::$_database);
		}
	}