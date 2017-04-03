<?php
require 'global.php';

$ALERTS=array();

if(isset($_POST['user_email'])) {
	if($user=checkUser($_POST['user_email'])) {
		if($plate=parseQuery($_POST['plate'])) {
			if(isset($_POST['submit'])) {
				$plate_data=sql_fetch("SELECT * FROM plates WHERE plate_id='".$plate['name']."' LIMIT 1");
				if($plate_data) {
					// Plate is already in DB
					// Check status
					if($plate_data['status']=="checked_in") {
						// Plate is checked in
						// Scan plate to verify check out
						if(isset($_POST['plate_verify'])) {
							if($plate['name']==$_POST['plate_verify']) {
								if(plateCheckOut($plate_data,$user['user_email'])) {
									$ALERTS[]=setAlerts("Plate checked out","success");
									$plate=FALSE;
								} else {
									$ALERTS[]=setAlerts("Could not check out plate!");
								}
							} else {
								$ALERTS[]=setAlerts("Plate name does not match!");
							}
						}
					} elseif($plate_data['status']=="checked_out") {
						// Plate is checked out
						// Show previous position!
						if(isset($_POST['position'])) {
							if(plateCheckIn($plate_data,$_POST['position'],$user['user_email'])) {
								$ALERTS[]=setAlerts("Plate was checked in successfully","success");
								$plate=FALSE;
							} else {
								$ALERTS[]=setAlerts("Could not check in plate!");
							}
						}
					}
				} else {
					// Plate is not in database
					// If position is set, proceed with adding plate
					if(isset($_POST['position'])) {
						// Check in new plate (add)
						if(plateAdd($plate['name'],$_POST['position'],$user['user_email'])) {
							$ALERTS[]=setAlerts("Plate was added successfully","success");
							$plate=FALSE;
						} else {
							$ALERTS[]=setAlerts("Could not add plate!");
						}
					}
				}
			} elseif(isset($_POST['return'])) {
				$plate_data=sql_fetch("SELECT * FROM plates WHERE plate_id='".$plate['name']."' LIMIT 1");
				if($plate_data) {
					if(isset($_POST['plate_verify'])) {
						if($plate['name']==$_POST['plate_verify']) {
							if(plateCheckOut($plate_data,$user['user_email'],'return')) {
								$ALERTS[]=setAlerts("Plate checked out for returning to user, this means a plate with the same name can not be checked in again","warning");
								$plate=FALSE;
							} else {
								$ALERTS[]=setAlerts("Could not check out plate!");
							}
						} else {
							$ALERTS[]=setAlerts("Plate name does not match!");
						}
					}
				}
			} elseif(isset($_POST['destroy'])) {
				$plate_data=sql_fetch("SELECT * FROM plates WHERE plate_id='".$plate['name']."' LIMIT 1");
				if($plate_data) {
					if(isset($_POST['plate_verify'])) {
						if($plate['name']==$_POST['plate_verify']) {
							if(plateCheckOut($plate_data,$user['user_email'],'destroy')) {
								$ALERTS[]=setAlerts("Plate checked out for destruction, this means a plate with the same name can not be checked in again","warning");
								$plate=FALSE;
							} else {
								$ALERTS[]=setAlerts("Could not check out plate!");
							}
						} else {
							$ALERTS[]=setAlerts("Plate name does not match!");
						}
					}
				}
			} elseif(isset($_POST['cancel'])) {
				$plate=FALSE;
			}
		} else {
			$plate=FALSE;
			$ALERTS[]=setAlerts("Invalid query: ".$_POST['plate']);
		}
	} else {
		$plate=FALSE;
		$ALERTS[]=setAlerts("Invalid user name");
	}
} else {
	$plate=FALSE;
}

// Build page
//=================================================================================================

$theform=new htmlForm('index.php');

// Find checked out plates
$checkedout=sql_query("SELECT * FROM plates WHERE status='checked_out'");
if($checkedout->num_rows>0) {
	while($checkedout_plate=$checkedout->fetch_assoc()) {
		$lastlog=getLastLog($checkedout_plate['log']);
		$checkedouttime=date('Y-m-d H:i:s', $lastlog['timestamp']);
		$diff=time()-$lastlog['timestamp'];
		if($diff<3600) {
			$diff=round($diff/60);
			$diff_msg=' <span class="label">ca '.$diff.' min ago</span>';
		} elseif($diff<86400) {
			$diff=round($diff/3600);
			$diff_msg=' <span class="warning label">ca '.$diff.' h ago</span>';
		} else {
			$diff=round($diff/86400);
			$diff_msg=' <span class="alert label">ca '.$diff.' days ago</span>';
		}
		
		$checked_out_data[]=array(
			'plate'				=> '<code class="plate">'.$checkedout_plate['plate_id'].'</code>', 
			'checked_out_by'	=> $lastlog['user'], 
			'checked_out_on'	=> date('Y-m-d H:i:s', $lastlog['timestamp']).$diff_msg, 
			'from'				=> '<code>'.$lastlog['position'].'</code>'
		);
	}
	
	$checkedout_table=new htmlTable('Checked out plates');
	$checkedout_table->addData($checked_out_data);
	$checkedout_html=$checkedout_table->render();
} else {
	$checkedout_html='';
}

if($plate['name']) {
	// Plate selected and validated
	$html=showPlateData($plate);
	
	// Hidden form fields with plate and user info
	$theform->addInput(FALSE,array("type" => "hidden", "name" => "user_email", "value" => $user['user_email']));
	$theform->addInput(FALSE,array("type" => "hidden", "name" => "plate", "value" => $plate['name']));
	
	if($plate_data) {
		// Plate exist in database already

		if($plate_data['status']=="checked_in") {
			// Plate is checked in, show location info
			$html.=showRackData($plate_data);

			// Check out plate
			$theform->addInput("Verify plate ID before proceeding",array("type" => "text", "name" => "plate_verify", "value" => "", "autocomplete" => "off"));
			$theform->addInput(FALSE,array("type" => "submit", "name" => "submit", "value" => "Check out plate", "class" => "button"));
			$theform->addInput(FALSE,array("type" => "submit", "name" => "return", "value" => "Return plate", "class" => "warning button"));
			$theform->addInput(FALSE,array("type" => "submit", "name" => "destroy", "value" => "Destroy plate", "class" => "alert button"));
			$theform->addInput(FALSE,array("type" => "submit", "name" => "cancel", "value" => "Cancel", "class" => "secondary button"));
		} else {
			// Plate is checked out, returned or destroyed
			if($plate_data['status']=="checked_out") {
				// Plate can only be checked in again if it's been checked out, not if it's been returned or destroyed
				$theform->addInput("Position",array("type" => "text", "name" => "position", "value" => "", "id" => "position", "autocomplete" => "off"));
				$theform->addInput(FALSE,array("type" => "submit", "name" => "submit", "value" => "Check in plate", "class" => "button"));
			}
			$theform->addInput(FALSE,array("type" => "submit", "name" => "cancel", "value" => "Cancel", "class" => "secondary button"));
		}
	} else {
		// Check in of new plate
		$ALERTS[]=setAlerts("Plate does not exist in plate database");
		$html.=$plate['search']['html'];
		// position field controlled by jQuery Regex that trigger AJAX request of conditional position information to #rackview div below
		$theform->addInput("Position",array("type" => "text", "name" => "position", "value" => "", "id" => "position", "autocomplete" => "off"));
		$theform->addInput(FALSE,array("type" => "submit", "name" => "submit", "value" => "Check in plate", "class" => "button"));
		$theform->addInput(FALSE,array("type" => "submit", "name" => "cancel", "value" => "Cancel", "class" => "secondary button"));
	}
} else {
	// Default view
	// No plate selected OR search results showing specific project or list of projects
	
	// Operator (user_email) field controlled by jQuery Regex that automatically change focus to next input if valid scilifelab domain email is entered (js/app.js)
	
	if($plate['search']) {
		// There are search matches but no plate selected
		$html=$plate['search']['html'];
		$theform->addInput("Operator (use your SciLifeLab email address)",array("type" => "text", "name" => "user_email", "value" => $user['user_email'], "required" => "", "id" => "user_email", "autocomplete" => "off"));
		$theform->addInput("Plate",array("type" => "text", "name" => "plate", "value" => $plate, "id" => "plate", "autocomplete" => "off"));
		$theform->addInput(FALSE,array("type" => "submit", "name" => "submit", "value" => "Next", "class" => "button"));
		$theform->addInput(FALSE,array("type" => "submit", "name" => "cancel", "value" => "Cancel", "class" => "secondary button"));
	} else {
		// Default view, no search matches and no plate selected
		$theform->addText('Manage plates by scanning plate barcode or search using plate/project ID or name.');
		$theform->addInput("Operator (use your SciLifeLab email address)",array("type" => "text", "name" => "user_email", "value" => "", "required" => "", "id" => "user_email", "autocomplete" => "off"));
		$theform->addInput("Plate",array("type" => "text", "name" => "plate", "value" => $plate, "required" => "", "id" => "plate", "autocomplete" => "off"));
		$theform->addInput(FALSE,array("type" => "submit", "name" => "submit", "value" => "Next", "class" => "button"));
	}
}

// Render Page
//=================================================================================================
?>

<!doctype html>
<html class="no-js" lang="en" dir="ltr">

<head>
	<meta charset="utf-8">
	<meta http-equiv="x-ua-compatible" content="ie=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>PlateJuggler</title>
	<link rel="stylesheet" href="css/foundation.css">
	<link rel="stylesheet" href="css/app.css">
	<link rel="stylesheet" href="css/icons/foundation-icons.css" />
</head>

<body>

<div class="top-bar">
	<div class="top-bar-left">
		<ul class="dropdown menu" data-dropdown-menu>
			<li class="menu-text">PlateJuggler</li>
			<li><a href="index.php">Manage plates</a></li>
			<li><a href="storage.php">Manage storage</a></li>
		</ul>
	</div>

	<div class="top-bar-right">
		<ul class="menu" id="server_status">
			<li><span class="label" id="lims-status">LIMS</span>&nbsp;</li>
			<li><span class="label" id="couch-status">StatusDB</span>&nbsp;</li>
		</ul>
	</div>
</div>

<?php echo formatAlerts($ALERTS); ?>

<div class="row">
<br>
<div class="large-12 columns">
<?php echo $html; ?>
<?php echo $theform->render(); ?>

<!-- Placeholder for live plate search, populated by _platesearch.php from AJAX request -->
<!-- Placeholder for plate position info during check in, populated by _rackview.php from AJAX request -->
<div id="query_data"></div>

<?php echo $checkedout_html; ?>
</div>
</div>

<script src="js/vendor/jquery.js"></script>
<script src="js/vendor/what-input.js"></script>
<script src="js/vendor/foundation.js"></script>
<script src="js/app.js"></script>
</body>

</html>
