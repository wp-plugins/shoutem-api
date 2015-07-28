<?php
/**
 * This class is designed to work with Brightcove embed markup wrapped in our own shortcode
 */
class ShoutemBrightcoveEmbedDao extends ShoutemDao {
	
	function attach_to_hooks() {
		$this->attach_to_shortcodes();
	}
	
	public function attach_to_shortcodes() {
		remove_shortcode( 'shoutembrightcoveembed');
		add_shortcode( 'shoutembrightcoveembed', array(&$this, 'shortcode_brightcoveembed' ) );

		remove_shortcode( 'shoutembrightcovesimple');
		add_shortcode( 'shoutembrightcovesimple', array(&$this, 'shortcode_brightcovesimple' ) );

		remove_shortcode( 'shoutemwpcomwidgetembed');
		add_shortcode( 'shoutemwpcomwidgetembed', array(&$this, 'shortcode_wpcomwidgetembed' ) );
	}

	/**
	 * shoutem brightcove embed shortcode
	 */
	function shortcode_brightcoveembed($atts, $content) {
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
		$brightcove_node = $xpath->query("//*[contains(concat(' ', @class, ' '),' brightcove-embed ')]")->item(0);
		if (!$brightcove_node) {
			return '';
		}

		return $this->parse_object($brightcove_node, $xpath);
	}

	/**
	 * shoutem brightcove simple embed code
	 */
	function shortcode_brightcovesimple($atts, $content) {
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
		$brightcove_simple_node = $xpath->query("//*[contains(@id,'simple-brightcove-')]")->item(0);
		if (!$brightcove_simple_node) {
			return '';
		}

		return $this->parse_object($brightcove_simple_node, $xpath);
	}

	/**
	 * shoutem wpcomwidget embed code
	 */
	function shortcode_wpcomwidgetembed($atts, $content) {
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
		$wpcom_widget_form_node = $xpath->query("//*[contains(@id,'wpcom-iframe-form-')]")->item(0);
		if (!$wpcom_widget_form_node) {
			return '';
		}

		$url = $wpcom_widget_form_node->getAttribute('action');
		parse_str(parse_url($url, PHP_URL_QUERY), $url_params);
		$url = preg_replace('/(https?:)?(.+)/i', 'http:$2', $url);
		$form_inputs = array();
		foreach ($xpath->query("//input", $wpcom_widget_form_node) as $form_input) {
			$form_inputs[$form_input->getAttribute('name')] = $form_input->getAttribute('value');
		}

		$headers = array('Content-type: application/x-www-form-urlencoded');
		if ($url_params['wpcom_origin']) {
			// $headers[] = 'Origin: '.$url_params['wpcom_origin'];
		}
		$options = array('http' => array(
			'header'  => $headers,
        	'method'  => 'POST',
        	'content' => http_build_query($form_inputs)
        ));

        $context  = stream_context_create($options);
		@$wp_com_widget = file_get_contents($url, false, $context);
		if (!$wp_com_widget) {
			$wp_com_widget = '';
		}
		
		$dom = new DOMDocument();
		// supress warnings caused by HTML5 tags
		@$dom->loadHTML($wp_com_widget);

		$xpath = new DOMXPath($dom);
		$flash_obj_node = $xpath->query("//*[@id='flashObj']")->item(0);
		if (!$flash_obj_node) {
			return $content;
		}
		
		$embed_node = $xpath->query("//embed", $flash_obj_node)->item(0);
		if (!$embed_node) {
			return $content;
		}

		$src = $embed_node->getAttribute('src');
		if (strpos($src, 'brightcove') === false) {
			return $content;
		}

		$queryChar = strpos($src, '?') === false ? '?' : '&';
		$src .= $queryChar.$embed_node->getAttribute('flashvars');
		$src = str_replace('federated_f9', 'htmlFederated', $src);
		$src = str_replace('videoId', '%40videoPlayer', $src);
		$embed_node->removeAttribute('flashvars');
		$embed_node->setAttribute('src', $src);

		return substr($dom->saveXML($dom->getElementsByTagName('body')->item(0)), 6, -7);
	}


	private function parse_object($brightcove_node, $xpath) {
		$paramIsVid = $xpath->query("//param[@name='isVid']", $brightcove_node)->item(0);
		if (!$paramIsVid || $paramIsVid->getAttribute('value') !== 'true') {
			return '';
		}

		$paramPlayerKey = $xpath->query("//param[@name='playerKey']", $brightcove_node)->item(0);
		$paramVideoPlayer = $xpath->query("//param[@name='@videoPlayer']", $brightcove_node)->item(0);
		if (!$paramPlayerKey || !$paramVideoPlayer) {
			return '';
		}

		$playerKey = $paramPlayerKey->getAttribute('value');
		$videoPlayer = $paramVideoPlayer->getAttribute('value');

		$paramSecureHTML = $xpath->query("//param[@name='secureHTMLConnections']", $brightcove_node)->item(0);
		$secureHTML = $paramSecureHTML && $paramSecureHTML->getAttribute('value') === 'true';
		$paramPubCode = $xpath->query("//param[@name='pubCode']", $brightcove_node)->item(0);
		$pubCode = $paramPubCode && $paramPubCode->getAttribute('value');
		
		$url = 'http://c.brightcove.com/services';
		if ($pubCode) {
			$url = $pubCode.'.ariessaucetown.local/services';
			if ($secureHTML) {
				$url = 'https://secure.'.$url;
			}
			else {
				$url = 'http://c.'.$url;
			}
		} else if ($secureHTML) {
			$url = 'https://secure.brightcove.com/services';
		}

		$url .= '/viewer/htmlFederated?';
		$url .= '&isVid=true';
		$url .= '&isUI=true';
		$url .= '&playerKey='.$playerKey;
		$url .= '&'.urlencode('@videoPlayer').'='.$videoPlayer;

		$paramWidth = $xpath->query("//param[@name='width']", $brightcove_node)->item(0);
		$paramHeight = $xpath->query("//param[@name='height']", $brightcove_node)->item(0);

		$out = '<object>'.'<embed src="'.$url.'"';
		if ($paramWidth && $paramHeight) {
			$out .= ' width="'.$paramWidth->getAttribute('value').'"';
			$out .= ' height="'.$paramHeight->getAttribute('value').'"';
		}
		$out .= '></embed></object>';
		return $out;
	}
} 
?>
