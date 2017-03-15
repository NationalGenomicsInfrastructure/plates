<?php
// Load configuration
require_once 'config.php';

//Connect to database
$DB=new mysqli("p:".$CONFIG['mysql']['server'],$CONFIG['mysql']['user'],$CONFIG['mysql']['pass'],$CONFIG['mysql']['db']);
if($DB->connect_errno>0){
    die('Unable to connect to database [' . $DB->connect_error . ']');
}

// Include libraries
require_once 'class.clarity.v3.php';
require_once 'class.html.php';

//--------------------------------------------------------------------------------------------------
// Global functions
//--------------------------------------------------------------------------------------------------

function sql_query($sql) {
	global $DB;
	if(!$result = $DB->query($sql)){
	    die('There was an error running the query [' . $DB->error . ']');
	}	
	return $result;
}

// Fetch one row
function sql_fetch($sql) {
	if($query=sql_query($sql)) {
		$row=$query->fetch_assoc();
		return $row;
	} else {
		return FALSE;
	}
}

function setAlerts($text,$type='alert') {
	$types=array("alert","success");
	if(trim($text)!="") {
		$type=in_array($type,$types) ? $type : 'alert';
		$result=array(
			'type' => $type, 
			'text' => $text
		);
	} else {
		$result=FALSE;
	}
	return $result;
}

function formatAlerts($alerts) {
	$html='';
	if(count($alerts)) {
		foreach($alerts as $key => $alert_data) {
			$html.="<div class=\"callout ".$alert_data['type']."\">".$alert_data['text']."</div>\n";
		}
	}
	return $html;
}

// Consider making more user friendly messages, e.g. parse position to write out storage and rack names
function addLog($message,$action,$position,$user_email,$json=FALSE) {
	if(trim($message)!="") {
		$log=json_decode($json,TRUE);
		$entry=array(
			'timestamp' => time(), 
			'user'		=> $user_email, 
			'action'	=> $action,
			'position'	=> $position,
			'message'	=> $message);
	
		$log[]=$entry;
		return json_encode($log);
	} else {
		return FALSE;
	}
}

function getLastLog($json) {
	$log=json_decode($json,TRUE);
	if(count($log)) {
		return array_pop($log);
	} else {
		return FALSE;
	}
}

function parseLog($json,$type='plate') {
	$log=json_decode($json,TRUE);
	if(count($log)) {
		foreach($log as $key => $log_data) {
			switch($log_data['action']) {
				case "add":
				case "check_in":
					$action="<span class=\"success label\">".$log_data['action']."</span>";
				break;
	
				case "check_out":
				case "update":
					$action="<span class=\"warning label\">".$log_data['action']."</span>";
				break;

				case "return":
				case "destroy":
					$action="<span class=\"alert label\">".$log_data['action']."</span>";
				break;
			}
			
			switch($type) {
				default:
				case 'plate':
					$data[]=array(
						'action' => $action, 
						'message' => $log_data['message'], 
						'position' => '<code>'.$log_data['position'].'</code>', 
						'operator' => $log_data['user'], 
						'time' => date("Y-m-d H:i:s",$log_data['timestamp'])
					);
				break;
				
				case 'storage':
					$data[]=array(
						'action' => $action, 
						'message' => $log_data['message'], 
						'operator' => $log_data['user'], 
						'time' => date("Y-m-d H:i:s",$log_data['timestamp'])
					);
				break;
			}
		}
	} else {
		$data=array();
	}
	
	return $data;
}

// Plate name must be validated using validatePlate first
function plateAdd($plate,$position,$user_email) {
	if($position_data=parsePosition($position)) {
		if($position_data['type']=='position') {
			$rack=getRack($position_data['rack_id']);
			$message="Plate added to rack ".$rack['data']['rack_name']." in ".$rack['storage']['storage_type']." ".$rack['storage']['storage_name']." (".$rack['storage']['storage_location'].")";
			$log=addLog($message,"add",$position,$user_email);
			$add=sql_query("INSERT INTO plates SET 
				plate_id='$plate', 
				status='checked_in', 
				rack_id=".$position_data['rack_id'].", 
				col=".$position_data['xpos'].", 
				row=".$position_data['ypos'].", 
				log='$log'");
			if($add) {
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
}

// $plate_data is the SQL results from querying plate table
function plateCheckOut($plate_data,$user_email,$action='check_out') {
	$actions=array('check_out','return','destroy');
	if(in_array($action,$actions)) {
		$position=renderPositionID($plate_data);
		$rack=getRack($plate_data['rack_id']);
		switch($action) {
			case "check_out":
				$status='checked_out';
				$message="Plate checked out from rack ".$rack['data']['rack_name']." in ".$rack['storage']['storage_type']." ".$rack['storage']['storage_name']." (".$rack['storage']['storage_location'].")";
			break;

			case "return":
				$status='returned';
				$message="Plate checked out from rack ".$rack['data']['rack_name']." in ".$rack['storage']['storage_type']." ".$rack['storage']['storage_name']." (".$rack['storage']['storage_location'].") and returned";
			break;

			case "destroy":
				$status='destroyed';
				$message="Plate checked out from rack ".$rack['data']['rack_name']." in ".$rack['storage']['storage_type']." ".$rack['storage']['storage_name']." (".$rack['storage']['storage_location'].") and destroyed";
			break;
		}
		
		$log=addLog($message,$action,$position,$user_email,$plate_data['log']);
	
		$update=sql_query("UPDATE plates SET 
			status='$status', 
			rack_id='0', 
			col='0', 
			row='0', 
			log='$log' 
			WHERE plate_id='".$plate_data['plate_id']."'");
		
		if($update) {
			return TRUE;
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
}

function plateCheckIn($plate_data,$position,$user_email) {
	if($position_data=parsePosition($position)) {
		if($position_data['type']=='position') {
			$rack=getRack($position_data['rack_id']);
			$message="Plate checked in to rack ".$rack['data']['rack_name']." in ".$rack['storage']['storage_type']." ".$rack['storage']['storage_name']." (".$rack['storage']['storage_location'].")";
			$log=addLog($message,"check_in",$position,$user_email,$plate_data['log']);
	
			$update=sql_query("UPDATE plates SET 
				status='checked_in', 
				rack_id=".$position_data['rack_id'].", 
				col=".$position_data['xpos'].", 
				row=".$position_data['ypos'].", 
				log='$log' 
				WHERE plate_id='".$plate_data['plate_id']."'");
	
			if($update) {
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
}

function plateFind($query) {
	global $DB;
	$query=$DB->real_escape_string($query);
	$search_query=sql_query("SELECT * FROM plates WHERE plate_id LIKE '$query%'");
	
	if($search_query->num_rows>0) {
		while($plate=$search_query->fetch_assoc()) {
			$results['data'][$plate['plate_id']]="<code class=\"plate\">".$plate['plate_id']."</code> ".formatPlateStatus($plate['status'])."<br>";
		}
		$results['html']=implode(" ",$results['data']);
	} else {
		$results['data']=FALSE;
		$results['html']='<p>No plates found</p>';
	}
	
	return $results;
}

function formatPlateStatus($status) {
	switch($status) {
		default:
			return "<span class=\"label\">Undefined</span>";
		break;
		
		case "checked_in":
			return "<span class=\"success label\">Checked in</span>";
		break;

		case "checked_out":
			return "<span class=\"warning label\">Checked out</span>";
		break;

		case "returned":
			return "<span class=\"alert label\">Returned</span>";
		break;

		case "destroyed":
			return "<span class=\"alert label\">Destroyed</span>";
		break;
	}
}

function formatStorageStatus($status) {
	switch($status) {
		default:
			return "<span class=\"label\">Undefined</span>";
		break;

		case "enabled":
			return "<span class=\"success label\">Enabled</span>";
		break;

		case "service":
			return "<span class=\"warning label\">Service</span>";
		break;

		case "disabled":
			return "<span class=\"alert label\">Disabled</span>";
		break;
	}
}

function renderPositionID($plate_data) {
	$position="R".sprintf('%04d',$plate_data['rack_id'])."X".sprintf('%02d',$plate_data['col'])."Y".sprintf('%02d',$plate_data['row']);
	return $position;
}

function renderStorageID($storage_data) {
	$result='S'.sprintf('%04d',$storage_data['storage_id']);
	return $result;
}

function renderRackID($rack_data) {
	$result='R'.sprintf('%04d',$rack_data['rack_id']).'_';
	return $result;
}

function parsePosition($position) {
	if(preg_match("/^R([0-9]{4})X([0-9]{2})Y([0-9]{2})$/",$position,$matches)==1) {
		$rack_id=ltrim($matches[1],"0");
		$xpos=ltrim($matches[2],"0");
		$ypos=ltrim($matches[3],"0");
		return array('type' => 'position', 'rack_id' => $rack_id, 'xpos' => $xpos, 'ypos' => $ypos);
	} elseif(preg_match("/^R([0-9]{4})_$/",$position,$matches)==1) {
		$rack_id=ltrim($matches[1],"0");
		return array('type' => 'rack', 'rack_id' => $rack_id);
	} elseif(preg_match("/^S([0-9]{4})$/",$position,$matches)==1) {
		$storage_id=ltrim($matches[1],"0");
		return array('type' => 'storage', 'storage_id' => $storage_id);
	} else {
		return FALSE;
	}
}

// Get storage and rack data from specified storage ID
function getStorage($storage_id) {
	if($storage_id=filter_var($storage_id,FILTER_VALIDATE_INT)) {
		if($storage_data=sql_fetch("SELECT * FROM storage WHERE storage_id=$storage_id LIMIT 1")) {
			$results['data']=$storage_data;
			$rack_query=sql_query("SELECT * FROM racks WHERE storage_id=$storage_id ORDER BY rack_name");
			while($rack_data=$rack_query->fetch_assoc()) {
				$results['racks'][$rack_data['rack_id']]=$rack_data;
			}
			$results['error']=FALSE;
		} else {
			$results['error']='Specified storage does not exist';
		}
	} else {
		$results['error']='Invalid storage ID';
	}

	return $results;
}

// Produce table data to show storage layout
function parseStorageLayout($storage) {
	if(count($storage['racks'])) {
		foreach($storage['racks'] as $rack_id => $rack_data) {
			$data[]=array(array(
				'text'		=> $rack_data['rack_name'], 
				'attrib'	=> array('data-position' => renderRackID($rack_data))
			));
		}
	}
	
	return $data;
}

// Get rack and plate data from specified rack ID
// If given plate ID and/or rack position this can be highlighted in results
// Function parseRackLocation will produce data that can be used for table output by htmlTable
function getRack($rack_id,$plate_id=FALSE,$xpos=FALSE,$ypos=FALSE) {
	$error=FALSE;
	if($rack_id=filter_var($rack_id,FILTER_VALIDATE_INT)) {
		if($rack_data=sql_fetch("SELECT * FROM racks WHERE rack_id=$rack_id LIMIT 1")) {
			$storage=sql_fetch('SELECT * FROM storage WHERE storage_id='.$rack_data['storage_id'].' LIMIT 1');
			$plate_query=sql_query("SELECT * FROM plates WHERE rack_id=".$rack_data['rack_id']);
			$meta['total_plates']=$plate_query->num_rows;
			$meta['max_positions']=$rack_data['slots']>0 ? $rack_data['slots']*$rack_data['cols']*$rack_data['rows'] : FALSE;
			
			$layout=array();
			for($row=1;$row<=$rack_data['rows'];$row++) {
				$cells=array();
				for($col=1;$col<=$rack_data['cols'];$col++) {
					$plates=array();
					$classes=array();
					
					$position="R".sprintf('%04d',$rack_id)."X".sprintf('%02d',$col)."Y".sprintf('%02d',$row);
					$plate_query=sql_query("SELECT * FROM plates WHERE rack_id=".$rack_data['rack_id']." AND col=$col AND row=$row");

					while($plate_data=$plate_query->fetch_assoc()) {
						$plates[$plate_data['plate_id']]=array(
							'data'		=> $plate_data, 
							'selected'	=> $plate_id==$plate_data['plate_id']
						);
					}

					$cells[]=array(
						'position'	=> $position, 
						'plates'	=> $plates, 
						'selected'	=> ($col==$xpos && $row==$ypos), 
						'full'		=> count($plates)==$rack_data['slots']
					);
				}
				$layout[]=$cells;
			}
		} else {
			// Rack does not exist
			$error='Rack does not exist';
		}
	} else {
		// Invalid rack ID
		$error='Invalid rack ID';
	}
	
	return array(
		'data'		=> $rack_data, 
		'storage'	=> $storage, 
		'meta'		=> $meta, 
		'layout'	=> $layout, 
		'error'		=> $error 
	);
}

// Produce table data to show rack layout
// If showplates is set it will show plates for each position, otherwise print barcode for each cell
function parseRackLayout($layout,$showplates=TRUE) {
	if(is_array($layout)) {
		$data=array();
		foreach($layout as $rowdata) {
			$cells=array();
			foreach($rowdata as $cell) {
				$plates=array();
				$classes=array();

				foreach($cell['plates'] as $plate_id => $plate_data) {
					$plates[]=$plate_data['selected'] ? "<code class=\"selected\">$plate_id</code>" : "<code>$plate_id</code>";
				}

				$classes[]=$cell['selected'] ? 'selected' : '';
				$classes[]=$cell['full'] ? 'full' : '';

				$cells[]=array(
					'text'		=> $showplates ? (count($plates) ? implode(" ",$plates) : '&nbsp;') : '<img alt="'.$cell['position'].'" src="barcode.php?text='.$cell['position'].'&print=true">', 
					'attrib'	=> array('class' => implode(" ",$classes),'data-position' => $cell['position'])
				);
			}
			$data[]=$cells;
		}
	} else {
		$data=array();
	}
	
	return $data;
}

// Validate name format and check if plate exist in LIMS
function validatePlate($query) {
	// Many different formats.... 
	// LIMS ID: NN-NNNNNN - if match fetch container name (also check for type [sample|other])
	// Plate name (project): PNNNNPN - type: [sample], if match fetch container LIMS ID
	// Plate name (WS): WSYYMMDD, WSYYMMDD-text - type: [other], if match fetch container LIMS ID
	
	// Returns array of name,limsid,type if validated, otherwise FALSE
	
	global $DB;

	if(preg_match("/(^\d{2}-\d+)/", $query, $matches)) {
		// LIMS container ID
		if($results=checkLIMScontainerID($matches[0])) {
			$results['type']=validateLIMScontainerType($results['name']);
		} else {
			// Same format as LIMS container ID but does not exist in LIMS...
			$results=FALSE;
		}
	} else {
		// Other format, presumably plate name
		if($results=checkLIMScontainerName($query)) {
			// Plate exists in LIMS
			$results['type']=validateLIMScontainerType($results['name']);
		} else {
			// Plate does not exist in LIMS, but it can be a new plate that hasn't been imported yet
			$name=trim($DB->real_escape_string($query));
			$results['name']=$name;
			$results['limsid']=FALSE;
			$results['type']=validateLIMScontainerType($name);
		}
	}
	
	return $results;
}

// Check both format and existence of a LIMS container based on LIMS container name
function checkLIMScontainerName($name) {
	global $CONFIG;
	$name=trim(filter_var($name,FILTER_SANITIZE_STRING));
	
	$clarity=new Clarity("https://genologics.scilifelab.se/api/v2/",$CONFIG['clarity']['user'],$CONFIG['clarity']['pass']);
	if(!$container=$clarity->getEntity("containers/?name=$name")) {
		$container=FALSE;
	}

	if(is_array($container)) {
		return array("name" => $name, "limsid" => $container['container']['limsid']);
	} else {
		return FALSE;
	}
}

// Check both format and existence of a LIMS container based on LIMS container ID
function checkLIMScontainerID($id) {
	global $CONFIG;
	if($id=validateLIMScontainerID($id)) {
		$clarity=new Clarity("https://genologics.scilifelab.se/api/v2/",$CONFIG['clarity']['user'],$CONFIG['clarity']['pass']);
		$container=$clarity->getEntity("containers/$id");

		if(is_array($container)) {
			return array("name" => $container['name'], "limsid" => $id);
		} else {
			return FALSE;
		}
	} else {
		return FALSE;
	}
}

// Only validate format of LIMS container ID
function validateLIMScontainerID($id) {
	return filter_var($id, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/(^\d{2}-\d+)/")));
}

// Only validate format of LIMS project ID
function validateLimsProjectID($lims_id) {
	return filter_var($lims_id, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/^P[0-9]{3,}/")));
}

// Only validate format of LIMS project name
function validateLimsProjectName($lims_name) {
	return filter_var($lims_name, FILTER_VALIDATE_REGEXP, array("options" => array("regexp" => "/^[A-Z]+\.[A-Za-z]{2,}_[0-9]{2}_[0-9]{2}/")));
}

// Determine wheter name is of sample plate format or other name
function validateLIMScontainerType($name) {
	if(preg_match("/(^P\d+)(P[1-9])/",$name)) {
		return "sample";
	} else {
		return "other";
	}
}

// Get LIMS ID from Plate ID
function parseProjectPlateName($plate_name) {
	if(preg_match("/(^P\d+)(P[1-9])/",$plate_name,$matches)) {
		return array("limsid" => $matches[1], "plate_number" => $matches[2]);
	} else {
		return FALSE;
	}
}

function findProjectByName($query) {
	$projects_all=getProjects();
	$project_names=array_column($projects_all,"project_name","lims_id");
	$search_results=array_filter($project_names,function($item) use ($query) {
		return (stripos($item,$query) !== FALSE);
	});
	
	$results['query']=$query;	
	$results['html']="<ul class=\"no-bullet\">";
	if(count($search_results)>0) {
		$results['data']=$search_results;
		foreach($search_results as $lims_id => $project_name) {
			$results['html'].="<li><code class=\"plate\">$lims_id</code> $project_name (".$projects_all[$lims_id]['project_name_user'].")</li>";
		}
	} else {
		$results['data']=FALSE;
		$results['html']="<li>No results matching query</li>";
	}
	$results['html'].="</ul>";
	
	return $results;
}

function showProjectData($project) {
	$platesearch=plateFind($project['limsid']);
	$libprepdata=parseLibprep($project['udf']['Library construction method']);

	$projectcard=new zurbCard();
	$projectdata=new htmlList('ul',array('class' => 'no-bullet'));
	$projectdata->listItem('Input material: '.$libprepdata['input']);
	$projectdata->listItem('Library prep: '.$libprepdata['type']);
	$projectcard->divider('<strong>Selected project</strong> '.$project['limsid'].', '.$project['name'].' ('.$project['udf']['Customer project reference'].")");
	$projectcard->section($projectdata->render());
	$projectcard->divider('Registered plates matching: '.$project['limsid']);
	$projectcard->section($platesearch['html']);
	
	return $projectcard->render();
}

// Fetch all projects from StatusDB
function getProjects() {
	global $CONFIG;
	$auth=$CONFIG['couch']['user'].":".$CONFIG['couch']['pass'];
	$url="http://$auth@tools.scilifelab.se:5984/projects/_design/project/_view/summary?reduce=false";
	$data=file_get_contents($url);
    $json=json_decode($data);

    foreach($json->rows as $object) {
	    $projectdata=array(
		    'lims_id'					=> $object->key[1], 
		    'project_name'				=> empty($object->value->project_name) ? "" : $object->value->project_name, 
		    'project_name_user'			=> empty($object->value->details->customer_project_reference) ? "" : $object->value->details->customer_project_reference, 
		    'project_funding'			=> empty($object->value->details->funding_agency) ? "" : $object->value->details->funding_agency, 
		    'portal_id'					=> empty($object->value->details->portal_id) ? "" : $object->value->details->portal_id, 
		    'type'						=> empty($object->value->details->type) ? "" : $object->value->details->type, 
		    'application'				=> empty($object->value->application) ? "" : $object->value->application, 
		    'reference_genome'			=> empty($object->value->reference_genome) ? "" : $object->value->reference_genome, 
		    'organism'					=> empty($object->value->details->organism) ? "" : $object->value->details->organism, 
		    'sample_units_ordered'		=> empty($object->value->details->sample_units_ordered) ? "" : $object->value->details->sample_units_ordered, 
		    'sample_units_imported'		=> empty($object->value->no_samples) ? "" : $object->value->no_samples, 
		    'sample_disposal'			=> empty($object->value->details->disposal_of_any_remaining_samples) ? "" : $object->value->details->disposal_of_any_remaining_samples, 
		    'lib_prep'					=> empty($object->value->details->library_construction_method) ? "" : $object->value->details->library_construction_method, 
		    'seq_platform'				=> empty($object->value->details->sequencing_platform) ? "" : $object->value->details->sequencing_platform, 
		    'seq_config'				=> empty($object->value->details->sequencing_setup) ? "" : $object->value->details->sequencing_setup, 
		    'seq_units_ordered'			=> empty($object->value->details->{'sequence_units_ordered_(lanes)'}) ? "" : $object->value->details->{'sequence_units_ordered_(lanes)'}, 
		    'date_order'				=> empty($object->value->details->order_received) ? "" : $object->value->details->order_received, 
		    'date_contract_sent'		=> empty($object->value->details->contract_sent) ? "" : $object->value->details->contract_sent, 
		    'date_plates_sent'			=> empty($object->value->details->plates_sent) ? "" : $object->value->details->plates_sent, 
		    'date_contract_received'	=> empty($object->value->details->contract_received) ? "" : $object->value->details->contract_received, 
		    'date_sample_info'			=> empty($object->value->details->sample_information_received) ? "" : $object->value->details->sample_information_received, 
		    'date_samples'				=> empty($object->value->details->samples_received) ? "" : $object->value->details->samples_received, 
		    'date_open'					=> empty($object->value->open_date) ? "" : $object->value->open_date, 
		    'date_queue'				=> empty($object->value->details->queued) ? "" : $object->value->details->queued, 
		    'date_seq_finished'			=> empty($object->value->details->all_samples_sequenced) ? "" : $object->value->details->all_samples_sequenced, 
		    'date_delivered'			=> empty($object->value->details->all_raw_data_delivered) ? "" : $object->value->details->all_raw_data_delivered, 
		    'date_close'				=> empty($object->value->close_date) ? "" : $object->value->close_date, 
		    'date_aborted'				=> empty($object->value->details->aborted) ? "" : $object->value->details->aborted 
	    );

	    $projects[$object->key[1]]=$projectdata;
    }

    return $projects;
}

// Get specific project from LIMS (using LIMS ID)
function getProject($lims_id) {
	global $ALERTS,$CONFIG;
	if($lims_id=validateLimsProjectID($lims_id)) {
		$clarity=new Clarity("https://genologics.scilifelab.se/api/v2/",$CONFIG['clarity']['user'],$CONFIG['clarity']['pass']);
		if($project=$clarity->getEntity("projects/$lims_id")) {
			return $project;
		} else {
			$ALERTS[]=setAlerts("Project not found: $lims_id");
			return FALSE;
		}
	} else {
		$ALERTS[]=setAlerts("Invalid format of LIMS ID: $lims_id");
		return FALSE;
	}
}

// This is ugly... Syntax of this context has changed
function parseLibprep($prep) {
 	if(preg_match("/(.+)\[(\d+|-)\]/",$prep,$temp_data)) {
	 	// Only parse the string if it matches the new format
	 	// input,type,option,category [document ID]
		list($data['input'],$data['type'],$data['option'],$data['category'])=explode(",",$temp_data[1]);
	 	$data['document']=$temp_data[2];
 	} else {
	 	// Latest format...
	 	list($data['input'],$data['type'],$data['option'],$data['category'],$data['document'])=explode(",",$prep);
 	}

 	// Remove whitespace
 	foreach($data as $key => $value) {
	 	$data[$key]=trim($value);
 	}
 	
 	return $data;
}

// Get all users from StatusDB
function getUsers($user=FALSE) {
	global $CONFIG;
	$auth=$CONFIG['couch']['user'].":".$CONFIG['couch']['pass'];
	$url="http://$auth@tools.scilifelab.se:5984/gs_users/_design/authorized/_view/users?reduce=false";
	$data=file_get_contents($url);
    $json=json_decode($data);

    foreach($json->rows as $object) {
	    $users[$object->key]=$object->value;
    }

    return $users;
}

// Check if email exists in StatusDB
function checkUser($user_email) {
	$users=getUsers();
    if(filter_var($user_email,FILTER_VALIDATE_EMAIL)) {
	    if(array_key_exists($user_email,$users)) {
		    return array("uid" => $users[$user_email], "user_email" => $user_email);
	    } else {
		    return FALSE;
	    }
    } else {
	    return FALSE;
    }
}

?>