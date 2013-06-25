<?php
class DataConstants{
	public static $_COLUMN = 'column';
	public static $_VALUE = 'value';
	public static $_TABLE = 'table';
	public static $_PRIORITIES_ = '_PRIORITIES_';

}
abstract class PersistentObject extends Model{

	public static $primary_key_db_type = 'int';
	public static $primary_key_column_name = '_id';

	// cache for the objects
	public static $cache = array();
	private static $key_cache= array();
	
	// excluded attributes
	private static $excluded_attrs = array();
	private static $obj_counter = array();

	private $_query_conditions = array();
	private $_meta;
	
	// primary key for each object
	protected $_key;	// class name hash
	protected $_id;	// object id or primary key

	protected $_retrieved = false;

	protected $_id_column;
	
	// foreign key for other class that play role as attribute to this class
	protected $_foreign_object;
	
	// variable for private attributes that must be persisted
	protected $included_attrs = array();

	
		
	public static function say_hello()
	{
		echo 'say hello from persistent object';
	}
		// get the object primary keys
	public static function get_key_column_name()
	{
		return '_key';
	}
	
	public static function get_primary_key_column_name()
	{
		return PersistentObject::$primary_key_column_name;
	}

	public static function factory($class_name)
	{
		
		/*TODO: create exception here if metadata not found*/

		return new $class_name;
	}


	protected function __construct()
	{
		$_class = get_class($this);
		$root = Utility::get_root_parent_class($_class);


		/*initialize*/
		$this->init($_class);

		/*initialize the 'high' key*/
		$this->_key = md5($_class);


		/*check metadata if it's not empty*/
		/*if metadata for a class empty then make new metadata*/
		if ( ! array_key_exists($root , PersistentObject::$key_cache))
		{
			try{	
				$root_meta = Broker::get_metadata_for_class($root);
				$n = Broker::get_max_id($root_meta->table_name(), $root_meta->id_column_name());
			}catch (Kohana_Exception $e){}
			PersistentObject::$key_cache[$root] = ( (! isset($n)) or empty($n))? 0:$n;


		}
		
		// increment the 'low' key
		PersistentObject::$key_cache[$root]++;


		$this->_id = PersistentObject::$key_cache[$root];

		return $this;




	}

	private function init($class_name)
	{
		$this->_meta = Broker::get_metadata_for_class($class_name);

		/*if metadata not exists use default column_id name*/
		if (! empty($this->_meta))
			$this->_id_column = $this->_meta->id_column_name();
		else
		{
			/*use the low key if column name for id not set*/
			$this->_id_column = PersistentObject::$primary_key_column_name;

			/*initialize id as 1 if extracted automatically*/
			$this->_id = 1;
			
			// initialize class database
			Broker::init($this);
			$this->_meta = Broker::get_metadata_for_class($class_name);	
		}

	}
	

	public function get_data()
	{
		if (! empty($this->_meta))
		{
			$data = array();
			$data[DataConstants::$_PRIORITIES_] = array();
			
			$obj_attrs = $this->get_object_attributes();
			
			foreach($obj_attrs as $var_name => $val)
			{
				echo $var_name;
				if (! is_null($val)  /*and ( ! $this->_meta->is_attribute_object($var_name) and! $this->_meta->is_attribute_array($var_name)) */)
				{
					$table = $this->_meta->get_table_name_of($var_name);
					$id_col = $this->_meta->get_column_name_of(Metadata_Constants::$ID_METADATA_STRING);
					if ( ! array_key_exists($table, $data))
					{
						$data[$table] = new DataRow($table, $this->_id, $id_col, $this->_key, PersistentObject::get_key_column_name());
						// print_r($data[$table]);
					}
					$col = $this->_meta->get_column_name_of($var_name);

					if (is_object($val))
					{
						$data[$table]->add_data($col, $val->get_primary_key_value());
						$related_meta = Broker::get_metadata_for_class(get_class($val));
						$related_table = $related_meta->table_name();
						array_unshift($data[DataConstants::$_PRIORITIES_] , $related_table);
						$data = DataRow::data_merge($data, $val->get_data());
					}
					else
					{
						
						if (is_array($val))
						{
							
							foreach($val as $singular)
							{
								$var_col = $this->_meta->get_column_name_of($var_name);
								$_table = $this->_meta->get_table_name_of($var_name);
								$relation_id = Utility::relation_id_col($_table, $id_col);
								$relation_table = Utility::relation_name($var_col, $_table);

								if( ! array_key_exists($relation_table, $data))
										$data[$relation_table] = array();

								$new_row = new DataRow($relation_table, $this->_id, $relation_id); 

								if (is_object($singular))
								{
									// $data[$table]->add_data($col, $val->get_primary_key_value());
									$_class = get_class($singular);
									$related_meta = Broker::get_metadata_for_class($_class);
									$related_table = $related_meta->table_name();
									array_unshift($data[DataConstants::$_PRIORITIES_] , $related_table);
									
									if ($related_meta->has_parent())
									{
										
										$related_root = Utility::get_root_parent_class($_class);
										$root_meta = Broker::get_metadata_for_class($related_root);
										$root_table = $root_meta->table_name();
										array_unshift($data[DataConstants::$_PRIORITIES_], $root_table);
									}

									$data = DataRow::data_merge_for_array($data, $singular->get_data());

									$other_relation_id = Utility::relation_id_col($related_table, $related_meta->id_column_name());

									
									//insert into relation
									$new_row->add_data($other_relation_id, $singular->get_primary_key_value());
									
								}
								else //if (Utility::is_base_type($singular))
								{
									Utility::debug('singular', $singular);
									//insert into relation
									$new_row->add_data($var_col, $singular);
								}

								$data[$relation_table][] = $new_row;
							}

						}
						else
							$data[$table]->add_data($col, $val);
					}
					
					// $data[$var_name] = array();
					// $data[$var_name][DataConstants::$_COLUMN] = $this->_meta->get_column_name_of($var_name);
					// $data[$var_name][DataConstants::$_TABLE] = $this->_meta->get_table_name_of($var_name);
					// $data[$var_name][DataConstants::$_VALUE] = $val;
					// $data[$var_name][DataConstants::$_ID] = $this->_id;

					// print_r($data);
					// echo '<br/><br/><br/>';
					// echo '<br/><br/><br/>';
					
				}
				
			}
			return $data;

		}

		return null;
	}

	public function get_pk_column_name()
	{
		return $this->_id_column;
	}

	protected function set_pk_column_name($column)
	{
		$this->_id_column = $column;
	}

	public function is_retrieved()
	{
		return $this->_retrieved;
	}

	public function set_pk_value($val)
	{
		$this->_id = $val;
	}

	// mechanism for saving private attributes
	private function include_private_attrs($object_vars)
	{

		foreach ($this->included_attrs as $var_name => $val)
		{
			$object_vars[$var_name] = $val;
		}

		return $object_vars;
	}
	
	private function exclude_base_attrs($object_vars, $exclude_id = false)
	{
		if (empty (PersistentObject::$excluded_attrs)) PersistentObject::$excluded_attrs = get_class_vars('PersistentObject');


		foreach (PersistentObject::$excluded_attrs as $key => $value)
		{
				
				if ($key != PersistentObject::$primary_key_column_name || $exclude_id)
				{
					unset($object_vars[$key]);
				}
				
		}


		return $object_vars;
	}
	
	// get the attributes of the object 
	public function get_object_attributes()
	{
		$obj_vars =  get_object_vars($this);

		foreach ($obj_vars as $var_name => $value) 
		{
			// if an object not yet registered then remove
			if (is_object($value) and (! Utility::is_persistent_object($value)))
			{
				unset($obj_vars[$var_name]);
			}
			
		}

		/*excluding attributes from class PersistentObject*/
		$obj_vars = $this->exclude_base_attrs($obj_vars);

		/*include registered private attributes of an object*/
		$obj_vars = $this->include_private_attrs($obj_vars);

		return $obj_vars;
	}

	public function class_attributes($class_name){
		$class_attrs = get_class_vars($class_name);

		$meta = Utility::change_array_format($class_attrs, $class_name);
		$parent = get_parent_class($class_name);
		// specialy for PersistentObject class
		if( $parent != 'PersistentObject')
		{
			$meta = Utility::blend_array($meta, $this->class_attributes($parent));
		}


		return $meta;
	}

	public function get_class_attributes($class = null, $exclude_id = false)
	{
		$class = (is_null($class))? get_class($this) : $class;
		$meta = $this->class_attributes($class);
		$meta = $this->exclude_base_attrs($meta, $exclude_id);

		return $meta;
	}

	public function get_class_attributes_and_values($class = null, $exclude_id = false)
	{
		$class = (is_null($class))? get_class($this) : $class;
		$attrs = get_class_vars($class);
		$attrs = $this->exclude_base_attrs($attrs, $exclude_id);

		return $attrs;
	}		

	// get the object values
	public function get_values()
	{
		$_values = array();
		$obj_attrs = $this->get_object_attributes();
		foreach($obj_attrs as $var_name => $val)
		{
			// insert quote for string valued variable
			// if (gettype($val) == 'string') $val = '\''.$val.'\'';

			// return variables value if not null

			if (! empty($val)  and ( ! $this->_meta->is_attribute_object($var_name) and ! $this->_meta->is_attribute_array($var_name)))
			{
				echo $var_name.'<br/>';
				$_values[] = $val;
				
			}
			
		}
		
		return $_values;
	}
	
	public function get_key_value()
	{
		return $this->_key;
	}
	
	// null for auto increment
	public function get_primary_key_value()
	{
		// return $this->_n_obj;
		return $this->_id;
	}

	// return the key and value of foreign object
	public function get_foreign_objects()
	{
		$_cols = array();
		$_cols['internal'] = array();
		$_cols['external'] = array();

		if ( ! empty($this->_foreign_object))
		{
			foreach($this->_foreign_object as $attr_name => $foreign)
			{
				if(is_object($foreign))
					// if object, then return the primary key value
					$_cols['internal'][$attr_name] = $foreign->get_primary_key_value();
				else
					// case if is array
					$_cols['external'][$attr_name] = $foreign;

			}
		}
		return $_cols;
	}

	protected function register_private_attribute($var_name, $attr)
	{
		// put the private attribute to be included in persistence
		// only for basic attributes
		// use the register_foreign_object function for array
		if ( ! is_object($attr) and ! is_array($attr))
			$this->included_attrs[$var_name] = $attr;
	}

	protected function register_foreign_object($attr_name,$object)
	{
		// register the foreign keys if the object is array or inherit from persistent object 
		if (is_array($object) or Utility::is_persistent_object($object))
			$this->_foreign_object[$attr_name] = $object;
	}
	
	public function save()
	{
		// only calling broker save
		Broker::save($this, $this->_meta);	
		return $this;
	}
	
	public function retrieve()
	{
		// only calling broker retrieve
		$res = Broker::retrieve(get_class($this), $this->_query_conditions);

		$this->assign_value($res);

		// empty query conditions
		$this->reset_query_conditions();
		$this->_retrieved = true;
		return $this;

	}

	public function retrieve_all()
	{
		// only calling broker retrieve
		$results = Broker::retrieve(get_class($this), $this->_query_conditions, true);
		
		$objects = array();
		foreach ($results as $res)
		{
			$objects[] = PersistentObject::factory(get_class($this))->assign_value($res);
		}

		// empty query conditions
		$this->reset_query_conditions();
		$this->_retrieved = true;
		
		return $objects;
	}

	public function delete()
	{
		// only calling broker delete
		$res = Broker::delete(get_class($this), $this->_query_conditions);
		// empty query conditions
		$this->reset_query_conditions();
		// print_r($res);
		return $this;
	}

	/*assign value to every variables*/
	protected function assign_value($vals)
	{
		if( ! empty($vals))
		{
			foreach ($vals as $attr => $val)
			{
				$is_object = false;
				$is_array = false;
				if ($attr != PersistentObject::get_key_column_name() && $attr != PersistentObject::$primary_key_column_name)
				{
					$is_object = $this->_meta->is_attribute_object($attr);
					$is_array = $this->_meta->is_attribute_array($attr);
				}

				if ( ! array_key_exists($attr, $this->included_attrs))
				{		
					if(! $is_object && ! $is_array)
						$this->$attr = $val;
					else
						$this->$attr = null;
				}
			}
			return $this;
		}

		// returning null if values not found
		return null;
	}

	public function where($attr, $operator, $val)
	{
		
		$this->_query_conditions[] = array('type' => 'where','parameters' => array($attr, $operator, $val));
		return $this;	
	}

	public function and_where($attr, $operator, $val)
	{
		$this->_query_conditions[] = array('type' => 'and_where','parameters' => array($attr, $operator, $val));
		return $this;
	}

	public function or_where($attr, $operator, $val)
	{
		$this->_query_conditions[] = array('type' => 'or_where','parameters' => array($attr, $operator, $val));
		return $this;
	}
	
	public function reset_query_conditions()
	{
		$this->_query_conditions = array();
	}


}
