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
		global $post;
		$wp_post = get_post($params['post_id']);
		if ($wp_post == null) {
			throw new ShoutemApiException('invalid_params');
		}
		setup_postdata($wp_post);
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
		global $post;
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
		
		if(isset($params['meta_key'])) {
			$post_args['meta_key'] = $params['meta_key'];
		}
		
		$posts = get_posts($post_args);
			
		if ($posts == null) {
			throw new ShoutemApiException('invalid_params');
		}			 		
		$remaped_posts = array();
		foreach($posts as $post) {
			setup_postdata($post);
			$remaped_posts[] = $this->get_post($post,$params); 
		}
		$paged_posts = $this->add_paging_info($remaped_posts,$params);
		
		return $paged_posts;
	}
	
	
	public function get_leading_image($post_id) {		
		//Post thumbnail is the wordpress term for leading-image
		
		$post_thumbnail = false;
		if (function_exists("get_the_post_thumbnail")) {  
	 		$post_thumbnail = get_the_post_thumbnail($post_id);
		}	
		if ($post_thumbnail) {
			$images = strip_images($post_thumbnail);
			if (count($images) > 0) {
				$image = $images[0];
				$image['id'] = "";
				return $image;
			}					
		} 
		return false;
		
	}
	
	
	private function get_post($post,$params) { 
		$attachments = array(
			'images' => array(),
			'videos' => array(),
			'audio' => array()
		);
		$this->attachments = &$attachments;
		$is_user_logged_in = isset($params['session_id']);
		$include_raw_post = isset($params['include_raw_post']);
		$is_reqistration_required = ('1' == get_option('comment_registration'));
		$remaped_post = $this->array_remap_keys($post, 
		array (
				'ID'			=> 'post_id',
				'post_date_gmt'	=> 'published_at',
				'post_title'	=> 'title',
				'post_excerpt'	=> 'summary',
				'post_content'	=> 'body',
				'comment_status'=>'commentable',									
				'comment_count'	=>'comments_count',						
		));
		
		//Vipers video quick tags
		if (isset($GLOBALS['VipersVideoQuicktags'])) {
			remove_shortcode( 'youtube');
			add_shortcode( 'youtube', array(&$this, 'shortcode_youtube') );
			
			remove_shortcode( 'vimeo');
			add_shortcode( 'vimeo', array(&$this, 'shortcode_vimeo') );
			
			
			remove_shortcode( 'quicktime');
			remove_shortcode( 'flash');
			remove_shortcode( 'videofile');
			remove_shortcode( 'video'); 
			remove_shortcode( 'avi'); 
			remove_shortcode( 'mpeg'); 
			remove_shortcode( 'wmv');
			
			add_shortcode( 'quicktime', array(&$this, 'shortcode_viper_generic') );
			add_shortcode( 'flash', array(&$this, 'shortcode_viper_generic') );
			add_shortcode( 'videofile', array(&$this, 'shortcode_viper_generic') );
			add_shortcode( 'video', array(&$this, 'shortcode_viper_generic') ); 
			add_shortcode( 'avi', array(&$this, 'shortcode_viper_generic') ); 
			add_shortcode( 'mpeg', array(&$this, 'shortcode_viper_generic') ); 
			add_shortcode( 'wmv', array(&$this, 'shortcode_viper_generic') ); 
		}
			
		//FlaGallery gallery support	
		if (isset($GLOBALS['flagdb'])) {				
						 
			remove_shortcode( 'flagallery');
			add_shortcode( 'flagallery', array(&$this, 'shortcode_flagallery' ));
			
			//FlaGallery video just remove for now.
			remove_shortcode( 'grandflv');
			add_shortcode( 'grandflv', array(&$this, 'shortcode_noop' ));		
			remove_shortcode( 'grandvideo');
			add_shortcode( 'grandvideo', array(&$this, 'shortcode_noop' ));
			
			//FlaGallery mp3 support
			remove_shortcode( 'grandmp3');
			add_shortcode( 'grandmp3', array(&$this, 'shortcode_grandmp3' ) );
			remove_shortcode( 'grandmusic');
			add_shortcode( 'grandmusic', array(&$this, 'shortcode_grandmusic' ) );
		}
				
		//NextGen Gallery support
		if (isset($GLOBALS['nggdb'])) {		
			remove_shortcode( 'album');
			add_shortcode( 'album', array(&$this, 'shortcode_album' ) );
			
        	remove_shortcode( 'imagebrowser');
        	remove_shortcode( 'slideshow');
	        remove_shortcode( 'nggallery');
	        add_shortcode( 'nggallery', array(&$this, 'shortcode_gallery') );
	        add_shortcode( 'slideshow', array(&$this, 'shortcode_gallery') );
	        add_shortcode( 'imagebrowser', array(&$this, 'shortcode_gallery') );
		}
        
		//Podpress support.
		if (function_exists('podPress_get_post_meta') && isset($GLOBALS['podPress'])) {			
			global $podPress;
			
			//remove default podpress filter to prevent it from injecting player html into the post
			remove_filter('the_content', array(&$podPress, 'insert_content'));
			
			//podcasts are stored in post metadata
			$audio_meta = podPress_get_post_meta($post->ID,'_podPressMedia',true);
			if (!$audio_meta) {
				$audio_meta = podPress_get_post_meta($post->ID,'podPressMedia',true);
			}
			if ($audio_meta) {
				foreach($audio_meta as $key => $audio) {
				$uri = $podPress->convertPodcastFileNameToWebPath($post->ID, $key, $audio['URI'], 'web');
					$audio_record = array(
						'id' => '',
						'src' => $uri,
						'type' => 'audio',
						'duration' => $audio['duration']
		 			);
		 			$attachments['audio'] []= $audio_record;
				}	
			}			
		}
	
		//Powerpress support
		if (function_exists('powerpress_get_enclosure')) {
			remove_filter('the_content', 'powerpress_content');			
			
			$audio_meta = powerpress_get_enclosure_data($post->ID);
			if ($audio_meta) {
				$audio_meta = array($audio_meta);
			} else {
				$audio_meta = array();
			}
			
			$podpress_meta = powerpress_get_enclosure_data_podpress($post->ID);
			if ($podpress_meta && is_array($podpress_meta)) {
				$audio_meta = array_merge($audio_meta, array($podpress_meta));
			}
			
			foreach($audio_meta as $audio) {
				$url = $audio['url'];
				$audio_record = array(
					'id' => '',
					'src' => $url,
					'type' => 'audio',
					'duration' => ''
	 			);
	 			$attachments['audio'] []= $audio_record;
			}	
		} 
		
		$body = apply_filters('the_content',do_shortcode($remaped_post['body']));
				
		if ($include_raw_post) {
			$remaped_post['raw_post'] = $body;
		}
		
		$striped_attachments = array();
		$remaped_post['body'] = sanitize_html($body,&$striped_attachments);
		
		$user_data = get_userdata($post->post_author);		
		$remaped_post['author'] = $user_data->display_name;		
		$remaped_post['likeable'] = 0;
		$remaped_post['likes_count'] = 0;
		$remaped_post['link'] = get_permalink($remaped_post['post_id']);
		
		$leading_image = $this->get_leading_image($remaped_post['post_id']);		
		$leading_image = apply_filters('shoutem_leading_image',$leading_image,$remaped_post['post_id']);
		
		
		if ($leading_image) {
			$leading_image['attachment-type'] = "leading_image";			
			array_unshift($attachments['images'],$leading_image);
		} 
		$attachments['images'] = array_merge($attachments['images'], $striped_attachments['images']);
		$attachments['videos'] = array_merge($attachments['videos'], $striped_attachments['videos']);
		$attachments['audio'] = array_merge($attachments['audio'], $striped_attachments['audio']);
		
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
	
	function shortcode_noop( $atts ) {
		return '';	
	}
	
	function get_gallery($db, $id, &$images) {
		$out = "";
		$gallery = $db->get_gallery($id,'sortorder','ASC',true);
		if(!$gallery) return $out;
		foreach($gallery as $image) {
			  
			$pid = $image->pid;  			
			$image = array(
				'src' => $image->imageURL,
				'id' => $pid,
				'width' => $image->meta_data['width'],
				'height' => $image->meta_data['height'],
				'thumbnail_url' => $image->thumbURL
			);
			$images []= $image;
			$out .= "<attachment id=\"$pid\" type=\"image\" xmlns=\"v1\" />";
		}
		return $out;
	}
	
	function put_video($url, $provider, $pid) {
		$video = array(
			'id' => $pid,
			'src' => $url,
			'provider' => $provider
		);
		$this->attachments['videos'] []= $video;
		return "<attachment id=\"$pid\" type=\"video\" xmlns=\"v1\" />";
	}
	
	function shortcode_viper_generic( $atts, $content = '' ) {
		extract(shortcode_atts(array(
            'se_visible'        => 'true',
        ), $atts ));
        
        if ($se_visible != 'true') {
        	return '';	
        }
		if (!empty($content)) {
			$this->put_video($content, '', '');
		}
		return '';
	}
	
	function shortcode_vimeo( $atts, $content = '' ) {
		
		extract(shortcode_atts(array(
            'se_visible'        => 'true',
        ), $atts ));
        
        if ($se_visible != 'true') {
        	return '';	
        }
		
		if ( empty($content) ) {
			return '';
		}
		
		$video_id = '';
		// If a URL was passed
		if ( 'http://' == substr( $content, 0, 7 ) ) {
			preg_match( '#http://(www.vimeo|vimeo)\.com(/|/clip:)(\d+)(.*?)#i', $content, $matches );
			if ( empty($matches) || empty($matches[3]) ) return '';

			$video_id = $matches[3];
		}
		// If a URL wasn't passed, assume a video ID was passed instead
		else {
			$video_id = $content;
		}
		if (!empty($video_id)) {
			return $this->put_video('http://player.vimeo.com/video/'.$video_id, 'vimeo', $video_id);	
		}
		return '';
		
		
	}
	
	
	function shortcode_youtube($atts, $content = '') {
		extract(shortcode_atts(array(
            'se_visible'        => 'true',
        ), $atts ));
        
        if ($se_visible != 'true') {
        	return '';	
        }
			
		if ( empty($content) ) {
			return '';			
		}
		
		$video_id = '';
		// If a URL was passed
		if ( 'http://' == substr( $content, 0, 7 ) ) {

			if ( false === stristr( $content, 'playlist' ) &&
				false === stristr( $content, 'view_play_list' )) { //disregard playlists
				
				preg_match( '#http://(www.youtube|youtube|[A-Za-z]{2}.youtube)\.com/(watch\?v=|w/\?v=|\?v=)([\w-]+)(.*?)#i', $content, $matches );
				if ( empty($matches) || empty($matches[3]) ) return '';

				$video_id = $matches[3];				
			}
		}
		// If a URL wasn't passed, assume a video ID was passed instead
		else {
			$video_id = $content;	
		}
		if (!empty($video_id)) {
			return $this->put_video('http://www.youtube.com/v/'.$video_id, 'youtube', $video_id);			
		}
		
		return '';
			
	}
	
	/**
	 * NGG album shortcode
	 */
	function shortcode_album($atts) {
		global $nggdb;
		extract(shortcode_atts(array(
            'id'        => 0,
            'se_visible' => 'true'
        ), $atts ));
        
        if ($se_visible != 'true') {
        	return '';	
        }
        
        $out = '';
        $album = $nggdb->find_album($id);
        if ($album && is_array($album->gallery_ids)) {
        	foreach($album->gallery_ids as $gallery_id) {
        		$out .= $this->get_gallery($nggdb, $gallery_id, $this->attachments['images']);
        	}
        }        
       
       	return $out;	
	}
	
	/**
	 * NGG gallery shortcode
	 */
	function shortcode_gallery($atts) {
		global $nggdb;		
        
        extract(shortcode_atts(array(
            'id'        => 0,
            'se_visible' => 'true'
        ), $atts ));
        
        if ($se_visible != 'true') {
        	return '';	
        }
        $out = '';        
        $out .= $this->get_gallery($nggdb, $id, $this->attachments['images']);
         
        
        return $out;
	}
	
	/**
	 * FLA Gallery shortcode
	 */
	function shortcode_flagallery($atts) {
		
		if (!isset($GLOBALS['flagdb'])) {
			return '';
		}
		
		global $flagdb;
		
		extract(shortcode_atts(array(
			'gid' 		=> '',
			'album'		=> '',
			'name'		=> '',
			'orderby' 	=> '',
			'order'	 	=> '',
			'exclude' 	=> '',
			'se_visible' => 'true'
		), $atts ));
		
		if ($se_visible != 'true') {
        	return '';	
        }
        
		$out = '';
		// make an array out of the ids
        if($album) {
        	$gallerylist = $flagdb->get_album($album);        	
        	$ids = explode( ',', $gallerylist );			
    		foreach ($ids as $id) {
    			$out .= $this->get_gallery($flagdb, $id, $this->attachments['images']);    			
    		}

        } elseif($gid == "all") {
			if(!$orderby) $orderby='gid';
			if(!$order) $order='DESC';
            $gallerylist = $flagdb->find_all_galleries($orderby, $order);
            if(is_array($gallerylist)) {
				$excludelist = explode(',',$exclude);
				foreach($gallerylist as $gallery) {
					if (in_array($gallery->gid, $excludelist))
						continue;
					$out .= $this->get_gallery($flagdb, $gallery->gid, $this->attachments['images']);					
				}
			}
        } else {
            $ids = explode( ',', $gid);
    		
    		foreach ($ids as $id) {
    			$out .= $this->get_gallery($flagdb, $id, $this->attachments['images']);
    		}    		
    	}
    	
        return $out;			
	}
	
	/**
	 * FLA Gallery music playlist shortcode 
	 */
	function shortcode_grandmusic( $atts ) {
		extract(shortcode_atts(array(
			'playlist'	=> ''			
		), $atts ));
		
		$out = '';
		
		if($playlist) {
			$flag_options = get_option('flag_options');
			$playlist_path = false;
			
			if (!$flag_options) {
				return $out;
			}
			
			$playlist_path = $flag_options['galleryPath'].'playlists/'.$playlist.'.xml';
			
			if (!file_exists($flag_options['galleryPath'].'playlists/'.$playlist.'.xml')) {
				return $out;
			}
			
			$playlist_content = file_get_contents($playlist_path);
			
			preg_match_all( '/.?<item id=".*?">(.*?)<\/item>/si', $playlist_content, $items );
			if (!isset($items[1]) || !is_array($items[1])) {
				return $out;
			}
			foreach($items[1] as $playlist_item) {
				preg_match( '/.?<track>(.*?)<\/track>/i', $playlist_item, $track);
				if (count($track) > 1) {
					$url = $track[1];
				}
				$audio_record = array(
						'id' => '',
						'src' => $url,
						'type' => 'audio',
						'duration' => ''
		 			);
		 			
				$this->attachments['audio'] []= $audio_record;				
			}
			
		}
		return $out;
	}
	
	/**
	 * FLA Gallery music mp3 
	 */
	function shortcode_grandmp3( $atts ) {
		extract(shortcode_atts(array(
			'id'	=> ''
		), $atts ));
		$out = '';
		$flag_options = get_option('flag_options');
		if($id) {
			$url = wp_get_attachment_url($id);
			$url = str_replace(array('.mp3'), array(''), $url);
			
			$audio_record = array(
						'id' => '',
						'src' => $url,
						'type' => 'audio',
						'duration' => ''
		 			);
		 			
			$this->attachments['audio'] []= $audio_record;			
		}
       	return $out;
	}
}

?>