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
class ShoutemUsersController extends ShoutemController {
	
	/**
	 * MAPS_TO: users/auchenticate 
	 * REQ PARAMS: username, password
	 */
	function authenticate() {
		$this->validate_required_params('username', 'password');
		
		$authenticate_dao = $this->dao_factory->get_users_dao();
		$user = $authenticate_dao->get_user($this->request->params);
		
		if ($user == false) {
			$this->response->send_error('401', "Invalid user credentials");
		}
		
		if ($authenticate_dao->validate_password($user, $this->request->params['password'])) {
			$session_id = $this->authentication->create_session_id($user);
			$this->response->send_json(array('session_id' => $session_id));
		} else {
			$this->response->send_error('401', "Invalid user credentials");
		}
	}	
}
?>
