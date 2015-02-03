<?php

	class JXP_Curl
	{
		private static $_opt      = array();
		private static $_init     = null;
		private static $_error    = 0;
		private static $_message  = null;
		private static $_response = null;

		public static function into($url = null)
		{
			self::$_init = null;

			if (!is_null($url) && !is_array($url))
			{
				self::$_error    = 0;
				self::$_response = null;

				$opt['CURLOPT_URL']            = $url;
				$opt['CURLOPT_RETURNTRANSFER'] = 1;
				$opt['CURLOPT_FOLLOWLOCATION'] = 1;
				$opt['CURLOPT_AUTOREFERER']    = 1;
				$opt['CURLOPT_CONNECTTIMEOUT'] = 120;
				$opt['CURLOPT_TIMEOUT']        = 120;
				$opt['CURLOPT_MAXREDIRS']      = 10;
				$opt['CURLOPT_SSL_VERIFYHOST'] = 0;
				$opt['CURLOPT_SSL_VERIFYPEER'] = 0;
				$opt['CURLOPT_VERBOSE']        = 1;

				self::$_opt = $opt;

			} else {

				if (is_array($url))
					self::$_message = 'Multiple URLs not yet supported.';
				else
					self::$_message = 'Please enter a valid URL';
			}

			if (is_null(self::$_init))
				self::$_init = new self();

			return self::$_init;
		}

		public static function withPost($post = array())
		{
			if (empty($post))
			{
				if (!is_string($post))
					$post = $_POST;
			}

			$post = JXP_Format::trimSpaces($post);
			$post = is_array($post) ? http_build_query($post) : urlencode($post);

			self::$_opt['CURLOPT_POST']       = 1;
			self::$_opt['CURLOPT_POSTFIELDS'] = $post;

			return self::$_init;
		}

		public static function withGet($get = array())
		{
			if (!is_array($get))
			{

			}

			return self::$_init;
		}

		public static function withOpt($opt = array(), $value = null)
		{
			if (is_array($opt) && !empty($opt))
				self::$_opt = array_merge(self::$_opt, $opt);
			else
				self::$_opt[(string) $opt] = $value;

			return self::$_init;
		}

		public static function run()
		{
			if (is_null(self::$_message))
			{
				$ch = curl_init();

				foreach (self::$_opt as $key => $val)
					curl_setopt($ch, constant($key), $val);

				self::$_response = curl_exec($ch);
				self::$_error    = curl_errno($ch);

				curl_close($ch);

				return strlen(self::$_response) == 0 && self::$_error > 0 ? self::_errorCode(self::$_error) : self::$_response;

			} else {

				return self::$_message;
			}
		}

		public static function getResponse()
		{

		}

		public static function getError()
		{
			return self::$_error === 0 ? 0 : self::$_error . ' ' . self::_errorCode(self::$_error);
		}

		private static function _errorCode($code = 0)
		{
			$errorCodes = array(
				1  => 'CURLE_UNSUPPORTED_PROTOCOL',
				2  => 'CURLE_FAILED_INIT',
				3  => 'CURLE_URL_MALFORMAT',
				4  => 'CURLE_URL_MALFORMAT_USER',
				5  => 'CURLE_COULDNT_RESOLVE_PROXY',
				6  => 'CURLE_COULDNT_RESOLVE_HOST',
				7  => 'CURLE_COULDNT_CONNECT',
				8  => 'CURLE_FTP_WEIRD_SERVER_REPLY',
				9  => 'CURLE_REMOTE_ACCESS_DENIED',
				11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
				13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
				14 => 'CURLE_FTP_WEIRD_227_FORMAT',
				15 => 'CURLE_FTP_CANT_GET_HOST',
				17 => 'CURLE_FTP_COULDNT_SET_TYPE',
				18 => 'CURLE_PARTIAL_FILE',
				19 => 'CURLE_FTP_COULDNT_RETR_FILE',
				21 => 'CURLE_QUOTE_ERROR',
				22 => 'CURLE_HTTP_RETURNED_ERROR',
				23 => 'CURLE_WRITE_ERROR',
				25 => 'CURLE_UPLOAD_FAILED',
				26 => 'CURLE_READ_ERROR',
				27 => 'CURLE_OUT_OF_MEMORY',
				28 => 'CURLE_OPERATION_TIMEDOUT',
				30 => 'CURLE_FTP_PORT_FAILED',
				31 => 'CURLE_FTP_COULDNT_USE_REST',
				33 => 'CURLE_RANGE_ERROR',
				34 => 'CURLE_HTTP_POST_ERROR',
				35 => 'CURLE_SSL_CONNECT_ERROR',
				36 => 'CURLE_BAD_DOWNLOAD_RESUME',
				37 => 'CURLE_FILE_COULDNT_READ_FILE',
				38 => 'CURLE_LDAP_CANNOT_BIND',
				39 => 'CURLE_LDAP_SEARCH_FAILED',
				41 => 'CURLE_FUNCTION_NOT_FOUND',
				42 => 'CURLE_ABORTED_BY_CALLBACK',
				43 => 'CURLE_BAD_FUNCTION_ARGUMENT',
				45 => 'CURLE_INTERFACE_FAILED',
				47 => 'CURLE_TOO_MANY_REDIRECTS',
				48 => 'CURLE_UNKNOWN_TELNET_OPTION',
				49 => 'CURLE_TELNET_OPTION_SYNTAX',
				51 => 'CURLE_PEER_FAILED_VERIFICATION',
				52 => 'CURLE_GOT_NOTHING',
				53 => 'CURLE_SSL_ENGINE_NOTFOUND',
				54 => 'CURLE_SSL_ENGINE_SETFAILED',
				55 => 'CURLE_SEND_ERROR',
				56 => 'CURLE_RECV_ERROR',
				58 => 'CURLE_SSL_CERTPROBLEM',
				59 => 'CURLE_SSL_CIPHER',
				60 => 'CURLE_SSL_CACERT',
				61 => 'CURLE_BAD_CONTENT_ENCODING',
				62 => 'CURLE_LDAP_INVALID_URL',
				63 => 'CURLE_FILESIZE_EXCEEDED',
				64 => 'CURLE_USE_SSL_FAILED',
				65 => 'CURLE_SEND_FAIL_REWIND',
				66 => 'CURLE_SSL_ENGINE_INITFAILED',
				67 => 'CURLE_LOGIN_DENIED',
				68 => 'CURLE_TFTP_NOTFOUND',
				69 => 'CURLE_TFTP_PERM',
				70 => 'CURLE_REMOTE_DISK_FULL',
				71 => 'CURLE_TFTP_ILLEGAL',
				72 => 'CURLE_TFTP_UNKNOWNID',
				73 => 'CURLE_REMOTE_FILE_EXISTS',
				74 => 'CURLE_TFTP_NOSUCHUSER',
				75 => 'CURLE_CONV_FAILED',
				76 => 'CURLE_CONV_REQD',
				77 => 'CURLE_SSL_CACERT_BADFILE',
				78 => 'CURLE_REMOTE_FILE_NOT_FOUND',
				79 => 'CURLE_SSH',
				80 => 'CURLE_SSL_SHUTDOWN_FAILED',
				81 => 'CURLE_AGAIN',
				82 => 'CURLE_SSL_CRL_BADFILE',
				83 => 'CURLE_SSL_ISSUER_ERROR',
				84 => 'CURLE_FTP_PRET_FAILED',
				85 => 'CURLE_RTSP_CSEQ_ERROR',
				86 => 'CURLE_RTSP_SESSION_ERROR',
				87 => 'CURLE_FTP_BAD_FILE_LIST',
				88 => 'CURLE_CHUNK_FAILED'
			);

			return $errorCodes[$code];
		}
	}