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

/**
 * Removes elements from html that would cause problems for shoutem clients.
 * @param html to be sanitized
 * @param attachments (out) if present contains attachments from $html @see strip_attachments 
 * @return sanitized html    
 */
function sanitize_html($html, &$attachments = null) {
	if (isset($attachments)) {		
		$attachments = strip_attachments(&$html);
	}	
	
	$filtered_html = "";
	$forbiden_elements = "/<(style|script|iframe|object|embed|table).*?>.*?<\/(\\1)>/si";
	//first try wp_kses for removal of html elements 
	if (function_exists('wp_kses')) {				
		
		$filtered_html = preg_replace($forbiden_elements, "",$html);
		
		$allowed_html = array(
				'attachment' => array('id'=>true,'type'=>true,'xmlns'=>true),
				'a' => array('href'=>true),
				'blockquote' => array(),
				'h1' => array(),
				'h2' => array(),
				'h3' => array(),
				'h4' => array(),
				'h5' => array(),
				'p' => array(),
				'br' => array(),
				'b' => array(),
				'strong' => array(),
				'em' => array(),
				'i' => array(),
				'ul' => array(),
				'li' => array(),
				'ol' => array()
			);
		$filtered_html = wp_kses($filtered_html, $allowed_html);
	} else {		
		$all_tags = "/<(\/)?\s*([\w-_]+)(.*?)(\/)?>/ie";
		$filtered_html = preg_replace($forbiden_elements, "",$html);
		$filtered_html = preg_replace($all_tags, "filter_tag('\\1','\\2','\\3','\\4')",$filtered_html);
		
	}
	
	/*
	 * This is needed because wp_kses always removes 'se-attachment' or 'se:attachment' tag regardles of $allowed_html parameter.
	 * To circumvent this, strip_attacments inserts <seattachment id=''/> instead of<se-attachment .../> into html.
	 * Here, seattachment label is replaced with the proper label
	 */  
	//$filtered_html = preg_replace("/xmlns=\"v1\"(\s)*\/>/i","xmlns=\"urn:xmlns:shoutem-com:cms:v1\"></attachment>",$filtered_html);	
	$filtered_html = preg_replace("/type=\"image\"(\s)*(\/)?>/i","type=\"image\" xmlns=\"urn:xmlns:shoutem-com:cms:v1\"></attachment>",$filtered_html);
	$filtered_html = preg_replace("/type=\"video\"(\s)*(\/)?>/i","type=\"video\" xmlns=\"urn:xmlns:shoutem-com:cms:v1\"></attachment>",$filtered_html);
	
	return $filtered_html;
}

/**
 * Use only trough sanitize_html! Stripe attachments out of html and marks the places where attachments are striped.
 * @param html in/out modifies it so it contains <se-attachment id="attachment-id"> where attachment was striped
 * @return attachments from html
 */
function strip_attachments(&$html) {
	$images = strip_images($html);		
	$videos = strip_videos($html);
	return array(
		'images' => $images,
		'videos' => $videos 
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
				'title' => ''
			));
			$images []= $image;	
			$html = str_replace($imageTag,"<attachment id=\"$id\" type=\"image\"/>",$html);
		}
	} 		
	return $images;
}
	
	
function strip_videos(&$html) {		
	$videos = array();		
	if(preg_match_all('/<object.*?<(embed.*?)>/si',$html,$matches) > 0) {
		foreach($matches[1] as $index => $video) {
			$tag_attr = get_tag_attr($video, array(
					'id' => 'video-'.$index,
					'attachment-type' => 'video',
					'src' => '',
					'width' => '',
					'height' => '',						
					'provider' => 'youtube'
					));	
			if (strpos($tag_attr['src'],'youtube') >= 0) {
				$videos []= $tag_attr;
				$id = $tag_attr['id'];
				$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"video\"/>",$html);
			}
		} 
	}
	
	if(preg_match_all('/<(iframe.*?)>/si',$html,$matches) > 0) {
		
		foreach($matches[1] as $index => $video) {			
			$tag_attr = get_tag_attr($video, array(
					'id' => 'video-'.$index,
					'attachment-type' => 'video',
					'src' => '',
					'width' => '',
					'height' => '',						
					'provider' => 'youtube'
					));
					
			//youtube video				
			if (strpos($tag_attr['src'],'youtube') !== false) {
				$videos []= $tag_attr;
				$id = $tag_attr['id'];
				$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"video\"/>",$html);								
			}
			
			//vimeo video
			if (strpos($tag_attr['src'],'vimeo') !== false) {
				$tag_attr['provider'] = 'vimeo';
				$videos []= $tag_attr;
				$id = $tag_attr['id'];				
				$html = str_replace($matches[0][$index],"<attachment id=\"$id\" type=\"video\"/>",$html);								
			}
		} 
	}
	
	return $videos;
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
	} else if (strcmp($name,'se-attachment') == 0) {
		$filtered_attr = $attr;
	} else if (strcmp($name,'img') == 0) {
		$filtered_attr = get_sanitized_attr('src',$attr);	
	} else if (strcmp($name,'a') == 0) {
		$filtered_attr = get_sanitized_attr('href',$attr);
	}
	
	$tag = '<'.$opening.$name.$filtered_attr.$closing.'>';
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