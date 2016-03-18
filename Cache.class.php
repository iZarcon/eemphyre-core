<?php
/*---------------------------------------------------
These files are part of the empiresPHPframework;
The original framework core (specifically the mysql.php
the router.php and the errorlog) was started by Timo Ewalds,
and rewritten to use APC and extended by Julian Haagsma,
for use in Earth Empires (located at http://www.earthempires.com );
it was spun out for use on other projects.

The general.php contains content from Earth Empires
written by Dave McVittie and Joe Obbish.


The example website files were written by Julian Haagsma.

All files are licensed under the MIT License.

First release, September 3, 2012
---------------------------------------------------*/

class Cache {
	public static $count = 0;
	public static $queries = array();
	
	function __construct(){
		//$this->queries = array();
		//$this->count = 0;
	}

	function __destruct(){

	}
	
	private static function addquery($stuff){
		self::$queries[] = $stuff;
		self::$count++;
		if(count(self::$queries) > 1000)
			array_shift(self::$queries);
	}

	public static function add($key,$val,$ttl=0){
		$start = microtime(true);
		$success = apcu_add($key,$val,$ttl);
		self::addquery(array('add',$success,microtime(true)-$start,$key,$ttl));
		return $success;
	}
	
	public static function store($key,$val,$ttl=0){
		$start = microtime(true);
		$success = apcu_store($key,$val,$ttl);
		self::addquery(array('store',$success,microtime(true)-$start,$key,$ttl));
		return $success;
	}
	
	public static function fetch($key, $default = null){
		$start = microtime(true);
		$val = apcu_fetch($key,$success);
		self::addquery(array('fetch',$success,microtime(true)-$start,$key,null));
		return ($success ? $val : $default);
	}

	public static function multi_fetch($keys){
		$return = array();
		foreach($keys as $key){
			$start = microtime(true);
			$val = apcu_fetch($key,$success);
			self::addquery(array('fetch',$success,microtime(true)-$start,$key,null));
			if($success)
				$return[$key] = $val;
		}
		return $return;
	}
	
	public static function fetch_prefix_keys($prefix,$keys){
		$fetch = array();
		foreach($keys as $k)
			$fetch[] = $prefix.$k;
			
		return self::multi_fetch($fetch);
	}
	
	public static function delete($key){
		$start = microtime(true);
		$success = apcu_delete($key);
		self::addquery(array('delete',$success,microtime(true)-$start,$key,null));
		return $success;
	}
	
	public static function clear_user_cache(){
		$start = microtime(true);
		$success = apcu_clear_cache('user');
		self::addquery(array('clear user cache',$success,microtime(true)-$start,null,null));
		return $success;
	}
	
	public static function clear_cache(){
		$start = microtime(true);
		$success = apcu_clear_cache();
		self::addquery(array('clear cache',$success,microtime(true)-$start,null,null));
		return $success;
	}
}