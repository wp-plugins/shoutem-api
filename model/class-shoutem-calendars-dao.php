<?php
/**
 * This class is designed to work only with Events Manager Wordpress plugin.
 */
class ShoutemCalendarsDao extends ShoutemDao {

	public function find($params) {
		global $wpdb;
		$query = $wpdb->prepare(
				"SELECT 
				category_id id, 
				category_name name				
				FROM wp_em_categories");
		return $this->find_by_sql($query, $params);	
	}
} 
?>
