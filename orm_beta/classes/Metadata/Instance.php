<?php

class Metadata_Instance{
	public static $p_key_column_name = 'class_hash';
	
	private $_class;
	private $_obj_meta;
	private $_id_column_name;

	private $_foreign_meta = array();
	private $_extern_f_meta = array();
	 
	public function __construct($class, $obj_meta)
	{
		$this->_class = $class; 
		$this->_obj_meta = $obj_meta[$class];
		print_r($obj_meta);
		// $base_class = $obj_meta[Metadata_Constants::$BASE_CLASS_STRING];
		// echo Metadata_Constants::$ID_METADATA_STRING;


		$this->_id_column_name = $this->_obj_meta[Metadata_Constants::$ATTRIBUTES_STRING][Metadata_Constants::$ID_METADATA_STRING];

		// get the attributes
		$attrs = $this->attributes();

		foreach($attrs as $_attr_name => $_attr_meta)
		{
			// if not relation then map directly
			if ($_attr_meta[Metadata_Constants::$DB_TYPE_STRING] == 'relation')
			{
				// if relation then get the object name, for one-to-one relation with object				
				if($_attr_meta[Metadata_Constants::$REL_TYPE_STRING] == 'one_to_one')
				{
					$foreign_name = $_attr_meta[Metadata_Constants::$REL_WITH_STRING];
					
					$this->_foreign_meta[$_attr_name] = Broker::get_metadata_for_class($foreign_name);


				}
				else
				{
					// to handle one to many relation
					if (Utility::is_base_type($_attr_meta[Metadata_Constants::$REL_WITH_STRING]))
					{
						$this->_extern_f_meta[$_attr_name] = $_attr_meta[Metadata_Constants::$REL_WITH_STRING];
					}
					else
					{
						$foreign_name = $_attr_meta[Metadata_Constants::$REL_WITH_STRING];
						$meta = Broker::get_metadata_for_class($foreign_name);
						if ( ! empty($meta))
							$this->_extern_f_meta[$_attr_name] = new Metadata_Instance($foreign_name, Broker::get_metadata_for_class($foreign_name)->as_array());
					}
				}
			}

		}
	}
	
	public function class_name()
	{
		return $this->_class;
	}

	public function table_name()
	{
		return $this->_obj_meta[Metadata_Constants::$TABLE_NAME_STRING];
	}
	public function id_meta()
	{
		return $this->_obj_meta[Metadata_Constants::$ATTRIBUTES_STRING][Metadata_Constants::$ID_METADATA_STRING];
	}

	public function id_column_name()
	{
		return $this->id_meta()[Metadata_Constants::$COLUMN_NAME_STRING];
	}

	public function id_db_type()
	{
		return $this->id_meta()[Metadata_Constants::$DB_TYPE_STRING];
	}
	
	public function as_json()
	{
		return json_encode(array($this->_class => $this->_obj_meta));
	}
	
	public function as_array()
	{
		return $this->_obj_meta;
	}
	public function is_attribute_object($name){
		return $this->attributes()[$name][Metadata_Constants::$REL_TYPE_STRING] == 'one_to_one';

	}
	public function is_attribute_array($name){
		return $this->attributes()[$name][Metadata_Constants::$REL_TYPE_STRING] == 'one_to_many';
	}
	
	// get the attributes with meta attributes
	public function attributes()
	{
		return $this->_obj_meta[Metadata_Constants::$ATTRIBUTES_STRING];
	}
	public function parent_name()
	{
		return $this->_obj_meta[Metadata_Constants::$PARENT_CLASS_STRING];
	}
	
	// get the name of attributes
	public function get_attr_names()
	{
		$attrs = $this->attributes();
		$_attr_names = array();
		foreach($attrs as $_attr_name => $_attr_meta)
		{
			if(  $_attr_meta[Metadata_Constants::$DB_TYPE_STRING] != 'relation')//! $this->is_attribute_array($_attr_name))//$_attr_meta['db_type'] != 'relation')
				$_attr_names[] = $_attr_name;
		}

		return $_attr_names;
	}

	public function get_column_name_of($var_name)
	{
		return $this->attributes()[$var_name][Metadata_Constants::$COLUMN_NAME_STRING];
	}

	public function get_table_name_of($var_name)
	{
		return $this->attributes()[$var_name][Metadata_Constants::$TABLE_NAME_STRING];	
	}

	public function get_attribute_rel_with($attr_name)
	{
		return $this->attributes()[$attr_name][Metadata_Constants::$REL_WITH_STRING];
	}

	public function has_foreign_keys()
	{
		return (! empty($this->_foreign_meta));
	}

	public function has_extern_foreign_keys()
	{
		return ! empty($this->_extern_f_meta);
	}
	
	/*attributes for inserting to metadata table*/
	public function as_meta_sql()
	{
		return array(md5($this->class_name()), $this->class_name(), $this->as_json());
	}
		
	// get attributes for create table format
	public function db_attributes_str()
	{
		// $pk = $this->_obj_meta[Metadata_Constants::$ATTRIBUTES_STRING][Metadata_Constants::$ID_METADATA_STRING];
		// $key = $pk[Metadata_Constants::$COLUMN_NAME_STRING].' '.$pk[Metadata_Constants::$DB_TYPE_STRING];

		$attrs = $this->attributes();
		$_attrs_arr = array();
		$_class_table = $this->table_name();

		foreach($attrs as $_attr_name => $_attr_meta)
		{
			if ($_attr_meta[Metadata_Constants::$TABLE_NAME_STRING] == $_class_table || $_attr_name == Metadata_Constants::$ID_METADATA_STRING)
			{
				// if not relation then map directly
				if ($_attr_meta[Metadata_Constants::$DB_TYPE_STRING] != 'relation')
					$_attrs_arr[] = $_attr_meta[Metadata_Constants::$COLUMN_NAME_STRING].' '.$_attr_meta[Metadata_Constants::$DB_TYPE_STRING];
				else
				{
					// if relation then get the object name, for one-to-one relation with object
					// $foreign_name = $_attr_meta['rel_with'];
					// only create column if one to one relationship
					if($_attr_meta[Metadata_Constants::$REL_TYPE_STRING] == 'one_to_one')
						$_attrs_arr[] = $_attr_meta[Metadata_Constants::$COLUMN_NAME_STRING].' '.Table_Manager::$PersistentObject_primary_key_type;
				}
			}

		}

		// array_unshift($_attrs_arr, $key);
		return implode(', ', $_attrs_arr);
	}
	public function has_parent()
	{
		$parent = $this->parent_name();
		return ! empty($parent);
	}
	// get comma seperated foreign keys
	public function db_foreign_keys()
	{
		if($this->has_foreign_keys())
		{
			$res = array();

			foreach($this->_foreign_meta as $var_name => $_f_m)
			{	
				$res[$var_name]= $_f_m->id_column_name();
			}
			return $res;
		}
		
		return null;
	}

	public function db_extern_foreign_columns()
	{
		if($this->has_extern_foreign_keys())
		{
			$res = array();
			foreach($this->_extern_f_meta as $var_name => $_f_m)
			{
				if (is_object($_f_m))
				{
					/*if object is also creating*/
					$res[$var_name] = new Attribute_External($this->_class.$_f_m->id_column_name(), $this->_class.'_'.$_f_m->table_name(), $_f_m->id_db_type(), true);
				}
				else
				{
					$res[$var_name] = new Attribute_External($this->_class.$this->id_column_name(), $this->table_name().'_'.$var_name, $_f_m, false);
					
				}
			}
			return $res;
		}
		
		return null;
	}
	
}
