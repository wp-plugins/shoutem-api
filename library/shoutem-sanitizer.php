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

function html_to_text($html) {
	$text = sanitize_html($html);
	$text = wp_kses($text,array(),array());
	$text = html_entity_decode($text,ENT_QUOTES,'UTF-8');
	return $text;
}

/**
 * Removes elements from html that would cause problems for shoutem clients.
 * @param html to be sanitized
 * @param attachments (out) if present contains attachments from $html @see strip_attachments
 * @return sanitized html
 */
function sanitize_html($html, &$attachments = null) {
	//NextGen gallery plugin fix. Sanitize images included inside of dl tags before image strip command.
	$forbiden_elements = "/<(dl).*?>.*?<\/(\\1)>/si";
	$filtered_html = preg_replace($forbiden_elements, "",$html);

	//remove comments
	$filtered_html = preg_replace("/<!--(.*?)-->/si", "",$filtered_html);

	if (isset($attachments)) {
		$attachments = strip_attachments($filtered_html);
	}

	$forbiden_elements = "/<(style|script|iframe|object|embed|dl).*?>.*?<\/(\\1)>/si";
	$filtered_html = preg_replace($forbiden_elements, "",$filtered_html);

	$disclaimer_div = "/<(div)(.+?id=(\\\\*([\\\"\\\']))disclaimer\\3.*?)>(.*?)<\/\\1>/si";
	$filtered_html = preg_replace($disclaimer_div, "<p$2>$5</p>", $filtered_html);
	
	$all_tags = "/<(\/)?\s*([\w-_]+)(.*?)(\/)?>/ie";
	$filtered_html = preg_replace($all_tags, "rename_tag_pre('\\1','\\2','\\3','\\4')",$filtered_html);
	//first try wp_kses for removal of html elements
	if (function_exists('wp_kses')) {
		$allowed_html = array(
				'attachment' => array('id'=>true,'type'=>true,'xmlns'=>true),
				'seattachment' => array('id'=>true,'type'=>true,'xmlns'=>true),
				'a' => array('href'=>true),
				'blockquote' => array(),
				'h1' => array(),
				'h2' => array(),
				'h3' => array(),
				'h4' => array(),
				'h5' => array(),
				'p' => array('id'=>true, 'class'=>true),
				'br' => array(),
				'b' => array(),
				'strong' => array(),
				'em' => array(),
				'i' => array(),
				'ul' => array(),
				'li' => array(),
				'ol' => array(),
				'twitterdiv' => array('class'=>true)
			);
		$filtered_html = wp_kses($filtered_html, $allowed_html);
	} else {
		$filtered_html = preg_replace($all_tags, "filter_tag('\\1','\\2','\\3','\\4')",$filtered_html);
	}
	$filtered_html = preg_replace($all_tags, "filter_attr('\\1','\\2','\\3','\\4')",$filtered_html);
	$filtered_html = preg_replace($all_tags, "rename_tag_post('\\1','\\2','\\3','\\4')",$filtered_html);

	$filtered_html = preg_replace("/<\s*([^>\s]+)([^>]*)xmlns=\"v1\"([^>]*?)\s*\/>/i", "<$1$2xmlns=\"urn:xmlns:shoutem-com:cms:v1\"$3></$1>", $filtered_html);
	$filtered_html = preg_replace("/xmlns=\"v1\"/i","xmlns=\"urn:xmlns:shoutem-com:cms:v1\"",$filtered_html);
	return $filtered_html;
}

function sanitize_the_url($url) {
	$sanitized_url = trim($url);
	$sanitized_url = str_replace(' ','%20', $sanitized_url);
	return $sanitized_url;
}

function sanitize_attachments(&$attachments) {
	if (!is_array($attachments)) {
		return;
	}
	foreach	($attachments as $key=>&$attachment) {
		if (is_array($attachment)) {
			sanitize_attachments($attachment);
		}
		if (preg_match('/(.*_url)|(src)/',$key)) {
			$attachments[$key] = sanitize_the_url($attachments[$key]);
		}
	}
}

function unparse_url($parsed_url) {
  $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
  $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
  $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
  $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
  $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
  $pass     = ($user || $pass) ? "$pass@" : '';
  $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
  $pathParts = explode($path, '/');
  $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
  $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
  return "$scheme$user$pass$host$port$path$query$fragment";
}

function html_decode_list(&$list) {
	foreach( $list as &$item) {
		if (is_array($item) && array_key_exists("src",$item)) {
			$item["src"] = html_entity_decode($item["src"]);
		}
	}
}

function json_request($url) {
	$response = wp_remote_get($url);
	if ($response['response']['code'] != 200) {
		return false;
	}
	$json = new SEServices_JSON();
    return $json->decode($response['body']);
}

/**
 * Use only trough sanitize_html! Stripe attachments out of html and marks the places where attachments are striped.
 * @param html in/out modifies it so it contains <se-attachment id="attachment-id"> where attachment was striped
 * @return attachments from html
 */
function strip_attachments(&$html) {
	$images = strip_images($html);
	$videos = strip_videos($html);
	$audio = strip_audio($html);
	html_decode_list($videos);
	html_decode_list($images);
	html_decode_list($audio);
	return array(
		'images' => $images,
		'videos' => $videos,
		'audio' => $audio
	);
}


function strip_images(&$html) {
	$images = array();

	if(preg_match_all('/<img.*?>/i',$html,$matches) > 0) {
		foreach($matches[0] as $index => $imageTag) {
			$id = 'img-'.$index;
			
			$image = get_tag_attr($imageTag, array(
				'id' => $id,
				'attachment-type' => 'image',
				'src' => '',
				'width' => '',
				'height' => '',
				'title' => '',
				'caption' => ''
			));

			$images []= $image;
			$html = str_replace($imageTag,"<attachment id=\"$id\" type=\"image\" xmlns=\"v1\" />",$html);
		}
	}
	return $images;
}

function sanitize_youtube_video_src($src) {
	//replace embed/id format with v/id format
	return str_replace('/embed/','/v/',$src);
}

function get_brightcove_video_id($src) {
	parse_str($src, $params);
	$key = '@videoPlayer';
	if (!array_key_exists($key, $params)) return false;
	return $params[$key];
}

function brightcove_attachment($tag_attr) {
	$src = $tag_attr['src'];
	$tag_attr['src'] = "";
	$shoutem_options = new ShoutemApiOptions($this);
	$options = $shoutem_options->get_options();
	$token = $options['brightcove_token'];
	if (!$token) {
		return false;
	}
	$video_id = get_brightcove_video_id($src);
	if (!$video_id) {
		return false;
	}

	$url = "http://api.brightcove.com/services/library".
			"?command=find_video_by_id".
			"&video_fields=name,length,FLVURL,thumbnailURL".
			"&media_delivery=http".
			"&video_id=".$video_id.
			"&token=".$token;
	$video_json = json_request($url);
	if (!$video_json || !$video_json->FLVURL) {
		return false;
	}
	
	$tag_attr['src'] = $video_json->FLVURL;
	$tag_attr['title'] = $video_json->name;
	$tag_attr['duration'] = $video_json->length/1000;
	$tag_attr['thumbnail_url'] = $video_json->thumbnailURL;
	$tag_attr['height'] = '';
	$tag_attr['width'] = '';

	return $tag_attr;
}

function strip_videos(&$html) {
	$videos = array();
	if(preg_match_all('/<object.*?<(embed.*?)>/si',$html,$matches) > 0) {
		foreach($matches[1] as $index => $video) {
			$tag_attr = get_tag_attr($video, array(
					'id' => 'video-'.(count($videos) + $index),
					'attachment-type' => 'video',
					'src' => '',
					'width' => '',
					'height' => '',
					'provider' => 'youtube'
					));

			if (strpos($tag_attr['src'],'youtube') !== false) {
				$tag_attr['src'] = sanitize_youtube_video_src($tag_attr['src']);
				$videos []= $tag_attr;
				$id = $tag_attr['id'];
				$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"video\" xmlns=\"v1\" />",$html);
			}

			if (strpos($tag_attr['src'],'brightcove') !== false) {
				$tag_attr['provider'] = 'brightcove';
				$bc_attachment = brightcove_attachment($tag_attr);
				if ($bc_attachment) {
					$videos []= $bc_attachment;
					$id = $bc_attachment['id'];
					$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"video\" xmlns=\"v1\" />",$html);
				}
			}
		}
	}

	if(preg_match_all('/<(iframe.*?)>/si',$html,$matches) > 0) {

		foreach($matches[1] as $index => $video) {
			$tag_attr = get_tag_attr($video, array(
					'id' => 'video-'.(count($videos) + $index),
					'attachment-type' => 'video',
					'src' => '',
					'width' => '',
					'height' => '',
					'provider' => 'youtube'
					));
			//youtube video
			if (strpos($tag_attr['src'],'youtube') !== false) {
				$tag_attr['src'] = sanitize_youtube_video_src($tag_attr['src']);
				$videos []= $tag_attr;
				$id = $tag_attr['id'];
				$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"video\" xmlns=\"v1\" />",$html);
			}

			//vimeo video
			if (strpos($tag_attr['src'],'vimeo') !== false) {
				$tag_attr['provider'] = 'vimeo';
				$videos []= $tag_attr;
				$id = $tag_attr['id'];
				$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"video\" xmlns=\"v1\" />",$html);
			}
		}
	}

	return $videos;
}

function get_sound_cloud_track_id($src) {
	$src = urldecode($src);
	if (preg_match("/\/tracks\/(\w+)/i",$src, $maches)) {
		$id = $maches[1];
		return $id;
	}
	return false;
}

function get_sound_cloud_playlist_id($src) {
	$src = urldecode($src);
	if (preg_match("/\/playlists\/(\w+)/i",$src, $maches)) {
		$id = $maches[1];
		return $id;
	}
	return false;
}

function sound_cloud_attachment($tag_attr) {
	$trackId = get_sound_cloud_track_id($tag_attr['src']);
	$playlistId = get_sound_cloud_playlist_id($tag_attr['src']);
	$server = "shoutem";
	if ($trackId) {
		$tag_attr['provider_id'] = $trackId;
		$tag_attr['src'] = "";
		$sc_api_url = 'http://api.'.$server.'.com/api/scstream?method=track/get&id='.$trackId;
		$response = json_request($sc_api_url);
		if ($response) {
			$tag_attr['duration'] = $response->duration;
			$tag_attr['src'] = $response->stream_url;
			if (property_exists($response, "title")) {
				$tag_attr['title'] = $response->title;
			}
		}
		return 	$tag_attr;
	} else if ($playlistId) {
		$sc_api_url = 'http://api.'.$server.'.com/api/scstream?method=playlist/get/first&id='.$playlistId;
		$response = json_request($sc_api_url);
		if ($response) {
			$tag_attr['provider_id'] = $response->id;
			$tag_attr['duration'] = $response->duration;
			$tag_attr['src'] = $response->stream_url;
			if (property_exists($response, "title")) {
				$tag_attr['title'] = $response->title;
			}
			return $tag_attr;
		}
	}
	return false;
}

function strip_audio(&$html) {
	$audios = array();
	if(preg_match_all('/<object.*?<(embed.*?)>/si',$html,$matches) > 0) {
		foreach($matches[1] as $index => $audio) {
			$tag_attr = get_tag_attr($audio, array(
					'id' => 'audio-'.(count($audios) + $index),
					'attachment-type' => 'audio',
					'src' => '',
					'provider' => 'soundcloud'
					));
			//soundcloud
			if (strpos($tag_attr['src'],'player.soundcloud.com') !== false) {
				$sc_attachment = sound_cloud_attachment($tag_attr);
				if ($sc_attachment) {
					$audios []= $sc_attachment;
					$id = $sc_attachment['id'];
					$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"audio\" xmlns=\"v1\" />",$html);
				}
			}
		}
	}

	if(preg_match_all('/<(iframe.*?)>/si',$html,$matches) > 0) {

		foreach($matches[1] as $index => $audio) {
			$tag_attr = get_tag_attr($audio, array(
					'id' => 'audio-'.(count($audios) + $index),
					'attachment-type' => 'audio',
					'src' => '',
					'provider' => 'soundcloud'
					));

			//soundcloud audio
			if (strpos($tag_attr['src'],'.soundcloud.com') !== false) {
				$sc_attachment = sound_cloud_attachment($tag_attr);
				$audios []= $sc_attachment;
				$id = $sc_attachment['id'];
				$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"audio\" xmlns=\"v1\" />",$html);
			}

		}
	}

	return $audios;
}

function get_tag_attr($tag, $defaults = array()) {
	$attr = $defaults;
	if (preg_match_all('/ +(\w+) *= *[\'"]([^\'"]+)[\'"]/i',$tag,$matches) > 0) {
		$attrNames = $matches[1];
		$attrVals = $matches[2];
		for($i = 0; $i < count($attrNames); $i++) {
			if (array_key_exists($attrNames[$i],$attr)) {
				$attr[$attrNames[$i]] = $attrVals[$i];
			}
		}
	}
	return $attr;
}

/**
 * private function used by sanitize_html to remove or modify each html tag.
 * @return filtered tag
 */
function filter_tag($opening, $name, $attr, $closing) {

	$allow_tags = array('se-attachment','seattachment','se:attachment','attachment','a','blockquote','h1','h2','h3','h4','h5',
						'p','br','b','strong','em','i','a','ul','li','ol');
	if(!in_array($name, $allow_tags)) {
		return '';
	}

	$filtered_attr = '';

	if (strcmp($name,'attachment') == 0) {
		$filtered_attr = $attr;
	} else if (strcmp($name,'se-attachment') == 0 || strcmp($name,'seattachment') == 0) {
		$filtered_attr = $attr;
	} else if (strcmp($name,'img') == 0) {
		$filtered_attr = get_sanitized_attr('src',$attr);
	} else if (strcmp($name,'a') == 0) {
		$filtered_attr = get_sanitized_attr('href',$attr);
	} else if (strcmp($name,'p') == 0) {
		$filtered_attr = get_sanitized_attr('class',$attr);
	} else if (strcmp($name,'twitterdiv') == 0) {
		$filtered_attr = get_sanitized_attr('class',$attr);
	}

	$tag = '<'.$opening.$name.$filtered_attr.$closing.'>';
	$tag = str_replace("\\\"", "\"", $tag);
	return $tag;
}

/**
 * private function used by sanitize_html to remove or modify class attribute of each html tag.
 * @return filtered tag
 */
function filter_attr($opening, $name, $attr, $closing) {
	$new_classes = array();
	$clear_id = false;
	$keep_class = false;
	
	$caption_class_regex = "/class=(\\\\*([\\\"\\']).*?)(wp-caption-text|image-caption).*?\\1/i";
	if (preg_match($caption_class_regex, $attr)) {
		$new_classes[] = 'image-caption';
	}

	$disclaimer_id_regex = "/id=(\\\\*([\\\"\\']))disclaimer\\1/i";
	if (preg_match($disclaimer_id_regex, $attr)) {
		$new_classes[] = 'disclaimer';
		$clear_id = true;
	}
	else if (strcmp($name,'p') == 0) {
		$clear_id = true;
	}

	if (strcmp($name,'twitterdiv') == 0) {
		$keep_class = true;
	}

	$new_attr = $attr;
	if (!$new_attr) $new_attr = '';
	if (!$keep_class) {
		$class_regex = "/(\s*class=(\\\\*[\\\"\\'])).*?\\2/i";
		if (preg_match($class_regex, $attr)) {
			$replace_string = '';
			if (count($new_classes)) {
				$replace_string = "$1".implode(" ", $new_classes)."$2";
			}
			$new_attr = preg_replace($class_regex, $replace_string, $new_attr);
		} else if (count($new_classes)) {
			$new_attr = $new_attr.' class="'.implode(" ", $new_classes).'"';
		}
		if ($clear_id) {
			$id_regex = "/(\s*id=(\\\\*[\\\"\\'])).*?\\2/i";
			$new_attr = preg_replace($id_regex, '', $new_attr);
		}
	}
	
	$tag = '<'.$opening.$name.$new_attr.$closing.'>';
	$tag = str_replace("\\\"", "\"", $tag);
	return $tag;
}

/**
 * private function used by sanitize_html to rename tags
 * @return filtered tag
 */
function rename_tag_pre($opening, $name, $attr, $closing) {
	if (strcmp($name,'se-attachment') == 0) {
		$name = 'seattachment';
	} else if (strcmp($name,'figcaption') == 0) {
		$name = 'p';
	} else if (strcmp($name,'tr') == 0 && strcmp($opening,'/') == 0) {
		//Limited support for tables: each table row starts from a new line
		$opening = '';
		$name = 'br';
		$attr = '';
		$closing = '/';
	}

	$tag = '<'.$opening.$name.$attr.$closing.'>';
	$tag = str_replace("\\\"", "\"", $tag);
	return $tag;
}

/**
 * private function used by sanitize_html to rename tags
 * @return filtered tag
 */
function rename_tag_post($opening, $name, $attr, $closing) {
	if (strcmp($name,'seattachment') == 0) {
		$name = 'se-attachment';
	} else if (strcmp($name,'twitterdiv') == 0) {
		$name = 'div';
	}

	$tag = '<'.$opening.$name.$attr.$closing.'>';
	$tag = str_replace("\\\"", "\"", $tag);
	return $tag;
}


function is_attr_forbidden($attr) {
	if (preg_match("/\s*javascript.*/i",$attr['value']) > 0) {
		return true;
	}
	return false;
}

function get_attr($name, $string) {
	$attr = false;
	//$string = str_replace("\\\"","\"",$string);
	$match_rule = "/\s*(".$name."=\s*([\"']).*?(\\2))/i";

	if (preg_match("/\s*(".$name."=([\"'])(.*?)(\\2))/i", $string, $matches) > 0) {
		$quote = $matches[2];
		$value = $matches[3];
		$attr['value'] = $value;
		$attr['name'] = $name;
		$attr['html_attr'] = ' '.$name.'='.$quote.$value.$quote;
	}
	return $attr;
}

function get_sanitized_attr($name, $string) {
	$tagAttr = get_attr($name,$string);
	if ($tagAttr && !is_attr_forbidden($tagAttr)) {
		return $tagAttr['html_attr'];
	}
	return '';
}

?>