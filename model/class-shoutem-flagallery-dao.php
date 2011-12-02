<?php
/**
 * This class is designed to work only with GRAND FLAGallery Wordpress plugin.
 */
class ShoutemFlaGalleryDao extends ShoutemDao {
	
	public static function available() {
		return isset($GLOBALS['flagdb']);
	}
	
	public function get($params) {
		global $flagdb;
		
		$pid = $params['post_id'];
		
		$image = $flagdb->find_image($pid);
		
		if ($image) {
			return $this->convert_to_se_post($image, $params);
		}
		return false;
	}
		
	public function find($params) {
		global $flagdb;
		$offset = $params['offset'];
		$limit = $params['limit'];
		$category_id = $params['category_id'];

		$images = $flagdb->get_gallery($category_id,'sortorder','ASC',true,$limit + 1,$offset);
		
		$results = array();
		
		
		foreach($images as $image) {			
			$results []= $this->convert_to_se_post($image, $params);
		}
		return $this->add_paging_info($results, $params);
	}
	
	public function categories($params) {
		global $flagdb;
		$offset = $params['offset'];
		$limit = $params['limit'];
		
		$galleries = $flagdb->find_all_galleries('gid', 'ASC', true, $limit + 1, $offset);
		
		$results = array();
		
		foreach($galleries as $gallery) {
			$result_gallery = array(
				'category_id' => $gallery->gid,
				'name' => $gallery->name,
				'allowed' => true
			);
			$results []= $result_gallery;
		}
		
		return $this->add_paging_info($results, $params);
	}
	
	/**
	 * Converts the image from the NGG format to the post format 
	 * defined by the ShoutEm Data Exchange Protocol: @link http://fiveminutes.jira.com/wiki/display/SE/Data+Exchange+Protocol
	 */
	private function convert_to_se_post($image, $params) {
		$user_data = get_userdata($image->author);		
		$remaped_post['author'] = $user_data->display_name;
		
		$result = array(
			'id' => $image->pid,
			'published_at' => $image->imagedate,
			'body' => $image->description,
			'title' => $image->alttext,
			'summary' => $image->description,
			'commentable' => 'no',
			'comments_count' => 0,
			'likeable' => false,
			'likes_count' => 0,
			'author' => $user_data->display_name,
			'link' => $image->imageURL,
			'attachments' => array(
				'images' => array(
					array(
						'src' => $image->imageURL,
						'id' => '',
						'width' => $image->meta_data['width'],
						'height' => $image->meta_data['height'],
						'thumbnail_url' => $image->thumbURL		
					)
				)
			)
		);
		return $result;
	}
} 
?>
