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
class ShoutemApiCaching {
	
	public function __construct($options) {
		$this->options = $options;
	}
	
	/**
	 * Note, this can only be used with shoutem controller methods because it relies on specific naming scheme in params 
	 * Returnes cached data when found, otherwise calls method and caches result
	 * @param method array(class_instance, method_name)
	 * @param params params to be passed to method (also used to create unique id for caching data)
	 * @return data (cached or fresh)
	 */
	public function use_cache($method, $params) {		
		$cached = false;
		
		$options = 	$this->options->get_options();
		$expiration = $options['cache_expiration'];
		$use_cache = true;
		if (isset($params['session_id'])) {
			$use_cache = false; //don't use for signed in user
		} else if ($expiration == 0) {
			$use_cache = false; //don't use if the user dissabled in options
		}

		if (!$use_cache) {
			return $method[0]->$method[1]($params);
		}
		
		$uid = $this->unique_id($params);

		$cached = $this->get_from_cache($uid);
		
		if ($cached === false) {
			$cached = $method[0]->$method[1]($params);			
			$this->set_to_cache($uid, $cached, $expiration);
		}  
		
		return $cached;
	}	
	
	private function unique_id($params) {
		ksort($params);
		
		if (!array_key_exists('method',$params)) {
			//at least method must exist to create unique id 
			throw new ShoutemApiException('intertal_server_error');
		}
						
		$unique_id = '';
		foreach($params as $value) {
			$unique_id .= $value;
		}
		return $unique_id;
	}
	
	private function get_from_cache($uid) {
		return get_transient($uid);
	}
	
	private function set_to_cache($uid, $value, $expiration) {		
		return set_transient($uid, $value, $expiration);		
	}
}
?>
