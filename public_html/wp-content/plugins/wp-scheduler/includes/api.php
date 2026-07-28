<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Hook to register REST API routes
add_action( 'rest_api_init', 'wp_scheduler_register_routes' );

function wp_scheduler_register_routes() {
	// Public routes
	register_rest_route( 'scheduler/v1', '/services', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'wp_scheduler_api_get_services',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'scheduler/v1', '/availability', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'wp_scheduler_api_get_availability',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'scheduler/v1', '/book', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'wp_scheduler_api_create_booking',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'scheduler/v1', '/cancel', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'wp_scheduler_api_cancel_booking',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'scheduler/v1', '/webhook', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'wp_scheduler_api_handle_webhook',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'scheduler/v1', '/appointment', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'wp_scheduler_api_get_appointment',
		'permission_callback' => '__return_true',
	) );

	// Admin capability protected routes
	register_rest_route( 'scheduler/v1', '/admin/services', array(
		'methods'             => WP_REST_Server::ALLMETHODS,
		'callback'            => 'wp_scheduler_api_admin_services',
		'permission_callback' => 'wp_scheduler_admin_permission_check',
	) );

	register_rest_route( 'scheduler/v1', '/admin/config', array(
		'methods'             => WP_REST_Server::ALLMETHODS,
		'callback'            => 'wp_scheduler_api_admin_config',
		'permission_callback' => 'wp_scheduler_admin_permission_check',
	) );

	register_rest_route( 'scheduler/v1', '/admin/appointments', array(
		'methods'             => WP_REST_Server::ALLMETHODS,
		'callback'            => 'wp_scheduler_api_admin_appointments',
		'permission_callback' => 'wp_scheduler_admin_permission_check',
	) );
}

function wp_scheduler_admin_permission_check() {
	return current_user_can( 'manage_options' );
}

// ─────────────────────────────────────────────────────────────────────────────
// PUBLIC ENDPOINTS IMPLEMENTATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get active services.
 */
function wp_scheduler_api_get_services() {
	global $wpdb;
	$table_services = $wpdb->prefix . 'scheduler_services';
	$results = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $table_services WHERE is_active = %d ORDER BY name ASC",
		1
	), ARRAY_A );

	foreach ( $results as &$r ) {
		$r['is_active'] = (bool) $r['is_active'];
		$r['duration_minutes'] = intval( $r['duration_minutes'] );
		$r['buffer_minutes'] = intval( $r['buffer_minutes'] );
	}

	return new WP_REST_Response( $results, 200 );
}

/**
 * Get Availability endpoint.
 */
function wp_scheduler_api_get_availability( WP_REST_Request $request ) {
	global $wpdb;
	$service_id = sanitize_text_field( $request->get_param( 'service_id' ) );
	$year       = intval( $request->get_param( 'year' ) );
	$month      = intval( $request->get_param( 'month' ) );

	if ( ! $service_id || ! $year || ! $month ) {
		return new WP_REST_Response( array( 'error' => 'service_id, year, month required' ), 400 );
	}

	$table_services  = $wpdb->prefix . 'scheduler_services';
	$table_schedules = $wpdb->prefix . 'scheduler_service_schedules';
	$table_appts     = $wpdb->prefix . 'scheduler_appointments';
	$table_blocked   = $wpdb->prefix . 'scheduler_blocked_dates';

	$service = $wpdb->get_row( $wpdb->prepare(
		"SELECT timezone, currency, buffer_minutes FROM $table_services WHERE id = %s LIMIT 1",
		$service_id
	), ARRAY_A );

	if ( ! $service ) {
		return new WP_REST_Response( array( 'error' => 'Service not found' ), 404 );
	}

	$tz         = $service['timezone'] ? $service['timezone'] : 'UTC';
	$buffer_min = intval( $service['buffer_minutes'] );

	$schedules = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, day_of_week, start_time, end_time, price, slot_duration_minutes FROM $table_schedules WHERE service_id = %s AND is_active = 1 ORDER BY start_time ASC",
		$service_id
	), ARRAY_A );

	if ( empty( $schedules ) ) {
		return new WP_REST_Response( array( 'days' => new stdClass() ), 200 );
	}

	$first_day = sprintf( '%04d-%02d-01', $year, $month );
	$num_days  = cal_days_in_month( CAL_GREGORIAN, $month, $year );
	$last_day  = sprintf( '%04d-%02d-%02d', $year, $month, $num_days );

	// Fetch booked slots
	$booked_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT appointment_date, start_time, end_time FROM $table_appts WHERE service_id = %s AND appointment_date >= %s AND appointment_date <= %s AND status IN ('pending', 'paid', 'confirmed_free')",
		$service_id, $first_day, $last_day
	), ARRAY_A );

	$booked_slots = array();
	foreach ( $booked_rows as $r ) {
		$booked_slots[] = array(
			'date'     => $r['appointment_date'],
			'startMin' => wp_scheduler_time_to_minutes( $r['start_time'] ),
			'endMin'   => wp_scheduler_time_to_minutes( $r['end_time'] ),
		);
	}

	// Fetch blocked dates
	$blocked_rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT blocked_date, start_time, end_time FROM $table_blocked WHERE (service_id = %s OR service_id IS NULL) AND blocked_date >= %s AND blocked_date <= %s",
		$service_id, $first_day, $last_day
	), ARRAY_A );

	$blocked_full_days = array();
	$blocked_ranges    = array();
	foreach ( $blocked_rows as $b ) {
		if ( empty( $b['start_time'] ) ) {
			$blocked_full_days[] = $b['blocked_date'];
		} else {
			$blocked_ranges[ $b['blocked_date'] ][] = array(
				'startMin' => wp_scheduler_time_to_minutes( $b['start_time'] ),
				'endMin'   => wp_scheduler_time_to_minutes( $b['end_time'] ),
			);
		}
	}

	$busy_periods = wp_scheduler_google_get_busy_periods( $first_day, $last_day );

	$days  = array();
	$today = wp_scheduler_local_today( $tz );

	for ( $d = 1; $d <= $num_days; $d++ ) {
		$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $d );
		if ( $date_str < $today ) {
			continue;
		}
		if ( in_array( $date_str, $blocked_full_days ) ) {
			continue;
		}

		$js_day = date( 'w', strtotime( $date_str ) );
		$day_schedules = array();
		foreach ( $schedules as $s ) {
			if ( intval( $s['day_of_week'] ) === intval( $js_day ) ) {
				$day_schedules[] = $s;
			}
		}

		if ( empty( $day_schedules ) ) {
			continue;
		}

		$all_slots = array();
		$min_price = INF;
		$date_blocked_ranges = isset( $blocked_ranges[ $date_str ] ) ? $blocked_ranges[ $date_str ] : array();

		foreach ( $day_schedules as $sched ) {
			$slots = wp_scheduler_generate_slots( $date_str, $sched, $buffer_min, $tz, $booked_slots, $busy_periods, $date_blocked_ranges );
			$all_slots = array_merge( $all_slots, $slots );
			$min_price = min( $min_price, floatval( $sched['price'] ) );
		}

		usort( $all_slots, function( $a, $b ) {
			return strcmp( $a['start'], $b['start'] );
		} );

		$any_available = false;
		foreach ( $all_slots as $s ) {
			if ( $s['available'] ) {
				$any_available = true;
				break;
			}
		}

		if ( ! empty( $all_slots ) ) {
			$days[ $date_str ] = array(
				'available' => $any_available,
				'price'     => $min_price === INF ? 0 : $min_price,
				'slots'     => $all_slots,
			);
		}
	}

	return new WP_REST_Response( array( 'days' => $days ), 200 );
}

function wp_scheduler_time_to_minutes( $t ) {
	$parts = explode( ':', $t );
	return intval( $parts[0] ) * 60 + intval( $parts[1] );
}

function wp_scheduler_minutes_to_hhmm( $m ) {
	return sprintf( '%02d:%02d', floor( $m / 60 ), $m % 60 );
}

function wp_scheduler_local_today( $tz ) {
	try {
		$dt = new DateTime( 'now', new DateTimeZone( $tz ) );
		return $dt->format( 'Y-m-d' );
	} catch ( Exception $e ) {
		return date( 'Y-m-d' );
	}
}

function wp_scheduler_generate_slots( $date_str, $sched, $buffer_min, $tz, $booked_slots, $busy_periods, $blocked_ranges ) {
	$slots = array();
	$start_min = wp_scheduler_time_to_minutes( $sched['start_time'] );
	$end_min   = wp_scheduler_time_to_minutes( $sched['end_time'] );
	$duration  = intval( $sched['slot_duration_minutes'] );
	$cursor    = $start_min;

	while ( $cursor <= $end_min ) {
		$slot_start_min = $cursor;
		$slot_end_min   = $cursor + $duration;
		$slot_start     = wp_scheduler_minutes_to_hhmm( $slot_start_min );
		$slot_end       = wp_scheduler_minutes_to_hhmm( $slot_end_min );

		$is_booked  = wp_scheduler_is_blocked_by_booking( $date_str, $slot_start_min, $slot_end_min, $buffer_min, $booked_slots );
		$is_busy    = wp_scheduler_is_overlapping_busy( $date_str, $slot_start, $slot_end, $tz, $busy_periods );
		$is_blocked = false;
		foreach ( $blocked_ranges as $r ) {
			if ( $slot_start_min < $r['endMin'] && $slot_end_min > $r['startMin'] ) {
				$is_blocked = true;
				break;
			}
		}

		$slots[] = array(
			'start'     => $slot_start,
			'end'       => $slot_end,
			'available' => ! $is_booked && ! $is_busy && ! $is_blocked,
		);
		$cursor += $duration;
	}

	return $slots;
}

function wp_scheduler_is_blocked_by_booking( $date_str, $slot_start_min, $slot_end_min, $buffer_min, $booked_slots ) {
	foreach ( $booked_slots as $b ) {
		if ( $b['date'] !== $date_str ) {
			continue;
		}
		$blocked_end = $b['endMin'] + $buffer_min;
		if ( $slot_start_min < $blocked_end && $slot_end_min > $b['startMin'] ) {
			return true;
		}
	}
	return false;
}

function wp_scheduler_is_overlapping_busy( $date_str, $slot_start, $slot_end, $tz, $busy_periods ) {
	$slot_start_utc = wp_scheduler_local_to_utc( sprintf( '%sT%s:00', $date_str, $slot_start ), $tz );
	$slot_end_utc   = wp_scheduler_local_to_utc( sprintf( '%sT%s:00', $date_str, $slot_end ), $tz );

	foreach ( $busy_periods as $b ) {
		$b_start = strtotime( $b['start'] ) * 1000;
		$b_end   = strtotime( $b['end'] ) * 1000;
		if ( $slot_start_utc < $b_end && $slot_end_utc > $b_start ) {
			return true;
		}
	}
	return false;
}

function wp_scheduler_local_to_utc( $local_iso, $tz ) {
	try {
		$dt = new DateTime( $local_iso, new DateTimeZone( $tz ) );
		return $dt->getTimestamp() * 1000;
	} catch ( Exception $e ) {
		return strtotime( $local_iso ) * 1000;
	}
}

/**
 * Create a new Booking.
 */
function wp_scheduler_api_create_booking( WP_REST_Request $request ) {
	global $wpdb;
	$body = $request->get_json_params();

	$service_id       = sanitize_text_field( $body['service_id'] );
	$schedule_id      = isset( $body['schedule_id'] ) ? sanitize_text_field( $body['schedule_id'] ) : null;
	$client_name      = sanitize_text_field( $body['client_name'] );
	$client_email     = sanitize_email( $body['client_email'] );
	$client_phone     = isset( $body['client_phone'] ) ? sanitize_text_field( $body['client_phone'] ) : null;
	$notes            = isset( $body['notes'] ) ? sanitize_textarea_field( $body['notes'] ) : null;
	$appointment_date = sanitize_text_field( $body['appointment_date'] );
	$start_time       = sanitize_text_field( $body['start_time'] );
	$end_time         = sanitize_text_field( $body['end_time'] );
	$price            = floatval( $body['price'] );
	$currency         = sanitize_text_field( $body['currency'] );

	if ( ! $service_id || ! $client_name || ! $client_email || ! $appointment_date || ! $start_time || ! $end_time ) {
		return new WP_REST_Response( array( 'error' => 'Missing required fields' ), 400 );
	}

	$table_services = $wpdb->prefix . 'scheduler_services';
	$table_appts    = $wpdb->prefix . 'scheduler_appointments';

	$svc = $wpdb->get_row( $wpdb->prepare(
		"SELECT timezone, language, name FROM $table_services WHERE id = %s LIMIT 1",
		$service_id
	), ARRAY_A );

	if ( ! $svc ) {
		return new WP_REST_Response( array( 'error' => 'Service not found' ), 404 );
	}

	$appointment_id = wp_generate_uuid4();
	$cancel_token   = wp_generate_uuid4();

	$inserted = $wpdb->insert( $table_appts, array(
		'id'               => $appointment_id,
		'service_id'       => $service_id,
		'schedule_id'      => $schedule_id,
		'client_name'      => $client_name,
		'client_email'     => $client_email,
		'client_phone'     => $client_phone,
		'notes'            => $notes,
		'appointment_date' => $appointment_date,
		'start_time'       => $start_time,
		'end_time'         => $end_time,
		'price'            => $price,
		'currency'         => $currency,
		'timezone'         => $svc['timezone'],
		'status'           => 'pending',
		'cancel_token'     => $cancel_token,
	) );

	if ( ! $inserted ) {
		return new WP_REST_Response( array( 'error' => 'Failed to create appointment' ), 500 );
	}

	if ( $price === 0.0 ) {
		// Confirm immediately
		$wpdb->update( $table_appts, array(
			'status'     => 'confirmed_free',
			'updated_at' => current_time( 'mysql' ),
		), array( 'id' => $appointment_id ) );

		// Google Calendar & Email notifications asynchronously/after-response
		wp_scheduler_async_confirm_booking( $appointment_id );

		return new WP_REST_Response( array( 'confirmed' => true, 'appointment_id' => $appointment_id ), 200 );
	}

	// Paid booking: create Stripe or MercadoPago checkout url
	$provider = get_option( 'wp_scheduler_payment_provider', 'stripe' );
	$site_url = site_url();

	$lang       = $svc['language'] ? $svc['language'] : 'es';
	$date_label = date_i18n( 'M j', strtotime( $appointment_date ) );

	$checkout_url = '';

	try {
		if ( $provider === 'stripe' ) {
			$checkout_url = wp_scheduler_create_stripe_session( array(
				'id'               => $appointment_id,
				'client_email'     => $client_email,
				'client_name'      => $client_name,
				'price'            => $price,
				'currency'         => $currency,
				'appointment_date' => $appointment_date,
				'start_time'       => $start_time,
				'service_name'     => $svc['name'],
			), $site_url, $date_label );
		} else {
			$checkout_url = wp_scheduler_create_mercadopago_preference( array(
				'id'               => $appointment_id,
				'client_email'     => $client_email,
				'client_name'      => $client_name,
				'price'            => $price,
				'currency'         => $currency,
				'appointment_date' => $appointment_date,
				'start_time'       => $start_time,
				'service_name'     => $svc['name'],
			), $site_url, $date_label );
		}
	} catch ( Exception $e ) {
		return new WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
	}

	return new WP_REST_Response( array( 'url' => $checkout_url, 'appointment_id' => $appointment_id ), 200 );
}

function wp_scheduler_async_confirm_booking( $appointment_id ) {
	// In WordPress, we can perform this directly or schedule a single event,
	// but to mirror the fire-and-forget, we execute it directly now.
	global $wpdb;
	$table_appts    = $wpdb->prefix . 'scheduler_appointments';
	$table_services = $wpdb->prefix . 'scheduler_services';

	$appt = $wpdb->get_row( $wpdb->prepare(
		"SELECT a.*, s.name as service_name, s.language as service_language, s.timezone as service_timezone FROM $table_appts a INNER JOIN $table_services s ON a.service_id = s.id WHERE a.id = %s LIMIT 1",
		$appointment_id
	), ARRAY_A );

	if ( ! $appt ) {
		return;
	}

	$tz         = $appt['service_timezone'] ? $appt['service_timezone'] : 'UTC';
	$lang       = $appt['service_language'] ? $appt['service_language'] : 'es';
	$locale     = $lang === 'es' ? 'es_AR' : 'en_US';
	$date_label = date_i18n( 'l, j \d\e F', strtotime( $appt['appointment_date'] ) );

	$event = wp_scheduler_google_create_event( array(
		'summary'        => sprintf( '%s — %s', $appt['service_name'], $appt['client_name'] ),
		'description'    => sprintf( "Client: %s\nEmail: %s\nPrice: %s", $appt['client_name'], $appt['client_email'], floatval( $appt['price'] ) === 0.0 ? 'Gratis' : $appt['price'] . ' ' . $appt['currency'] ),
		'startDateTime'  => sprintf( '%sT%s:00', $appt['appointment_date'], substr( $appt['start_time'], 0, 5 ) ),
		'endDateTime'    => sprintf( '%sT%s:00', $appt['appointment_date'], substr( $appt['end_time'], 0, 5 ) ),
		'attendeeEmail'  => $appt['client_email'],
		'attendeeName'   => $appt['client_name'],
		'timezone'       => $tz,
	) );

	$meet_link = null;
	$event_id  = null;
	if ( ! is_wp_error( $event ) ) {
		$event_id  = $event['id'];
		$meet_link = $event['meetLink'];

		$wpdb->update( $table_appts, array(
			'google_event_id'  => $event_id,
			'google_meet_link' => $meet_link,
		), array( 'id' => $appointment_id ) );
	}

	// Send emails
	$cancel_link = site_url( 'wp-json/scheduler/v1/cancel?token=' . $appt['cancel_token'] );
	$price_label = floatval( $appt['price'] ) === 0.0 ? 'Gratis' : $appt['price'] . ' ' . $appt['currency'];

	wp_scheduler_send_confirmation_email( array(
		'clientName'  => $appt['client_name'],
		'clientEmail' => $appt['client_email'],
		'serviceName' => $appt['service_name'],
		'date'        => $date_label,
		'startTime'   => wp_scheduler_format_time( $appt['start_time'] ),
		'endTime'     => wp_scheduler_format_time( $appt['end_time'] ),
		'price'       => $price_label,
		'meetLink'    => $meet_link,
		'calendarLink'=> ! is_wp_error( $event ) ? $event['htmlLink'] : null,
		'cancelLink'  => $cancel_link,
	) );

	wp_scheduler_send_admin_notification_email( array(
		'clientName'  => $appt['client_name'],
		'clientEmail' => $appt['client_email'],
		'clientPhone' => $appt['client_phone'],
		'serviceName' => $appt['service_name'],
		'date'        => $date_label,
		'startTime'   => wp_scheduler_format_time( $appt['start_time'] ),
		'endTime'     => wp_scheduler_format_time( $appt['end_time'] ),
		'price'       => $price_label,
		'notes'       => $appt['notes'],
		'meetLink'    => $meet_link,
		'calendarLink'=> ! is_wp_error( $event ) ? $event['htmlLink'] : null,
	) );
}

function wp_scheduler_format_time( $t ) {
	$parts = explode( ':', $t );
	$h = intval( $parts[0] );
	$m = intval( $parts[1] );
	$p = $h >= 12 ? 'PM' : 'AM';
	$formatted_h = $h % 12 ?: 12;
	return sprintf( '%d:%02d %s', $formatted_h, $m, $p );
}

function wp_scheduler_create_stripe_session( $appt, $site_url, $date_label ) {
	$secret_key = get_option( 'wp_scheduler_stripe_secret_key' );
	if ( ! $secret_key ) {
		throw new Exception( 'STRIPE_SECRET_KEY not set' );
	}

	$body = array(
		'payment_method_types'                                 => array( 'card' ),
		'mode'                                                 => 'payment',
		'customer_email'                                       => $appt['client_email'],
		'line_items'                                           => array(
			array(
				'price_data' => array(
					'currency'     => strtolower( $appt['currency'] ),
					'unit_amount'  => round( $appt['price'] * 100 ),
					'product_data' => array(
						'name'        => $appt['service_name'],
						'description' => sprintf( '%s · %s', $date_label, substr( $appt['start_time'], 0, 5 ) ),
					),
				),
				'quantity'   => 1,
			),
		),
		'metadata'                                             => array( 'appointment_id' => $appt['id'] ),
		'success_url'                                          => $site_url . '/booking-success?appointment_id=' . $appt['id'] . '&session_id={CHECKOUT_SESSION_ID}',
		'cancel_url'                                           => $site_url . '/booking-cancel',
	);

	// Convert nested array into application/x-www-form-urlencoded format that Stripe API expects
	$post_fields = http_build_query( $body );

	$response = wp_remote_post( 'https://api.stripe.com/v1/checkout/sessions', array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $secret_key,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		),
		'body'    => $post_fields,
	) );

	if ( is_wp_error( $response ) ) {
		throw new Exception( 'Stripe communication error: ' . $response->get_error_message() );
	}

	$res_code = wp_remote_retrieve_response_code( $response );
	$res_body = wp_remote_retrieve_body( $response );
	if ( $res_code !== 200 ) {
		throw new Exception( 'Stripe API error: ' . $res_body );
	}

	$data = json_decode( $res_body, true );
	return $data['url'];
}

function wp_scheduler_create_mercadopago_preference( $appt, $site_url, $date_label ) {
	$access_token = get_option( 'wp_scheduler_mp_access_token' );
	if ( ! $access_token ) {
		throw new Exception( 'MP_ACCESS_TOKEN not set' );
	}

	$body = array(
		'items'              => array(
			array(
				'title'       => $appt['service_name'],
				'description' => sprintf( '%s · %s', $date_label, substr( $appt['start_time'], 0, 5 ) ),
				'quantity'    => 1,
				'unit_price'  => $appt['price'],
				'currency_id' => strtoupper( $appt['currency'] ),
			),
		),
		'payer'              => array(
			'email' => $appt['client_email'],
			'name'  => $appt['client_name'],
		),
		'external_reference' => $appt['id'],
		'back_urls'          => array(
			'success' => $site_url . '/booking-success?appointment_id=' . $appt['id'],
			'failure' => $site_url . '/booking-cancel',
			'pending' => $site_url . '/booking-cancel',
		),
		'notification_url'   => rest_url( 'scheduler/v1/webhook' ),
	);

	if ( strpos( $site_url, 'https://' ) === 0 ) {
		$body['auto_return'] = 'approved';
	}

	$response = wp_remote_post( 'https://api.mercadopago.com/checkout/preferences', array(
		'headers' => array(
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		),
		'body'    => json_encode( $body ),
	) );

	if ( is_wp_error( $response ) ) {
		throw new Exception( 'MercadoPago communication error: ' . $response->get_error_message() );
	}

	$res_code = wp_remote_retrieve_response_code( $response );
	$res_body = wp_remote_retrieve_body( $response );
	if ( $res_code !== 201 && $res_code !== 200 ) {
		throw new Exception( 'MercadoPago API error: ' . $res_body );
	}

	$data = json_decode( $res_body, true );
	return $data['init_point'];
}

/**
 * Cancel an appointment.
 */
function wp_scheduler_api_cancel_booking( WP_REST_Request $request ) {
	global $wpdb;
	$token = sanitize_text_field( $request->get_param( 'token' ) );
	if ( ! $token ) {
		return new WP_REST_Response( array( 'error' => 'token required' ), 400 );
	}

	$table_appts    = $wpdb->prefix . 'scheduler_appointments';
	$table_services = $wpdb->prefix . 'scheduler_services';

	$appt = $wpdb->get_row( $wpdb->prepare(
		"SELECT a.*, s.name as service_name, s.language as service_language FROM $table_appts a INNER JOIN $table_services s ON a.service_id = s.id WHERE a.cancel_token = %s LIMIT 1",
		$token
	), ARRAY_A );

	if ( ! $appt ) {
		return new WP_REST_Response( array( 'error' => 'Appointment not found' ), 404 );
	}

	if ( $appt['status'] === 'cancelled' ) {
		return new WP_REST_Response( array( 'error' => 'already_cancelled' ), 409 );
	}

	$appt_date = strtotime( $appt['appointment_date'] );
	if ( $appt_date < strtotime( 'today' ) ) {
		return new WP_REST_Response( array( 'error' => 'past_appointment' ), 409 );
	}

	$wpdb->update( $table_appts, array(
		'status'       => 'cancelled',
		'cancelled_at' => current_time( 'mysql' ),
	), array( 'id' => $appt['id'] ) );

	// Clean up Google Calendar and send cancellation email
	if ( ! empty( $appt['google_event_id'] ) ) {
		wp_scheduler_google_delete_event( $appt['google_event_id'] );
	}

	$lang       = $appt['service_language'] ? $appt['service_language'] : 'es';
	$date_label = date_i18n( 'l, j \d\e F', $appt_date );

	wp_scheduler_send_cancellation_email( array(
		'clientName'  => $appt['client_name'],
		'clientEmail' => $appt['client_email'],
		'serviceName' => $appt['service_name'],
		'date'        => $date_label,
		'startTime'   => wp_scheduler_format_time( $appt['start_time'] ),
	) );

	// Redirect to a cancellation page or return OK
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Handle Webhooks (Stripe / MercadoPago).
 */
function wp_scheduler_api_handle_webhook( WP_REST_Request $request ) {
	global $wpdb;
	$raw_body = $request->get_body();
	$parsed   = json_decode( $raw_body, true );

	$provider = get_option( 'wp_scheduler_payment_provider', 'stripe' );
	$appointment_id = null;

	try {
		if ( $provider === 'stripe' ) {
			$sig = $request->get_header( 'stripe-signature' );
			$webhook_secret = get_option( 'wp_scheduler_stripe_webhook_secret' );
			if ( ! $sig || ! $webhook_secret ) {
				throw new Exception( 'Missing Stripe webhook signature or secret' );
			}

			if ( wp_scheduler_verify_stripe_signature( $raw_body, $sig, $webhook_secret ) ) {
				$event = $parsed;
				if ( isset( $event['type'] ) && $event['type'] === 'checkout.session.completed' ) {
					if ( isset( $event['data']['object']['payment_status'] ) && $event['data']['object']['payment_status'] === 'paid' ) {
						$appointment_id = $event['data']['object']['metadata']['appointment_id'];
					}
				}
			}
		} else {
			// MercadoPago
			$topic = $request->get_param( 'topic' );
			if ( ! $topic ) {
				$topic = $request->get_param( 'type' );
			}
			if ( ! $topic ) {
				$topic = $request->get_header( 'x-notification-topic' );
			}

			if ( $topic === 'payment' ) {
				$payment_id = null;
				if ( ! empty( $parsed['data']['id'] ) ) {
					$payment_id = $parsed['data']['id'];
				} elseif ( ! empty( $parsed['resource'] ) ) {
					$payment_id = $parsed['resource'];
				} elseif ( ! empty( $parsed['id'] ) ) {
					$payment_id = $parsed['id'];
				}

				if ( $payment_id ) {
					$access_token = get_option( 'wp_scheduler_mp_access_token' );
					$mp_response = wp_remote_get( 'https://api.mercadopago.com/v1/payments/' . $payment_id, array(
						'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
					) );

					if ( ! is_wp_error( $mp_response ) && wp_remote_retrieve_response_code( $mp_response ) === 200 ) {
						$payment = json_decode( wp_remote_retrieve_body( $mp_response ), true );
						if ( isset( $payment['status'] ) && $payment['status'] === 'approved' ) {
							$appointment_id = $payment['external_reference'];
						}
					}
				}
			}
		}

		if ( ! $appointment_id ) {
			return new WP_REST_Response( 'ignored', 200 );
		}

		// Process booking confirmation
		$table_appts = $wpdb->prefix . 'scheduler_appointments';
		$appt = $wpdb->get_row( $wpdb->prepare(
			"SELECT status FROM $table_appts WHERE id = %s LIMIT 1",
			$appointment_id
		), ARRAY_A );

		if ( $appt && $appt['status'] === 'pending' ) {
			$wpdb->update( $table_appts, array(
				'status'     => 'paid',
				'updated_at' => current_time( 'mysql' ),
			), array( 'id' => $appointment_id ) );

			// Trigger Google Calendar Sync + Emails
			wp_scheduler_async_confirm_booking( $appointment_id );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	} catch ( Exception $e ) {
		return new WP_REST_Response( array( 'error' => $e->getMessage() ), 500 );
	}
}

function wp_scheduler_verify_stripe_signature( $body, $sig, $secret ) {
	$parts = array();
	foreach ( explode( ',', $sig ) as $part ) {
		$pair = explode( '=', $part );
		if ( count( $pair ) === 2 ) {
			$parts[ $pair[0] ] = $pair[1];
		}
	}

	$ts = isset( $parts['t'] ) ? $parts['t'] : '';
	$v1 = isset( $parts['v1'] ) ? $parts['v1'] : '';
	if ( ! $ts || ! $v1 ) {
		return false;
	}

	$payload = $ts . '.' . $body;
	$computed = hash_hmac( 'sha256', $payload, $secret );

	if ( hash_equals( $computed, $v1 ) ) {
		if ( abs( time() - intval( $ts ) ) < 300 ) {
			return true;
		}
	}

	return false;
}

/**
 * Get appointment details (non-sensitive info).
 */
function wp_scheduler_api_get_appointment( WP_REST_Request $request ) {
	global $wpdb;
	$id = sanitize_text_field( $request->get_param( 'id' ) );
	if ( ! $id ) {
		return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
	}

	$table_appts    = $wpdb->prefix . 'scheduler_appointments';
	$table_services = $wpdb->prefix . 'scheduler_services';

	$appt = $wpdb->get_row( $wpdb->prepare(
		"SELECT a.client_name, a.appointment_date, a.start_time, a.end_time, a.google_meet_link, a.status, s.name as service_name, s.language as service_language FROM $table_appts a INNER JOIN $table_services s ON a.service_id = s.id WHERE a.id = %s LIMIT 1",
		$id
	), ARRAY_A );

	if ( ! $appt ) {
		return new WP_REST_Response( array( 'error' => 'not found' ), 404 );
	}

	return new WP_REST_Response( array(
		'client_name'      => $appt['client_name'],
		'appointment_date' => $appt['appointment_date'],
		'start_time'       => $appt['start_time'],
		'end_time'         => $appt['end_time'],
		'google_meet_link' => $appt['google_meet_link'],
		'status'           => $appt['status'],
		'services'         => array(
			'name'     => $appt['service_name'],
			'language' => $appt['service_language'] ? $appt['service_language'] : 'es',
		),
	), 200 );
}


// ─────────────────────────────────────────────────────────────────────────────
// ADMIN PROTECTED ENDPOINTS IMPLEMENTATION
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Admin API: Get & CRUD Services and schedules.
 */
function wp_scheduler_api_admin_services( WP_REST_Request $request ) {
	global $wpdb;
	$method = $request->get_method();
	$table_services = $wpdb->prefix . 'scheduler_services';
	$table_schedules = $wpdb->prefix . 'scheduler_service_schedules';

	if ( $method === 'GET' ) {
		$services = $wpdb->get_results( "SELECT * FROM $table_services ORDER BY created_at ASC", ARRAY_A );
		$schedules = $wpdb->get_results( "SELECT * FROM $table_schedules ORDER BY service_id ASC, day_of_week ASC", ARRAY_A );

		foreach ( $services as &$svc ) {
			$svc['is_active'] = (bool) $svc['is_active'];
			$svc['duration_minutes'] = intval( $svc['duration_minutes'] );
			$svc['buffer_minutes'] = intval( $svc['buffer_minutes'] );
			$svc['schedules'] = array();
			foreach ( $schedules as $sched ) {
				if ( $sched['service_id'] === $svc['id'] ) {
					$sched['is_active'] = (bool) $sched['is_active'];
					$sched['day_of_week'] = intval( $sched['day_of_week'] );
					$sched['slot_duration_minutes'] = intval( $sched['slot_duration_minutes'] );
					$sched['price'] = floatval( $sched['price'] );
					$svc['schedules'][] = $sched;
				}
			}
		}

		return new WP_REST_Response( array( 'services' => $services ), 200 );
	}

	if ( $method === 'POST' ) {
		$body = $request->get_json_params();
		$action = sanitize_text_field( isset( $body['action'] ) ? $body['action'] : '' );

		if ( $action === 'create_service' ) {
			$new_id = wp_generate_uuid4();
			$wpdb->insert( $table_services, array(
				'id'               => $new_id,
				'name'             => sanitize_text_field( isset( $body['name'] ) ? $body['name'] : 'Nuevo servicio' ),
				'description'      => sanitize_textarea_field( isset( $body['description'] ) ? $body['description'] : '' ),
				'duration_minutes' => intval( isset( $body['duration_minutes'] ) ? $body['duration_minutes'] : 60 ),
				'currency'         => sanitize_text_field( isset( $body['currency'] ) ? $body['currency'] : 'USD' ),
				'timezone'         => sanitize_text_field( isset( $body['timezone'] ) ? $body['timezone'] : 'America/Argentina/Buenos_Aires' ),
				'language'         => sanitize_text_field( isset( $body['language'] ) ? $body['language'] : 'es' ),
				'buffer_minutes'   => intval( isset( $body['buffer_minutes'] ) ? $body['buffer_minutes'] : 0 ),
				'is_active'        => isset( $body['is_active'] ) && $body['is_active'] === false ? 0 : 1,
			) );
			return new WP_REST_Response( array( 'ok' => true, 'id' => $new_id ), 200 );
		}

		if ( $action === 'update_service' ) {
			$id = sanitize_text_field( isset( $body['id'] ) ? $body['id'] : '' );
			if ( ! $id ) {
				return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
			}

			$update = array();
			if ( isset( $body['name'] ) ) {
				$update['name'] = sanitize_text_field( $body['name'] );
			}
			if ( isset( $body['description'] ) ) {
				$update['description'] = sanitize_textarea_field( $body['description'] );
			}
			if ( isset( $body['duration_minutes'] ) ) {
				$update['duration_minutes'] = intval( $body['duration_minutes'] );
			}
			if ( isset( $body['currency'] ) ) {
				$update['currency'] = sanitize_text_field( $body['currency'] );
			}
			if ( isset( $body['timezone'] ) ) {
				$update['timezone'] = sanitize_text_field( $body['timezone'] );
			}
			if ( isset( $body['language'] ) ) {
				$update['language'] = sanitize_text_field( $body['language'] );
			}
			if ( isset( $body['buffer_minutes'] ) ) {
				$update['buffer_minutes'] = intval( $body['buffer_minutes'] );
			}
			if ( isset( $body['is_active'] ) ) {
				$update['is_active'] = $body['is_active'] ? 1 : 0;
			}

			$wpdb->update( $table_services, $update, array( 'id' => $id ) );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		if ( $action === 'delete_service' ) {
			$id = sanitize_text_field( isset( $body['id'] ) ? $body['id'] : '' );
			if ( ! $id ) {
				return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
			}
			$wpdb->delete( $table_services, array( 'id' => $id ) );
			// Also cascade delete schedules
			$wpdb->delete( $table_schedules, array( 'service_id' => $id ) );

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		if ( $action === 'upsert_schedule' ) {
			$schedule_id = isset( $body['id'] ) ? sanitize_text_field( $body['id'] ) : '';
			$service_id  = sanitize_text_field( isset( $body['service_id'] ) ? $body['service_id'] : '' );
			$day_of_week = intval( isset( $body['day_of_week'] ) ? $body['day_of_week'] : 0 );
			$start_time  = sanitize_text_field( isset( $body['start_time'] ) ? $body['start_time'] : '' );
			$end_time    = sanitize_text_field( isset( $body['end_time'] ) ? $body['end_time'] : '' );
			$price       = floatval( isset( $body['price'] ) ? $body['price'] : 0.0 );
			$slot_duration_minutes = intval( isset( $body['slot_duration_minutes'] ) ? $body['slot_duration_minutes'] : 60 );
			$is_active   = isset( $body['is_active'] ) && $body['is_active'] === false ? 0 : 1;

			if ( ! $service_id || ! $start_time || ! $end_time ) {
				return new WP_REST_Response( array( 'error' => 'service_id, start_time and end_time required' ), 400 );
			}

			if ( $schedule_id ) {
				// Update
				$wpdb->update( $table_schedules, array(
					'start_time'            => $start_time,
					'end_time'              => $end_time,
					'price'                 => $price,
					'slot_duration_minutes' => $slot_duration_minutes,
					'is_active'             => $is_active,
				), array( 'id' => $schedule_id ) );
				return new WP_REST_Response( array( 'ok' => true, 'id' => $schedule_id ), 200 );
			} else {
				// Insert
				$new_id = wp_generate_uuid4();
				$wpdb->insert( $table_schedules, array(
					'id'                    => $new_id,
					'service_id'            => $service_id,
					'day_of_week'           => $day_of_week,
					'start_time'            => $start_time,
					'end_time'              => $end_time,
					'price'                 => $price,
					'slot_duration_minutes' => $slot_duration_minutes,
					'is_active'             => $is_active,
				) );
				return new WP_REST_Response( array( 'ok' => true, 'id' => $new_id ), 200 );
			}
		}

		if ( $action === 'delete_schedule' ) {
			$id = sanitize_text_field( isset( $body['id'] ) ? $body['id'] : '' );
			if ( ! $id ) {
				return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
			}
			$wpdb->delete( $table_schedules, array( 'id' => $id ) );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}
	}

	return new WP_REST_Response( array( 'error' => 'Invalid Request' ), 400 );
}

/**
 * Admin API: Get & Update Configuration.
 */
function wp_scheduler_api_admin_config( WP_REST_Request $request ) {
	$method = $request->get_method();

	$editable_keys = array(
		'PAYMENT_PROVIDER',
		'STRIPE_SECRET_KEY',
		'STRIPE_WEBHOOK_SECRET',
		'MP_ACCESS_TOKEN',
		'MP_WEBHOOK_SECRET',
		'GOOGLE_CLIENT_ID',
		'GOOGLE_CLIENT_SECRET',
		'GOOGLE_REFRESH_TOKEN',
		'GOOGLE_CALENDAR_ID',
		'EMAIL_FROM',
		'EMAIL_FROM_NAME',
		'ADMIN_EMAIL',
		'APP_URL',
		'ADMIN_SECRET', // Keeping for compatibility, though nonce is used for WP REST Auth
	);

	if ( $method === 'GET' ) {
		$config = array();
		foreach ( $editable_keys as $key ) {
			$val = get_option( 'wp_scheduler_' . strtolower( $key ), '' );
			$is_plain = in_array( $key, array( 'PAYMENT_PROVIDER', 'EMAIL_FROM', 'EMAIL_FROM_NAME', 'ADMIN_EMAIL', 'APP_URL' ) );
			if ( $is_plain ) {
				$config[ $key ] = $val;
			} else {
				$config[ $key ] = wp_scheduler_mask_secret( $val );
			}
		}
		return new WP_REST_Response( array( 'config' => $config, 'envWritable' => true ), 200 );
	}

	if ( $method === 'POST' ) {
		$body = $request->get_json_params();
		$updates = isset( $body['updates'] ) ? $body['updates'] : array();

		$updated_keys = array();
		foreach ( $updates as $key => $val ) {
			if ( ! in_array( $key, $editable_keys ) ) {
				continue;
			}
			if ( strpos( $val, '••••' ) !== false ) {
				continue; // Masked value unchanged
			}
			update_option( 'wp_scheduler_' . strtolower( $key ), sanitize_text_field( $val ) );
			$updated_keys[] = $key;
		}

		return new WP_REST_Response( array( 'ok' => true, 'updated' => $updated_keys ), 200 );
	}
}

function wp_scheduler_mask_secret( $value ) {
	if ( empty( $value ) ) {
		return '';
	}
	if ( strlen( $value ) <= 8 ) {
		return '••••••••';
	}
	return substr( $value, 0, 4 ) . '••••••••' . substr( $value, -4 );
}

/**
 * Admin API: Get & Update Appointments / Block dates.
 */
function wp_scheduler_api_admin_appointments( WP_REST_Request $request ) {
	global $wpdb;
	$method = $request->get_method();
	$table_appts    = $wpdb->prefix . 'scheduler_appointments';
	$table_services = $wpdb->prefix . 'scheduler_services';
	$table_blocked  = $wpdb->prefix . 'scheduler_blocked_dates';

	if ( $method === 'GET' ) {
		$view = sanitize_text_field( $request->get_param( 'view' ) ? $request->get_param( 'view' ) : 'upcoming' );
		$from = sanitize_text_field( $request->get_param( 'from' ) );
		$to   = sanitize_text_field( $request->get_param( 'to' ) );

		if ( $view === 'blocked' ) {
			if ( $from && $to ) {
				$blocked = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM $table_blocked WHERE blocked_date >= %s AND blocked_date <= %s ORDER BY blocked_date ASC, start_time ASC",
					$from, $to
				), ARRAY_A );
			} else {
				$blocked = $wpdb->get_results( "SELECT * FROM $table_blocked ORDER BY blocked_date ASC, start_time ASC", ARRAY_A );
			}
			return new WP_REST_Response( array( 'blocked' => $blocked ), 200 );
		}

		$today = date( 'Y-m-d' );

		if ( $view === 'upcoming' ) {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT a.*, s.name as service_name FROM $table_appts a INNER JOIN $table_services s ON a.service_id = s.id WHERE a.appointment_date >= %s AND a.status != 'cancelled' ORDER BY a.appointment_date ASC, a.start_time ASC",
				$today
			), ARRAY_A );
		} elseif ( $view === 'range' && $from && $to ) {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT a.*, s.name as service_name FROM $table_appts a INNER JOIN $table_services s ON a.service_id = s.id WHERE a.appointment_date >= %s AND a.appointment_date <= %s ORDER BY a.appointment_date ASC, a.start_time ASC",
				$from, $to
			), ARRAY_A );
		} else {
			$results = array();
		}

		$appointments = array();
		foreach ( $results as $r ) {
			$appointments[] = array(
				'id'               => $r['id'],
				'client_name'      => $r['client_name'],
				'client_email'     => $r['client_email'],
				'client_phone'     => $r['client_phone'],
				'appointment_date' => $r['appointment_date'],
				'start_time'       => $r['start_time'],
				'end_time'         => $r['end_time'],
				'price'            => floatval( $r['price'] ),
				'currency'         => $r['currency'],
				'status'           => $r['status'],
				'notes'            => $r['notes'],
				'cancel_token'     => $r['cancel_token'],
				'google_meet_link' => $r['google_meet_link'],
				'services'         => array( 'name' => $r['service_name'] ),
			);
		}

		return new WP_REST_Response( array( 'appointments' => $appointments ), 200 );
	}

	if ( $method === 'POST' ) {
		$body   = $request->get_json_params();
		$action = sanitize_text_field( isset( $body['action'] ) ? $body['action'] : '' );
		$id     = sanitize_text_field( isset( $body['id'] ) ? $body['id'] : '' );

		if ( ! $action ) {
			return new WP_REST_Response( array( 'error' => 'action required' ), 400 );
		}

		if ( $action === 'block_date' ) {
			$block_date = sanitize_text_field( isset( $body['date'] ) ? $body['date'] : '' );
			$service_id = isset( $body['service_id'] ) ? sanitize_text_field( $body['service_id'] ) : null;
			$reason     = isset( $body['reason'] ) ? sanitize_textarea_field( $body['reason'] ) : null;
			$start_time = isset( $body['start_time'] ) ? sanitize_text_field( $body['start_time'] ) : null;
			$end_time   = isset( $body['end_time'] ) ? sanitize_text_field( $body['end_time'] ) : null;

			if ( ! $block_date ) {
				return new WP_REST_Response( array( 'error' => 'date required' ), 400 );
			}

			$timezone = 'America/Argentina/Buenos_Aires';
			if ( $service_id ) {
				$svc_tz = $wpdb->get_var( $wpdb->prepare( "SELECT timezone FROM $table_services WHERE id = %s LIMIT 1", $service_id ) );
				if ( $svc_tz ) {
					$timezone = $svc_tz;
				}
			} else {
				$svc_tz = $wpdb->get_var( "SELECT timezone FROM $table_services LIMIT 1" );
				if ( $svc_tz ) {
					$timezone = $svc_tz;
				}
			}

			$block_id = wp_generate_uuid4();
			$wpdb->insert( $table_blocked, array(
				'id'           => $block_id,
				'blocked_date' => $block_date,
				'service_id'   => $service_id,
				'reason'       => $reason,
				'start_time'   => $start_time,
				'end_time'     => $end_time,
			) );

			// Call Google Calendar to block the date
			$event_id = wp_scheduler_google_create_block_event( $block_date, $timezone, $start_time, $end_time );
			if ( ! is_wp_error( $event_id ) ) {
				$wpdb->update( $table_blocked, array( 'google_event_id' => $event_id ), array( 'id' => $block_id ) );
			}

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		if ( $action === 'unblock_date' ) {
			if ( ! $id ) {
				return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
			}

			$block = $wpdb->get_row( $wpdb->prepare( "SELECT google_event_id FROM $table_blocked WHERE id = %s LIMIT 1", $id ), ARRAY_A );
			$wpdb->delete( $table_blocked, array( 'id' => $id ) );

			if ( ! empty( $block['google_event_id'] ) ) {
				wp_scheduler_google_delete_event( $block['google_event_id'] );
			}

			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$status_map = array(
			'cancel'         => 'cancelled',
			'mark_noshow'    => 'no_show',
			'mark_completed' => 'completed',
		);

		$new_status = isset( $status_map[ $action ] ) ? $status_map[ $action ] : '';
		if ( ! $new_status ) {
			return new WP_REST_Response( array( 'error' => 'unknown action' ), 400 );
		}
		if ( ! $id ) {
			return new WP_REST_Response( array( 'error' => 'id required' ), 400 );
		}

		$update = array( 'status' => $new_status );
		if ( $action === 'cancel' ) {
			$update['cancelled_at'] = current_time( 'mysql' );
		}

		$wpdb->update( $table_appts, $update, array( 'id' => $id ) );

		if ( $action === 'cancel' ) {
			$google_event_id = $wpdb->get_var( $wpdb->prepare( "SELECT google_event_id FROM $table_appts WHERE id = %s", $id ) );
			if ( $google_event_id ) {
				wp_scheduler_google_delete_event( $google_event_id );
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	return new WP_REST_Response( array( 'error' => 'Invalid Request' ), 400 );
}
