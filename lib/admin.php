<?php

class GAT_Sync_Admin
{

	/**
	 * This is our constructor
	 *
	 * @return Gravity_Asana
	 */
	public function __construct() {
		add_action			(	'admin_enqueue_scripts',				array(	$this,	'scripts_styles'		),	10		);
		add_action			(	'admin_init',							array(	$this,	'reg_settings'			)			);
		add_filter			(	'plugin_action_links',					array(	$this,	'quick_link'			),	10,	2	);
	}

	/**
	 * [scripts_styles description]
	 * @param  [type] $hook [description]
	 * @return [type]       [description]
	 */
	public function scripts_styles( $hook ) {

		$screen	= get_current_screen();

		if ( is_object( $screen ) && $screen->base == 'forms_page_gf-asana' ):

			wp_enqueue_style( 'gat-sync-admin', plugins_url( '/css/gat.sync.admin.css', __FILE__), array(), GAT_SYNC_VER, 'all' );

		endif;

	}

	/**
	 * show settings link on plugins page
	 *
	 * @return GF_Asana_Tasks
	 */
	/**
	 * show settings link on plugins page
	 * @param  [type] $links [description]
	 * @param  [type] $file  [description]
	 * @return [type]        [description]
	 */
	public function quick_link( $links, $file ) {

		static $this_plugin;

		if ( ! $this_plugin ) {
			$this_plugin = GAT_SYNC_BASE;
		}

		// check to make sure we are on the correct plugin
		if ( $file == $this_plugin ) {

			$settings_link  = '<a href=" ' .menu_page_url( 'gf-asana', 0 ) . '">' . __( 'Settings', 'gravity-asana-sync' ) . '</a>';

			array_unshift( $links, $settings_link );
		}

		return $links;

	}

	/**
	 * register the settings
	 * @return [type] [description]
	 */
	public function reg_settings() {
		register_setting( 'gf-asana',		'gf-asana'		);
		register_setting( 'gf-asana-api',	'gf-asana-api'	);
	}

	/**
	 * Display main options page structure
	 *
	 * @return GF_Asana_Tasks
	 */

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// pull in our API key
		$apikey	= get_option( 'gf-asana-api' );

		echo '<div class="wrap">';
			echo '<h2>'. __( 'Asana Task Sync for Gravity Forms', 'gravity-asana-sync' ) . '</h2>';
			echo '<form method="post" action="options.php">';
			echo '<table class="form-table">';
			echo '<tbody>';

			// if we have an empty API key field, show that field only
			if( empty( $apikey ) ) {
				settings_fields( 'gf-asana-api' );
				echo self::get_apikey_field();
			}

			// we have a key. show our fields
			if( ! empty( $apikey ) ) {
				// use our settings field for proper saving
				settings_fields( 'gf-asana' );
				// pull any saved data
				$data       = get_option( 'gf-asana' );

				// field for GF form selection
				echo self::get_formselect_field( $data );

				// field for task name
				echo self::get_taskname_field( $data );

				// field for selecing a workspace
				echo self::get_workspace_field( $data, $apikey );

				// field for projects
				echo self::get_projects_field( $data, $apikey );

				// field for users
				echo self::get_users_field( $data, $apikey );

			}

			echo '</tbody>';
			echo '</table>';

			echo '<p><input type="submit" class="button-primary" value="' . __( 'Save Changes' ) . '" /></p>';

			echo '</form>';

			// list the tags available
			echo self::list_available_tags();

		echo '</div>';

	}


	/**
	 * admin settings field to display input
	 * regarding API key
	 * @return [type] [description]
	 */
	static function get_apikey_field() {

		$field	= '';

		$field	.= '<tr>';
			$field	.= '<th scope="row">'. __( 'API Key', 'gravity-asana-sync' ) . '</th>';
			$field	.= '<td>';
				$field	.= '<input type="text" class="regular-text code" name="gf-asana-api" id="gf-asana-api" value="" />';
				$field	.= '<p class="description">' . __( 'Enter your Asana API key. Found in Account Settings -> Apps', 'gravity-asana-sync' ) . '</p>';
			$field	.= '</td>';
		$field	.= '</tr>';

		// send it back
		return $field;

	}

	/**
	 * admin settings field to display available
	 * forms in a dropdown
	 *
	 * @return [type] [description]
	 */
	static function get_formselect_field( $data ) {

		// data checks
		$form_id	= isset( $data['form-id'] )	? $data['form-id'] : '';
		$forms		= RGFormsModel::get_forms( 1, 'title' );

		// build form
		$field	= '';

		$field	.= '<tr>';
			$field	.= '<th scope="row">'. __( 'Select Form', 'gravity-asana-sync' ).'</th>';
			$field	.= '<td>';

			// check for available forms
			if ( $forms ) {
				// loop through the forms
				$field	.= '<select name="gf-asana[form-id]" id="gf-asana-form-id">';
				foreach ( $forms as $form ) {
					$field	.= '<option value="'.$form->id.'" '.selected( $form_id, $form->id, false ).'>'.$form->title.'</option>';
				}
				$field	.= '</select>';
			} else {
				// display a message telling them to make a damn form
				$field	.= '<span class="description">'. __( 'Please create a new Gravity Form', 'gravity-asana-sync' ).'</span>';
			}

			$field	.= '</td>';

		$field	.= '</tr>';

		// send it back
		return $field;

	}

	/**
	 * admin settings field to display available
	 * Asana tasks in a dropdown
	 *
	 * @return [type] [description]
	 */
	static function get_taskname_field( $data ) {

		// data checks
		$task_name	= isset( $data['task-name'] ) ? $data['task-name'] : '';

		// build form
		$field	= '';

		$field	.= '<tr>';
			$field	.= '<th scope="row">'. __( 'Task Name', 'gravity-asana-sync' ).'</th>';
			$field	.= '<td>';
				$field	.= '<input type="text" class="regular-text" name="gf-asana[task-name]" value="' . esc_attr( $task_name ) . '" />';
				$field	.= '<p class="description">'. __( 'Placeholders allowed. Please see the available list below.', 'gravity-asana-sync' ).'</p>';
			$field	.= '</td>';
		$field	.= '</tr>';

		// send it back
		return $field;

	}

	/**
	 * [get_workspace_field description]
	 * @param  [type] $data [description]
	 * @return [type]       [description]
	 */
	static function get_workspace_field( $data, $apikey ) {

		// data checks
		$workspaces = GAT_Sync_Data::workspaces( $apikey );
		$current	= isset( $data['workspace'] ) ? $data['workspace'] : '';

		// build form
		$field	= '';

		$field	.= '<tr>';
			$field	.= '<th scope="row">'. __( 'Select Workspace', 'gravity-asana-sync' ).'</th>';
			$field	.= '<td>';

			// if there are available fields
			if ( $workspaces ) {

				$field	.= '<select name="gf-asana[workspace]" id="gf-asana-workspace">';
				// loop through
				foreach ( $workspaces as $workspace ):
					// ensure proper space ID casting
					$workspace_id	= GAT_Sync_Data::format_id( $workspace->id );

					$field	.= '<option value="' . $workspace_id . '" ' . selected( $current, $workspace_id, false ) . '>' . esc_html( $workspace->name ) . '</option>';

				endforeach;

				$field	.= '</select>';

			} else {

				// display a message telling them to make a workspace
				$field	.= '<span class="description">'. __( 'There are no available workspaces', 'gravity-asana-sync' ).'</span>';

			}

			$field	.= '</td>';

		$field	.= '</tr>';

		// send it back
		return $field;
	}

	/**
	 * get available projects
	 * @param  [type] $data   [description]
	 * @param  [type] $apikey [description]
	 * @return [type]         [description]
	 */
	static function get_projects_field( $data, $apikey ) {

		$workspace	= isset( $data['workspace'] ) && ! empty( $data['workspace'] ) ? $data['workspace']	: '';
		// we need a workspace to fetch projects
		if ( empty( $workspace ) ) {
			return;
		}

		// fetch data
		$projects	= GAT_Sync_Data::projects( $apikey, $workspace );
		$current	= isset( $data['project'] )		? $data['project']		: '';

		// build field
		$field	= '';

		$field	.= '<tr>';
			$field	.= '<th scope="row">'. __( 'Select Project', 'gravity-asana-sync' ).'</th>';

			$field	.= '<td>';
			// loop through projects if available
			if ( $projects ) {

				$field	.= '<select name="gf-asana[project]" id="gf-asana-project">';

				// loop through
				foreach ( $projects as $project ):
					// ensure proper space ID casting
					$project_id	= GAT_Sync_Data::format_id( $project->id );

					$field	.= '<option value="' . $project_id . '" ' . selected( $current, $project_id, false ) . '>' . esc_html( $project->name ) . '</option>';

				endforeach;

				$field	.= '</select>';

			} else {

				$field	.= '<span class="description">'. __( 'Please select a workspace above', 'gravity-asana-sync' ).'</span>';

			}

			$field	.= '</td>';

		$field	.= '</tr>';

		// send it back
		return $field;

	}

	/**
	 * get available users
	 * @param  [type] $data   [description]
	 * @param  [type] $apikey [description]
	 * @return [type]         [description]
	 */
	static function get_users_field( $data, $apikey ) {

		// fetch data
		$users		= GAT_Sync_Data::users( $apikey );
		$current	= isset( $data['user'] ) ? $data['user'] : '';

		// build field
		$field	= '';

		$field	.= '<tr>';
			$field	.= '<th scope="row">'. __( 'Select Assigned User', 'gravity-asana-sync' ).'</th>';

			$field	.= '<td>';
			// loop through users if available
			if ( $users ) {

				$field	.= '<select name="gf-asana[user]" id="gf-asana-user">';

				// loop through
				foreach ( $users as $user ):
					// ensure proper space ID casting
					$user_id	= GAT_Sync_Data::format_id( $user->id );

					$field	.= '<option value="' . $user_id . '" ' . selected( $current, $user_id, false ) . '>' . esc_html( $user->name ) . '</option>';

				endforeach;

				$field	.= '</select>';

			} else {

				$field	.= '<span class="description">'. __( 'Select the user to assign the task to.', 'gravity-asana-sync' ).'</span>';

			}

			$field	.= '</td>';

		$field	.= '</tr>';

		// send it back
		return $field;

	}

	/**
	 * [list_available_tags description]
	 * @return [type] [description]
	 */
	static function list_available_tags() {

		// fetch the tags
		$tags	= GAT_Sync_Data::task_tag_data();

		if ( ! $tags ) {
			return;
		}

		$list	= '<div class="gat-sync-tags-list">';
		$list	.= '<h3>'. __( 'Available merge tags', 'gravity-asana-sync' ).'</h3>';
		$list	.= '<ul>';

		foreach ( $tags as $tag ) :
			if ( isset( $tag['label'] ) && isset( $tag['code'] ) ) :
				$list	.= '<li>';
				$list	.= '<code>' . $tag['code'] . '</code>';
				$list	.= '<span>' . $tag['label'] . '</span>';
				$list	.= '</li>';
			endif;
		endforeach;

		$list	.= '</ul>';
		$list	.= '</div>';

		// send it back
		return $list;

	}

/// end class
}

new GAT_Sync_Admin();