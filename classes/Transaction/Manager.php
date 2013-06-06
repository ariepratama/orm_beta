<?php

class Transaction_Manager {
	private $_pending_query;

	public function __construct()
	{
		$this->$_pending_query = array();
	}

	public function add_query($query)
	{
		
	}
}