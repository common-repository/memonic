<?php
/*
Plugin Name: Memonic
Plugin URI: http://memonic.com/tools/goodies/wordpress
Description: Put your Memonic collection to use by having it right at your fingertips when writing a blog post; display a Memonic badge on your sidebar; add a clip button to your posts. <em>After installation</em> 1) activate the plugin, 2) go to the <a href="options-general.php?page=memonic/memonic.php">settings screen</a>, 3) enter your Memonic credentials in <a href="profile.php#memonic">your profile</a>.
Version: 1.1.0
Author: Memonic
Author URI: http://www.memonic.com/
License: GPL2
*/

/*  Copyright 2011  Memonic  (email : comment@memonic.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class memonic {
	private $pluginPath;
	private $pluginUrl;
	private $version;
	private $pageSize = 4;
	public $serverURL = 'https://www.memonic.com';
        
	function __construct() {
		/* set plugin variables */
		/* set Plugin Path */
		$this->pluginPath = dirname(__FILE__);
	
		/* set Plugin URL */
		$this->pluginUrl = WP_PLUGIN_URL . '/memonic';
		
		// set plugin version
		$this->version = '1.0';
		
		// get plugin settings
		$opt = get_option('memonic');
		
		register_activation_hook(__FILE__, array(&$this, 'activate'));
		
		/* initialize language domain */
		add_filter('init', array(&$this, 'init_locale'));
		add_filter('init', array(&$this, 'enqueue_static'));
		
		add_action('admin_menu', array(&$this, 'admin_menu'));
		//add_action('save_post', array(&$this, 'save_post_option'));
		
		/* user specific settings */
		add_action('show_user_profile', array(&$this, 'add_user_options'));
		add_action('edit_user_profile', array(&$this, 'add_user_options'));
		add_action('personal_options_update', array(&$this, 'save_user_options'));
		add_action('edit_user_profile_update',  array(&$this, 'save_user_options'));
		
		if ($opt['post_btn']) {
			add_action('wp_ajax_nopriv_memonic_getPost', array(&$this, 'get_post_attributes'));
			add_action('wp_ajax_memonic_getPost', array(&$this, 'get_post_attributes'));
			if ($opt['post_btn_pos'] == 'none') {
				/* register short code to output memonic button */
				add_action('memonic_btn', array(&$this, 'btn_shortcode'));
			} else {
				/* inject button on posts */
				add_filter('template_redirect', array(&$this, 'insert_memonicBtn'));
			}
		}		
		
		if ($opt['show_collection'] and is_admin()) {
			add_action('add_meta_boxes', array(&$this, 'feed_box'));
			/* AJAX actions */
			add_action('wp_ajax_memonic_notesList', array(&$this, 'notes_list'));
			add_action('wp_ajax_memonic_noteDetail', array(&$this, 'note_details'));
			add_action('wp_ajax_memonic_noteGuestpass', array(&$this, 'note_guestpass'));
			add_action('wp_ajax_memonic_userDisable', array(&$this, 'user_disable'));
		}
				
		/* initiate Memonic Widget */
		add_action( 'widgets_init', create_function( '', 'register_widget("Memonic_Widget");' ) );
		
		/* load memonic API library */
		require_once $this->pluginPath.'/lib/memonic.lib.php';
			
	}
	
    /**
     * Method initializes the plugin specific locale repository which holds
     * all i18n strings for the plugin
     * @param void
     * @return void
     */
	public function init_locale(){
		load_plugin_textdomain('memonic', false, $this->pluginPath.'/languages/');
	}

	public function enqueue_static() {
		if (is_admin()) {
			 wp_enqueue_script('memonic_admin',
				plugins_url('js/admin.js', __FILE__),
				array('jquery-ui-droppable'));
			wp_enqueue_style('memonic-collection_style',
				plugins_url('css/styleadmin.css', __FILE__));
				
			/*
			 * initialize AJAX nonce
			 * declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
			 */
			wp_localize_script( 'memonic_admin', 'memonic', array(
			    'postEditNonce' => wp_create_nonce('memonic-nonce'), 
			));
			
		} else {
			$opt = get_option('memonic');
			if  ($opt['post_btn']) {
				wp_enqueue_script('memonic-getpost', $this->pluginUrl.'/js/memonic-getpost.js', array('jquery'));

				/*
				 * initialize AJAX nonce
				 * declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)
				 */
				wp_localize_script( 'memonic-getpost', 'MemonicWP', array(
					'ajaxurl' => admin_url( 'admin-ajax.php' ),
					'clipNonce' => wp_create_nonce('memonic-nonce'), 
				));
			}
		}
	}
	
	public function activate() {
		if (!get_option('memonic')) 
			add_option('memonic', $this->get_default_option_values());
	}
	
	private function get_default_option_values() {
		$memOptions = array();
		$curLang = substr(get_locale(), 0, 2);
		$memOptions['lang'] = in_array($curLang, array('de', 'en')) ? $curLang : 'en';
		$memOptions['show_collection'] = 0;
		$memOptions['post_btn'] = 0;
		$memOptions['post_btn_img'] = 'clip_button_m';
		$memOptions['post_btn_pos'] = 'top_right';
		$memOptions['badge_css'] = 
			'#memonic_badge {width: 100%;}'.
			'#memonic_badge {border: 2px solid #135AB2; padding: 0; margin: 5px; font-family: arial,helvetica,clean,sans-serif; font-size: 13px; overflow: hidden;}'.
			'#memonic_badge a {color: #135AB2;}'.
			'#memonic_badge p {padding: 0; margin: 0;}'.
			'#memonic_badge img {border: 0; padding: 0; margin: 0;}'.
			'#memonic_badge .clearfix {zoom: 1;}'.
			'#memonic_badge .clearfix:after {display: block; visibility: hidden; clear: both; height: 0; content: ".";}'.
			'#memonic_badge .gravatar {margin-right: 1em;}'.
			'#memonic_badge .right {float: right;}'.
			'#memonic_badge_header {background: #e8eef7; padding: 5px;}'.
			'#memonic_badge_header h2 {padding: 0; margin: 0; font-size: 138.5%;}'.
			'#memonic_badge_profile {float: left; padding: 0; margin: 0;}'.
			'#memonic_badge_items {clear: both; padding: 5px 5px 5px 10px;}'.
			'#memonic_badge_items ol {margin: 0; padding: 0; overflow: hidden;}'.
			'#memonic_badge_items li {list-style: none; border-bottom: 1px dotted #999; padding: 0.6154em; margin: 0;}'.
			'#memonic_badge_items h3 {float: left; margin: 0; padding: 0; font-size: 123.1%;}'.
			'#memonic_badge_items .date {margin-right: 5px;}'.
			'#memonic_badge_items a.url {color: #036;}'.
			'#memonic_badge_items .content {clear: both; margin-top: 2em;}'.
			'#memonic_badge_items .additional {margin-top: 0.5385em;}'.
			'#memonic_badge_items li img {float: left; margin-right: 0.7692em;}'.
			'#memonic_badge_footer {clear: both; background: #e8eef7; height: 40px; padding: 5px;}'.
			'#memonic_badge_logo {padding-left: 15px; line-height: 60px;}';
		return $memOptions;
	}
	
	public function option_page_memonic () {
		//Page Presentation
		echo '
		<div class="wrap">
			<h2>' . __('Memonic Settings', 'memonic') . '</h2>
			<form method="post" action="options.php">';
		settings_fields('memonic');
		do_settings_sections('memonic');
		echo '
			<p class="submit">
					<input type="submit" name="Options" id="submit" class="button-primary" value="'. __('Update Options', 'memonic') . '">
				</p>
			</form>
		</div>
		';
	}

	public function admin_menu() {
		if (function_exists('add_options_page')) {
			add_options_page(__('Memonic Settings', 'memonic'), 'Memonic', 5, 'memonic/' . basename(__FILE__), array('memonic', 'option_page_memonic'));
			/* register options for plugin */
			add_action('admin_init', array(&$this, 'admin_settings_init'));
		}
	}
	
	public function admin_settings_init() {
		register_setting('memonic', 'memonic', array(&$this, 'admin_settings_validate'));
		add_settings_section('memonic_main', __('General', 'memonic'), array(&$this, 'admin_main_text'), 'memonic');
		add_settings_field('lang', __('Language', 'memonic'), array(&$this, 'admin_option_lang'), 'memonic', 'memonic_main');
		add_settings_field('show_collection', __('Memonic Collection', 'memonic'), array(&$this, 'admin_option_show_collection'), 'memonic', 'memonic_main');
		add_settings_section('memonic_btn', __('Article Button', 'memonic'), array(&$this, 'admin_btn_text'), 'memonic');
		add_settings_field('post_btn', __('Memonic Clip Button', 'memonic'), array(&$this, 'admin_option_post_btn'), 'memonic', 'memonic_btn');
		add_settings_field('post_btn_pos', __('Position', 'memonic'), array(&$this, 'admin_option_post_btn_pos'), 'memonic', 'memonic_btn');
		add_settings_field('post_btn_img', __('Button image', 'memonic'), array(&$this, 'admin_option_post_btn_img'), 'memonic', 'memonic_btn');
		add_settings_section('memonic_badge', __('Widget Settings', 'memonic'), array(&$this, 'admin_badge_text'), 'memonic');
		add_settings_field('badge_css', __('Badge Formatting (CSS)', 'memonic'), array(&$this, 'admin_option_badge_css'), 'memonic', 'memonic_badge');
	}

	public function admin_main_text() {
		_e('General settings to integrate the Memonic services', 'memonic');
	}

	public function admin_btn_text() {
		_e('Settings regarding the Memonic button for readers of the blog', 'memonic');
	}

	public function admin_badge_text() {
		_e('Badge settings to be used within the widget', 'memonic');
	}

	public function admin_option_lang() {
		$opt = get_option('memonic');
		echo '
			<select id="lang" name="memonic[lang]">
				<option ' . ( $opt['lang'] == 'de' ? 'selected="selected"' : '') . 'value="de">deutsch</option>
				<option ' . ( $opt['lang'] == 'en' ? 'selected="selected"' : '') . 'value="en">english</option>
				<option ' . ( $opt['lang'] == 'fr' ? 'selected="selected"' : '') . 'value="fr">français</option>
				<option ' . ( $opt['lang'] == 'it' ? 'selected="selected"' : '') . 'value="it">italiano</option>
				<option ' . ( $opt['lang'] == 'es' ? 'selected="selected"' : '') . 'value="es">espagñol</option>								
			</select>
		';
	}
	
	public function admin_option_show_collection() {
		$opt = get_option('memonic');
		echo '
			<input type="checkbox" id="show_collection" name="memonic[show_collection]" value="1" '.checked($opt['show_collection'], 1, false).' />'.__('Show Collection on Article/Page Edit', 'memonic').'
		';
	}

	public function admin_option_post_btn() {
		$opt = get_option('memonic');
		echo '
			<input type="checkbox" id="post_btn" name="memonic[post_btn]" value="1" '.checked($opt['post_btn'], 1, false).' />'.__('Show button on posts for readers to clip', 'memonic').'
		';
	}
	
	public function admin_option_post_btn_pos() {
		$opt = get_option('memonic');
		echo '
			<select id="post_btn_pos" name="memonic[post_btn_pos]">
				<option ' . ( $opt['post_btn_pos'] == 'none' ? 'selected="selected"' : '') . 'value="none">- ('.__('use shortcode', 'memonic').')</option>
				<option ' . ( $opt['post_btn_pos'] == 'top_left' ? 'selected="selected"' : '') . 'value="top_left">'.__('top left', 'memonic').'</option>
				<option ' . ( $opt['post_btn_pos'] == 'top_right' ? 'selected="selected"' : '') . 'value="top_right">'.__('top right', 'memonic').'</option>
				<option ' . ( $opt['post_btn_pos'] == 'bottom_left' ? 'selected="selected"' : '') . 'value="bottom_left">'.__('bottom left', 'memonic').'</option>
				<option ' . ( $opt['post_btn_pos'] == 'bottom_right' ? 'selected="selected"' : '') . 'value="bottom_right">'.__('bottom right', 'memonic').'</option>								
			</select>
		';
	}
	
	public function admin_option_post_btn_img() {
		$opt = get_option('memonic');
		if ($opt['lang'] == '') $opt['lang'] = 'en';
		echo '
			<input type="radio" name="memonic[post_btn_img]" value="clip_button_m" '.checked($opt['post_btn_img'], 'clip_button_m', false).' /> <img src="'. get_option('siteurl') .'/wp-content/plugins/memonic/img/clip_button_m.png" width="24" height="16" /><br />
			<input type="radio" name="memonic[post_btn_img]" value="clip_button_m_text_" '.checked($opt['post_btn_img'], 'clip_button_m_text_', false).' /> <img src="'. get_option('siteurl') .'/wp-content/plugins/memonic/img/clip_button_m_text_'.$opt['lang'].'.png" width="" height="" /><br />
			<input type="radio" name="memonic[post_btn_img]" value="clip_button_net" '.checked($opt['post_btn_img'], 'clip_button_net', false).' /> <img src="'. get_option('siteurl') .'/wp-content/plugins/memonic/img/clip_button_net.png" width="41" height="28" />
		';
	}
	
	public function admin_option_badge_css() {
		$opt = get_option('memonic');
		
		echo '
			<textarea id="badge_css" name="memonic[badge_css]" cols="60" rows="15">'.$opt['badge_css'].'</textarea>
		';
	}
	
	public function admin_settings_validate($input) {
		$sanitized['lang'] = (in_array($input['lang'], array('de', 'en', 'fr', 'it', 'es')) ? $input['lang'] : 'en');
		$sanitized['show_collection'] = (isset($input['show_collection']) ? 1 : 0);
		$sanitized['post_btn'] = (isset($input['post_btn']) ? 1 : 0);
		$sanitized['post_btn_pos'] = (in_array($input['post_btn_pos'], array('none', 'top_left', 'top_right', 'bottom_left', 'bottom_right')) ? $input['post_btn_pos'] : 'none');
		$sanitized['post_btn_img'] = trim($input['post_btn_img']);
		$sanitized['badge_css'] = $input['badge_css'];
		
		return $sanitized;
	}
	
	public function add_user_options($user) {
	    if(!($mem_user_username = get_user_meta($user->ID, 'mem_user_username', true))) $mem_user_username = '';
		$mem_username = htmlentities($mem_username);

	    if(!($mem_user_password = get_user_meta($user->ID, 'mem_user_password', true))) $mem_user_password = '';
		$mem_user_password = htmlentities($mem_user_password);
		
	?>
	<a name="memonic"></a>
	<h3><?php echo __('Memonic User Credentials', 'memonic'); ?></h3>
	<table class="form-table" summary="Credentials to access memonic for the current user.">
	    <tr>
	        <th><label for="mem_user_username"><?php echo __('Username', 'memonic'); ?></label></th>
	        <td>
	            <input type="text" name="mem_user_username" id="mem_user_username" value="<?php echo $mem_user_username;?>" class="regular-text" />
	            <span class="description"><?php echo __('Enter your Memonic user name or e-mail address', 'memonic'); ?></span>
	        </td>
	    </tr>
		<tr>
		    <th><label for="mem_user_password"><?php echo __('Password', 'memonic'); ?></label></th>
		    <td>
		        <input type="password" name="mem_user_password" id="mem_user_password" value="<?php echo $mem_user_password;?>" />
		        <span class="description"><?php echo __('Enter your Memonic password', 'memonic'); ?></span>
		    </td>
		</tr>
	</table>
	
	<?php
	}
	
	public function save_user_options($user_id) {
	    if (!current_user_can('edit_user', $user_id)) { return false; }
		
		$mem_username = trim($_POST['mem_user_username']);
		$mem_password = trim($_POST['mem_user_password']);
		
		if ($mem_username != '' && $mem_password != '') {
			$checkUser = new memonicAPI($mem_username, $mem_password);

			if (! $checkUser->userId = get_user_meta($user_id, 'mem_user_id')) {
				if (!$checkUser->getUser()) {
					echo '<div class="error">
						<p>'.__('Can\'t connect to Memonic service.', 'memonic').' ('.$cur->errorMsg.'). <a href="">'.__('Check your personal Memonic settings.', 'memonic').'</a></p>
					</div>';
					delete_user_meta($user_id, 'mem_user_password');
					return false;
				}
			}

		    update_user_meta($user_id, 'mem_user_username', $mem_username);
		    update_user_meta($user_id, 'mem_user_password', $mem_password);
			$mem_userid = (is_array($checkUser->userId) ? $checkUser->userId[0] : $checkUser->userId);
		    update_user_meta($user_id, 'mem_user_id', $mem_userid);
			delete_user_meta($user_id, 'mem_user_disabled');
		} else {
			delete_user_meta($user_id, 'mem_user_username');
			delete_user_meta($user_id, 'mem_user_password');
			delete_user_meta($user_id, 'mem_user_id');
		}
	
	}
	
	private function get_memonicBtn($side = 'right', $pid) {
		$memoOptions = get_option('memonic');
		if (substr($memoOptions['post_btn_img'], -1) == '_')
			$img = $memoOptions['post_btn_img'].$memoOptions['lang'].'.png';
		else
			$img = $memoOptions['post_btn_img'].'.png';
		
		$out = "\n" 
		. '<div class="memonicBtn" style="float:'.$side.'; position:relative;">'
		. "\t".'<img class="progress" style="position:absolute; display:none; top:50%; left:50%; margin-top:-9px; margin-left:-8px; z-index:10;" src="'.get_option('siteurl') . '/wp-content/plugins/memonic/img/saving_icon_animated.gif" />'."\n"
		. "\t".'<img data-pid="'.$pid.'" class="memonic_button" style="cursor: pointer;" src="'
		. get_option('siteurl') . '/wp-content/plugins/memonic/img/'.$img
		. '" alt="'.__('Clip to Memonic', 'memonic').'"/>'."\n"
		. '</div>';
		
		return $out;
	}
	
	public function attach_memonicBtn ($content = '') {
		global $post;
		$opt = get_option('memonic');
		$posDetail = explode('_', $opt['post_btn_pos']);
		$out = $this->get_memonicBtn($posDetail[1], $post->ID);

		switch ($posDetail[0]) {
			case 'top':
				$out = $out . $content;
				break;
			case 'bottom':
				$out = $content . $out;
				break;
		}
		return $out;
	}

	public function insert_memonicBtn () {
		add_filter( 'the_content', array(&$this, 'attach_memonicBtn'));
	}
	
	/**
	 * use "<?php do_action('memonic_btn', array('pos' => 'right')); ?>" 
	 */
	
	public function btn_shortcode($atts) {
		extract( shortcode_atts( array(
			'pos' => 'left',
		), $atts ) );
 		
		$out = $this->get_memonicBtn($pos);
		echo $content . $out;
	}

	public function updateFolderList($memonicAPIObj = NULL) {
		global $current_user;
		if ($memonicAPIObj === NULL) {
			$memonicAPIObj = new memonicAPI(get_user_option('mem_user_username'), get_user_option('mem_user_password'));
			$memonicAPIObj->userId = get_user_option('mem_user_id');
		}
		
		/* get list of folder either from cached values or from the API */
		if (false === ($folderList = get_transient('memonicFolders_'.get_user_meta($current_user->id, 'mem_user_id', true)))) {
			if (!$memonicAPIObj->getFolders()) {
				echo '<div class="error">
					<p>'.__('Can\'t get folders.', 'memonic').' ('.$memonicAPIObj->errorMsg.')</p>
				</div>';
				return array();
			} else {
				$folders = $memonicAPIObj->sets;
				
				$folderList = array();
				foreach($folders->sets as $k => $folder) {
					$folderList[$folder->id] = $folder->title;
				}
				set_transient('memonicFolders_'.get_user_meta($current_user->id, 'mem_user_id', true), $folderList, 6*60*60);
			}			
		}
		return $folderList;
	}
	
	/*
	 * 
	 * return array()
	 */
	public function updateGroupList($memonicObj = NULL) {
		global $current_user;
		
		if ($memonicObj == NULL) {
			$memonicObj = new memonicAPI(get_user_option('mem_user_username'), get_user_option('mem_user_password')); 
		}
		
		/* get list of groups either from cached values or from the API */
		if (false === ($groupList = get_transient('memonicGroups_'.get_user_meta($current_user->id, 'mem_user_id', true)))) {
			if (!$memonicObj->getGroups()) {
				echo '<div class="error">
					<p>'.__('Can\'t get groups.', 'memonic').' ('.$memonicObj->errorMsg.')</p>
				</div>';
				return array();
			} else {
				$groups = $memonicObj->groups;
				
				$groupList = array();
				foreach($groups->groups as $k => $group) {
					$groupList[$group->id] = $group->title;
				}
				set_transient('memonicGroups_'.get_user_meta($current_user->id, 'mem_user_id', true), $groupList, 6*60*60);
			}			
		}
		return $groupList;
	}
			
	public function feed_box() {
		global $current_user;
		
		if (get_user_meta($current_user->id, 'mem_user_id') && !get_user_meta($current_user->id, 'mem_user_disabled')) {
			/* add box for post editing */
			add_meta_box( 
			    'memonic_collection',
			    __('Memonic', 'memonic'),
			    array(&$this, 'feed_box_content'),
			    'post' 
			);
			/* add box for page editing */
			add_meta_box( 
			    'memonic_collection',
			    __('Memonic', 'memonic' ),
			    array(&$this, 'feed_box_content'),
			    'page' 
			);
		} elseif (!get_user_meta($current_user->id, 'mem_user_disabled')) {
			$msg = __('You didn\'t enter your Memonic credentials in your profile. Either {link}fill in your credentials{/link} or {link}disable Memonic{/link} for your account.', 'memonic');
			$link_profile = '<a href="profile.php#memonic"  title="'.__('Your Profile').'">$1</a>';
			$msg = preg_replace('/\{link\}(.*?)\{\/link\}/', $link_profile, $msg, 1);
			$disable_memonic = '<a href="#" id="memonic_user_disable" title="'.__('Disable Memonic for me', 'memonic').'">$1</a>';
			$msg = preg_replace('/\{link\}(.*?)\{\/link\}/', $disable_memonic, $msg, 1);
			echo '<div class="updated"><p>'.$msg.'</p></div>';
		}
	}
	
	public function feed_box_content($post) {
		global $current_user;

		$cur = new memonicAPI(get_user_option('mem_user_username'), get_user_option('mem_user_password'));
		
		if (! $cur->userId = get_user_meta($current_user->id, 'mem_user_id', true)) {
			if (!$cur->getUser()) {
				echo '<div class="error">
					<p>'.__('Can\'t connect to Memonic service.', 'memonic').' ('.$cur->errorMsg.')</p>
				</div>';
				return false;
			} else {
				update_user_meta($current_user->id, 'mem_user_id', $cur->user_Id);
			}
		}

		$folderList = $this->updateFolderList(&$cur);
		$curFolder = '__inbox__';
		
		$groupList = $this->updateGroupList(&$cur);	
		
		echo '<div id="memonic_bar">
			<input type="radio" value="folder" name="itemSource" id="listFolders" checked />
			<label for="memonicFolders">'.__('Folder:', 'memonic').'</label>
			<select id="memonicFolders" name="memonicFolders">
		';

		foreach ($folderList as $key => $folder) {
			echo '<option value="'.$key.'"'. ($key == $curFolder ? ' selected' : '') .'>'.$folder.'</option>'."\n";	
		}
		echo '</select>
			<input type="radio" value="group" name="itemSource" id="listGroups"'. ( sizeof($groupList) == 0 ? ' disabled' : '') .'/>
			<label for="memonicGroups">'.__('Group:', 'memonic').'</label>
			<select id="memonicGroups" name="memonicGroups" disabled>
		';

		foreach ($groupList as $key => $group) {
			echo '<option value="'.$key.'"'. ($group == $curGroup ? ' selected' : '') .'>'.$group.'</option>'."\n";	
		}
		
		echo '</select>
			<span class="right">
				<button id="memonic_refresh_list">'.__('Refresh', 'memonic').'</button>
			</span>
			<span class="right" style="padding-right: 10px;">
				<label for="sortlist">'.__('Sort by', 'memonic').'
				<select id="memonic_sort_list" name="sortlist">
					<option value="date_desc">'.__('date', 'memonic').' ⬇</option>
					<option value="date_asc">'.__('date', 'memonic').' ⬆</option>
					<option value="title_desc">'.__('title', 'memonic').' ⬇</option>
					<option value="title_asc">'.__('title', 'memonic').' ⬆</option>
				</select>
			</span>
		</div>
		<div id="memonic_list">
		</div>';
	}

	public function notes_list() {
		global $current_user;
        // check request nonce and permissions
        $nonce = $_POST['postEditNonce'];

        if (!wp_verify_nonce($nonce, 'memonic-nonce'))
                die('Unauthorized Request');
		
		$memonic = new memonicAPI(get_user_option('mem_user_username'), get_user_option('mem_user_password'));
		$page = intval($_POST['page']);
		
		if (! $memonic->userId = get_user_meta($current_user->id, 'mem_user_id', true)) {
			if (!$memonic->getUser()) {
				die(__('Authentication failed.', 'memonic'));
			}
		}

		if (@$_POST['folder'] != '') {
			$sid = wp_kses_data($_POST['folder']);
			$src = 'f';
		}
		
		if (@$_POST['group'] != '') {
			$sid = wp_kses_data($_POST['group']);
			$src = 'g';
		}
		
		$updateList = false;
		
		if ($thisItems = get_transient('memNotes_'.$current_user->id.'_'.$sid)) {
			if ($thisItems->pagination->current_source != $sid) $updateList = 'folder';
			elseif ($thisItems->pagination->current_page != $page) $updateList = 'page';
		} else {
			$updateList = 'folder';
		}
		
		/* check if update forced */
		if (intval($_POST['update'])) $updateList = 'refresh'; 
			
		if ($updateList) {
			switch ($src) {
				case 'f':
					$memonic->getFolderItems($sid, $page, $this->pageSize, 'summary');
					break;
				case 'g':
					$memonic->getGroupItems($sid, $page, $this->pageSize, 'summary');
					break;
			}
			
			$thisItems = $memonic->items;
			$thisItems->pagination->current_source = $sid;
			$thisItems->pagination->current_page = $page;
			if ($updateList == 'page') {
				$allItems = get_transient('memNotes_'.$current_user->id.'_'.$sid);
				$allItems->items = array_merge($allItems->items, $thisItems->items);
				$allItems->pagination->current_page = $page;
				set_transient('memNotes_'.$current_user->id.'_'.$sid, $allItems, 5*60);
			} else
				set_transient('memNotes_'.$current_user->id.'_'.$sid, $thisItems, 5*60);
		}

		if ($thisItems->items) {
			foreach ($thisItems->items as $item) {
				if (!isset($item->data)) {
					$item->data->title = &$item->title;
					$item->data->source = &$item->source;
					$item->data->abstract = &$item->abstract;
				}
				echo '
				<div class="note summary" dndtype="note" data-nid="'.$item->id.'" data-nurl="'.$item->tinyurl.'" id="note_'.$item->id.'">
				    <h2>'.$item->data->title.'</h2>
				    <p class="metadata">
						<span class="privacy privacy-'.$item->permission.'">'.$item->permission.'</span> | 
						<span class="date" data-date="'.$item->modified.'">'.date_i18n(get_option('date_format'), strtotime($item->modified)).'</span> | 
				        <span class="source"><a href="'.$item->data->source.'">'.parse_url($item->data->source, PHP_URL_HOST).'</a></span>
				    </p>
				    <div class="abstract">
				        '.$item->data->abstract.'
				    </div>
				    <div class="insert-toolbar">
				    	<span>'.__('Insert', 'memonic').':</span>
						<button class="icon insertTitleLinkSource"'. ($item->data->source ? '' : ' disabled') .'>'.__('Title, linked to source', 'memonic').'</button>
						<button class="icon insertTitleLinkNote">'.__('Title, linked to note', 'memonic').'</button>
						<button class="icon insertAbstractQuote">'.__('Quote with abstract', 'memonic').'</button>
						<button class="icon insertFullQuote" disabled>'.__('Quote with full text', 'memonic').'</button>
					</div>
				</div>'."\n";
			}

			if ($thisItems->pagination->current_page < $thisItems->pagination->last) {
				echo '
				<div class="scroll_info">
					<div class="scroll_info_load">
						<a class="button bluebutton" id="memonic_moreNotes" data-nextpage="'. (intval($thisItems->pagination->current_page)+1) .'">'.__('weitere Notizen laden', 'memonic').'</a>
        			</div>
				</div>
				';
			}
		} else {
			echo '
			<div class="info">
				'.__('No notes found.', 'memonic').'
			</div>'."\n";
		}
		
		die();
	}
	
	public function note_details() {
		global $current_user;
        // check request nonce and permissions
        $nonce = $_POST['postEditNonce'];

        if (!wp_verify_nonce($nonce, 'memonic-nonce'))
                die('Unauthorized Request');
		
		$memonic = new memonicAPI(get_user_option('mem_user_username'), get_user_option('mem_user_password'));
		$iid = wp_kses_data($_POST['itemId']);

		if (! $memonic->userId = get_user_meta($current_user->id, 'mem_user_id', true)) {
			if (!$memonic->getUser()) {
				die(__('Authentication failed.', 'memonic'));
			}
		}
		
		$memonic->getNote($iid);
		$thisNote = $memonic->item;
		$content = @html_entity_decode( $thisNote->data->body, ENT_QUOTES, get_option( 'blog_charset' ));

		echo '
			<div class="data">
		        '.$content.'
		    </div>';
		
		die();
	}
	
	public function note_guestpass() {
		global $current_user;
        // check request nonce and permissions
        $nonce = $_POST['postEditNonce'];

        if (!wp_verify_nonce($nonce, 'memonic-nonce'))
                die('Unauthorized Request');
		
		$memonic = new memonicAPI(get_user_option('mem_user_username'), get_user_option('mem_user_password'));
		$iid = wp_kses_data($_POST['itemId']);

		if (! $memonic->userId = get_user_meta($current_user->id, 'mem_user_id', true)) {
			if (!$memonic->getUser()) {
				die(__('Authentication failed.', 'memonic'));
			}
		}
		
		$memonic->getGuestpass($iid);
		$thisPass = $memonic->guestpass;

		echo $thisPass->url;
		
		die();
	}

	public function user_disable() {
		global $current_user;
        // check request nonce and permissions
        $nonce = $_POST['postEditNonce'];

        if (!wp_verify_nonce($nonce, 'memonic-nonce'))
                die('Unauthorized Request');
		
		update_user_meta($current_user->id, 'mem_user_disabled', true);
		
		echo true;
		
		die();
	}
	
	public function get_post_attributes() {
        // check request nonce and permissions
        $nonce = $_POST['clipNonce'];

        if (!wp_verify_nonce($nonce, 'memonic-nonce'))
                die('Unauthorized Request');
		
		$pid = wp_kses_data($_POST['postId']);
		$postData = get_post($pid);
		
		echo json_encode(array('title' => $postData->post_title, 'content' => $postData->post_content, 'url' => get_permalink($pid)));
		
		die();
	}

}

/**
 * Memonic Widget Class
 */
class Memonic_Widget extends WP_Widget {
	/** constructor */
	function __construct() {
		parent::WP_Widget( /* Base ID */'memonic_widget', /* Name */'Memonic_Widget', array( 'description' => 'The Memonic Widget allows you to integrate a \'badge\' for a folder of your collection to be shown in the sidebar of your blog' ) );
	}

	/** @see WP_Widget::widget */
	function widget( $args, $instance ) {
		extract( $args );
		extract($instance);
		// get memonic options
		$memoOptions = get_settings('memonic');
		
		$title = apply_filters( 'Memonic Badge', $title );
		echo $before_widget;
		if ( $title )
			echo $before_title . $after_title;
		?>
		<!-- Start of Memonic Badge -->
		<style type="text/css">
			<?php echo $memoOptions['badge_css']; ?>
		</style>
		<div id="memonic_badge">
			<div id="memonic_badge_header" class="clearfix">
				<div id="memonic_badge_profile">
					<table cellpadding="0" cellspacing="0" border="0">
						<tr>
							<td rowspan="2" id="memonic_badge_gravatar">
								<a href="<?php echo $memonic_url; ?>/user/<?php $memonic_uuid; ?>" >
									&nbsp;
								</a>
							</td>
							<td>
								<h2 id="memonic_badge_title"><a href="<?php echo $memonic_url ?>/user/<?php echo $memonic_uuid; ?>/set/<?php echo $memonicFolder; ?>.html" ><?php echo $title; ?></a></h2>
							</td>
							</tr>
						<tr>
							<td>
								<p id="memonic_badge_user"><?php _e('Memonic Folder by', 'memonic'); ?> <a href="<?php echo $memonic_url; ?>/user/<?php echo $memonic_uuid; ?>/profile">&nbsp;</a></p>
							</td>
						</tr>
					</table>
				</div>
				<div id="memonic_badge_rss" class="right">
					<a href="<?php echo $memonic_url; ?>/user/<?php echo $memonicFolder; ?>/set/<?php echo $memonicFolders; ?>.atom" ><img src="http://s.memonic.ch/assets/badge/v3/img/icon-rss.png" alt="RSS Feed" /></a>
				</div>
			</div>
			<div id="memonic_badge_items">
				<script type="text/javascript" src="<?php echo $memonic_url; ?>/user/<?php echo $memonic_uuid; ?>/folder/<?php echo $memonicFolder; ?>.badge?count=<?php echo $memonicNotesCnt; ?>&lang=<?php $memoOptions['lang']; ?>&link=<?php echo $memonicLinkTo; ?>&mode=<?php echo $memonicMode; ?>&new_tab=<?php echo $memonicOpenIn; ?>&v=3&wide=True"></script>
			</div>
			<div id="memonic_badge_footer">
			<div id="memonic_badge_logo">
				<a href="<?php echo $memonic_url; ?>" class="home" ><img src="http://s.memonic.ch/assets/badge/v3/img/logo.png" alt="memonic" width="80" height="15" /></a>
			</div>
		</div>
	</div>
	<!-- End of Memonic Badge -->

		<?php
		echo $after_widget;
	}

	/** @see WP_Widget::update */
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['memonicFolder'] = strip_tags($new_instance['memonicFolder']);
		$instance['memonicMode'] = strip_tags($new_instance['memonicMode']);
		$instance['memonicNotesCnt'] = intval($new_instance['memonicNotesCnt']);
		$instance['memonicLinkTo'] = intval($new_instance['memonicLinkTo']);
		$instance['memonicOpenIn'] = intval($new_instance['memonicOpenIn']);
		$instance['memonic_uuid'] = strip_tags($new_instance['memonic_uuid']);
		$instance['memonic_url'] = strip_tags($new_instance['memonic_url']);
		return $instance;
	}

	/** @see WP_Widget::form */
	function form( $instance ) {
		global $current_user, $memonicObj;
		
		$defaults = array(
			'title' => __('Folder Name', 'memonic'),
			'memonicFolder' => '__inbox__',
			'memonicMode' => 'full',
			'memonicNotesCnt' => 5,
			'memonicLinkTo' => 0,
			'memonicOpenIn' => 0,
			'memonic_uuid' => get_user_meta($current_user->id, 'mem_user_id', true),
			'memonic_url' => $memonicObj->serverURL,
		);
		$instance = wp_parse_args( (array) $instance, $defaults );

		if (!$folderList = get_transient('memonicFolders_'.get_user_meta($current_user->id, 'mem_user_id', true))) {
			$folderList = $memonicObj->updateFolderList();
		}

		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $instance['title']; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('memonicFolder'); ?>"><?php _e('Folder:', 'memonic'); ?></label> 
			<select id="<?php echo $this->get_field_id('memonicFolder'); ?>" name="<?php echo $this->get_field_name('memonicFolder'); ?>" class="widefat">
		<?php
		foreach ($folderList as $key => $folder) {
			echo '<option value="'.$key.'"'. ($key == $instance['memonicFolder'] ? ' selected' : '') .'>'.$folder.'</option>'."\n";	
		}
		?>	
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('memonicMode'); ?>"><?php _e('Mode:', 'memonic'); ?></label><br />
			<input class="" id="<?php echo $this->get_field_id('memonicMode'); ?>" name="<?php echo $this->get_field_name('memonicMode'); ?>" type="radio" value="full"<?php checked( $instance['memonicMode'], 'full' ); ?> /> <?php _e('List', 'memonic') ?>
			<input class="" id="<?php echo $this->get_field_id('memonicMode'); ?>" name="<?php echo $this->get_field_name('memonicMode'); ?>" type="radio" value="compact"<?php checked( $instance['memonicMode'], 'compact' ); ?> /> <?php _e('One liner', 'memonic') ?>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('memonicNotesCnt'); ?>"><?php _e('# of notes:', 'memonic'); ?></label> 
			<input id="<?php echo $this->get_field_id('memonicNotesCnt'); ?>" name="<?php echo $this->get_field_name('memonicNotesCnt'); ?>" type="text" value="<?php echo $instance['memonicNotesCnt']; ?>" size="3" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('memonicLinkTo'); ?>"><?php _e('Links:', 'memonic'); ?></label><br />
			<input class="" id="<?php echo $this->get_field_id('memonicLinkTo'); ?>" name="<?php echo $this->get_field_name('memonicLinkTo'); ?>" type="checkbox" value="1"<?php checked( $instance['memonicLinkTo'], '1' ); ?> /> <?php _e('Link title to original source', 'memonic') ?><br />
			<input class="" id="<?php echo $this->get_field_id('memonicOpenIn'); ?>" name="<?php echo $this->get_field_name('memonicOpenIn'); ?>" type="checkbox" value="1"<?php checked( $instance['memonicOpenIn'], '1' ); ?> /> <?php _e('Open link in new window', 'memonic') ?>
		</p>
		<input type="hidden" id="<?php echo $this->get_field_id('memonic_uuid'); ?>" name="<?php echo $this->get_field_name('memonic_uuid'); ?>" value="<?php echo $instance['memonic_uuid']; ?>" />
		<input type="hidden" id="<?php echo $this->get_field_id('memonic_url'); ?>" name="<?php echo $this->get_field_name('memonic_url'); ?>" value="<?php echo $instance['memonic_url']; ?>" />
  		<?php 
	}

}

$memonicObj = new memonic;

?>
