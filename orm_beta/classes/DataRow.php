<?php

class DataRow{
	public $columns;
	public $values;
	public $id;
	public $id_column;
	public $key;
	public $key_column;

	public $id_exists;
	public $table;

	public function __construct($table, $id, $id_column, $key, $key_column, $columns = array(), $values = array())
	{
		$this->table = $table;
		$this->id = $id;
		$this->id_column = $id_column;

		$this->key = $key;
		$this->key_column = $key_column;

		$this->columns = $columns;
		$this->values = $values;
		
	}

	public function add_data($column, $value)
	{
		array_push($this->columns, $column);
		array_push($this->values, $value);
		$this->id_exists = $column == $this->id_column;//array_key_exists($this->id_column, $this->columns);

	}

	public function columns()
	{
		
		$_cols = $this->columns;
		array_push($_cols, $this->key_column);
		if ( ! $this->id_exists)
			array_push($_cols, $this->id_column);
		return $_cols;
	}

	public function values()
	{

		$_vals = $this->values;
		array_push($_vals, $this->key);
		if ( ! $this->id_exists)
			array_push($_vals, $this->id);
		return $_vals;
	}

	public function update_columns()
	{
		$_cols = $this->columns;
		// array_push($_cols, $this->key_column);s
		if ($this->id_exists)
			unset($_cols, $this->id_column);
		return $_cols;
	}

	public function update_values()
	{

		$_vals = $this->values;
		// array_push($_vals, $this->key);
		if ($this->id_exists)
			unset($_vals, $this->id);
		return $_vals;
	}


}