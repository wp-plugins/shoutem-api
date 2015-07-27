<?php
/**
 * This class is designed to work with SMG gallery output (USA Today specific) wrapped in our own shortcode
 */
class ShoutemSMGDao extends ShoutemDao {
	
	function attach_to_hooks() {
		remove_action('shoutem_get_post_start',array(&$this,'on_shoutem_post_start'));
		add_action('shoutem_get_post_start',array(&$this,'on_shoutem_post_start'));
		$this->attach_to_shortcodes();
	}
	
	public function on_shoutem_post_start($params) {
		$this->attachments = &$params['attachments_ref'];		
	}
	
	public function attach_to_shortcodes() {
		remove_shortcode( 'shoutemsmgallery');
		add_shortcode( 'shoutemsmgallery', array(&$this, 'shortcode_smgallery' ) );
	}

	/**
	 * SMG gallery shortcode
	 */
	function shortcode_smgallery($atts, $content) {
        extract(shortcode_atts(array(
            'se_visible' => 'true'
        ), $atts ));
        
        if ($se_visible != 'true') {
        	return '';	
        }

        return $this->get_gallery($content, $this->attachments['images']);
	}
	
	private function get_gallery($content, &$images) {
		$dom = new DOMDocument();
		// posts don't have a single root, so wrap them
		// supress warnings caused by HTML5 tags
		@$dom->loadHTML($content);

		$xpath = new DOMXPath($dom);
		$gallery_node = $xpath->query("//*[@id='SMG_PhotoGallery']")->item(0);
		if (!$gallery_node) {
			return '';
		}

		$replacement_list = '';
		$img_wrappers = $xpath->query("//*[contains(concat(' ', @class, ' '),' _smg-image-wrap ')]", $gallery_node);
		foreach ($img_wrappers as $index => $img_wrapper) {
			$img_src = $img_wrapper->getAttribute('data-imgsrc');
			if (!$img_src) continue;

			$pid = 'smg-img-'.$index;
			$image = array_merge(
				array(
					'src' => $img_src,
					'id' => $pid
				),
				$this->extract_size_from_src($img_src)
			);

			$img_caption_list = $xpath->query("a/text()", $img_wrapper);
			$img_caption = '';
			foreach ($img_caption_list as $img_caption_line) {
				$img_caption = $img_caption.' '.trim($img_caption_line->wholeText);
			}
			$replacement_item = "<se-attachment id=\"$pid\" type=\"image\" xmlns=\"urn:xmlns:shoutem-com:cms:v1\" />";
			if ($img_caption) {
				$image['caption'] = $img_caption;
				// TODO
				// $replacement_item = '<figure>'.$replacement_item.'<figcaption class="image-caption">'.$img_caption.'</figcaption>'.'</figure>';
			}
			$replacement_list = $replacement_list.$replacement_item;

			$img_thumb_src = $img_wrapper->getAttribute('data-imgthumbsrc');
			if ($img_thumb_src) {
				$image['thumbnail_url'] = $img_thumb_src;
			}
			// TODO
			// $this->extract_size_from_src($img_thumb_src)

			$images []= $image;
		}
		$replacement = '<se-attachment type="gallery" xmlns="urn:xmlns:shoutem-com:cms:v1">'.$replacement_list.'</se-attachment>';
		$replacement_fragment = $dom->createDocumentFragment();
		$replacement_fragment->appendXML($replacement);
		$gallery_node->parentNode->replaceChild($replacement_fragment, $gallery_node);
		return substr($dom->saveXML($dom->getElementsByTagName('body')->item(0)), 6, -7);
	}

	private function extract_size_from_src($src) {
		$size = array();
		if ($src) {
			if(preg_match('/\\?resize=(\\d+)%2C(\\d+)/i', $src, $matches)) {
				$size['width'] = $matches[1];
				$size['height'] = $matches[2];
			}
		}
		return $size;
	}
} 
?>
