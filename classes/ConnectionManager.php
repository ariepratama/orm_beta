<?php
// tidak perlu? harusnya pakai module 'database' dari Kohana

class ConnectionManager{
	private $connection;
	
	public function __construct()
	{
	
	}
	
	public function _connect($app_name, $dbms = 'MySql', $db_username = 'root', $db_password = '')
	{
		
		$this->connection = mysql_connect();
	}
}