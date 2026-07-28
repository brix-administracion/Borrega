<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Refresh Google Calendar Access Token.
 */
function wp_scheduler_google_get_access_token() {
	$client_id     = get_option( 'wp_scheduler_google_client_id' );
	$client_secret = get_option( 'wp_scheduler_google_client_secret' );
	$refresh_token = get_option( 'wp_scheduler_google_refresh_token' );

	if ( ! $client_id || ! $client_secret || ! $refresh_token ) {
		return new WP_Error( 'missing_credentials', 'Missing Google OAuth credentials.' );
	}

	$response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
		'body' => array(
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'refresh_token' => $refresh_token,
			'grant_type'    => 'refresh_token',
		),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['access_token'] ) ) {
		return new WP_Error( 'token_refresh_failed', 'Failed to retrieve access token from Google: ' . wp_remote_retrieve_body( $response ) );
	}

	return $body['access_token'];
}

/**
 * Create a Google Calendar Event for an appointment.
 */
function wp_scheduler_google_create_event( $event_data ) {
	$calendar_id = get_option( 'wp_scheduler_google_calendar_id' );
	if ( ! $calendar_id ) {
		return new WP_Error( 'missing_calendar_id', 'Missing Google Calendar ID.' );
	}

	$token = wp_scheduler_google_get_access_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$uuid = wp_generate_uuid4();

	$body = array(
		'summary'        => $event_data['summary'],
		'description'    => $event_data['description'],
		'start'          => array( 'dateTime' => $event_data['startDateTime'], 'timeZone' => $event_data['timezone'] ),
		'end'            => array( 'dateTime' => $event_data['endDateTime'], 'timeZone' => $event_data['timezone'] ),
		'attendees'      => array( array( 'email' => $event_data['attendeeEmail'], 'displayName' => $event_data['attendeeName'] ) ),
		'conferenceData' => array(
			'createRequest' => array(
				'requestId'             => $uuid,
				'conferenceSolutionKey' => array( 'type' => 'hangoutsMeet' ),
			),
		),
		'reminders'      => array(
			'useDefault' => false,
			'overrides'  => array(
				array( 'method' => 'email', 'minutes' => 60 ),
				array( 'method' => 'popup', 'minutes' => 10 ),
			),
		),
	);

	$url = sprintf( 'https://www.googleapis.com/calendar/v3/calendars/%s/events?conferenceDataVersion=1&sendUpdates=all', urlencode( $calendar_id ) );

	$response = wp_remote_post( $url, array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		),
		'body'    => json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$res_code = wp_remote_retrieve_response_code( $response );
	if ( $res_code !== 200 ) {
		return new WP_Error( 'google_api_error', 'Google Calendar API error: ' . wp_remote_retrieve_body( $response ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	$meet_link = null;
	if ( ! empty( $data['conferenceData']['entryPoints'] ) ) {
		foreach ( $data['conferenceData']['entryPoints'] as $ep ) {
			if ( $ep['entryPointType'] === 'video' ) {
				$meet_link = $ep['uri'];
				break;
			}
		}
	}

	return array(
		'id'       => $data['id'],
		'meetLink' => $meet_link,
		'htmlLink' => $data['htmlLink'],
	);
}

/**
 * Create a block event in Google Calendar.
 */
function wp_scheduler_google_create_block_event( $date, $timezone, $start_time = null, $end_time = null ) {
	$calendar_id = get_option( 'wp_scheduler_google_calendar_id' );
	if ( ! $calendar_id ) {
		return new WP_Error( 'missing_calendar_id', 'Missing Google Calendar ID.' );
	}

	$token = wp_scheduler_google_get_access_token();
	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$is_all_day = ! $start_time || ! $end_time;
	if ( $is_all_day ) {
		$body = array(
			'summary' => '🔒 Bloqueado',
			'start'   => array( 'date' => $date ),
			'end'     => array( 'date' => $date ),
		);
	} else {
		$body = array(
			'summary' => '🔒 Bloqueado',
			'start'   => array( 'dateTime' => sprintf( '%sT%s:00', $date, substr( $start_time, 0, 5 ) ), 'timeZone' => $timezone ),
			'end'     => array( 'dateTime' => sprintf( '%sT%s:00', $date, substr( $end_time, 0, 5 ) ), 'timeZone' => $timezone ),
		);
	}

	$url = sprintf( 'https://www.googleapis.com/calendar/v3/calendars/%s/events', urlencode( $calendar_id ) );

	$response = wp_remote_post( $url, array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		),
		'body'    => json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$res_code = wp_remote_retrieve_response_code( $response );
	if ( $res_code !== 200 ) {
		return new WP_Error( 'google_api_error', 'Google Calendar API error: ' . wp_remote_retrieve_body( $response ) );
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return $data['id'];
}

/**
 * Delete a Google Calendar Event.
 */
function wp_scheduler_google_delete_event( $event_id ) {
	$calendar_id = get_option( 'wp_scheduler_google_calendar_id' );
	if ( ! $calendar_id || ! $event_id ) {
		return;
	}

	$token = wp_scheduler_google_get_access_token();
	if ( is_wp_error( $token ) ) {
		return;
	}

	$url = sprintf( 'https://www.googleapis.com/calendar/v3/calendars/%s/events/%s', urlencode( $calendar_id ), urlencode( $event_id ) );

	$response = wp_remote_request( $url, array(
		'method'  => 'DELETE',
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
		),
	) );

	if ( is_wp_error( $response ) ) {
		error_log( '[google-calendar] Failed to delete event: ' . $response->get_error_message() );
	}
}

/**
 * Query free/busy times from Google Calendar.
 */
function wp_scheduler_google_get_busy_periods( $first_day, $last_day ) {
	$client_id     = get_option( 'wp_scheduler_google_client_id' );
	$client_secret = get_option( 'wp_scheduler_google_client_secret' );
	$refresh_token = get_option( 'wp_scheduler_google_refresh_token' );
	$calendar_id   = get_option( 'wp_scheduler_google_calendar_id' );

	if ( ! $client_id || ! $client_secret || ! $refresh_token || ! $calendar_id ) {
		return array();
	}

	$token = wp_scheduler_google_get_access_token();
	if ( is_wp_error( $token ) ) {
		return array();
	}

	$body = array(
		'timeMin' => $first_day . 'T00:00:00Z',
		'timeMax' => $last_day . 'T23:59:59Z',
		'items'   => array( array( 'id' => $calendar_id ) ),
	);

	$response = wp_remote_post( 'https://www.googleapis.com/calendar/v3/freeBusy', array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
		),
		'body'    => json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		return array();
	}

	$res_code = wp_remote_retrieve_response_code( $response );
	if ( $res_code !== 200 ) {
		return array();
	}

	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return isset( $data['calendars'][ $calendar_id ]['busy'] ) ? $data['calendars'][ $calendar_id ]['busy'] : array();
}
