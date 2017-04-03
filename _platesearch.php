<?php
require 'global.php';

$cache='cache/plates.json';
$current_time=time(); 
$expire_time=3600; // Cached for 1h

// Plates are cached in a json file
if(file_exists($cache)) {
	$file_time=filemtime($cache);
	if($current_time-$expire_time<$file_time) {
		$data=json_decode(file_get_contents($cache));
	} else {
		// Time has expired - generate new cache file
		$data=getPlates($cache);
	}
} else {
	// Cache file does not exist - generate new cache file
	$data=getPlates($cache);
}

if(isset($_REQUEST['query'])) {
	$query=trim($_REQUEST['query']);
	if(is_array($data)) {
		$search_results=array_filter($data,function($item) use ($query) {
			return (stripos($item,$query) !== FALSE);
		});
		
		$card=new zurbCard();
		$card->divider('Registered plates matching: '.$query);
		foreach($search_results as $plate) {
			$content.='<code class="plate">'.$plate.'</code> ';
		}
		$card->section($content);
		echo $card->render();
	}
}
?>
