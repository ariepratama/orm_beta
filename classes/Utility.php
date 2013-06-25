<?php
class Utility{

	private static $base_types = array('boolean', 'text', 'int', 'double');
	public static $is_debug = true;
	public static function get_root_parent_class($_class)
	{
		$root = get_parent_class($_class);
		if (! empty($root) and $root != 'PersistentObject')
		{
			return Utility::get_root_parent_class($root);
			
		}
		else
			return $_class;
	}

	public static function debug($msg, $var = null)
	{
		if(Utility::$is_debug)
		{
			echo $msg.': ';
			if (! is_null($var))
			{
				if (is_array($var) || is_object($var))
					print_r($var);
				else
					echo $var;

				echo '<br/>';
			}
			echo '<br/><br/>';
		}

	}
	public static function relation_name($var_name, $related_table)
	{
		return $var_name.'_of_'.$related_table;
	}

	public static function relation_id_col($base_table, $id_col)
	{
		return $base_table.$id_col;
	}


	public static function is_persistent_object($obj)
	{
		$_class = get_class($obj);
		$base = Utility::get_root_parent_class($_class);
		$root = get_parent_class($base);
		return $root == 'PersistentObject';
	}

	public static function is_base_type($type)
	{
		return in_array($type, Utility::$base_types);
	}

	public static function change_array_format($vars, $class){
		$newvar = array();
		foreach ($vars as $var => $default)
		{
			$newvar[$var] = $class;
		}
		return $newvar;
	}

	public static function blend_array($arr1, $arr2){
		foreach($arr2 as $el => $val)
		{
			$arr1[$el] = $val;
		}
		return $arr1;
	}


}