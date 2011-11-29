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
class ShoutemApiOptions {
	
	var $shoutem_options_name = "shoutem_api_options";
	
	var $shoutem_default_options = array (
		'encryption_key'=>'change.me',
		'cache_expiration' => 3600 //1h
	);
	
	public function __construct($shoutem_api) {
		$this->shoutem_api = $shoutem_api;
		add_action('admin_menu',array(&$this, 'admin_menu'));
	}
	
	public function add_listener($listener) {
		add_action("update_option_$this->shoutem_options_name",$listener);
	}
	
	public function admin_menu() {
		add_options_page('Shoutem API Settings', 'Shoutem API', 'manage_options', 'shoutem-api', array(&$this, 'admin_options'));
	}
	
	public function get_options() {
		$shoutem_options = $this->shoutem_default_options;
		$saved_options = get_option($this->shoutem_options_name);
		if(!empty($saved_options)) {
			foreach($saved_options as $key=>$val) {
				$shoutem_options[$key] = $val;
			}
		}
		return $shoutem_options;
	}
	
	public function save_options($options) {
		update_option($this->shoutem_options_name,$options);
	}
	
	public function admin_options() {
		if (!current_user_can('manage_options'))  {
	    	wp_die( __('You do not have sufficient permissions to access this page.') );
	 	}
	 	$options = $this->get_options();
	 	$encryption_key = $options['encryption_key'];
	 	if (!empty($_REQUEST['_wpnonce']) && wp_verify_nonce($_REQUEST['_wpnonce'], "update-options")) {
	 		$this->update_options(&$options);
	 	}
	 	
	 	if (class_exists('ShoutemNGGDao')) {
	 		$base_dir = $this->shoutem_api->base_dir;
			require_once "$base_dir/model/class-shoutem-ngg-dao.php";
		}
		
		if (class_exists('ShoutemFlaGalleryDao')) {
	 		$base_dir = $this->shoutem_api->base_dir;
			require_once "$base_dir/model/class-shoutem-flagallery-dao.php";
		}
		
		$ngg_integration = array(
				'plugin_name' => __('NextGEN Gallery'),
				'integration_desc' => __('Allowes you to import NextGEN galleries in to your ShoutEm application'),
				'integration_ok' => ShoutemNGGDao::available() 
				);
		$flagallery_integration = array(
				'plugin_name' => __('GRAND FlAGallery'),
				'integration_desc' => __('Allowes you to import GRAND FlAGallery galleries in to your ShoutEm application'),
				'integration_ok' => ShoutemFlaGalleryDao::available() 
				);
		
		$plugin_integration = array(
			$ngg_integration,
			$flagallery_integration
		);
		
	 	$this->print_options_page($options,$plugin_integration);
		
	}
	
	private function update_options(&$options) {
		if(!empty($_REQUEST['encryption_key'])) {
			$options['encryption_key'] = $_REQUEST['encryption_key']; 
		}
		if(array_key_exists('cache_expiration',$_REQUEST)) {
			$expiration = $_REQUEST['cache_expiration'];			
			if (is_numeric($expiration) 
			&& (int)$expiration >= 0) {
				$options['cache_expiration'] = $expiration;	
			}			 
		}
		
		$this->save_options($options);
	}
	
	private function print_options_page($options, $plugin_integrations) {
		$default_encryption_key_warrning = '';
		if($options['encryption_key'] == $this->shoutem_default_options['encryption_key']) {
			$default_encryption_key_warrning = 
			'<p>*Currently, the default encryption key is set. Leaving default encryption key could lead to compromised security of site. Change encryption key!</p>';
		}
		?>
			<div class="wrap">
  				<div id="icon-options-general" class="icon32"><br /></div>
  				<h2><?php _e('Shoutem API Settings') ?></h2>
  				<script type="text/javascript">
  					//Tnx for password generator to: Xhanch Studio http://xhanch.com
  					function gen_numb(min, max){
		                return (Math.floor(Math.random() * (max - min)) + min);
		            }
		
		            function gen_chr(num, lwr, upr, oth, ext){
		                var num_chr = "0123456789";
		                var lwr_chr = "abcdefghijklmnopqrstuvwxyz";
		                var upr_chr = lwr_chr.toUpperCase();
		                var oth_chr = "`~!@#$%^&*()-_=+[{]}\\|;:'\",<.>/? ";
		                var sel_chr = ext;
		
		                if(num == true)
		                    sel_chr += num_chr;
		                if(lwr == true)
		                    sel_chr += lwr_chr;
		                if(upr == true)
		                    sel_chr += upr_chr;
		                if(oth == true)
		                    sel_chr += oth_chr;
		                return sel_chr.charAt(gen_numb(0, sel_chr.length));
		            }
		
		            function gen_pass(len, ext, bgn_num, bgn_lwr, bgn_upr, bgn_oth,
		                flw_num, flw_lwr, flw_upr, flw_oth){
		                var res = "";
		
		                if(len > 0){
		                    res += gen_chr(bgn_num, bgn_lwr, bgn_upr, bgn_oth, ext);
		                    for(var i=1;i<len;i++)
		                        res += gen_chr(flw_num, flw_lwr, flw_upr, flw_oth, ext);
		                    return res;
		                }
		            }
  					var generate_random_encryption_key_on_click = function() {
        					var encryption_key_element = document.getElementById('shoutem_api_encryption_key_input');
        					encryption_key_element.setAttribute('value',gen_pass(16,true,true,true,false,true,true,true,false));
        			}
  				</script>
  				
    			<form action="options-general.php?page=shoutem-api" method="post">
    				<?php wp_nonce_field('update-options'); ?>
    				<table class="form-table">
      					<tr valign="top">
        				<th scope="row">Shoutem api encryption key</th>
        				<td><input type="text" id="shoutem_api_encryption_key_input" name="encryption_key" value="<?php echo htmlentities($options['encryption_key']); ?>" size="15" />        				
        				<input class="button-primary" type="button" name="generate_random_encryption_key" onClick="generate_random_encryption_key_on_click();" value="<?php _e('Generate') ?>" />
        				</td>
        				<tr valign="top">
        				<th scope="row">Cache expiration</th>
        				<td><input type="text" id="shoutem_api_cache_expiration_input" name="cache_expiration" value="<?php echo htmlentities($options['cache_expiration']); ?>" size="15" />
        				seconds (0 dissables caching)
        				</td>
        				
      				</tr>
    				</table>
    				<?php echo $default_encryption_key_warrning; ?>
    				<p class="submit">
				    	<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
				    </p>
    			</form>
    			<div class="clear"></div>
    			<h4><?php _e('Shoutem supports integration with these wordpress plugins:') ?></h4>
    			<table class="widefat">
    				<thead>
    					<tr>
    						<th class="manage-column"><?php _e('Plugin') ?></th>
    						<th class="manage-column"><?php _e('Integration Status') ?></th>
    						<th class="manage-column"><?php _e('Integration Description') ?></th>
    					</tr>    					
    				</thead>
    					<?php foreach($plugin_integrations as $plugin_integration) { ?>
    						<tr>
    							<td class="plugin-title"><?php echo $plugin_integration['plugin_name'] ?></td>
    							<td><?php $plugin_integration['integration_ok'] ? _e('Integration OK') : _e('Not Integrated, check if the plugin is installed and active');?></td>
    							<td class="desc"><?php echo $plugin_integration['integration_desc'] ?></td>
    						</tr>
    					<?php } ?>
    			</table>    			
    		</div>
		<?php	
	}
}
?>
