<?php
class Attribute_Meta{
	public $_name;
	
	public $_db_type;
	public $_app_type;

	public $_table;
	public $_rel_with;
	public $_rel_type;


	public function _construct($name, $db_type, $app_type, $table, $rel_with, $rel_type){
		$this->_name = $name;
		
		$this->_db_type = $db_type ;
		$this->_app_type = $app_type;

		$this->_table = $table ;
		$this->_rel_with = $rel_with ;
		$this->_rel_type = $rel_type ;
	}

	public function as_array(){
		return array($this->_name,
					 $this->_db_type,
					 $this->_app_type,
					 $this->_table,
					 $this->_rel_with,
					 $this->_rel_type);
	}
}