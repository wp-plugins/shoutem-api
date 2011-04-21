<?php
class ShoutemEventsController extends ShoutemController {
	
	/**
	 * Called automatically before methods
	 */
	public function doBefore() {
		//Events only work with events manager plugin
		$this->validate_required_plugins('events-manager/events-manager.php');
	}
	
	/**
	 * MAPS_TO events/find
	 * Required params: category_id
	 * Optional params: session_id, offset, limit 
	 */
	public function find() {
		$params = $this->accept_standard_params_and('category_id','from_time','till_time','name');
		$this->request->use_default_params($this->default_paging_params());
		$this->validate_required_params('category_id');
		
		$dao = $this->dao_factory->get_events_dao();
		$data = $this->caching->use_cache(
							array($dao,'find'),
							$this->request->params
							);
		
		$this->view->show_recordset($data);		
	}
	
	/**
	 * MAPS_TO events/get
	 * Required params: event_id
	 * Optional params: session_id 
	 */
	public function get() {
		$params = $this->accept_standard_params_and('event_id');
		$this->request->use_default_params($this->default_paging_params());
		$this->validate_required_params('event_id');
		
		$dao = $this->dao_factory->get_events_dao();
		$data = $this->caching->use_cache(
					array($dao,'get'),
					$this->request->params
					);
		$this->view->show_record($data);		
	}
}
?>
