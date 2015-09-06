<?php
/*
Plugin Name: BLN.FM Cal Sync
Description: 
Author: Nico Knoll
Version: 1.7
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

	if(!count($data['feed']['entry'])) return false;

	foreach($data['feed']['entry'] as $event) {
		$return_event = array();
		foreach($event as $key => $value) {
			if(preg_match("%^gsx\\$%Uis", $key)) {
				$key = str_replace('gsx$', '', $key);
				if($key == '_cn6ca') $key = 'starttag';

				if($key == 'starttag' || $key == 'endtag') {
					$tmp = explode('.', $value["\$t"]);
					$return_event[$key] = date('Y-m-d', mktime(0,0,0,$tmp[1],$tmp[0],$tmp[2]));
				} elseif($key == 'startzeit' || $key == 'endzeit') {
					$tmp = explode(':', $value["\$t"]);
					$return_event[$key] = date('H:i:s', mktime($tmp[0],$tmp[1],0,0,0,0));
				} else {
					$return_event[$key] = $value["\$t"];
				}
			}
		}
		$return[] = $return_event;
	}

	return $return;
}

function addLocation($name) {
	$em_location = new EM_Location();

	$em_location->location_name = $name;
	$em_location->location_address = $name;
	$em_location->location_town = 'Berlin';
	$em_location->location_state = 'Berlin';
	$em_location->location_country = 'DE';

	$em_location->save();
	$em_location->save_meta();

	return $em_location->location_id;
}

function getLocationIDs() {
	$EM_Locations = EM_Locations::get();
	$return = array();

	foreach($EM_Locations as $EM_Location){
	  	@$return[$EM_Location->name] = @$EM_Location->id; 
	}

	return $return;
}


function addEvent($data) {
	$em_event = new EM_Event();

	$em_event->event_start_date = $data["starttag"];
	$em_event->event_start_time = $data["startzeit"];
	$em_event->event_end_date = $data["endtag"];
	$em_event->event_end_time = $data["endzeit"];

	$em_event->start = strtotime($em_event->event_start_date." ".$em_event->event_start_time);
	$em_event->end = strtotime($em_event->event_end_date." ".$em_event->event_end_time);

	$em_event->location_id = $data["locationid"];

	$em_event->post_title = $data["titel"];
	$em_event->event_name = $data["titel"];

	$em_event->body = (($data["kurzbeschreibung"]) ? $data["kurzbeschreibung"] : '');
	$em_event->post_content = (($data["kurzbeschreibung"]) ? $data["kurzbeschreibung"] : '');
	$em_event->post_tags = $data[""];

	// meta
	$em_event->group_id = 0;
	$em_event->event_date_modified = date('Y-m-d H:i:s', time());
	$em_event->event_all_day = 0;
	$em_event->event_rsvp = 0;

	$check = $em_event->save();

	wp_update_post(array('ID' => $em_event->post_id));

	var_dump($em_event->post_id);

	return $check;
}

function addEvents() {
	$spreadsheet = spreadsheetToArray();
	if($spreadsheet)
		foreach(spreadsheetToArray() as $event) {
			// reload locations as we dynamically create them if missing
			$locations = getLocationIDs();

			$event['locationid'] = @$locations[$event['venue']];
			if($event['locationid'] == '') $event['locationid'] = addLocation($event['venue']);

			addEvent($event);
		}
	}
}


function blnfmcalsync_page_function() {
	if(@$_POST['syncnow']) {
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



function blnfmcalsync_page() {
	add_plugins_page( 'BLN.FM Cal Sync', 'BLN.FM Cal Sync', 'None', 'BLNFMCalSync', 'blnfmcalsync_page_function');
	add_submenu_page( 'edit.php?post_type=event', 'BLN.FM Cal Sync', 'BLN.FM Cal Sync', 'manage_options', 'BLNFMCalSync', 'blnfmcalsync_page_function');
}

add_action('admin_menu', 'blnfmcalsync_page');


?>
