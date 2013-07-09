<?php
class Table_Manager{
	
	// class name is used as table name
	public static $PersistentObject_key_type = 'varchar(32)';
	public static $PersistentObject_primary_key_type = 'int';
	public static $pk_constraint = 'not null';
	
	public static function create($table_name, $obj_meta, $index = null, $set_primary = null, $id_name = '_id', $id_type = 'int')
	{
		if(gettype($set_primary) == 'array') $set_primary = implode(', ', $set_primary);
		
		$_primary_sql = ($set_primary != null)? ', primary key ('.$set_primary.') ' : '';
		
		$_create_sql ='create table if not exists '.$table_name. ' (_key varchar(32) not null, '.$obj_meta->db_attributes_str().$_primary_sql.')';

		$res = DB::query(null, $_create_sql)->execute();
		 
		if (gettype($index) == 'array') $index = implode(', ', $index);
		
		if ($index != null and gettype($index) == 'string')
		{
			// create index if available
			$_create_index_sql = 'create index p_idx on '.$table_name.'('.$index.')';
			try{
				DB::query(null, $_create_index_sql)->execute();
			}catch(Kohana_Exception $e){}
		}
			
		return $res;
	}

	public static function create_relation($rel_name, $f_key_name, $val_name, $f_key_type, $val_type, $index = null)
	{
		$index_type = null;
		if (is_null($index))
			
		// foreign key added by calling function add_foreign_keys
		$_create_sql = 'create table if not exists '.$rel_name.
						'('.$f_key_name.' '.$f_key_type.', '.$val_name.' '.$val_type.')';

		$res = DB::query(null, $_create_sql)->execute();

		return $res;
	}
	
	public static function add_foreign_keys($table_name, $column_names, $table_ref, $ref_column_names,$on_delete = 'no action', $on_update = 'no action', $constraint = null )
	{
		$cols = (gettype($column_names) == 'array')? implode(',', $column_names):$column_names;
		$ref_cols = (gettype($ref_column_names) == 'array')? implode(',', $ref_column_names):$ref_column_names;
		$cons_name = (is_null($constraint))? $table_name.'_fk' : $constraint;
		
		$drop_fkey = 'alter table '.$table_name.' drop foreign key '.$cons_name;


		/*droping foreign key constraint*/
		try{DB::query(null, $drop_fkey)->execute();}catch (Kohana_Exception $e){}

		$f_key_sql = 'alter table '.$table_name.' add '.
				 ' constraint '.$cons_name.
				 ' foreign key ('.($cols).')'.
				 ' references '.$table_ref.'('.$ref_cols.')'.
				 ' on delete '.$on_delete.
				 ' on update '.$on_update;

		$res = DB::query(null, $f_key_sql)->execute();
		
		return $res;
	}
	
	public static function insert($table_name, $obj_attrs = null, $obj_values)
	{
		// if (gettype($obj_values) == 'array' )
		// 	$params = implode(',', $obj_values);
		// else if (gettype($obj_values) == 'string')
		// 	$params = $obj_values;	
		
		// if (gettype($obj_attrs) == 'array')
		// 	$columns = implode(', ', $obj_attrs);
		// else if (gettype($obj_attrs) == 'string')
		// 	$columns = $obj_attrs;
		// else
		// 	$columns = '';
		
		// $_insert_sql = 'insert into '.$table_name.((empty($columns))? $columns:'('.$columns.')').' values ('.$params.')';

		
		return DB::insert($table_name, $obj_attrs)->values($obj_values)->execute();
	}


	private static function insert_data_chunck($chunk)
	{
		if (is_array($chunk))
		{
			$size = count($chunk);
			foreach($chunk as $single_chunk)
				DB::insert($single_chunk->table, $single_chunk->columns())->values($single_chunk->values())->execute();
		}
		else
			DB::insert($chunk->table, $chunk->columns())->values($chunk->values())->execute();
	}
	private static function insert_and_unset($data, $table)
	{
		// if ($table != DataConstants::$_PRIORITIES_)
			Table_Manager::insert_data_chunck($data[$table]);
		// DB::insert($table, $data[$table]->columns())->values($data[$table]->values())->execute();	
		unset($data[$table]);

		return $data;
	}
	public static function insert_data($data, $root_table)
	{
		
		if (! empty($data))
		{
			
			if(! empty($data[DataConstants::$_PRIORITIES_]))
			{
				$data[DataConstants::$_PRIORITIES_] = array_unique($data[DataConstants::$_PRIORITIES_]);
				Utility::debug('priorities ', $data[DataConstants::$_PRIORITIES_]);

				foreach($data[DataConstants::$_PRIORITIES_] as $priority_table)
					$data = Table_Manager::insert_and_unset($data, $priority_table);
			}

			unset($data[DataConstants::$_PRIORITIES_]);
			
			// insert base class attributes first
			if (array_key_exists($root_table, $data))
				$data = Table_Manager::insert_and_unset($data, $root_table);

			
			foreach ($data as $chunk) 
			{
				// echo $table;
				// echo '<br/><br/><br/>';

				// Utility::debug('chunk: ',$chunk);
				Table_Manager::insert_data_chunck($chunk);
				// if (is_array($chunk))
				// {
				// 	foreach($chunk as $single_chunk)
				// 		DB::insert($single_chunk->table, $single_chunk->columns())->values($single_chunk->values())->execute();
				// }
				// else
				// 	DB::insert($chunk->table, $chunk->columns())->values($chunk->values())->execute();	
			}
		}
		else
		{
			// Utility::debug('empty data',$data);
			throw new Kohana_Exception("Data is empty");
			
		}	
	}
	public static function update_data($data)
	{

		// insert base class attributes first
		// DB::insert($root_table, $data[$root_table]->columns())->values($data[$root_table]->values())->execute();	
		// unset($data[$root_table]);
		// $id_column = $data[$root_table]->id_column;
		// $id_value = $data[$root_table]->id;

		foreach ($data as $chunk) 
		{
			// echo $table;
			// echo '<br/><br/><br/>';

			Utility::debug('update chunk: ',$chunk);	
			// $merged = array_combine($chunk->columns(), $chunk->values());
			if (! empty($chunk))
				DB::update($chunk->table)->set(array_combine($chunk->update_columns(), $chunk->update_values()))->where($chunk->id_column, '=', $chunk->id)->execute();
			// DB::insert($chunk->table, $chunk->columns())->values($chunk->values())->execute();	
		}
	}


	public static function update($table_name, $id_column, $id_value, $columns, $values)
	{
		$merged = array();
		$data_size = count($columns);

		for ($i = 0; $i < $data_size; $i++) 
		{
			$merged[$columns[$i]] = $values[$i];
		}
		$query = DB::update($table_name)->set($merged)->where($id_column,'=', $id_value);
		
		return $query->execute();
	}

	
	public static function retrieve_simple($table_name, $attr, $exp, $value)
	{
		return DB::select()->from($table_name)->where($attr, $exp, $value)->execute();
	}

	public static function retrieve($root, $class, $class_meta, $tables, $conditions, $id_col)
	{
		$root_table = $tables[$root];
		$query = DB::select()->from($root_table);
		
		unset($tables[$root]);
		// join each relating tables
		foreach($tables as $table)
		{
			$query->join($table)->on($root_table.'.'.$id_col, '=', $table.'.'.$id_col);
		}

		// build the retrieve queries 
		foreach($conditions as $condition)
		{
			$con_type = $condition['type'];
			$con_param = $condition['parameters'];
			$col_name = $class_meta->get_column_name_of($con_param[0]);

			$query->$con_type($col_name, $con_param[1], $con_param[2]);
		}

		return $query->execute();
	}

	public static function delete($table_name, $conditions)
	{
		$query = DB::delete($table_name);

		// build the retrieve queries 
		foreach($conditions as $condition)
		{
			$con_type = $condition['type'];
			$con_param = $condition['parameters'];

			$query->$con_type($con_param[0], $con_param[1], $con_param[2]);
		}

		return $query->execute();
	}
	
	public static function retrieve_max_id($_class, $column = '_id')
	{
		$res =  DB::select(array(DB::expr('MAX('.$column.')'), 'counter'))->from($_class)->execute()->get('counter');
		return $res;
	}

	public static function force_add_column_to($table_name, $columns, $types)
	{
		$sql = "alter table $table_name ";

		foreach($columns as $col)
			$sql = $sql."add column $col ".array_shift($types)." ,";
		
		
		$sql = substr($sql, 0, -1);

		return DB::query(null, $sql)->execute();
	}
	
}