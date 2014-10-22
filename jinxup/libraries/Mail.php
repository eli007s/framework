<?php

	require_once(dirname(__DIR__) . DS . 'vendors' . DS . 'swift' . DS . 'swift_required.php');

	class JXP_Mail
	{
		private static $_mailer  = null;
		private static $_message = null;

		public static function __callStatic($name, $params)
		{
			if (is_null(self::$_mailer))
			{
				self::$_mailer  = Swift_Mailer::newInstance(Swift_MailTransport::newInstance());
				self::$_message = Swift_Message::newInstance();
			}

			if (strtolower($name) !== 'send')
			{
				if (strtolower($name) == 'tostring')
					return self::$_message->toString();
				else
					call_user_func_array(array(self::$_message, $name), $params);
			}

			return new self();
		}

		public function __call($name, $params)
		{
			if (strtolower($name) == 'send')
			{
				return self::$_mailer->send(self::$_message);

			} else {

				if (strtolower($name) == 'tostring')
					return self::$_message->toString();
				else
					call_user_func_array(array(self::$_message, $name), $params);

				return new self();
			}
		}
	}