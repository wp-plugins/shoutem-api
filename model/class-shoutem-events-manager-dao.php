<?php
/**
 * This class is designed to work only with Events Manager Wordpress plugin.
 */
class ShoutemEventsManagerDao extends ShoutemDao {
	
	public function available() {		
		return class_exists('EM_Categories');
	}
	
	public static function categories($params) {
		
		$categories = EM_Categories::get(array(
			'offset' => $params['offset'],
			'limit' => $params['limit']
		));
		
		$results = array();
		foreach ($categories as $category) {
			$results []= array(
				'name' => $category->name,
				'category_id' => $category->id,
				'allowed' => true
			);
		}
		return $this->add_paging_info($results, $params);		
	}
	
	/**
	 * get event.
	 * Required params event_id
	 */
	public function get($params) {
		global $wpdb;
		$query = $wpdb->prepare('SELECT 
				event_id id, 
				event_author event_author,
				CONCAT(event_start_date, " ", event_start_time) 	start_time,
				CONCAT(event_end_date, " ", event_end_time)	end_time,
				event_name	name,
				event_notes	description,
				event_date_modified	updated_time,
				NULL	image_url,
				"open"	privacy,
				"unknown"	category,				
				l.location_id	venue_id,
				l.location_name	venue_name,
				l.location_address	venue_street,
				l.location_town	venue_state,
				NULL	venue_country,
				location_latitude	venue_latitude,
				location_longitude	venue_longitude	
		FROM 	wp_em_events AS e join wp_em_locations AS l ON e.location_id = l.location_id 
		WHERE	event_id = %s',$params['event_id']);		
		$data = $this->get_by_sql($query, $params);
		return $this->remapEvent($data);
	}
		
	public function find($params) {
		
		global $wpdb;
		$criterion = "";
		if (isset($params['category_id'])) {		
			$criterion .= (strcmp($criterion,"") == 0) ? 'WHERE ' : ' AND ';			
			$criterion .= $wpdb->prepare('(event_category_id = %d)',$params['category_id']);
		}
		if (isset($params['from_time'])) {		
			$criterion .= (strcmp($criterion,"") == 0) ? 'WHERE ' : ' AND ';			
			$criterion .= $wpdb->prepare('(TIMESTAMP(event_start_date,event_start_time) >= TIMESTAMP(%s))',$params['from_time']);
		}
		if (isset($params['till_time'])) {		
			$criterion .= (strcmp($criterion,"") == 0) ? 'WHERE ' : ' AND ';			
			$criterion .= $wpdb->prepare('(TIMESTAMP(event_start_date,event_start_time) <= TIMESTAMP(%s))',$params['till_time']);
		}
		if (isset($params['name'])) {		
			$criterion .= (strcmp($criterion,"") == 0) ? 'WHERE ' : ' AND ';			
			$criterion .= $wpdb->prepare("(event_name LIKE '%%%s%%')",$params['name']);
		}
		if (isset($params['category'])) {		
			//category is not currently supported.
		}	
				
		$query ='SELECT 
				event_id id, 
				event_author event_author,
				CONCAT(event_start_date, " ", event_start_time) 	start_time,
				CONCAT(event_end_date, " ", event_end_time)	end_time,
				event_name	name,
				event_notes	description,
				event_date_modified	updated_time,
				NULL	image_url,
				"open"	privacy,
				"unknown"	category,
				l.location_id	venue_id,
				l.location_name	venue_name,
				l.location_address	venue_street,
				l.location_town	venue_state,
				NULL	venue_country,
				location_latitude	venue_latitude,
				location_longitude	venue_longitude	
				FROM wp_em_events AS e join wp_em_locations AS l ON e.location_id = l.location_id ' . $criterion;		
		$paged_data = $this->find_by_sql($query, $params);
		
		//Remap the data from 
		$remaped_result_set = array();
		foreach($paged_data['data'] as $result) {
			$remaped_result_set[] = $this->remapEvent($result); 	
		}
		$paged_data['data'] = $remaped_result_set;		
		return $paged_data;
	}
		
	private function remapEvent($event) {
		$remaped_event = array(
			'id' => $event['id'],
			'start_time' => $event['start_time'],
			'end_time' => $event['end_time'],
			'name' => $event['name'],
			'description' => $event['description'],
			'updated_time' => $event['updated_time'],
			'image_url' => $event['image_url'],
			'category' => $event['category'],			
		);
		
		$user_id = $event['event_author'];
		if ($user_id > 0) {
			$user = get_userdata($user_id);
			$remaped_event['owner'] = array(
					'id' => $user_id,
					'name' => $user->user_nicename
					);
		}
		
		$venue = array (
			'id' => $event['venue_id'],
			'name' => $event['venue_name'],
			'street' => $event['venue_street'],
			'state' => $event['venue_state'],
			'country' => $event['venue_country'],
			'latitude' => $event['venue_latitude'],
			'longitude' => $event['venue_longitude'],
		);
		
		$remaped_event['venue'] = $venue;
		
		return $remaped_event;	
	}
} 
?>
