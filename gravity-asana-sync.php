<?php
/*
Plugin Name: Gravity Asana Sync
Plugin URI: http://andrewnorcross.com/plugins/
Description: Sends details of a form into Asana
Author: Andrew Norcross
Version: 0.0.3
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


if( !defined( 'GAS_BASE') )
	define( 'GAS_BASE', plugin_basename(__FILE__) );

if( !defined('GAS_VER') )
	define( 'GAS_VER', '0.0.3' );



require_once('lib/asana-connect-class.php');

class GF_Asana_Tasks
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
		add_action          ( 'admin_init',                 array( $this, 'reg_settings'        	)           );
		add_action			( 'admin_notices',              array( $this, 'gf_active_check'     	),  10      );
		add_action			( 'gform_after_submission',		array( $this, 'create_task'				),	10,	2	);
		add_filter          ( 'plugin_action_links',        array( $this, 'quick_link'          	),  10, 2   );
		add_filter          ( 'gform_addon_navigation',     array( $this, 'create_menu'         	)           );
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return GF_Asana_Tasks
	 */

	public static function getInstance() {
		if ( !self::$instance )
			self::$instance = new self;
		return self::$instance;
	}

	/**
	 * check for GF being active
	 *
	 * @return GF_Asana_Tasks
	 */

	public function gf_active_check() {
		$screen = get_current_screen();

		if ($screen->parent_file !== 'plugins.php' )
			return;

		if(is_plugin_active('gravityforms/gravityforms.php') )
			return;

		echo '<div id="message" class="error fade below-h2"><p><strong>This plugin requires Gravity Forms to function.</strong></p></div>';

	}

	/**
	 * show settings link on plugins page
	 *
	 * @return GF_Asana_Tasks
	 */

	public function quick_link( $links, $file ) {

		static $this_plugin;

		if (!$this_plugin) {
			$this_plugin = GAS_BASE;
		}

		// check to make sure we are on the correct plugin
		if ($file == $this_plugin) {

			$settings_link  = '<a href="'.menu_page_url( 'gf-asana', 0 ).'">Settings</a>';

			array_unshift($links, $settings_link);
		}

		return $links;

	}

	/**
	 * send the data to Asana
	 *
	 * @return GF_Asana_Tasks
	 */

	public function create_task( $entry, $form ) {

		$data       = get_option( 'gf-asana' );
		$apikey     = isset( $data['api-key'] )		? $data['api-key']		: '';
		$space		= isset( $data['workspace'] )	? $data['workspace']	: '';
		$project	= isset( $data['project'] )		? $data['project']		: '';
		$person		= isset( $data['person'] )		? $data['person']		: '';
		$form_id	= isset( $data['form-id'] ) 	? $data['form-id']		: '';
		$task_name	= isset( $data['task-name'] )	? $data['task-name']	: 'New Form';

		if ( empty( $form_id ) || $form_id != $form['id'] )
			return $form;

		// run our various data checks
		if( !isset( $apikey ) || empty( $apikey ) )
			return $form;

		if( !isset( $space ) || empty( $space ) )
			return $form;

		if( !isset( $project ) || empty( $project ) )
			return $form;

		if( !isset( $person ) || empty( $person ) )
			return $form;

		// check the spam flag and ax it if need be
		if( isset( $entry['status'] ) && $entry['status'] == 'spam' )
			return $form;

		// parse out the form in a very sexy way. I mean, wait, what?
		$details = '';
		foreach( $form['fields'] as $field ) :

			$label = $field['label'];

			foreach( $entry as $input_id => $value ) :

				if( intval( $input_id ) == $field['id'] ) :

					if ( !empty($value))
						$details .= $label.': '.$value."\r\n";

				endif;
			endforeach;
		endforeach;

		// start the API process
		$asana		= new Asana( $apikey );

		// build array of stuff for Asana
     	$task = array(
     		'workspace'	=> $space,
     		'projects'	=> array( $project ),
     		'name'		=> $task_name,
     		'notes'		=> $details,
			'assignee'	=> $person
     	);

     	// create the task
     	$create = $asana->createTask($task);

		// be sure to return the form when we're done
		return $form;

	}

	/**
	 * Register settings
	 *
	 * @return GF_Asana_Tasks
	 */

	public function reg_settings() {
		register_setting( 'gf-asana', 'gf-asana');

	}

	/**
	 * add submenu option for tooltips
	 *
	 * @return GF_Asana_Tasks
	 */


	public function create_menu($menu_items){

		$menu_items[] = array(
			'name'      	=> 'gf-asana',
			'label'     	=> __('Asana Task Sync'),
			'callback'  	=> array( $this, 'gf_asana_page' ),
			'permission'	=> 'manage_options'
		);

		return $menu_items;
	}

	/**
	 * Get and return workspaces
	 *
	 * @return GF_Asana_Tasks
	 */

	public function workspaces( $apikey ) {

		if( false == get_transient( 'gf_asana_workspaces' ) ) :

			if( !isset( $apikey ) || empty( $apikey ) )
				return;

			$asana = new Asana( $apikey );

			// first, get workspaces
			$workspaces = $asana->getWorkspaces();

			// we got workspaces, so lets do this
			if( $asana->responseCode != '200' )
				return;

			if( is_null( $workspaces ) )
				return;

			$data_object	= json_decode( $workspaces );
			$data_array		= $data_object->data;

			// store our transient
			set_transient( 'gf_asana_workspaces', $data_array, 60*60*24 );

		endif;

		$data_array = get_transient( 'gf_asana_workspaces' );

		// send back our array
		return $data_array;

	}

	/**
	 * Get and return projects
	 *
	 * @return GF_Asana_Tasks
	 */

	public function projects( $workspace, $apikey ) {

		if( false == get_transient( 'gf_asana_projects' ) ) :

			if( !isset( $apikey ) || empty( $apikey ) )
				return;

			if( !isset( $workspace ) || empty( $workspace ) )
				return;

			$asana = new Asana( $apikey );

			// get projects
			$projects = $asana->getProjectsInWorkspace($workspace);

			// we got workspaces, so lets do this
			if( $asana->responseCode != '200' )
				return;

			if( is_null( $projects ) )
				return;

			$data_object	= json_decode( $projects );
			$data_array		= $data_object->data;

			// store our transient
			set_transient( 'gf_asana_projects', $data_array, 60*60*24 );

		endif;

		$data_array = get_transient( 'gf_asana_projects' );

		// send back our array
		return $data_array;

	}


	/**
	 * Get and return users
	 *
	 * @return GF_Asana_Tasks
	 */

	public function users( $apikey ) {

		if( false == get_transient( 'gf_asana_users' ) ) :

			if( !isset( $apikey ) || empty( $apikey ) )
				return;

			$asana = new Asana( $apikey );

			// get people
			$users = $asana->getUsers();

			// we got people, so lets do this
			if( $asana->responseCode != '200' )
				return;

			if( is_null( $users ) )
				return;

			$data_object	= json_decode( $users );
			$data_array		= $data_object->data;

			// store our transient
			set_transient( 'gf_asana_users', $data_array, 60*60*24 );

		endif;

		$data_array = get_transient( 'gf_asana_users' );

		// send back our array
		return $data_array;

	}

	/**
	 * Display main options page structure
	 *
	 * @return GF_Asana_Tasks
	 */

	// 150T4Fy3.VfnxzMuvLdTYKq5OLKvwsEp

	public function gf_asana_page() {
		if (!current_user_can('manage_options') )
			return;
		?>
		<div class="wrap">
			<img alt="" src="<?php echo plugins_url( '/lib/img/gravity-edit-icon-32.png', __FILE__ ); ?>" style="float:left; margin:7px 7px 0 0;"/>
			<h2><?php _e('Asana for Gravity Forms') ?></h2>

			<?php
			echo '<form method="post" action="options.php">';
			settings_fields( 'gf-asana' );
			$data       = get_option('gf-asana');

			// data item checks
			$apikey     = isset($data['api-key'])		? $data['api-key']		: '';
			$space		= isset($data['workspace'])		? $data['workspace']	: '';
			$project	= isset($data['project'])		? $data['project']		: '';
			$person		= isset($data['person'])		? $data['person']		: '';
			$form_id	= isset($data['form-id'])		? $data['form-id']		: '';
			$task_name	= isset($data['task-name'])		? $data['task-name']	: '';


				echo '<table class="form-table">';
				echo '<tbody>';

				// we have the API stored. we can go forth
				if( !empty( $apikey ) ) :

					echo '<tr>';
						echo '<th scope="row">'. __('Select Form').'</th>';
						echo '<td>';
						$gf_active	= RGForms::get( 'active' ) == '' ? null : RGForms::get( 'active' );
						$gf_forms	= RGFormsModel::get_forms( $gf_active, 'title' );

						if ( $gf_forms ) :
							echo '<select name="gf-asana[form-id]" id="gf-asana-form-id">';
							foreach ( $gf_forms as $item ):
								echo '<option value="'.$item->id.'" '.selected( $form_id, $item->id, false ).'>'.$item->title.'</option>';
							endforeach;
							echo '</select>';
						else:
							echo '<span class="description">'. __('Please create a new Gravity Form').'</span>';
						endif;
						echo '</td>';

					echo '</tr>';

					echo '<tr>';
						echo '<th scope="row">'. __('Task Title').'</th>';
						echo '<td>';
							echo '<input type="text" class="regular-text" name="gf-asana[task-name]" value="'.$task_name.'" />';
							echo '<p class="description">'. __('Enter your preferred title for the new task').'</p>';
						echo '</td>';
					echo '</tr>';

					echo '<tr>';
						echo '<th scope="row">'. __('Select Workspace').'</th>';
						echo '<td>';
						$workspaces = $this->workspaces( $apikey );
						if ( $workspaces ) :
							echo '<select name="gf-asana[workspace]" id="gf-asana-workspace">';
							foreach ( $workspaces as $item ):
								$item_id	= number_format($item->id, 0, '', '');
								echo '<option value="'.$item_id.'" '.selected( $space, $item_id, false ).'>'.$item->name.'</option>';
							endforeach;
							echo '</select>';
						else:
							echo '';
						endif;
						echo '</td>';

					echo '</tr>';

					// don't show projects and users until a workspace is set
					if( !empty( $space ) ) :

						echo '<tr>';
							echo '<th scope="row">'. __('Select Project').'</th>';
							echo '<td>';
							$projects = $this->projects( $space, $apikey );
							if ( $projects ) :
								echo '<select name="gf-asana[project]" id="gf-asana-project">';
								foreach ( $projects as $item ):
									$item_id	= number_format($item->id, 0, '', '');
									echo '<option value="'.$item_id.'" '.selected( $project, $item_id, false ).'>'.$item->name.'</option>';
								endforeach;
								echo '</select>';
							else:
								echo '<span class="description">'. __('Please select a workspace above').'</span>';
							endif;
							echo '</td>';

						echo '</tr>';

						echo '<tr>';
							echo '<th scope="row">'. __('Select User').'</th>';
							echo '<td>';
							$users = $this->users( $apikey );
							if ( $users ) :
								echo '<select name="gf-asana[person]" id="gf-asana-person">';
								foreach ( $users as $item ):
									$item_id	= number_format($item->id, 0, '', '');
									echo '<option value="'.$item_id.'" '.selected( $person, $item_id, false ).'>'.$item->name.'</option>';
								endforeach;
								echo '</select>';
							else:
								echo '<span class="description">'. __('Please select a workspace above').'</span>';
							endif;
							echo '</td>';

						echo '</tr>';

					endif;


				endif;

				// only show the field for the API key if it hasn't been entered yet
				if( empty( $apikey ) ) :
					echo '<tr>';
						echo '<th scope="row">'. __('API Key').'</th>';
						echo '<td>';
							echo '<input type="text" class="regular-text code" name="gf-asana[api-key]" value="" />';
							echo '<p class="description">'. __('Enter your Asana API key. Found in Account Settings -> Apps').'</p>';
						echo '</td>';
					echo '</tr>';

				else:
					echo '<input type="hidden" name="gf-asana[api-key]" value="'.$apikey.'" />';
				endif;

				echo '</tbody>';
				echo '</table>';

				echo '<p><input type="submit" class="button-primary" value="'. __('Save Changes').'" /></p>';

				echo '</form>';
			?>
		</div>

	<?php }



/// end class
}

// Instantiate our class
$GF_Asana_Tasks = GF_Asana_Tasks::getInstance();

