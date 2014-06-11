<?php
/**
 * Plugin Name: Tekserve Shared Data
 * Plugin URI: https://github.com/bangerkuwranger
 * Description: Stores global data that is reused over all Tekserve web assets
 * Version: 1.2
 * Author: Chad A. Carino
 * Author URI: http://www.chadacarino.com
 * License: MIT
 */
/*
The MIT License (MIT)
Copyright (c) 2014 Chad A. Carino
 
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

//version of db
$tsd_db_version = '1.1';
$installed_ver = get_option( "tsd_db_version" );

//define external file url for local master 
global $masterurl;
global $masterfile;

//dev constants in lieu of final function.
$masterfile = 'current_settings.json';
$masterurl = plugins_url('tsdmaster/'.$masterfile, __FILE__);
$mdfiveurl = get_site_url().'/wp-admin/options-general.php';

//create tables
function tekserve_shared_data_install() {
	global $tsd_db_version;
	global $wpdb;
	if( $installed_ver != $tsd_db_version ) {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		//create table for tekserve department directory
		$directory_table_name = $wpdb->prefix . "tekserve_shared_data_directory";
		$directory_sql = "CREATE TABLE ".$directory_table_name ."(
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			name tinytext NOT NULL,
			html text NOT NULL,
			active boolean DEFAULT false NOT NULL,
			UNIQUE KEY id (id)
		);";
		dbDelta( $directory_sql );
	
		//create table for tekserve store hours
		$hours_table_name = $wpdb->prefix . "tekserve_shared_data_hours";
		$hours_sql = "CREATE TABLE ".$hours_table_name." (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			name tinytext NOT NULL,
			html text NOT NULL,
			imgurl VARCHAR(255) DEFAULT '#' NOT NULL,
			mobileimgurl VARCHAR(255) DEFAULT '#' NOT NULL,
			active boolean DEFAULT false NOT NULL,
			UNIQUE KEY id (id)
		);";
		dbDelta( $hours_sql );
	
		//version db for later upgrades
		update_option( "tsd_db_version", $tsd_db_version );
	}
}

//check db version on plugins_loaded to insure correct db structure after autoupdate, runs tsdinstall if necessary
function tsd_update_db_check() {
    global $tsd_db_version;
    if (get_site_option( 'tsd_db_version' ) != $tsd_db_version) {
        tekserve_shared_data_install();
    }
}
add_action( 'plugins_loaded', 'tsd_update_db_check' );

//creates the tables on initial install
register_activation_hook( __FILE__, 'tekserve_shared_data_install' );

function tekserve_shared_data_settings_api_init() {

	//create settings section
	add_settings_section( 'tekserve_shared_data_settings_section', 'Tekserve Shared Data', 'tekserve_shared_data_setting_section_callback', 'general');

	//create setting options field for site master
	add_settings_field( 'tekserve_shared_data_status_setting', 'Shared Data Master', 'tekserve_shared_data_status_setting_callback', 'general', 'tekserve_shared_data_settings_section' );

	//register site master setting
	register_setting( 'general', 'tekserve_shared_data_status_setting' );

	//create setting options field for site master url
	add_settings_field( 'tekserve_shared_data_master_url', 'Shared Data Master URL', 'tekserve_shared_data_master_url_callback', 'general', 'tekserve_shared_data_settings_section' );

	//register site master url setting
	register_setting( 'general', 'tekserve_shared_data_master_url' );
	
	//create setting options field for site master key
	add_settings_field( 'tekserve_shared_data_master_key', 'Shared Data Master Key', 'tekserve_shared_data_master_key_callback', 'general', 'tekserve_shared_data_settings_section' );

	//register site master key setting
	register_setting( 'general', 'tekserve_shared_data_master_key' );
	
	//create setting options field for site master keygen
	add_settings_field( 'tekserve_shared_data_master_keygen', 'Shared Data Master Keygen', 'tekserve_shared_data_master_keygen_callback', 'general', 'tekserve_shared_data_settings_section' );

	//register site master key setting
	register_setting( 'general', 'tekserve_shared_data_master_keygen' );
	
	tekserve_shared_data_readwrite();
	
}

//schedule read/write
add_action( 'wp_loaded', 'tekserve_shared_data_setup_schedule' );

//On an early action hook, check if the hook is scheduled - if not, schedule it.
function tekserve_shared_data_setup_schedule() {
	if ( ! wp_next_scheduled( 'tekserve_shared_data_readwrite' ) ) {
		wp_schedule_event( time(), 'hourly', 'tekserve_shared_data_readwrite');
	}
}

//read or generate master file
function tekserve_shared_data_readwrite(){
	if ( get_option( 'tekserve_shared_data_status_setting' ) ) {		
		write_selected_data();
	} else {
		write_client_data();
	}
}

//generate settings menu in admin
add_action( 'admin_init', 'tekserve_shared_data_settings_api_init' );

//create top level options menus in admin
add_action( 'admin_menu', 'tekserve_shared_data_menu' );

//generates the menu for the data iteslf
function tekserve_shared_data_menu() {
	add_menu_page( 'Tekserve Shared Data', 'Tekserve Shared Data', 'edit_published_pages', 'tekserve_shared_data_menu', 'tekserve_shared_data_menu_page', 'dashicons-networking', '8.212' );
	add_submenu_page( 'tekserve_shared_data_menu', 'Tekserve Shared Data', 'Tekserve Shared Data', 'edit_published_pages', 'tekserve_shared_data_menu', 'tekserve_shared_data_menu_page');
	if ( get_option( 'tekserve_shared_data_status_setting' ) ) {
		add_submenu_page( 'tekserve_shared_data_menu', 'Tekserve Shared Data - New Hours', 'New Hours', 'edit_published_pages', 'tekserve_shared_data_create_new_hours', 'tekserve_shared_data_create_new_hours');
		add_submenu_page( 'tekserve_shared_data_menu', 'Tekserve Shared Data - Store Hours', 'Set Store Hours', 'edit_published_pages', 'tekserve_shared_data_hours_menu_page', 'tekserve_shared_data_hours_menu_page');
		add_submenu_page( 'tekserve_shared_data_menu', 'Tekserve Shared Data - Department Directory', 'Edit Directory', 'edit_published_pages', 'tekserve_shared_data_directory_menu_page', 'tekserve_shared_data_directory_menu_page');
	}
}
//generates html for the top of the options setting page
function tekserve_shared_data_setting_section_callback() {
	echo '<p>Select whether this instance is the master or client install. If this is the master site, URL will be generated below to enter into the settings of a client instance. On the first install, a master key must also be generated by selecting the checkbox to generate a new master key before clients can be added. If this is a client install, type the URL and key for the master here.</p>';
}

//generates html for the site master setting
function tekserve_shared_data_status_setting_callback() {
	echo '<input name="tekserve_shared_data_status_setting" id="tekserve_shared_data_status_setting" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'tekserve_shared_data_status_setting' ), false ) . ' /><label for="tekserve_shared_data_status_setting">If selected, this site will act as the master, and store all shared data between related sites. A change here will be reflected on all other sites with this plugin.</label>';
}

//generates html for the site url setting
function tekserve_shared_data_master_url_callback() {
	if ( get_option( 'tekserve_shared_data_status_setting' ) ) {
		global $masterurl;
		update_option( 'tekserve_shared_data_master_url', $masterurl );
		echo "<h3>Current Status: <span style='color: #f36f37'>MASTER</span></h3>";
		echo "<p><strong>Master URL: </strong><input size='90' name='tekserve_shared_data_master_url' id='tekserve_shared_data_master_url' type='url' value='" . get_option( 'tekserve_shared_data_master_url' ) . "' readonly /></p>";
	} else {
		echo "<h3>Current Status: <span style='color: #f36f37'>CLIENT</span></h3>";
		echo "<p><strong>Enter Master URL: </strong><input size='90' name='tekserve_shared_data_master_url' id='tekserve_shared_data_master_url' type='url' value='" . get_option( 'tekserve_shared_data_master_url' ) . "' /></p>";
	}
}

//generates html for the site key setting
function tekserve_shared_data_master_key_callback() {
	if ( get_option( 'tekserve_shared_data_status_setting' ) ) {
		echo "<p><strong>Master KEY: </strong><input size='90' name='tekserve_shared_data_master_key' id='tekserve_shared_data_master_key' type='text' value='" . get_option( 'tekserve_shared_data_master_key' ) . "' readonly /></p>";
	} else {
		echo "<p><strong>Enter Master KEY: </strong><input size='90' name='tekserve_shared_data_master_key' id='tekserve_shared_data_master_key' type='text' value='" . get_option( 'tekserve_shared_data_master_key' ) . "' /></p>";
	}
}

//generates html for the site key regen
function tekserve_shared_data_master_keygen_callback() {
	if ( get_option( 'tekserve_shared_data_status_setting' ) ) {
		echo "<p><input type='checkbox' name='tekserve_shared_data_master_keygen' id='tekserve_shared_data_master_keygen'/>   Generate New Key? (note: this will disconnect all client sites from master until the key is updated on the client.)";
		if ( get_option( 'tekserve_shared_data_master_keygen' ) == 'on' ) {
			update_option( 'tekserve_shared_data_master_keygen', '' );
			$newkey = generate_hash();
			echo "<p><b>New Key Generated: <span id='tekserve-shared-data-newkey'>" . $newkey . "</span></b></p>";
			echo "<script type='text/javascript'>jQuery( document ).ready(function() {jQuery('#tekserve_shared_data_master_key').val('".$newkey."');});</script>";
		}
	}
}

//generates html for the main data page
function tekserve_shared_data_menu_page() {
	global $wpdb;
	$wpdb->tekserve_shared_data_hours = $wpdb->prefix . 'tekserve_shared_data_hours';
	$current_hours = $wpdb->get_row("SELECT * FROM $wpdb->tekserve_shared_data_hours WHERE active = true");
	$wpdb->tekserve_shared_data_directory = $wpdb->prefix . 'tekserve_shared_data_directory';
	$tekserve_shared_data_current_directory = $wpdb->get_row("SELECT * FROM $wpdb->tekserve_shared_data_directory WHERE active = true");
	echo "<h2>Tekserve Shared Data</h2>";
	if ( get_option( 'tekserve_shared_data_status_setting' ) ) {
		echo "<h3>Current Status: <span style='color: #f36f37'>MASTER</span></h3>";
	} else {
		echo "<h3>Current Status: <span style='color: #f36f37'>CLIENT</span></h3>";
	}
	echo "<div id='tekserve-current-hours'><h2><br/>Current Store Hours:</h2>";
	if ($current_hours){
		echo "<div id='tekserve-current-hours-name'><h3>Name: ".$current_hours->name."</h3></div>";
		echo "<div id='tekserve-current-hours-html' style='background-color: #fff; padding 1em;'>".$current_hours->html."</div>";
		echo "<div id='tekserve-current-hours-image' style='background-color: #004d72; max-width: 950; padding: 1em;'><img src='".$current_hours->imgurl."' /></div>";
		echo "<div id='tekserve-current-hours-image' style='background-color: #004d72; max-width: 950; padding: 1em;'><img src='".$current_hours->mobileimgurl."' /></div>";
	} else {
		echo "<h1 style='color: #f36f37;'>ERROR: No Hours Currently Selected.</h1>";
	}
	echo "</div>";
	echo "<div id='tekserve-current-directory'><h2><br/>Current Store Directory:</h2>";
	if ($tekserve_shared_data_current_directory){
		echo "<div id='tekserve-current-directory-name'><h3>Name: ".$tekserve_shared_data_current_directory->name."</h3></div>";
		echo "<div id='tekserve-current-directory-html' style='background-color: #fff; padding 1em;'>".$tekserve_shared_data_current_directory->html."</div>";
	} else {
		echo "<h1 style='color: #f36f37;'>ERROR: No Directory Currently Selected.</h1>";
	}
	echo "</div>";
	tekserve_shared_data_readwrite();
}

//generates html for the create hours data page
function tekserve_shared_data_create_new_hours() {
	add_action('post_edit_form_tag', 'tekserve_shared_data_add_edit_form_multipart_encoding');
	global $wpdb;
	$wpdb->tekserve_shared_data_hours = $wpdb->prefix . 'tekserve_shared_data_hours';
	$wpdbhrstable = $wpdb->prefix . 'tekserve_shared_data_hours';
	$settings = array( 
		'textarea_rows' => 4
	);
	//create new store hours record with html, name, and file passed to it
	if ( $_POST['submit'] && ! empty( $_POST ) && check_admin_referer( 'createnewhours', 'nhrsuptsd' ) ) {
		$newhrsname = esc_sql( $_POST['new_store_hours_name'] );
		$newhrstime = current_time( 'mysql' );
		$newhrshtml = $_POST['new_store_hours'];
		$newhrsimg  = $_POST['tekserve_shared_data_create_new_hours_img'];
		$newhrshtml = wp_kses( $newhrshtml, array(
			'table' => array(
				'class' => array(),
				'data-sort' => array(),
				'data-page' => array()
			),
			'thead' => array(),
			'tr' => array(),
			'th' => array(
				'data-hide' => array()
			),
			'td' => array(
				'style' => array(),
				'colspan' => array()
			),
			'tbody' => array(),
			'a' => array(
				'href' => array(),
				'title' => array()
			),
			'br' => array(),
			'em' => array(),
			'strong' => array()
			)
		);
		// HANDLE THE FILE UPLOAD
		// If the upload field has a file in it
		if(isset($_FILES['tekserve_shared_data_create_new_hours_img']) && ($_FILES['tekserve_shared_data_create_new_hours_img']['size'] > 0) && isset($_FILES['tekserve_shared_data_create_new_hours_mobile_img']) && ($_FILES['tekserve_shared_data_create_new_hours_mobile_img']['size'] > 0)) {
			// Get the type of the uploaded file. This is returned as "type/extension"
			$arr_file_type = wp_check_filetype(basename($_FILES['tekserve_shared_data_create_new_hours_img']['name']));
			$arr_m_file_type = wp_check_filetype(basename($_FILES['tekserve_shared_data_create_new_hours_mobile_img']['name']));
			$uploaded_file_type = $arr_file_type['type'];
			$uploaded_m_file_type = $arr_m_file_type['type'];
			// Set an array containing a list of acceptable formats
			$allowed_file_types = array('image/jpg','image/jpeg','image/gif','image/png','image/svg', 'image/svg+xml');
			// If the uploaded file is the right format
			if(in_array($uploaded_file_type, $allowed_file_types) && in_array($uploaded_m_file_type, $allowed_file_types)) {
				// Options array for the wp_handle_upload function. 'test_upload' => false
				$upload_overrides = array( 'test_form' => false ); 
				// Handle the upload using WP's wp_handle_upload function. Takes the posted file and an options array
				$uploaded_file = wp_handle_upload($_FILES['tekserve_shared_data_create_new_hours_img'], $upload_overrides);
				$uploaded_m_file = wp_handle_upload($_FILES['tekserve_shared_data_create_new_hours_mobile_img'], $upload_overrides);
				// If the wp_handle_upload call returned a local path for the image
				if(isset($uploaded_file['file']) && isset($uploaded_m_file['file'])) {
					// The wp_insert_attachment function needs the literal system path, which was passed back from wp_handle_upload
					$newhrsurl = $uploaded_file['url'];
					$newhrsmurl = $uploaded_m_file['url'];
					// Set the feedback flag to false, since the upload was successful
					$upload_feedback = 'Yep, Saved:';
					$wpdbnewhrs = array(
						'time' => $newhrstime,
						'name' => $newhrsname,
						'html' => $newhrshtml,
						'imgurl' => $newhrsurl,
						'mobileimgurl' => $newhrsmurl,
						'active' => 0
					);
					$wpdb->insert( $wpdbhrstable, $wpdbnewhrs );
				} else { // wp_handle_upload returned some kind of error. the return does contain error details, so you can use it here if you want.
					$upload_feedback = 'There was a problem with your upload.';
				}
			} else { // wrong file type
				$upload_feedback = 'Please upload only image files (jpg, gif, svg, or png).';
			}
		} else { // No file was passed
			$upload_feedback = 'Please select an image to upload.';
		}
// 		echo "debug: <br/>".$arr_file_type." - ".$arr_m_file_type."<br/>";
// 		echo $uploaded_file_type." - ".$uploaded_m_file_type."<br/>";
// 		echo $allowed_file_types."<br/>";
// 		echo $uploaded_file_type." - ".$uploaded_m_file_type."<br/>";
		echo $upload_feedback."<br/>";
		echo $$wpdbnewhrs;
		echo $newhrsname."<br/>".$newhrstime."<br/>".$newhrshtml."<br/><img src='".$newhrsurl."' /><br/>".$_POST['tekserve_shared_data_create_new_hours_img']."<br/><img src='".$newhrsmurl."' /><br/>".$_POST['tekserve_shared_data_create_new_hours_mobile_img'];
	} else {
		echo "<h2>Create New Store Hours:</h2>";
		echo '<form enctype="multipart/form-data" id="create_new_hours" method="POST" action="">';
		wp_nonce_field( 'createnewhours', 'nhrsuptsd' );
		echo '<div class="new_hours_form"><div class="new_hours_form_name"><label for="new_store_hours_name">Enter Unique name for these settings: </label><input type="text" name="new_store_hours_name" id="new_store_hours_name" /></div>';
		echo '<div class="new_hours_form_image"><label for="tekserve_shared_data_create_new_hours_img">Upload a new header image: </label><input type="file" name="tekserve_shared_data_create_new_hours_img" id="tekserve_shared_data_create_new_hours_img" /></div>';
		echo '<div class="new_hours_form_mobile_image"><label for="tekserve_shared_data_create_new_hours_mobile_img">Upload a new mobile header image: </label><input type="file" name="tekserve_shared_data_create_new_hours_mobile_img" id="tekserve_shared_data_create_new_hours_mobile_img" /></div>';
		echo '<input type="hidden" name="tekserve_shared_data_create_new_hours_manual_save_flag" value="true" />';
		echo '<div class="new_hours_form_html"><label for="new_store_hours">Enter new hours info: </label>';
		wp_editor( '', 'new_store_hours', $settings );
		echo "</div>";
		submit_button( 'Create New Hours' );
		echo '</div></form>';
	}
}

//generates html for the hours data page
function tekserve_shared_data_hours_menu_page() {
	global $wpdb;
	$wpdb->tekserve_shared_data_hours = $wpdb->prefix . 'tekserve_shared_data_hours';
	$wpdbhrstable = $wpdb->prefix . 'tekserve_shared_data_hours';
	if ( $_POST['submit'] && ! empty( $_POST ) && check_admin_referer( 'selecthours', 'shrsuptsd' ) ) {
		$notactive = array( 'active' => 0 );
		$active = array( 'active' => 1 );
		$selectedhrs = $_POST['selecthrs'];
		$selected = array ( 'id' => $selectedhrs );
		$selectedq = "SELECT * FROM $wpdb->tekserve_shared_data_hours WHERE id = '" . $selectedhrs . "'";
		$wpdb->update( $wpdbhrstable, $notactive, $active );
		$wpdb->update( $wpdbhrstable, $active, $selected );
		$selectedout = $wpdb->get_row( $selectedq, ARRAY_A );
		echo "<h1 id='success-saved-hours'>YEP, SAVED:</h1>";
		echo "<div  style='display: inline;' class='selecthrs_option_title'><h2 style='display: inline;'>  ".$selectedout['id']." - ".$selectedout['name']."</h2></div><div class='selecthrs_option_html'><h3>HTML</h3>".$selectedout['html']."</div><div class='selecthrs_option_img'><h3>Image</h3><div style='background-color: #004d72;'><img src='".$selectedout['imgurl']."' /></div></div><div class='selecthrs_option_mobile_img'><h3>Mobile Image</h3><div style='background-color: #004d72;'><img src='".$selectedout['mobileimgurl']."' /></div></div></div>";    // outputs the saved value		
		tekserve_shared_data_readwrite();
	} else {
		echo "<h2>Select Store Hours:</h2>";
		$getallhours = 'SELECT * FROM '.$wpdbhrstable;
		$allhours = $wpdb->get_results( $getallhours, ARRAY_A );
		if ($allhours) {
			echo '<form id="select_new_hours" method="POST" action="">';
			wp_nonce_field( 'selecthours', 'shrsuptsd' );
			foreach( $allhours as $row ) {
				echo "<div class='selecthrs_option' style='padding: 1em; border: 1px solid #40a8c9; margin: 1em auto; max-width: 960px;'><input type='radio' name='selecthrs' value=".$row['id']."' ".checked( $row['active'], 1, false )." />";
				echo "<label for='".$row['id']."'><div  style='display: inline;' class='selecthrs_option_title'><h2 style='display: inline;'>  ".$row['id']." - ".$row['name']."</h2></div><div class='selecthrs_option_html'><h3>HTML</h3>".$row['html']."</div><div class='selecthrs_option_img'><h3>Image</h3><div style='background-color: #004d72;'><img src='".$row['imgurl']."' /></div></div><div class='selecthrs_option_mobile_img'><h3>Mobile Image</h3><div style='background-color: #004d72;'><img src='".$row['mobileimgurl']."' /></div></div></div></label>";    // outputs the value
			}
			submit_button( 'Use These Hours' );
			echo "</form>";
		} else {
			echo "<h1 style='color: #f36f37;'>ERROR: No Hours Found.</h1>";
		}
	}
}

//generates html for the directory data page
function tekserve_shared_data_directory_menu_page() {
	global $wpdb;
	$wpdb->tekserve_shared_data_directory = $wpdb->prefix . 'tekserve_shared_data_directory';
	$tekserve_shared_data_current_directory = $wpdb->get_row("SELECT * FROM $wpdb->tekserve_shared_data_directory WHERE active = true");
	//update directory html with html passed to it
	if ( $_POST['submit'] && ! empty( $_POST ) && check_admin_referer( 'updatedirectory', 'diruptsd' ) ) {
		$newhtml = $_POST['departmentdirectory'];
	
		global $wpdb;
		$wpdb->tekserve_shared_data_directory = $wpdb->prefix . 'tekserve_shared_data_directory';
		$newhtml = wp_kses( $newhtml, array(
			'table' => array(
				'class' => array(),
				'data-sort' => array(),
				'data-page' => array()
			),
			'thead' => array(),
			'tr' => array(),
			'th' => array(
				'data-hide' => array()
			),
			'td' => array(
				'style' => array(),
				'colspan' => array()
			),
			'tbody' => array(),
			'a' => array(
				'href' => array(),
				'title' => array(),
				'id'	=> array()
			),
			'br' => array(),
			'em' => array(),
			'strong' => array()
			)
		);
		$newdirectory = array( 'html' => $newhtml, 'time' => current_time( 'mysql' ) );
		$wpdb->update( 'wp_tekserve_shared_data_directory', $newdirectory , array( 'active' => true ) );
		$tekserve_shared_data_current_directory = $wpdb->get_row("SELECT * FROM $wpdb->tekserve_shared_data_directory WHERE active = true");
		echo "<h1 id='success-saved-directory'>YEP, SAVED:</h1>";
		echo "<div style='background-color: #fff; padding 1em;'>".$newhtml."</div>";
		tekserve_shared_data_readwrite();
	} else {
		$current_dir_html = $tekserve_shared_data_current_directory->html;
		$settings = array('wpautop' => true, 'media_buttons' => true, 'quicktags' => true, 'textarea_rows' => '25');
		echo "<h2>Edit the Current Store Directory:</h2>";
		echo '<form id="update_directory" method="POST" action="">';
		wp_nonce_field( 'updatedirectory', 'diruptsd' );
		wp_editor( $current_dir_html, 'departmentdirectory', $settings );
		submit_button( 'Submit Changes' );
		echo '</form>';
	}
	
}

//shortcode for tekserve department directory
add_shortcode( 'departmentdirectory', 'department_directory' );
function department_directory($atts) {
	global $wpdb;
	$wpdb->tekserve_shared_data_directory = $wpdb->prefix . 'tekserve_shared_data_directory';
	$tekserve_shared_data_current_directory = $wpdb->get_row("SELECT * FROM $wpdb->tekserve_shared_data_directory WHERE active = true");
	return "<div class='department_directory'>".$tekserve_shared_data_current_directory->html."</div>";
}

//shortcode for tekserve store hours
add_shortcode( 'storehours', 'store_hours' );
function store_hours($atts) {
	global $wpdb;
	$wpdb->tekserve_shared_data_hours = $wpdb->prefix . 'tekserve_shared_data_hours';
	$current_hours = $wpdb->get_row("SELECT * FROM $wpdb->tekserve_shared_data_hours WHERE active = true");
	return "<div class='store_hours'>".$current_hours->html."</div>";
}

//function to write json settings file from master
function write_selected_data() {
	global $wpdb;
	$wpdb->tekserve_shared_data_directory = $wpdb->prefix . 'tekserve_shared_data_directory';
	$tekserve_shared_data_current_directory = $wpdb->get_row( "SELECT * FROM $wpdb->tekserve_shared_data_directory WHERE active = true" );
	$wpdb->tekserve_shared_data_hours = $wpdb->prefix . 'tekserve_shared_data_hours';
	$current_hours = $wpdb->get_row( "SELECT * FROM $wpdb->tekserve_shared_data_hours WHERE active = true" );
	$key_array = str_split( get_option( 'tekserve_shared_data_master_key' ), 4 );
	$d_name = $key_array[0].base64_encode($tekserve_shared_data_current_directory->name);
	$d_time = $key_array[2].base64_encode($tekserve_shared_data_current_directory->time);
	$d_html = $key_array[3].base64_encode($tekserve_shared_data_current_directory->html);
	$cdir = array(
		$d_name,
		$d_time,
		$d_html
	);
	$response[] = array( $current_hours, $cdir );
	$fp = fopen( dirname(__FILE__).'/tsdmaster/current_settings.json', 'w')
		or die("Error opening output file");
	fwrite($fp, json_encode($response));
	fclose($fp);
}

//function to read json settings file
function read_selected_data() {
	global $wpdb;
	$tekserve_shared_data_directory_table = $wpdb->prefix . 'tekserve_shared_data_directory';
	$tekserve_shared_data_hours_table = $wpdb->prefix . 'tekserve_shared_data_hours';
	$new_data = file_get_contents ( dirname(__FILE__).'/tsdmaster/current_settings.json' );
	$new_data = json_decode( $new_data, true );
	$key_array = str_split( get_option( 'tekserve_shared_data_master_key' ), 4 );
	
	if ( substr( $new_data[0][1][0], 0, 4 ) == $key_array[0] && substr( $new_data[0][1][1], 0, 4 ) == $key_array[2] && substr( $new_data[0][1][2], 0, 4 ) == $key_array[3] ){
		$new_hours_time = $new_data[0][0]['time'];
		$new_hours_name = $new_data[0][0]['name'];
		$new_hours_html = $new_data[0][0]['html'];
		$new_hours_imgurl = $new_data[0][0]['imgurl'];
		$new_hours_mimgurl = $new_data[0][0]['mobileimgurl'];
		$new_dir_time = substr($new_data[0][1][1], 4);
		$new_dir_name = substr($new_data[0][1][0], 4);
		$new_dir_html = substr($new_data[0][1][2], 4);
		$new_dir_html = base64_decode($new_dir_html);
		$new_dir_time = base64_decode($new_dir_time);
		$new_dir_name = base64_decode($new_dir_name);
		$active = array( 'active' => 1 );
		$notactive = array( 'active' => 0 );
		$wpdb->update( $tekserve_shared_data_hours_table, $notactive, $active );
		$wpdb->insert($tekserve_shared_data_hours_table, array(
			'time' => $new_hours_time,
			'name' => $new_hours_name,
			'html' => $new_hours_html,
			'imgurl' => $new_hours_imgurl,
			'mobileimgurl' => $new_hours_mimgurl,
			'active' => 1,
			)
		);
		//here, remove previous (notactive) hours from client table
		$wpdb->delete( $tekserve_shared_data_hours_table, $notactive );
		$wpdb->update( $tekserve_shared_data_directory_table, $notactive, $active );
		$wpdb->insert($tekserve_shared_data_directory_table, array(
			'time' => $new_dir_time,
			'name' => $new_dir_name,
			'html' => $new_dir_html,
			'active' => 1,
			)
		);
		//here, remove previous (notactive) directory records from client table
		$wpdb->delete( $tekserve_shared_data_directory_table, $notactive );
	} else {
	echo "<h1>Data is Currently Invalid</h1>";
	}
}

//function to write json settings file from client
function write_client_data() {
	$local = dirname(__FILE__).'/tsdmaster/current_settings.json';
	if( get_option( 'tekserve_shared_data_master_url' ) ) {
		$file = get_option( 'tekserve_shared_data_master_url' );
		if (!$file) {
			echo "<p>Unable to open remote file.\n</p>";
			exit;
		}
		copy($file, $local);
		read_selected_data();
	}
}

//function to generate a new random auth key
function generate_hash() {
	$max = 4;
	$random = '';
	for ($i = 0; $i < $max; $i ++) {
		$random .= md5(microtime(true).mt_rand(10000,90000));
	}
	$key = substr($random, 0, 28);
	update_option( 'tekserve_shared_data_master_key', $key );
	return $key;
}

add_action('wp_footer', 'tekserve_shared_data_swap_headerimg');

//function to generate a footer script that replaces the header image with the selected header image in the db
//**note** this function does not automatically switch to the mobile header image; this is done with an external script contained in Tekserve Blog Theme, /js/ui-elements.js - the logic for width detection already existed there.
function tekserve_shared_data_swap_headerimg() {
	global $wpdb;
	$wpdb->tekserve_shared_data_hours = $wpdb->prefix . 'tekserve_shared_data_hours';
	$current_hours = $wpdb->get_row("SELECT * FROM $wpdb->tekserve_shared_data_hours WHERE active = true");
	$hrsimgurl = $current_hours->imgurl;
	$hrsmimgurl = $current_hours->mobileimgurl;
	if ($hrsimgurl) {
		echo "<script type='text/javascript'>
		";
		echo "	jQuery( document ).ready(function() {
		";
		echo "		jQuery('#wrap #header .wrap #title-area').css('backgroundImage', 'url(".$hrsimgurl.")' );
		";
		echo "		jQuery('#wrap #header .wrap #title-area').after('<span id=\"tekserve-shared-data-hours-swap\" style=\"display: none;\">url(".$hrsmimgurl.")</span>' );
		";
		echo "	});
		";
		echo "</script>
		";
	}
}