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
function shoutem_api_comment_duplicate_trigger() {
	throw new ShoutemApiException('comment_duplicate_trigger');	
}

function shoutem_api_comment_flood_trigger() {
	throw new ShoutemApiException('comment_flood_trigger');	
}

class ShoutemPostsCommentsDao extends ShoutemDao {

	public function get($params) {
		global $wpdb;
		
		$query = $wpdb->prepare("
			SELECT
				c.comment_ID comment_id,
				IFNULL(u.user_nicename, c.comment_author) author,
				IFNULL(u.user_url, c.comment_author_url) author_url,
				IFNULL(u.user_email, c.comment_author_email) author_image_url,
				c.comment_date published_at,
				c.comment_content message,
				0 'likeable',
				0 'likes_count',
				0 'deletable'
			FROM $wpdb->comments as c
			LEFT JOIN $wpdb->users as u 
				ON u.ID = c.user_id
			WHERE c.comment_approved = 1 AND
				c.comment_post_ID = %d AND
				c.comment_ID = %d
			", $params['post_id'], $params['comment_id']);
		
		return $this->get_by_sql($query);
	}
	
	public function find($params) {
		global $wpdb;
		
		$query = $wpdb->prepare("
			SELECT
				c.comment_ID comment_id,
				IFNULL(u.user_nicename, c.comment_author) author,
				IFNULL(u.user_url, c.comment_author_url) author_url,
				IFNULL(u.user_email, c.comment_author_email) author_image_url,
				c.comment_date published_at,
				c.comment_content message,
				0 'likeable',
				0 'likes_count',
				0 'deletable'		
			FROM $wpdb->comments as c
			LEFT JOIN $wpdb->users as u 
				ON u.ID = c.user_id
			WHERE c.comment_approved = 1 AND
				c.comment_post_ID = %d
			", $params['post_id']);
		
		return $this->find_by_sql($query, $params);
	}
	
	/**
	 * @throws ShoutemApiException
	 */
	public function create($record) {
		
		$commentdata =(array(
			'comment_author' => $record['author'],
			'comment_author_email' => $record['author_email'],
			'comment_author_url' => $record['author_url'],
			'user_id' => (int)$record['user_id'],
			'comment_content' => $record['message'],
			'comment_post_ID' => (int)$record['post_id'],
			'comment_type' => ''
		));

		add_action('comment_duplicate_trigger', 'shoutem_api_comment_duplicate_trigger');
		add_action('comment_flood_trigger', 'shoutem_api_comment_flood_trigger');
		
		$comment_id = wp_new_comment($commentdata);
		$comment = false;
		if($comment_id !== false) {
			$comment = get_comment($comment_id);
		}
		
		if($comment !== false) {
			return array(
				'comment_id' => $comment->comment_ID,
				'published_at' => $comment->comment_date,
				'author' => $comment->comment_author,
				'message' => $comment->comment_content,
				'likes_count' => 0,
				'approved' => $comment->comment_approved
			);	
		} else {
			throw new ShoutemApiException('comment_create_error');
		}
		
	}
	
	
	public function delete($record) {
		
		global $wpdb;
		
		$query = $wpdb->prepare("
			SELECT comment_ID 
			FROM $wpdb->comments
			WHERE comment_ID = %d AND
				comment_post_ID = %d AND
				user_id = %d
			LIMIT 1
			", $record['comment_id'], 
			$record['post_id'], 
			$record['user_id']);
			
		if($wpdb->query($query) === false) {
			throw new ShoutemApiException("comment_delete_error");
		}
		
		return wp_delete_comment($record['comment_id'],true);
	}
	
}

?>