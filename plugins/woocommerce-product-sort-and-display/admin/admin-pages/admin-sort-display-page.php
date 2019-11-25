<?php
/* "Copyright 2012 A3 Revolution Web Design" This software is distributed under the terms of GNU GENERAL PUBLIC LICENSE Version 3, 29 June 2007 */
// File Security Check
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<?php
/*-----------------------------------------------------------------------------------
WC Admin Sort & Display Page

TABLE OF CONTENTS

- var menu_slug
- var page_data

- __construct()
- page_init()
- page_data()
- add_admin_menu()
- tabs_include()
- admin_settings_page()

-----------------------------------------------------------------------------------*/

class WC_Admin_Sort_Display_Page extends WC_PSAD_Admin_UI
{	
	/**
	 * @var string
	 */
	private $menu_slug = 'wc-sort-display';
	
	/**
	 * @var array
	 */
	private $page_data;
	
	/*-----------------------------------------------------------------------------------*/
	/* __construct() */
	/* Settings Constructor */
	/*-----------------------------------------------------------------------------------*/
	public function __construct() {
		$this->page_init();
		$this->tabs_include();
	}
	
	/*-----------------------------------------------------------------------------------*/
	/* page_init() */
	/* Page Init */
	/*-----------------------------------------------------------------------------------*/
	public function page_init() {
		
		add_filter( $this->plugin_name . '_add_admin_menu', array( $this, 'add_admin_menu' ) );
	}
	
	/*-----------------------------------------------------------------------------------*/
	/* page_data() */
	/* Get Page Data */
	/*-----------------------------------------------------------------------------------*/
	public function page_data() {
		
		$page_data = array(
			'type'				=> 'submenu',
			'parent_slug'		=> 'woocommerce',
			'page_title'		=> __( 'Sort & Display', 'woocommerce-product-sort-and-display' ),
			'menu_title'		=> __( 'Sort & Display', 'woocommerce-product-sort-and-display' ),
			'capability'		=> 'manage_options',
			'menu_slug'			=> $this->menu_slug,
			'function'			=> 'wc_admin_sort_display_page_show',
			'admin_url'			=> 'admin.php',
			'callback_function' => '',
			'script_function' 	=> '',
			'view_doc'			=> '',
		);
		
		if ( $this->page_data ) return $this->page_data;
		return $this->page_data = $page_data;
		
	}
	
	/*-----------------------------------------------------------------------------------*/
	/* add_admin_menu() */
	/* Add This page to menu on left sidebar */
	/*-----------------------------------------------------------------------------------*/
	public function add_admin_menu( $admin_menu ) {
		
		if ( ! is_array( $admin_menu ) ) $admin_menu = array();
		$admin_menu[] = $this->page_data();
		
		return $admin_menu;
	}
	
	/*-----------------------------------------------------------------------------------*/
	/* tabs_include() */
	/* Include all tabs into this page
	/*-----------------------------------------------------------------------------------*/
	public function tabs_include() {
		
		include_once( $this->admin_plugin_dir() . '/tabs/global-settings-tab.php' );
		include_once( $this->admin_plugin_dir() . '/tabs/endless-scroll-tab.php' );
		include_once( $this->admin_plugin_dir() . '/tabs/view-all-count-meta-tab.php' );
		
	}
	
	/*-----------------------------------------------------------------------------------*/
	/* admin_settings_page() */
	/* Show Settings Page */
	/*-----------------------------------------------------------------------------------*/
	public function admin_settings_page() {
		global $wc_psad_admin_init;
		
		$wc_psad_admin_init->admin_settings_page( $this->page_data() );
	}
	
}

global $wc_admin_sort_display_page;
$wc_admin_sort_display_page = new WC_Admin_Sort_Display_Page();

/** 
 * wc_admin_sort_display_page_show()
 * Define the callback function to show page content
 */
function wc_admin_sort_display_page_show() {
	global $wc_admin_sort_display_page;
	$wc_admin_sort_display_page->admin_settings_page();
}

?>