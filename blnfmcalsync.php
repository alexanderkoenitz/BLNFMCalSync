<?php
/*
Plugin Name: BLN.FM Cal Sync
Description: 
Author: Nico Knoll
Version: 1.6
Author URI: http://nico.is
*/

define('BLNFM_GOOGLE_SPREADSHEET_ID', '1ALu_lizA9GFiW_86lN6FGqVIoOY6buWEBB_QU2PfdYA');


function file_get_contents_curl($url) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Set curl to return the data instead of printing it to the browser.
	curl_setopt($ch, CURLOPT_URL, $url);

	$data = curl_exec($ch);
	curl_close($ch);

	return $data;
}


function spreadsheetToArray() {
	$id = BLNFM_GOOGLE_SPREADSHEET_ID;
	$url = 'https://spreadsheets.google.com/feeds/list/'.$id.'/od6/public/full?alt=json';

	$data = json_decode(file_get_contents_curl($url), true);
	$return = array();

	foreach($data['feed']['entry'] as $event) {
		$return_event = array();
		foreach($event as $key => $value) {
			if(preg_match("%^gsx\\$%Uis", $key)) {
				$key = str_replace('gsx$', '', $key);
				$return_event[$key] = $value["\$t"];
			}
		}
		$return[] = $return_event;
	}

	return $return;
}

function getLocationIDs() {
	$EM_Locations = EM_Locations::get();
	$return = array();

	foreach($EM_Locations as $EM_Location){
	  	$return[$EM_Location->name] = $EM_Location->id; 
	}

	return $return;
}


function addEvent($data) {
	$em_event = new EM_Event();

	$em_event->event_start_date = $data["endtag"];
	$em_event->event_start_time = $data["startzeit"];
	$em_event->event_end_date = $data["endtag"];
	$em_event->event_end_time = $data["endzeit"];

	$em_event->location_id = $data["locationid"];

	$em_event->post_title = $data["titel"];
	$em_event->event_name = $data["titel"];

	$em_event->post_content = $data["kurzbeschreibung"];
	$em_event->post_tags = $data[""];

	return $em_event->save();
}

function addEvents() {
	$locations = getLocationIDs();

	foreach(spreadsheetToArray() as $event) {
		$event['locationid'] = $locations[$event['venue']];
		if($event['locationid'] == '') $event['venue'];

		addEvent($event);
	}
}

function blnfmcalsync() {
	$chosen = hello_dolly_get_lyric();
	echo "<p id='dolly'>$chosen</p>";
}

function blnfmcalsync_page_function() {

	if($_POST['syncnow']) {
		echo '<div id="message" class="updated">
		<p>Veranstaltungen sollten jetzt synchronisiert sein.</p>
		</div>';

		update_option( 'blnfmcalsync-lastsync', time() );

		addEvents();
	}

	$lastSynced = get_option( 'blnfmcalsync-lastsync' );


	echo '<div class="wrap">
	<h1>Mit Google Spreadsheet synchronisieren</h1>
	<p>Spreadsheet URL: '.BLNFM_GOOGLE_SPREADSHEET_ID.'</p>
	<p>Letztes mal synchronisiert: '.date('d.m.Y H:i', $lastSynced).' Uhr</p>
	<p>Button klicken um die Synchronisation zu starten.</p>
	<form action="'.$_SERVER['REQUEST_URI'].'" method="post">
	<input type="submit" id="dbem_options_submit" class="button-primary" name="syncnow" value="Jetzt synchronisieren">
	</form>';
}

//add_action( 'admin_notices', 'blnfmcalsync' );




function blnfmcalsync_page() {
	add_plugins_page( 'BLN.FM Cal Sync', 'BLN.FM Cal Sync', 'None', 'BLNFMCalSync', 'blnfmcalsync_page_function');
	add_submenu_page( 'edit.php?post_type=event', 'BLN.FM Cal Sync', 'BLN.FM Cal Sync', 'manage_options', 'BLNFMCalSync', 'blnfmcalsync_page_function');
}

add_action('admin_menu', 'blnfmcalsync_page');


?>
