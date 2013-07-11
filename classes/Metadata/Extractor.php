<?php



class Metadata_Extractor{
// TODO: konversi tipe dan penentuan relasi objek secara otomatis
// TODO: cari + bandingin tipe data mysql dan php
// TODO: gimana cara ngatasin nested array?
	private static $mapper;
	// private static $excluded_attr;
		
	private static function init_meta_cache()
	{
		Metadata_Extractor::$_meta_cache = array();
	}
	
	private static function init_constants()
	{
		
		Metadata_Extractor::$mapper = array(
		// map string in application to text in db
			'string' => array('db_type' => 'text', 'rel_type' => null),
		// map integer to integer
			'integer' => array('db_type' => 'int', 'rel_type' => null),
		// map floating point (in php float and double are the same) to doubles
			'double' => array('db_type' => 'double', 'rel_type' => null),
			// 'boolean' => array('db_type' => 'boolean', 'rel_type' => null),
		// map object to one to one relation, and array to one to many relation
			'object' => array('db_type' => 'relation', 'rel_type' => 'one_to_one'),
			'array' =>  array('db_type' => 'relation', 'rel_type' => 'one_to_many'),
		);
		// relation type 2 = one_to_one & one_to_many
		
		// relation type1 = has_one, belongs_to, has_many
		// has_one = foreign key on one class table
		// belongs_to = foreign key on one class table (sama dengan has_one, namun semantiknya beda)
		// has_many = foreign key on one cardinality class table
		
		
		
	}
	
	public static function extract_metadata($object)
	{
		// create and save metadata to database
		if (Metadata_Extractor::$mapper === null) Metadata_Extractor::init_constants();
		
		// print_r(Metadata_Extractor::get_class_attrs(get_class($object)));
		// return metadata instance
		return new Metadata_Instance(get_class($object), Metadata_Extractor::deserialize_object($object));
	}
	
	private static function create_atribute_metadata($attr_name, $attr, $table, $_rel_type = null, $_rel_with = null)
	{
		// format metadata JSON:
		// "<nama_attribute>" : {"app_type": "<type_name>" , "db_type": "<type_name>" ,"rel_type": "<relation_type_name>", "rel_with": "<object_related_with>", "db_column_name": <db column name>}"
		// delete null valued variable
		// with column name on database
		$attr_type = gettype($attr);

		if ( ! is_null($attr))
		{
			$rel_with = null;

			// condition if an attribute is having relation with object or many object
			if(is_object($attr))
				$rel_with = get_class($attr);
			else if (is_array($attr))
			{
				// CONSTRAINT: 
				// array value must be singled type, not mixed
				$first_el = reset($attr);

				// TODO: create exception for mixed array
			// TODO: what about complex array??
				if (is_object($first_el))
					$rel_with = get_class($first_el);
				else
					$rel_with = Metadata_Extractor::$mapper[gettype($first_el)]['db_type'];

			
			} 


			$map = new Attribute_Map(
							 $attr_type, 
							 Metadata_Extractor::$mapper[$attr_type]['db_type'], 
							 (is_null($_rel_type)) ? Metadata_Extractor::$mapper[$attr_type]['rel_type'] : $_rel_type, 
							 (is_null($_rel_with)) ? $rel_with : $_rel_with,
							 $attr_name,
							 $table
							);

			return $map->as_array();
		}
		else
		{
			return null;
		}
	}




	private static function get_class_attrs($class_name, $attrs){
		
		$class_attrs = get_class_vars($class_name);
		$meta = Metadata_Extractor::change_array_format($class_attrs);
		$parent = get_parent_class($class_name);
		
		// specialy for PersistentObject class
		if($parent != 'PersistentObject')
		{
			$meta = Metadata_Extractor::blend_array($meta, Metadata_Extractor::get_class_attrs($parent));
		}


		return $meta;


	}

	private static function create_meta_array($class_name, $class_attrs, $table = null)
	{
		$class_meta = array();

		// set the table name the same as class name
		if (is_null($table))
			$class_meta[Metadata_Constants::$TABLE_NAME_STRING] = $class_name;
		else
			$class_meta[Metadata_Constants::$TABLE_NAME_STRING] = $table;

		foreach ($class_attrs as $var_name => $value)
		{
			$attr_meta = Metadata_Extractor::create_atribute_metadata($var_name, $value, $class_meta[Metadata_Constants::$TABLE_NAME_STRING]); 

			if ($attr_meta != null)
				$class_meta[Metadata_Constants::$ATTRIBUTES_STRING][$var_name] = $attr_meta;
		}

		return $class_meta;
	}

	private static function separate_extended_class_meta($classes, $class_meta, $class_attrs)
	{
		$separated = array();

		// initialize each classes as array
		foreach($classes as $class)
		{
			$separated[$class] = array();
		}

		foreach($class_meta as $var_name => $class)
		{
			$separated[$class][$var_name] = $class_attrs[$var_name];
		}

		return $separated;
	}
	private static function get_base_class($class)
	{
		
		$current = $class;
		$parent = get_parent_class($current);
		while ($parent != 'PersistentObject')
		{
			$current = Metadata_Extractor::get_base_class($parent);
			$parent = get_parent_class($current);
		}
		return $current;
	}
	private static function build_attributes_metadata_recursive($class, $separated)
	{
		$parent = get_parent_class($class);
		$meta = array();
		
		if ($parent != 'PersistentObject')
		{
			$meta_parent = Metadata_Extractor::build_attributes_metadata_recursive($parent, $separated);
			if (! empty($meta_parent))
				$meta = array_merge($meta, $meta_parent);
		}
		

		Utility::debug('build for '.$parent, $meta);
		$meta_current = Metadata_Extractor::create_meta_array($class, $separated[$class]);
		if (! empty ($meta_current))
			$meta = array_merge($meta, $meta_current[Metadata_Constants::$ATTRIBUTES_STRING]);


		return $meta;


	}


	public static function deserialize_object($object)
	{
		// create deserialize representative array for object
		$obj_attrs = $object->get_object_attributes();		
		$class_name = get_class($object);
		$class_meta = $object->get_class_attributes();
		$id_type = $object->get_id_db_type();
		// echo 'base: sdasdf';
		$root = Utility::get_root_parent_class($class_name);

		
		// Utility::debug('base', $root);
		// Utility::debug('class meta', $class_meta);

		// get distinct classes
		$classes = array_unique($class_meta);
		Utility::debug('classes', $classes);
		// $meta = Broker::get_metadata_for_class($classes['street_name']);
		$separated = Metadata_Extractor::separate_extended_class_meta($classes, $class_meta, $obj_attrs);

		Utility::debug('separated', $separated);
		$meta = array();


		foreach($separated as $class => $attrs)
		{
			$meta_class = Broker::get_metadata_for_class($class);
			Utility::debug('making metadata for', $class);
			
			if(empty($meta_class))
			{
				if ( ! isset($meta[$class_name]))
					$meta[$class_name] = array();

				// if ( ! array_key_exists(Metadata_Constants::$ID_METADATA_STRING, $attrs))
				// 	$attrs[Metadata_Constants::$ID_METADATA_STRING] = $separated[$root][Metadata_Constants::$ID_METADATA_STRING];

				// if ($class != $class_name)
				// 	$real_attrs = $object->get_class_attributes($class, true);

				// Utility::debug('the class attributes', $attrs);

				$attr_meta = Metadata_Extractor::build_attributes_metadata_recursive($class, $separated);
				// Utility::debug('this is test', $test);

				$meta_class[$class][Metadata_Constants::$TABLE_NAME_STRING] = $class;//Metadata_Extractor::create_meta_array($class, $attrs);
				$meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING] = $attr_meta;

				$current_class_parent = (get_parent_class($class) == 'PersistentObject')? '':get_parent_class($class);
				$meta_class[$class][Metadata_Constants::$PARENT_CLASS_STRING] = $current_class_parent;


				if (! empty($current_class_parent))
				{
					$meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING][Metadata_Constants::$ID_METADATA_STRING] = Metadata_Extractor::create_atribute_metadata(
																																		Metadata_Constants::$ID_METADATA_STRING,
																																		$obj_attrs[Metadata_Constants::$ID_METADATA_STRING],
																																		$class,
																																		RelationType::$ONE_TO_ONE,
																																		$root
																																	);
					
				}
				if (! is_null($id_type))
					$meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING][Metadata_Constants::$ID_METADATA_STRING][Metadata_Constants::$DB_TYPE_STRING] = $id_type;
				
				// Utility::debug('the meta class', $meta_class);
				
				// if (! array_key_exists(Metadata_Constants::$ID_METADATA_STRING, $meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING]))
				// 	$meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING] = 

				if ($class == $class_name)
					$meta[$class_name] = array_merge($meta[$class_name], $meta_class[$class]);
				else
				{
					Broker::save_metadata($class, json_encode($meta_class));
					// $meta[$class_name][Metadata_Constants::$ATTRIBUTES_STRING] = array_merge($meta[$class_name][Metadata_Constants::$ATTRIBUTES_STRING],
					// 								 $meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING]);
				}
			}
			else
			{
				$meta_class = $meta_class->as_array();
				$_table = $meta_class[Metadata_Constants::$TABLE_NAME_STRING];
				// if ($meta_class !== $meta[$class])
				// 	Broker::update_metadata($class, json_encode($meta[$class]));

				// $meta[$class_name][Metadata_Constants::$ATTRIBUTES_STRING] = array_merge($meta[$class_name][Metadata_Constants::$ATTRIBUTES_STRING], 
				// 								 Metadata_Extractor::create_meta_array($class, $attrs, $_table)[Metadata_Constants::$ATTRIBUTES_STRING]);
			}
			if ($class != $class_name)
				unset($meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING][Metadata_Constants::$ID_METADATA_STRING]);			

			$meta[$class_name][Metadata_Constants::$ATTRIBUTES_STRING] = array_merge($meta[$class_name][Metadata_Constants::$ATTRIBUTES_STRING],
													 $meta_class[$class][Metadata_Constants::$ATTRIBUTES_STRING]);
		}
		
		// Utility::debug('the metadata', $meta);
		
		

		// $attr_array = array();

		// // create metadata foreach atttribute of the object
		// foreach($obj_attrs as $obj_name => $obj_attr)
		// {
		// 	$attr_meta = Metadata_Extractor::create_atribute_metadata($obj_name, $obj_attr, $class_meta[$obj_name]);
			
		// 	if ($attr_meta != null)
		// 		$attr_array[$obj_name] = $attr_meta;
		// }
		// echo '<br/><br/><br/>';
		// print_r($attr_array);	
		
		// if (empty($meta))
		// {
		// 	$meta = array();
		// 	$meta[$class_name] = array();
		// 	// $meta[$class_name]['table_name'] = $class_name;
		// }
		// $id_map =  new Attribute_Map(
		// 								gettype($object->get_primary_key_value()),
		// 								Table_Manager::$PersistentObject_primary_key_type,
		// 								null,
		// 								null,
		// 								$object->get_pk_column_name()
		// 							);
		// $id_map->set_db_constraint(Table_Manager::$pk_constraint);
		// /*if extracted automatically id data type in database is int*/
		// // $meta[$class_name]['_id'] = $id_map->as_array();

		// /*contains other properties of the class */
		// $meta[$class_name] = $attr_array;

		return $meta;
	}
	
}