<?php
/**
 * This class is designed to work only with NextGenGallery Wordpress plugin.
 */
class ShoutemNGGDao extends ShoutemDao {
	
	public static function available() {
		return isset($GLOBALS['nggdb']);
	}
	
	public function get($params) {
		global $nggdb;
		
		$pid = $params['post_id'];
		
		$image = $nggdb->find_image($pid);
		
		if ($image) {
			return $this->get_image($image, $params);
		}
		return false;
	}
		
	public function find($params) {
		global $nggdb;
		$offset = $params['offset'];
		$limit = $params['limit'];
		$category_id = $params['category_id'];

		$images = $nggdb->get_gallery($category_id,'sortorder','ASC',true,$limit + 1,$offset);
		
		$results = array();
		
		
		foreach($images as $image) {			
			$results []= $this->get_image($image, $params);
		}
		return $this->add_paging_info($results, $params);
	}
	
	public function categories($params) {
		global $nggdb;
		$offset = $params['offset'];
		$limit = $params['limit'];
		
		$galleries = $nggdb->find_all_galleries('gid', 'ASC', true, $limit + 1, $offset);
		
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
	
	private function get_image($image, $params) {
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