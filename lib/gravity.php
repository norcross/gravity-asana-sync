<?php

class GAT_Sync_Gravity
{

	/**
	 * This is our constructor
	 *
	 * @return Gravity_Asana
	 */
	public function __construct() {
		add_filter			(	'gform_addon_navigation',				array(	$this,	'create_menu'			)			);
		add_action			(	'gform_after_submission',				array(	$this,	'create_task'			),	10,	2	);
	}

	/**
	 * add submenu option for tooltips
	 * @param  [type] $menu_items [description]
	 * @return [type]             [description]
	 */
	public function create_menu( $menu_items ){

		$admin	= new GAT_Sync_Admin();

		$menu_items[] = array(
			'name'      	=> 'gf-asana',
			'label'     	=> __( 'Asana Task Sync', 'gravity-asana-sync' ),
			'callback'  	=> array( $admin, 'settings_page' ),
			'permission'	=> 'manage_options'
		);

		return $menu_items;
	}

	/**
	 * send the data to Asana
	 *
	 * @return GF_Asana_Tasks
	 */
	/**
	 * create a new task from the form data and
	 * push up to Asana
	 *
	 * @param  [type] $entry [description]
	 * @param  [type] $form  [description]
	 * @return [type]        [description]
	 */
	public function create_task( $entry, $form ) {

		// fetch the API key
		$apikey		= get_option( 'gf-asana-api' );

		// bail without an API key
		if( ! isset( $apikey ) || empty( $apikey ) ) {
			return $form;
		}

		// fetch the rest of our data
		$data		= get_option( 'gf-asana' );

		if( ! isset( $data ) || empty( $data ) ) {
			return $form;
		}

		// get our individual items
		$form_id	= isset( $data['form-id'] ) 	? $data['form-id']		: '';
		$space		= isset( $data['workspace'] )	? $data['workspace']	: '';
		$project	= isset( $data['project'] )		? $data['project']		: '';
		$user		= isset( $data['user'] )		? $data['user']			: '';

		$taskname	= isset( $data['task-name'] )	? $data['task-name']	: __( 'New Submission', 'gravity-asana-sync' );

		// check that we have the required items
		if( empty( $form_id ) || empty( $space ) || empty( $project ) || empty( $user ) ) {
			return $form;
		}

		// make sure our form ID matches
		if ( empty( $form_id ) || $form_id != $form['id'] ) {
			return $form;
		}

		// check the spam flag and axe it if need be
		if( isset( $entry['status'] ) && $entry['status'] == 'spam' ) {
			return $form;
		}

		// parse out the form in a very sexy way. I mean, wait, what?
		$details = '';
		foreach( $form['fields'] as $field ) {

			$label = $field['label'];

			foreach( $entry as $input_id => $value ) {

				if( intval( $input_id ) == $field['id'] ) {

					if ( ! empty( $value ) ) {
						$details .= $label.': '.$value."\r\n";
					}

				} //endif
			} //endforeach
		} //endforeach

		// convert our task name
		$taskname	= GAT_Sync_Data::convert_taskname( $taskname, $form, $entry );

		// start the API process
		$asana		= new Asana( $apikey );

		// build array of stuff for Asana
		$task = array(
			'workspace'	=> $space,
			'projects'	=> array( $project ),
			'name'		=> $taskname,
			'notes'		=> $details,
			'assignee'	=> $user
		);

		// create the task
		$create = $asana->createTask( $task );

		// be sure to return the form when we're done
		return $form;

	}

/// end class
}

new GAT_Sync_Gravity();