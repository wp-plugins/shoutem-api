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
class ShoutemUsersDao {
	
	function __construct() {
		$this->session_id_header = 'shoutem_api_session_id';
	}
	/**
	 * Returns true if the user can be autenticated
	 */
	function validate_password($user, $password) {
		return wp_check_password($password, $user->user_pass);
	}
	
	function get_user($params) {
		$username = $params['username'];
		$user = get_userdatabylogin($username);
		return $user;
	}
}
?>
