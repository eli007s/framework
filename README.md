Installation
-------------
Make sure that AllowOverride All is toggled for Apache Installations

- Drop Jinxup into your docment root

Usage
------
Taking a sample url

http://mydomain.com/somepage/someaction/param1/param2

- Controller: somepage
- Action    : someaction
- Param1    : param1
- Param2    : param2

To create a controller that will server content for the exmaple url, make a new file called "someaction.php" or "someaction_controller.php" or "csomeaction.php"

someaction.php

<?php

	class SomePage_Controller
	{
		public function someactionAction($userId = 0)
		{
			$params = Jinxup::getParams();

			// The first param is passed as a function argument if any
			// Alternatively $params[0] will also contain the first parameter following the action call
			echo 'UserId: ' . $userId;
			echo '<br />';
			// Gets the second param in the url following the action call
			echo 'Param2: ' . $params[1];
		
			// Create an associative array of the parameters in the url
			$assoc  = Jinxup::assocParams();
		
			echo '<pre>', print_r($assoc, true), '</pre>';
		
			/*
			 * will return
			 * 
			 * array (
			 *  param1 => param2
			 * )
			 */

			if ($userId > 0)
			{
				echo 'Pulling data for user ' . $userId;

				// Fuel database connection, alternatively connection parameters can be stored in the global config file
				// skipping the "fuel" altogether
				// "alias", "host", "dbname", "user", "pass"
				JXP_DB::fuel('test', 'host', 'dbname', 'user', 'pass');

				// Connection "test" can now be used
				$bind = array('uid' => $userId);
				$user = JXP_DB::test('select * from users where userId = :uid', $bind);
			
				// Get query log information for last query ran. Contains any errors returned by PDO, the query sent,
				// and a preview of the query with bound parameters filled in.
				$log = JXP_DB::getLog();

				// Set view variable
				JXP_View::set('user', $user);

				// Load view relative to the view directory i.e /applications/{app}/views/
				JXP_View::render('path/to/view.tpl');

			} else {

				echo 'Invalid user';
			}
		}
	}
