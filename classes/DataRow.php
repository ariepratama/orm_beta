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

	public static function data_merge($data1, $data2)
	{
		foreach($data2 as $table => $row)
		{
			
			if (! is_object($row))
				Utility::debug('warning this is not object', $row);

			if(array_key_exists($table, $data1))
				if (is_object($data1[$table]))
					$data1[$table]->add_multiple_data($row->columns, $row->values);
				else
					$data1[$table] = array_merge($row, $data1[$table]);
			else 
				$data1[$table] = $row;

		}
		return $data1;
	}

	public static function data_merge_for_array($data1, $data2)
	{
		foreach($data2 as $table => $row)
		{
			
			// if (! is_object($row))
				

			if(array_key_exists($table, $data1))
			{
				Utility::debug(' data1 ', $data1[$table]);
				if (is_object($data1[$table]))
				{
					$temp = $data1[$table];
					$data1[$table] = array($data1[$table], $row);
				}
				else if($table != DataConstants::$_PRIORITIES_)
					array_push($data1[$table], $row);
				else
					$data1[$table] = array_merge($row, $data1[$table]);
			}
			else 
				$data1[$table] = $row;

		}
		return $data1;
	}

	public function __construct($table, $id, $id_column, $key = null, $key_column = null, $columns = array(), $values = array())
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

	public function add_multiple_data($columns, $values)
	{
		$this->columns = array_merge($this->columns, $columns);
		$this->values  = array_merge($this->values, $values);
		$this->id_exists = in_array($this->id_column, $this->columns);
	}

	public function columns()
	{
		
		$_cols = $this->columns;
		if ( ! is_null($this->key_column))
			array_push($_cols, $this->key_column);
		if ( ! $this->id_exists)
			array_push($_cols, $this->id_column);
		return $_cols;
	}

	public function values()
	{

		$_vals = $this->values;
		if ( ! is_null($this->key))
			array_push($_vals, $this->key);
		if ( ! $this->id_exists)
			array_push($_vals, $this->id);
		return $_vals;
	}

	public function update_columns()
	{
		$_cols = $this->columns;

		// array_push($_cols, $this->key_column);s
		if ($this->id_exists){
			unset($_cols[$this->id_column]);
		}
		

		return $_cols;
	}

	public function update_values()
	{

		$_vals = $this->values;
		// array_push($_vals, $this->key);
		if ($this->id_exists)
			unset($_vals[$this->id_column]);
		return $_vals;
	}


}