<?php

class GAT_Sync_Data
{

	/**
	 * [asana_fetch description]
	 * @param  [type] $apikey [description]
	 * @return [type]         [description]
	 */
	static function asana_fetch( $apikey, $key = false, $workspace = false, $data = false ) {

		if ( ! $apikey || ! $key ) {
			return false;
		}

		// make our initial API load call
		$asana = new Asana( array(
			'apiKey' => $apikey
		));

		// no response from Asana. bail
		if ( empty( $asana ) || ! is_object( $asana ) ) {
			return false;
		}

		// set data to false
		$request	= '';

		// pull workspaces
		if ( $key == 'workspaces' ) {
			$request	= $asana->getWorkspaces();
		}

		// pull projects for a workspace
		if ( $key == 'projects' && ! is_null( $workspace ) ) {
			$request	= $asana->getProjectsInWorkspace( $workspace );
		}

		// pull users
		if ( $key == 'users' ) {
			$request	= $asana->getUsers();
		}

		// add filter for other pulls
		$request	= apply_filters( 'gat_sync_data_request', $request, $key, $data );

		// bail if no data returned
		if ( ! $request || is_null( $request ) ) {
			return false;
		}

		// now parse our data
		$response	= json_decode( $request );

		// bail if it didn't decode
		if ( ! $response || empty( $response ) ) {
			return false;
		}

		// bail if no data present
		if ( ! isset( $response->data ) || empty( $response->data ) ) {
			return false;
		}

		// send it back
		return $response->data;

	}

	/**
	 * get available workspaces from Asana
	 * and return in an array
	 * @param  [type] $apikey [description]
	 * @return [type]         [description]
	 */
	static function workspaces( $apikey ) {

		// bail if no API key
		if( ! $apikey ) {
			return;
		}

		if( false === get_transient( 'gf_asana_workspaces' ) ) :

			// make our data fetch
			$data	= self::asana_fetch( $apikey, 'workspaces' );

			// bail if nothing came back
			if ( ! $data || empty( $data ) ) {
				return;
			}

			// store our transient
			set_transient( 'gf_asana_workspaces', $data, DAY_IN_SECONDS );

		endif;

		$data = get_transient( 'gf_asana_workspaces' );

		// send back our array
		return $data;

	}

	/**
	 * get available projects from Asana
	 * and return in an array
	 * @param  [type] $workspace [description]
	 * @param  [type] $apikey    [description]
	 * @return [type]            [description]
	 */
	static function projects( $apikey, $workspace ) {

		// bail if no API key or workspace
		if( ! $apikey || ! $workspace ) {
			return;
		}

		if( false === get_transient( 'gf_asana_projects' ) ) :

			$data	= self::asana_fetch( $apikey, 'projects', $workspace );

			// bail if nothing came back
			if ( ! $data || empty( $data ) ) {
				return;
			}

			// store our transient
			set_transient( 'gf_asana_projects', $data, DAY_IN_SECONDS );

		endif;

		$data = get_transient( 'gf_asana_projects' );

		// send back our array
		return $data;

	}

	/**
	 * get available users in Asana
	 * @param  [type] $apikey [description]
	 * @return [type]         [description]
	 */
	static function users( $apikey ) {

		// bail if no API key
		if( ! $apikey ) {
			return;
		}

		if( false === get_transient( 'gf_asana_users' ) ) :

			$data	= self::asana_fetch( $apikey, 'users' );

			// bail if nothing came back
			if ( ! $data || empty( $data ) ) {
				return;
			}

			// store our transient
			set_transient( 'gf_asana_users', $data, DAY_IN_SECONDS );

		endif;

		$data = get_transient( 'gf_asana_users' );

		// send back our array
		return $data;

	}

	/**
	 * ensure Asana IDs are properly formatted
	 * since it can have multiple zeros
	 *
	 * @param  [type] $value [description]
	 * @return [type]        [description]
	 */
	static function format_id( $value ) {

		return number_format( $value, 0, '', '' );

	}

	/**
	 * build and display the available tags to use within the email content
	 * the "item" portion is tied to where the data lives, either a function, the
	 * database, or part of the $_POST data
	 *
	 * @return [type] [description]
	 */
	static function task_tag_data( $field = false ) {

		$tags	= array(
			array(
				'code'	=> '{site-name}',
				'label'	=> __( 'Name of current site.', 'gravity-asana-sync' )
			),
			array(
				'code'	=> '{form-name}',
				'label'	=> __( 'Name of form', 'gravity-asana-sync' )
			),
			array(
				'code'	=> '{form-date}',
				'label'	=> __( 'Date / time the form was submitted', 'gravity-asana-sync' )
			),
			array(
				'code'	=> '{first-name}',
				'label'	=> __( 'First name from submission', 'gravity-asana-sync' )
			),
			array(
				'code'	=> '{last-name}',
				'label'	=> __( 'Last name from submission', 'gravity-asana-sync' )
			),
			array(
				'code'	=> '{user-email}',
				'label'	=> __( 'Email address from submission', 'gravity-asana-sync' )
			),
		);

		$tags	= apply_filters( 'gat_sync_tag_list', $tags );

		if ( $field ) {
			return wp_list_pluck( $tags, $field );
		}

		return $tags;

	}

	/**
	 * check for placeholders in the task name
	 * and swap out
	 *
	 * @param  [type] $taskname [description]
	 * @param  [type] $form     [description]
	 * @param  [type] $entry    [description]
	 * @return [type]           [description]
	 */
	static function convert_taskname( $taskname, $form, $entry ) {

		// pull form data (with checks)
		$first	= isset( $entry['4.3'] )	&& ! empty( $entry['4.3'] )	? $entry['4.3']	: '';
		$last	= isset( $entry['4.6'] )	&& ! empty( $entry['4.6'] )	? $entry['4.6']	: '';
		$email	= isset( $entry['3'] )		&& ! empty( $entry['3'] )	? $entry['3']	: '';

		// get some data for swapping
		$site	= get_bloginfo( 'name' );
		$name	= $form['title'];
		$date	= date( apply_filters( 'gat_sync_tag_date', 'm/d/Y @ g:i a' ), strtotime( $entry['date_created'] ) );
		$first	= apply_filters( 'gat_sync_tag_first', $first );
		$last	= apply_filters( 'gat_sync_tag_last', $last );
		$email	= apply_filters( 'gat_sync_tag_email', $email );

		// now our swapping
		$hold	= array( '{site-name}', '{form-name}', '{form-date}', '{first-name}', '{last-name}', '{user-email}' );
		$full	= array( $site, $name, $date, $first, $last, $email );

		// do the replace
		$taskname	= str_replace( $hold, $full, $taskname );

		// filter the text for other possible search replace
		$taskname	= apply_filters( 'gat_sync_tag_convert', $taskname, $form, $entry );

		return $taskname;
	}

	/// end class
}

new GAT_Sync_Data();