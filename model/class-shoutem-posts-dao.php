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
class ShoutemPostsDao extends ShoutemDao {
	
	public function get($params) {
		$wp_post = get_post($params['post_id']);
		if ($wp_post == null) {
			throw new ShoutemApiException('invalid_params');
		}
		$post = $this->get_post($wp_post,$params);
		return $post;
	}
	
	public function categories($params) {		
		$offset = $params['offset'];
		$limit = $params['limit'];
				
		$results = array();
		
		if ($offset == 0) {
			//add fictive category all
			$blog_name = get_bloginfo('name');
			$limit = $limit - 1;
			$results[] = array( 
					'category_id' => -1,
					'name' => $blog_name,
					'allowed' => true
						);
		} else {
			//compensate offset for fictive category all
			$offset -= 1;
		}
		
		$category_args = array (
			'number' => $offset + $limit + 1
		);
		
		$categories = get_categories();
	 	//because there is no offset in get_categories();
	 	$categories = array_slice($categories,$offset, $limit + 1);
	 	
		foreach($categories as $category) {
			$remaped_category = 
				$this->array_remap_keys($category, array (
						'cat_ID' 	=> 'category_id',
						'name'		=> 'name'
				));			
			$remaped_category['allowed'] = true;
			$results[] = $remaped_category;
		}
		
		return $this->add_paging_info($results,$params);	
		
	}
	
	public function find($params) {
		 
		$offset = $params['offset'];
		$limit = $params['limit'];
	
		$post_args = array(
			 'numberposts'     	=> $limit + 1,
			 'offset'			=> $offset,
			 'orderby'         	=> 'post_date',
			 'order'           	=> 'DESC',
			 'post_type'       	=> 'post',
			 'post_status'     	=> 'publish' 
		);
		if(isset($params['category_id']) 
				&& '-1' != $params['category_id']) { //cetogory_id = -1 when fitive category all post is searched
			$post_args['category'] = $params['category_id']; 	
		}
		
		$posts = get_posts($post_args);
			
		if ($posts == null) {
			throw new ShoutemApiException('invalid_params');
		}			 		
		$remaped_posts = array();
		foreach($posts as $post) {					
			$remaped_posts[] = $this->get_post($post,$params); 
		}
		$paged_posts = $this->add_paging_info($remaped_posts,$params);
		
		return $paged_posts;
	}
		
	private function get_post($post,$params) {
		
		$is_user_logged_in = isset($params['session_id']);
		$include_raw_post = isset($params['include_raw_post']);
		$is_reqistration_required = ('1' == get_option('comment_registration'));
		
		$remaped_post = $this->array_remap_keys($post, 
		array (
				'ID'			=> 'post_id',
				'post_date'		=> 'published_at',				
				'post_title'	=> 'title',
				'post_excerpt'	=> 'summary',
				'post_content'	=> 'body',
				'comment_status'=>'commentable',									
				'comment_count'	=>'comments_count',						
		));
		
		$body = apply_filters('the_content',do_shortcode($remaped_post['body']));		
		if ($include_raw_post) {
			$remaped_post['raw_post'] = $body;
		}
		$attachments = array();
		$remaped_post['body'] = sanitize_html($body,&$attachments);
		
		$remaped_post['author'] = get_userdata($post->post_author)->user_nicename;
		$remaped_post['likeable'] = 0;
		$remaped_post['likes_count'] = 0;
		$remaped_post['link'] = get_permalink($remaped_post['post_id']);
		
		$remaped_post['attachments'] = $attachments;
		$remaped_post['image_url'] = '';		
		
		$images = $attachments['images'];
		if (count($images) > 0) {
			$remaped_post['image_url'] = $images[0]['src'];
		} 
		
		$post_commentable =  ($remaped_post['commentable'] == 'open');
		
		$remaped_post['commentable'] = $this->get_commentable($post_commentable, $is_user_logged_in, $is_reqistration_required);
		
		$remaped_posts[] = $remaped_post; 
		return $remaped_post;
	}
	
	private function get_commentable($post_commentable, $is_user_logged_in, $is_reqistration_required) {
		//post is not commentable
		if ($post_commentable == false) {
			return 'no';
		} 
		
		//post is commentable, user is logged in
		if ($is_user_logged_in) {
			return 'yes';
		} 
		
		//post is commentable, user not logged in		
		if ($is_reqistration_required) {
		 	return 'denied';
		} 
		
		//post is commentable, user not logged in, anonymous comments are enabled
		return 'yes';
		
			
	}
}

?>