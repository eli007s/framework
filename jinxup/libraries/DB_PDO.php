<?php

	class JXP_DB_PDO
	{
		private $_con    = null;
		private $_log    = array();
		private $_driver = null;
		private $_user   = null;
		private $_pass   = null;
		private $_hash   = null;

		public function __construct($driver, $user = null, $pass = null)
		{
			$this->_driver = $driver;
			$this->_user   = $user;
			$this->_pass   = $pass;

			if (is_null($this->_con))
			{
				try {

					if (strpos($this->_driver, 'sqlite') !== false)
						$this->_con = new PDO($this->_driver);
					else
						$this->_con = new PDO($this->_driver, $this->_user, $this->_pass);

					$this->_con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

				} catch (PDOException $e) {

					$this->_log['connection'] = $e;
				}
			}
		}

		public function getObj()
		{
			return isset($this->_con) ? $this->_con : null;
		}

		public function getHash($query, $bind)
		{
			return md5($query . json_encode($bind));
		}

		public function getLog($hash = null)
		{
			$hash = is_null($hash) ? $this->_hash : $hash;

			return !empty($this->_log[$hash]) ? $this->_log[$hash] : array();
		}

		public function clearLog($hash = null)
		{
			$hash = is_null($hash) ? $this->_hash : $hash;
			
			if (is_null($hash))
				$this->_log = array();
			else
				$this->_log[$hash] = array();
		}

		public function trimQuery($query)
		{
			return trim(preg_replace('/(\r\n|\s{2,})/m', ' ', $query));
		}

		public function previewQuery($query = null, $params = array())
		{
			$query  = $this->trimQuery($query);
			$keys   = array();
			$values = array();

			if (!empty($params))
			{
				foreach ($params as $key => $value)
				{
					if (!is_array($value))
					{
						$keys[]   = is_string($key) ? '/:' . $key . '/' : '/[?]/';
						$values[] = is_numeric($value) ? intval($value) : '"' . $value . '"';
					}
				}

				$query = preg_replace($keys, $values, $query, 1, $count);
			}

			return $query;
		}

		public function query($query, $bind = array())
		{
			$this->_hash = $this->getHash($query, $bind);

			$query  = $this->trimQuery($query);
			$return = $this->_runQuery($query, $bind, $this->_hash);

			return $return;
		}

		public function beginTransaction()
		{
			$this->_con->beginTransaction();
		}

		public function commit()
		{
			$this->_con->commit();
		}

		private function _runQuery($query, $bind, $hash)
		{
			$results = null;

			$this->_log[$hash]['hash']  = $this->_hash;
			$this->_log[$hash]['error'] = null;
			$this->_log[$hash]['query'] = array('raw' => $query, 'prewiew' => $this->previewQuery($query, $bind));

			try
			{
				if (!empty($this->_con))
				{
					$stmt = $this->_con->prepare($query);

					if (count($bind) > 0)
					{
						$this->_log[$hash]['tokens']['total'] = count($bind);

						preg_match_all("/(?<=\:)\w*/i", $query, $params);

						$params = array_map('array_values', array_map('array_filter', $params));

						$this->_prepareParameters($stmt, $bind, $params, $hash);
					}
					
					$execute = $stmt->execute();

					if ($execute !== false)
					{
						if (preg_match('/^(select|describe|call|drop|create|show)/im', $query))
							$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

						if (preg_match('/^(delete|update)/im', $query))
							$results = $stmt->rowCount();

						if (preg_match('/^insert/im', $query))
							$results = $this->_con->lastInsertId();
					
					} else {
						
						$this->_log[$hash]['error']['message'] = 'There was an error executing your query';
					}

				}  else {

					$this->_log[$hash]['error']['message'] = $this->_log['connection']->getMessage();
				}

			} catch (PDOException $e) {
				
				$debug = debug_backtrace();

				$this->_log[$hash]['error'] = array(
					'file'    => $debug[2]['file'],
					'line'    => $debug[2]['line'],
					'message' => $e->getMessage()
				);
			}

			return is_null($results) ? array() : $results;
		}

		private function _prepareParameters($stmt, $bind, $params, $hash)
		{
			foreach ($params as $key)
			{
				foreach ($key as $value)
				{
					if (isset($bind[$value]))
					{
						$param = null;
						$type  = null;

						if (is_string($bind[$value]))
						{
							$type  = 'STRING';
							$param = PDO::PARAM_STR;
						}

						if (is_null($bind[$value]) || empty($bind[$value]))
						{
							$type  = 'NULL';
							$param = PDO::PARAM_NULL;
						}

						if (is_numeric($bind[$value]))
						{
							$type  = 'INTEGER';
							$param = PDO::PARAM_INT;
						}

						if (is_bool($bind[$value]))
						{
							$type  = 'BOOLEAN';
							$param = PDO::PARAM_BOOL;
						}

						$arr = array(
							'name'  => $value,
							'value' => $bind[$value],
							'type'  => $type
						);

						$this->_log[$hash]['tokens']['bound'][] = $arr;

						if (!is_null($param))
							$stmt->bindValue(":{$value}", $bind[$value], $param);

					} else {

						$this->_log[$hash]['tokens']['unknown'][] = $value;
					}
				}
			}
		}
	}