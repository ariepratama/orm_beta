<?php defined('SYSPATH') or die('No direct script access.');
// try 
// {
	// if metadata table not exists
	// DB::query(Database::SELECT, 'select * from \'_meta\'')->execute();
// } catch(Database_Exception $e)
// {
	// create metadtaa table if not exists
	
	$_res = DB::query(null, '
		create table if not exists _meta(
			class_hash varchar(32) not null,
			class text not null,
			metadata text not null,
			primary key (class_hash)
		)
	')->execute();
// }