<?php
/*
Plugin Name: Gravity Asana Tasks
Plugin URI: http://andrewnorcross.com/plugins/
Description: Sends details of a form into Asana
Author: Andrew Norcross
Version: 0.0.4
Requires at least: 3.5
Author URI: http://andrewnorcross.com
*/
/*  Copyright 2013 Andrew Norcross

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; version 2 of the License (GPL v2) only.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


if( ! defined( 'GAT_SYNC_BASE' ) ) {
	define( 'GAT_SYNC_BASE', plugin_basename(__FILE__) );
}

if ( ! defined( 'GAT_SYNC_DIR' ) ) {
	define( 'GAT_SYNC_DIR', plugin_dir_path( __FILE__ ) );
}

if( ! defined( 'GAT_SYNC_VER' ) ) {
	define( 'GAT_SYNC_VER', '0.0.4' );
}

class GAT_Sync_Core
{

	/**
	 * Static property to hold our singleton instance
	 * @var GF_Asana_Tasks
	 */
	static $instance = false;

	/**
	 * This is our constructor
	 *
	 * @return GF_Asana_Tasks
	 */
	private function __construct() {
		add_action			(	'plugins_loaded',						array(	$this,	'textdomain'			)			);
		add_action			(	'plugins_loaded',						array(	$this,	'load_files'			)			);
		add_action			(	'admin_notices',						array(	$this,	'active_check'			),	10		);
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return GF_Asana_Tasks
	 */

	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * load textdomain
	 *
	 * @return string load_plugin_textdomain
	 */

	public function textdomain() {

		load_plugin_textdomain( 'gravity-asana-sync', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	}

	/**
	 * [load_files description]
	 * @return [type] [description]
	 */
	public function load_files() {

		require_once( GAT_SYNC_DIR . 'lib/admin.php'	);
		require_once( GAT_SYNC_DIR . 'lib/data.php'		);
		require_once( GAT_SYNC_DIR . 'lib/gravity.php'	);
		require_once( GAT_SYNC_DIR . 'lib/asana.php'	);
	}

	/**
	 * check for GF being active
	 *
	 * @return GF_Asana_Tasks
	 */

	public function active_check() {

		$screen = get_current_screen();

		if ( $screen->parent_file !== 'plugins.php' ) {
			return;
		}

		if ( ! class_exists( 'GFForms' ) ) {

			echo '<div id="message" class="error fade below-h2"><p><strong>'.__( 'This plugin requires Gravity Forms to function.', 'gravity-asana-sync' ).'</strong></p></div>';

			// hide activation method
			unset( $_GET['activate'] );

			// deactivate YOURSELF
			deactivate_plugins( plugin_basename( __FILE__ ) );

		}

		return;

	}

/// end class
}

// Instantiate our class
$GAT_Sync_Core = GAT_Sync_Core::getInstance();

