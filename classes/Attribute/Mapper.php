<?php

class Attribute_Mapper{
	
	private static $mapper;

	private static init()
	{
		Attribute_Mapper::$mapper = array(
		// map string in application to text in db
			'string' => array('db_type' => 'text', 'rel_type' => null),
		// map integer to integer
			'integer' => array('db_type' => 'int', 'rel_type' => null),
		// map floating point (in php float and double are the same) to doubles
			'double' => array('db_type' => 'double', 'rel_type' => null),
		// map object to one to one relation, and array to one to many relation
			'object' => array('db_type' => 'relation', 'rel_type' => 'one_to_one'),
			'array' =>  array('db_type' => 'relation', 'rel_type' => 'one_to_many'),
		);	
		
	}
	
	public static function map_attributes()
	{

		
	}
	
}