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
class ShoutemPostsController extends ShoutemController {
	/**
	 * MAPS_TO: posts/get 
	 * REQ PARAMS: post_id
	 * OPT PARAMS: session_id
	 */
	function get() {
		$params = $this->accept_standard_params_and('post_id','include_raw_post');
		$this->validate_required_params('post_id');
		$postsDao = $this->dao_factory->get_posts_dao();
		
		$data = $this->caching->use_cache(
						array($postsDao,'get'), 
						$this->request->params
						);
								
		$this->view->show_record($data);
	}
	
	function categories() {	
		$this->accept_standard_params_and();
		$this->request->use_default_params($this->default_paging_params());
		$dao = $this->dao_factory->get_posts_dao();
		
		$data = $this->caching->use_cache(
						array($dao,'categories'), 
						$this->request->params
						);
						
		$this->view->show_recordset($data);
	}
	
	/**
	 * MAPS_TO: posts/find 
	 * OPT PARAMS: session_id, category_id, offeset (default 0), limit (default 100)
	 */
	function find() {
		$this->accept_standard_params_and('category_id');
		$this->request->use_default_params($this->default_paging_params());
		
		$postsDao = $this->dao_factory->get_posts_dao();
		$result = $this->caching->use_cache(
						array($postsDao,'find'), 
						$this->request->params
						);		
		$this->view->show_recordset($result);
	}
}

?>
