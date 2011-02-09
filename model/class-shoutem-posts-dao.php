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
		global $wpdb;
		$query = $wpdb->prepare("
			SELECT wposts.ID post_id,
				wposts.post_modified_gmt published_at,
				wusers.user_nicename author,
				wposts.post_title title,
				wposts.post_excerpt summary,
				wposts.post_content body,
				wposts.comment_status commentable,
				0 likeable,
				comment_count comments_count,
				0 likes_count
			FROM $wpdb->posts as wposts, 
				$wpdb->users as wusers
			WHERE wposts.post_author = wusers.ID AND
				wposts.ID = %d AND
				wposts.post_status = 'publish'								
			", $params['post_id']);
		
		$post = $this->get_by_sql($query);
		
		$is_user_logged_in = isset($params['session_id']);
		$is_reqistration_required = ('1' == get_option('comment_registration')); 
		$post_commentable = ($post['commentable'] == 'open');
		 
		$post['commentable'] = 	$this->get_commentable($post_commentable, $is_user_logged_in, $is_reqistration_required);	
		$post['link'] = get_permalink($post['post_id']);
		return $post;
	}
	
	public function categories($params) {
		
		global $wpdb;
		$query = $wpdb->prepare("
			SELECT wterms.term_id category_id,
				wterms.name,
				1 allowed
			FROM $wpdb->terms as wterms,
				$wpdb->term_taxonomy as wterm_taxonomy
			WHERE wterms.term_id = wterm_taxonomy.term_id AND
				wterm_taxonomy.taxonomy = %s											
		",'category');
		//standard return $this->find_by_sql($query,$params); can not be used because of fictive category for all posts.
		$offset = $params['offset'];
		$limit = $params['limit'];
		$result = array();
		if ($offset == 0) {
			//add fictive category all
			$blog_name = get_bloginfo('name');
			$limit = $limit - 1;
			$result[] = array( 
					'category_id' => -1,
					'name' => $blog_name,
					'allowed' => true
						);
		} else {
			//compensate offset for fictive category all
			$offset -= 1;
		}
		
		$result = array_merge($result, $this->get_data_by_sql($query, $offset, $limit));
		return $this->add_paging_info($result,$params);	
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
		
		$is_user_logged_in = isset($params['session_id']);
		$is_reqistration_required = ('1' == get_option('comment_registration')); 
		
		$remaped_posts = array();
		foreach($posts as $post) {
			$remaped_post = $this->array_remap_keys($post, 
			array (
					'ID'			=> 'post_id',
					'post_date'		=> 'published_at',
					'post_excerpt'	=> 'author',
					'post_title'	=> 'title',
					'post_excerpt'	=> 'summary',
					'post_content'	=> 'body',
					'comment_status'=>'commentable',									
					'comment_count'	=>'comments_count',						
			));

			$remaped_post['author'] = get_userdata($post->post_author)->user_nicename;
			$remaped_post['likeable'] = 0;
			$remaped_post['likes_count'] = 0;
			$remaped_post['link'] = get_permalink($remaped_post['post_id']);
			$post_commentable =  ($remaped_post['commentable'] == 'open');
			
			$remaped_post['commentable'] = $this->get_commentable($post_commentable, $is_user_logged_in, $is_reqistration_required);
			
			$remaped_posts[] = $remaped_post; 
			
			$paged_posts = $this->add_paging_info($remaped_posts,$params);
		}
		
		return $paged_posts;
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