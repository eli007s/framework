<?php

	class JXP_Session implements SessionHandlerInterface
	{
		private $_client;
		private $_ttl;
		private $_prefix;

		public function __construct($client, $prefix = 'JINXUP_', $ttl = 3600)
		{
			$this->_client = $client;
			$this->_prefix = $prefix;
			$this->_ttl    = $ttl;
		}

		public function open($savePath, $sessionName) {}

		public function close() {}

		public function read($id)
		{
			$id   = $this->_prefix . $id;

			if ($this->_client instanceof Predis\Client)
				$data = $this->_client->get($id);

			if ($this->_client instanceof Predis\Client)
				$this->_client->expire($id, $this->_ttl);

			return $data;
		}

		public function write($id, $data)
		{
			$id = $this->_prefix . $id;

			if ($this->_client instanceof Predis\Client)
				$this->_client->set($id, $data);

			if ($this->_client instanceof Predis\Client)
				$this->_client->expire($id, $this->_ttl);
		}

		public function destroy($id)
		{
			$id = $this->_prefix . $id;

			if ($this->_client instanceof Predis\Client)
				$this->_client->del($id);

			$bind   = array('id' => session_id());
			$config = Jinxup::config();
			$db     = $config['database']['jinxup'];

			JXP_DB::jinxup('UPDATE users SET session_id = null WHERE session_id = :id', $bind);

			// TODO: user pusher to alert the user they been logged off
		}

		public function gc($maxLifeTime) {}
	}