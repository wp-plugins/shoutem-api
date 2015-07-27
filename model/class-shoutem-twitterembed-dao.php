<?php
/**
 * This class is designed to work with Twitter embed markup wrapped in our own shortcode
 */
class ShoutemTwitterEmbedDao extends ShoutemDao {
	
	function attach_to_hooks() {
		$this->attach_to_shortcodes();
	}
	
	public function attach_to_shortcodes() {
		remove_shortcode( 'shoutemtwitterembed');
		add_shortcode( 'shoutemtwitterembed', array(&$this, 'shortcode_twitterembed' ) );
	}

	/**
	 * shoutem twitter embed shortcode
	 */
	function shortcode_twitterembed($atts, $content) {
        extract(shortcode_atts(array(
            'se_visible' => 'true'
        ), $atts ));
        
        if ($se_visible != 'true') {
        	return '';	
        }

        $dom = new DOMDocument();
		// supress warnings caused by HTML5 tags
		@$dom->loadHTML('<?xml encoding="UTF-8">'.$content);
		$xpath = new DOMXPath($dom);
		$tweet_node = $xpath->query("//*[contains(concat(' ', @class, ' '),' embed-twitter ')]")->item(0);
		if (!$tweet_node) {
			return '';
		}

		$text_node = null;
		$user_node = null;
		foreach ($xpath->query("*/p", $tweet_node) as $paragraph) {
			$trimmed_content = trim(str_replace('\\n', '', $paragraph->textContent));
			if (!$trimmed_content) continue;

			if (!$text_node) {
				$text_node = $paragraph;
				continue;
			}

			if (!$user_node) {
				$user_node = $paragraph;
				continue;
			}

			if ($text_node && $user_node) {
				break;
			}
		}

		if (!$text_node || !$user_node) {
			return '';
		}

		$tweet_text = $text_node->nodeValue;

		$timestamp = '';
		$timestamp_node = $xpath->query('a', $user_node)->item(0);
		if ($timestamp_node) {
			$timestamp = $timestamp_node->textContent;
		}

		$user_info_text = '';
		$user_info_text_node = $xpath->query('text()', $user_node)->item(0);
		if ($user_info_text_node) {
			$user_info_text = $user_info_text_node->textContent;
		}
		$user_info_text = str_replace(html_entity_decode('&mdash;'), '', $user_info_text);
		$user_info_text = str_replace('&mdash;', '', $user_info_text);
		preg_match('/\s*(.*)\\((.*)\\)/i', $user_info_text, $user_info);

		return '<twitterdiv class="twitter-embed">'.
					'<twitterdiv class="twitter-icon"></twitterdiv>'.
  					'<twitterdiv class="name">'.trim($user_info[1]).'</twitterdiv>'.
  					'<twitterdiv class="handle">'.$user_info[2].'</twitterdiv>'.
  					'<twitterdiv class="tweet">'.$tweet_text.'</twitterdiv>'.
  					'<twitterdiv class="timestamp">'.$timestamp.'</twitterdiv>'.
				'</twitterdiv>';
	}

} 
?>
