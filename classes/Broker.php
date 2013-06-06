<?php

class Broker{
	private static $meta_cache = array();
	private static $meta_table = '_meta';

	private static $_pending_query = array();

	
	private static function get_metadata($obj)
	{
		$obj_class = get_class($obj);
		
		// cache the metadata array if not exists
		if( ! array_key_exists($obj_class, Broker::$meta_cache))
		{
			// check in database if it's already exists
			$_saved_meta = Table_Manager::retrieve_simple(Broker::$meta_table, Metadata_Instance::$p_key_column_name, '=', md5($obj_class))->as_array(); 
			
			// try retrieve metadata from database
			if ( ! empty ($_saved_meta))
			{
				$_retrieved_meta = array_shift($_saved_meta);
				print_r($_retrieved_meta);
				$_meta = new Metadata_Instance ($_retrieved_meta['class'], json_decode($_retrieved_meta['metadata'], true));
			}
			else
			{
				// create new metadata for new class
				$_meta = Metadata_Extractor::extract_metadata($obj);
				// insert metadata to database
				Table_Manager::insert(Broker::$meta_table, null, $_meta->as_meta_sql());
			}
			Broker::$meta_cache[$obj_class] = $_meta;
		}
		
		return Broker::$meta_cache[$obj_class];
	}

	public static function get_metadata_for_class($class_name)
	{
		if ( ! array_key_exists($class_name, Broker::$meta_cache))
		{
			$result = Table_Manager::retrieve_simple(Broker::$meta_table, Metadata_Instance::$p_key_column_name, '=', md5($class_name))->as_array();

			if ( ! empty($result))
				Broker::$meta_cache[$class_name] = new Metadata_Instance($class_name, json_decode(reset($result)['metadata'], true));
		
			$res = (! empty ($result))? Broker::$meta_cache[$class_name] : '';
		}
		else
			$res = Broker::$meta_cache[$class_name];		


		return $res;

	}

	public static function is_table_exists($table_name)
	{
		$show_table_sql = 'show tables like \''.$table_name.'\'';
		$res = DB::query(null, $show_table_sql)->execute();

		return ! empty($res);

	}

	private static function create_storage($class_name, $meta, $root, $root_meta = null)
	{
		$table_name = $meta->table_name();
		$id_as_foreign_key = false;
		$_id = $meta->id_column_name();
		$parent = $meta->parent_name();

		$root_meta = (is_null($root_meta))? Broker::get_metadata_for_class($root):$root_meta;
		$root_table = $root_meta->table_name();
		$root_id_column = $root_meta->id_column_name();

		if ($meta->has_parent())
		{
			$parent_meta = Broker::get_metadata_for_class($parent);
			$parent_table = $parent_meta->table_name();
			$prnt_table_exists = Broker::is_table_exists($parent_table);
			$id_as_foreign_key = true;
		}

		// create table for parent class if not exists
		if (isset($prnt_table_exists) && ! $prnt_table_exists)
		{
			Broker::create_storage($parent, $parent_meta, $root, $root_meta);
		}
		
		Table_Manager::create($table_name, $meta, null, ($id_as_foreign_key)? null:$_id);

		if($id_as_foreign_key)
		{
			// $parent_id_column = $parent_meta->id_column_name();
			$root_id_column = $parent_meta->id_column_name();
			Table_Manager::add_foreign_keys($table_name, $_id, $root_table, $root_id_column, 'cascade', 'cascade', $class_name.'_child_'.$root);
		}
		// set class name hash as foreign key to the metadata table
		Table_Manager::add_foreign_keys($table_name, PersistentObject::get_key_column_name(), Broker::$meta_table, Metadata_Instance::$p_key_column_name, 'cascade', 'cascade', $meta->table_name().Broker::$meta_table);

	}

	private static function init_foreign_keys($_meta)
	{
		/*deal with object to object relations*/
		if ($_meta->has_foreign_keys())
		{
			foreach ($_meta->db_foreign_keys() as $key => $value) 
			{
				$foreign_meta = Broker::get_metadata_for_class($_meta->get_attribute_rel_with($key));
				$related_table = $_meta->get_table_name_of($key); 

				Table_Manager::add_foreign_keys($related_table, $key, $foreign_meta->table_name(), $value,'no action', 'no action');
			}
		}
	}

	private static function init_relations($table_name, $_meta)
	{
		if($_meta->has_extern_foreign_keys())
		{
			foreach($_meta->db_extern_foreign_columns() as $var_name => $ext) 
			{
				/*relation name is combination of container class name and attribute class name*/
				$rel_name = $ext->extern_table;
				/*extern column is key column */
				/*model_key = column from the class*/ 
				$model_key = $ext->extern_column;

				$model_key_type = $_meta->id_db_type();

				$rel_key_type = $ext->db_type;

				/*column for other table*/
				$rel_key = $var_name;


				if ($ext->is_rel_with_object())
				{
					/*add the attribute class foreign key*/
					/*get the class that related with wrapping object*/
					$rel_with = $_meta->get_attribute_rel_with($var_name);	

					$extern_meta = Broker::get_metadata_for_class($rel_with);

					$rel_key = $extern_meta->class_name().$extern_meta->id_column_name();

				}
				
				/*create the relation table*/
				/*if not relation with objects*/
				Table_Manager::create_relation($rel_name, $model_key, $rel_key, $model_key_type, $rel_key_type);

				//add the current class foreign key
				Table_Manager::add_foreign_keys($rel_name, $model_key, $table_name, $_meta->id_column_name() ,'cascade', 'cascade');

				if ($ext->is_rel_with_object())
					Table_Manager::add_foreign_keys($rel_name, $rel_key, $extern_meta->table_name(), $_meta->id_column_name(), 'cascade', 'cascade', $extern_meta->table_name().'_fk');
				

				/*handle foreign key for object related with many object*/


				
			}
		}
	}


	
	public static function init($obj)
	{
		// get the class of object
		$obj_class = get_class($obj);
		$root = Utility::get_root_parent_class($obj_class);

		// get the metadata
		$_meta = Broker::get_metadata($obj);
				

		$table_name = $_meta->table_name();
		
		/*create table for object if not exists as metadata says*/

		Broker::create_storage($obj_class, $_meta, $root);	
		
		Broker::init_foreign_keys($_meta);

		Broker::init_relations($table_name, $_meta);
		


	}
	
	public static function save($obj, $meta)
	{
		// get the class of object
		$obj_class = get_class($obj);
		
		// get the metadata
		$_meta = ($meta == null)? Broker::get_metadata($obj) : $meta;
		$_root_class = Utility::get_root_parent_class($obj_class);
		echo 'root '.$_root_class.' root';
		$_root_meta = Broker::get_metadata_for_class($_root_class);

		$table_name = $_meta->table_name();

		// determine if the operation to do is update or insert
		// $is_update = false;
		$is_update = $obj->is_retrieved(); //Broker::id_exist($obj_class, $obj->get_primary_key_value());
		
		// get the primary keys and column name of foreign objects
		$f_objs = $obj->get_foreign_objects();
		$internals = $f_objs['internal'];
		$externals = $f_objs['external'];

		$_key_value = $obj->get_key_value();
		$_pk_value = $obj->get_primary_key_value();
		
		$_data = $obj->get_data();
		

		//insert the values of the object
		// $columns = array_merge(
		// 		  			  $_meta->get_attr_names(), 
		// 		  			  // array(PersistentObject::get_key_column_name(),$_meta->id_column_name()),
		// 		  			  PersistentObject::get_key_column_name(),
		// 		  			  array_keys($internals)
		// 		  			 );

		// $values = array_merge(
		// 		  			  $obj->get_values(), 
		// 		  			  $_key_value,
		// 		  			  array_values($internals)
		// 		  			 );
		
		// print_r($columns);
		// print_r($values);
		if ( ! $is_update)
			// Table_Manager::insert($table_name, $columns, $values);
			Table_Manager::insert_data($_data, $_root_meta->table_name());
		else
			// Table_Manager::update($table_name, $_meta->id_column_name(), $_pk_value, $columns, $values);
			Table_Manager::update_data($_data);

		// // check for relation
		// if( ! empty($externals))
		// {
		// 	foreach($externals as $attr_name => $vals)
		// 	{
		// 		foreach($vals as $val)
		// 		{
					
		// 			$extern_table_name = $attr_name;
		// 			$rel_key = $attr_name;

		// 			if(is_object($val)) 
		// 			{
		// 				$extern_obj_meta = Broker::get_metadata_for_class(get_class($val));
		// 				/*replace the value to be the foreign key of external table*/
		// 				$val = $val->get_primary_key_value();
						
		// 				$extern_table_name = $extern_obj_meta->table_name();
		// 				$rel_key = $extern_obj_meta->class_name().$extern_obj_meta->id_column_name();
		// 			}

		// 			$rel_name = $_meta->table_name().'_'.$extern_table_name;

		// 			$obj_key = $_meta->class_name().$_meta->id_column_name();
					
		// 			if(is_string($val)) $val =$val;

		// 			$extern_columns = array($obj_key, $rel_key);
		// 			$extern_values = array($_pk_value, $val);

		// 			if ( ! $is_update)
		// 				Table_Manager::insert($rel_name, $extern_columns, $extern_values);
		// 			else
		// 				Table_Manager::update($rel_name, $obj_key, $_pk_value, $extern_columns, $extern_values);
		// 		}
		// 	}
		// }
				
	}

	// $val could be the key of another object or the value of basic types
	// $from and $to are table names, $to could be variable name for basic type
	public static function create_relation($from, $to, $f_key_name, $val_name, $f_key_type, $val_type)
	{
		
		$rel_name = $from.'_have_'.$to;
		Table_Manager::create($rel_name, $f_key_name, $val_name, $f_key_type, $val_type);
		// add foreign key for the object
		Table_Manager::add_foreign_keys($rel_name, $f_key_name, $from, $f_key_name, 'no action', 'no action');
		// add foreign key if an object has many other object
		if ($val_type === 'object')
			Table_Manager::add_foreign_keys($rel_name, $val_name, $to, $val_name, 'no action', 'no action');
	}
	
	public static function retrieve($class, $queries, $multiple = false)
	{
		// $meta = Broker::get_metadata_for_class($class);
		// $table_name = $meta->table_name();


		// $res =  Table_Manager::retrieve($table_name, $queries)->as_array();
		$join_table = array();
		
		$meta[$class] = Broker::get_metadata_for_class($class);
		$join_table[] = $meta[$class]->table_name();

		$current_meta = $meta[$class];
		$root = Utility::get_root_parent_class($class);

		while($current_meta->has_parent())
		{
			$parent = get_parent_class($current_meta->class_name());	
			$meta[$parent] = Broker::get_metadata_for_class($parent);

			$join_table[$parent] = $meta[$parent]->table_name();
			$current_meta = $meta[$parent];

			// Utility::debug('tables: ', $join_table);
		}


		$res = Table_Manager::retrieve($root, $class, $meta[$class], $join_table, $queries, $meta[$root]->id_column_name())->as_array();



		return ($multiple)? $res : reset($res);
	}

	public static function delete($class, $queries)
	{
		$meta = Broker::get_metadata_for_class($class);
		$table_name = $meta->table_name();


		$res =  Table_Manager::delete($table_name, $queries);
				
		return $res;
	}
	public static function id_exist($class, $id)
	{
		// get metadata for spesific class
		$meta = Broker::get_metadata_for_class($class);
		// get the id column name in database
		$id_column = $meta->id_column_name();
		$res = Table_Manager::retrieve_simple($class, $id_column, '=', $id)->as_array();
		
		if (! empty ($res))
			return true;
		
		return false;
	}
	
	
	public static function reset_meta_all()
	{
		Broker::$meta_cache = array();
	}
	
	public static function get_max_id($table, $id_column)
	{
		return Table_Manager::retrieve_max_id($table, $id_column);
	}

	public static function save_metadata($class, $meta){
		try
		{
			Table_Manager::insert(Broker::$meta_table, array('class_hash','class','metadata'), array(md5($class), $class, $meta));
		}catch(Exception $e){echo 'metadata exists';}
		//do nothing with the exception
	}
	public static function update_metadata($class, $meta)
	{
		try
		{
			Table_Manager::update(Broker::$meta_table, 'class_hash', md5($class), array('class', 'metadata'), array($class, $meta));
		}catch(Exception $e){echo 'metadata exists';}
	}
}