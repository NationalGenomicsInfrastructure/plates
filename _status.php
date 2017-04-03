<?php
require 'global.php';

$cache='cache/status.json';
$current_time=time(); 
$expire_time=60; // Cached for 1min

if(file_exists($cache)) {
	$file_time=filemtime($cache);
	if($current_time-$expire_time<$file_time) {
		$data=file_get_contents($cache);
	} else {
		// Time has expired - generate new cache file
		$data=getStatus($cache);
	}
} else {
	// Cache file does not exist - generate new cache file
	$data=getStatus($cache);
}

echo $data;

function getStatus($cache=FALSE) {
	global $CONFIG;
	$systems['couch-status']=new Couch($CONFIG['couch']['host'],$CONFIG['couch']['port'],$CONFIG['couch']['user'],$CONFIG['couch']['pass']);
	$systems['lims-status']=new Clarity($CONFIG['clarity']['uri'],$CONFIG['clarity']['user'],$CONFIG['clarity']['pass']);
	
	foreach($systems as $system => $data) {
		if($data->testConnection()) {
			$result[$system]='success';
		} else {
			$result[$system]='alert';
		}
	}

	$data=json_encode($result);
	if($cache) {
		file_put_contents($cache,$data);
	}
	
	return $data;
}
?>
