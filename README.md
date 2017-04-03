# NGI Plate database

A tool for keeping track of plates used at NGI. 

## Location barcodes

Storage units (e.g. freezers and fridges), racks and positions within racks all have specific ID's described below:

- Full position barcode: `R[NNNN]X[NN]Y[NN]`
- Rack location only: `R[NNNN]_`
- Storage unit only: `S[NNNN]`

S: Storage unit, R: Rack, X/Y: position

## Authentication

Login using password is not required. Users enter their scilifelab email address which is checked against the list of allowed users in StatusDB.

## Back end

Tested on Apache (MAMP), PHP 7.1.1 and MySQL 5.6.35

Barcode generation: https://github.com/davidscotttufts/php-barcode

## Front end

This site use the framework "Foundation" (http://foundation.zurb.com/), version 6.3.0.
It also requires a JavaScript enabled browser.

## Configuration

The necessary credentials for connecting to database, LIMS and StatusDB is added to the file `config.php` that's placed in the root directory: 

	<?php
	$CONFIG=array(
		'mysql' => array(
			'user' 		=> '', 
			'pass' 		=> '', 
			'db' 		=> '',
			'server'	=> ''
		), 
		'clarity' => array(
			'user' 		=> '', 
			'pass' 		=> '', 
			'uri'		=> ''
		), 
		'couch' => array(
			'user' 		=> '', 
			'pass' 		=> '', 
			'host'		=> '', 
			'port'		=> , 
			'views'		=> array(
				'users'		=> '', 
				'projects'	=> ''
			)
		)
	);
	?>

## Database schema

The database structure can be created using the file `database.sql`. 

