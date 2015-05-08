<?php
$memcache = new DRMemcache;
$memcache->addServer("localhost",11211);
$memcache->flush();
mysql_connect("localhost","drserver","DrsCab1123");
mysql_select_db("drcache");
mysql_query("truncate table cache");
if (function_exists("zend_shm_cache_clear")) {
	zend_shm_cache_clear();
	zend_disk_cache_clear();
}
