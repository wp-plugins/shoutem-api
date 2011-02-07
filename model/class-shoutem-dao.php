<?php
/*
  Copyright 2011 by ShoutEm, Inc. (www.shoutem.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
class ShoutemDao {
	protected function get_by_sql($query) {
		global $wpdb;
		return $wpdb->get_row($query, "ARRAY_A");
	}
	
	/**
	 * @param array
	 * @param map
	 * @return array array with remaped keys map(old_key_val=>new_key_val) 
	 */
	protected function array_remap_keys($input, $map) {
		$remaped_array = array();
		foreach($input as $key => $val) {
			if(array_key_exists($key, $map)) {
				$remaped_array[$map[$key]] = $val;
			}
		}
		return $remaped_array;
	}
	
	/**
	 * Returns array containing results and paging info
	 * @param results 
	 * @param params array(offset,limit)
	 * @param limit
	 * return array( data => results, paging => pagingInfo)
	 */
	protected function add_paging_info($results, $params) {
		$offset = (int)$params['offset'];
		$limit = (int)$params['limit'];
		$next_page = count($results) > $limit; // wheter there is a next page
		$paging = array();
		
		if ($offset != 0) { // if it's not first
			$paging["previous"] = array(
				"offset" => max($offset - $limit, 0),
				"limit" => $limit
			);
		}
		
		if ($next_page) {
			$paging["next"] = array(
				"offset" => $offset + $limit,
				"limit" => $limit
			);
		}

		return array(
			"data" => array_slice($results, 0, $limit),
			"paging" => $paging
		);
	}
	
	protected function get_data_by_sql($query, $offset, $limit) {
		$limit_segment = sprintf(" LIMIT %d, %d", $offset, $limit);
		$query .= $limit_segment;
		global $wpdb;
		return $wpdb->get_results($query, "ARRAY_A");
	}
	
	protected function find_by_sql($query, $params) {
		$offset = $params['offset'];
		$limit = $params['limit'];
		
		$results = $this->get_data_by_sql($query,$offset, $limit+1);
		return $this->add_paging_info($results, $params);
	}
	
	protected function create_and_get_id($table_name, $record) {
		global $wpdb;
		$result = $wpdb->insert($table_name, $record);
		if ($result) {
			return $wpdb->insert_id;
		}
		return false;
	}
	
	protected function current_gmtdate() {
		return gmdate("Y-m-d H:i:s", time());
	}
	
	protected function current_date() {
		return date("Y-m-d H:i:s", time());
	}
}

?>