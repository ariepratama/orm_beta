<?php

class Attribute_External{
	public $extern_column;
	public $extern_table;
	public $db_type;
	public $relate_with_object;

	public function __construct($ec, $et, $dbt, $is_object)
	{
		$this->extern_column = $ec;
		$this->extern_table = $et;
		$this->db_type = $dbt;
		$this->relate_with_object = $is_object;
	}

	public function is_rel_with_object()
	{
		return $this->relate_with_object ;
	}
}