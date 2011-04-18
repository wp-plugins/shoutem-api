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
class ShoutemController {

	public function ShoutemController($request, $response, $dao_factory, $authentication) {
		$this->request = $request;
		$this->response = $response;
		$this->dao_factory = $dao_factory;
		$this->authentication = $authentication;
		$this->view = new ShoutemControllerView($request, $response);
	}

	protected function default_paging_params() {
		return array (
			'offset' => 0,
			'limit' => 100
		);
	}
	
	// validating stuff 
	
	public function validate_required_params() {
		foreach(func_get_args() as $required_param) {	
			if (!isset($this->request->params[$required_param])) {
				$this->response->send_error(400, "Missing required parameter $required_param");
			}
		}
	}
			
	// callbacks
	
	public function before() {
		if(isset($_REQUEST['session_id'])) {
			$session_id = $_REQUEST['session_id'];
			$credentials = $this->authentication->get_credentials($session_id);
		
			if($credentials == false || $credentials->is_valid() == false) {
				$this->response->send_error(401, "Invalid user credentials");
			} else {
				$this->request->credentials = $credentials;
			}
		}
	}
	
	public function after() {	
	}

}

?>