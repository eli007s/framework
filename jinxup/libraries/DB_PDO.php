<?php

	class JXP_DB_PDO
	{
		private $_con       = null;
		private $_log       = array();
		private $_driver    = null;
		private $_user      = null;
		private $_pass      = null;
		private $_hash      = null;
		private $_alias     = null;
		private $_fetchMode = PDO::FETCH_ASSOC;
		private $_mute      = false;

		public function __construct($alias, $driver, $user = null, $pass = null)
		{
			$this->_alias  = $alias;
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

		public function mute($mute = false)
		{
			$this->_mute = $mute;
		}

		public function getDSN()
		{
			return $this->_driver;
		}

		public function setFetchMode($mode = 'assoc')
		{
			$mode = strtolower($mode);

			if ($mode == 'assoc')
				$this->_fetchMode = PDO::FETCH_ASSOC;
			else if ($mode == 'object')
				$this->_fetchMode = PDO::FETCH_OBJ;
			else
				$this->_fetchMode = PDO::FETCH_ASSOC;

			return $this;
		}

		public function getObj()
		{
			return isset($this->_con) ? $this->_con : null;
		}

		public function getHash($query, $bind)
		{
			return md5($query . json_encode($bind));
		}

		public function results($hash = null)
		{
			$hash = is_null($hash) ? $this->_hash : $hash;

			return isset($this->_log[$hash]['results']) ? $this->_log[$hash]['results'] : array();
		}

		public function log($hash = null)
		{
			$hash = is_null($hash) ? $this->_hash : $hash;

			return $this->_log[$hash];
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

				$query = preg_replace($keys, $values, $query);
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
			$debug = debug_backtrace();

			$callerIdx['file']     = 0;
			$callerIdx['line']     = 0;
			$callerIdx['class']    = 0;
			$callerIdx['function'] = 0;

			if (isset($debug[3]) && $debug[3]['function'] == '_loadApplication')
			{
				$callerIdx['file']     = 1;
				$callerIdx['line']     = 2;
				$callerIdx['class']    = 2;
				$callerIdx['function'] = 2;
			}

			if (isset($debug[5]) && $debug[5]['function'] == '_loadApplication')
			{
				$callerIdx['file']     = 3;
				$callerIdx['line']     = 4;
				$callerIdx['class']    = 4;
				$callerIdx['function'] = 4;
			}

			if (isset($debug[6]) && $debug[6]['function'] == '_loadApplication')
			{
				$callerIdx['file']     = 3;
				$callerIdx['line']     = 3;
				$callerIdx['class']    = 4;
				$callerIdx['function'] = 4;
			}

			if (isset($debug[7]) && $debug[7]['function'] == '_loadApplication')
			{
				$callerIdx['file']     = 4;
				$callerIdx['line']     = 4;
				$callerIdx['class']    = 6;
				$callerIdx['function'] = 5;
			}

			if (isset($debug[8]) && $debug[8]['function'] == '_loadApplication')
			{
				$callerIdx['file']     = 3;
				$callerIdx['line']     = 3;
				$callerIdx['class']    = 4;
				$callerIdx['function'] = 4;
			}

			if (isset($debug[9]) && $debug[9]['function'] == '_loadApplication')
			{
				$callerIdx['file']     = 5;
				$callerIdx['line']     = 5;
				$callerIdx['class']    = 6;
				$callerIdx['function'] = 6;
			}

			if (isset($debug[11]) && $debug[11]['function'] == '_loadApplication')
			{
				$callerIdx['file']     = 8;
				$callerIdx['line']     = 8;
				$callerIdx['class']    = 6;
				$callerIdx['function'] = 6;
			}

			$results  = null;
			$starTime = microtime(true);
			$endTime  = 0;

			$this->_log[$hash]['alias']  = $this->_alias;
			$this->_log[$hash]['hash']   = $this->_hash;
			$this->_log[$hash]['error']  = null;
			$this->_log[$hash]['time']   = 0;

			if (!$this->_mute)
			{
				$this->_log[$hash]['caller'] = array(
					'file'     => $debug[$callerIdx['file']]['file'],
					'line'     => $debug[$callerIdx['line']]['line'],
					'class'    => $debug[$callerIdx['class']]['class'],
					'function' => $debug[$callerIdx['function']]['function']
				);
			}

			$this->_log[$hash]['query']  = array('raw' => $query, 'preview' => $this->previewQuery($query, $bind));

			try
			{
				if (!empty($this->_con))
				{
					$stmt = $this->_con->prepare($query);

					if (count($bind) > 0)
					{
						$this->_log[$hash]['tokens']['total'] = count($bind);

						preg_match_all('/(?<=\:)\w*/im', $query, $params);

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
					
						$endTime = microtime(true);

					} else {

						$this->_log[$hash]['error']['message'] = 'There was an error executing your query';
					}

				}  else {

					$this->_log[$hash]['error']['message'] = $this->_log['connection']->getMessage();
				}

			} catch (PDOException $e) {
				
				$endTime = microtime(true);
				$debug   = debug_backtrace();

				if(!$this->_mute)
				{
					$this->_log[$hash]['error'] = array(
						'file'    => $debug[2]['file'],
						'line'    => $debug[2]['line'],
						'message' => $e->getMessage()
					);
				}
			}

			$this->_log[$hash]['time'] = $endTime - $starTime;

			if (is_null($this->_log[$hash]['error']))
				unset($this->_log[$hash]['error']);

			$this->_log[$hash]['results'] = $results;

			return $results;
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

						$stmt->bindValue(":{$value}", $bind[$value], $param);

					} else {

						$this->_log[$hash]['tokens']['unknown'][] = $value;
					}
				}
			}
		}
	}