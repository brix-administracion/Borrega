<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Creates custom database tables for the scheduler.
 */
function wp_scheduler_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = array();

	// Services Table
	$table_services = $wpdb->prefix . 'scheduler_services';
	$sql[] = "CREATE TABLE $table_services (
		id varchar(36) NOT NULL,
		name text NOT NULL,
		description text,
		duration_minutes int NOT NULL DEFAULT 60,
		currency varchar(10) NOT NULL DEFAULT 'USD',
		timezone varchar(100) NOT NULL DEFAULT 'America/Argentina/Buenos_Aires',
		language varchar(5) NOT NULL DEFAULT 'es',
		buffer_minutes int NOT NULL DEFAULT 0,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";

	// Service Schedules Table
	$table_schedules = $wpdb->prefix . 'scheduler_service_schedules';
	$sql[] = "CREATE TABLE $table_schedules (
		id varchar(36) NOT NULL,
		service_id varchar(36) NOT NULL,
		day_of_week int NOT NULL,
		start_time time NOT NULL,
		end_time time NOT NULL,
		price decimal(10,2) NOT NULL DEFAULT '0.00',
		slot_duration_minutes int NOT NULL DEFAULT 60,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (id)
	) $charset_collate;";

	// Appointments Table
	$table_appointments = $wpdb->prefix . 'scheduler_appointments';
	$sql[] = "CREATE TABLE $table_appointments (
		id varchar(36) NOT NULL,
		service_id varchar(36) NOT NULL,
		schedule_id varchar(36),
		client_name text NOT NULL,
		client_email varchar(255) NOT NULL,
		client_phone varchar(50),
		appointment_date date NOT NULL,
		start_time time NOT NULL,
		end_time time NOT NULL,
		price decimal(10,2) NOT NULL DEFAULT '0.00',
		currency varchar(10) NOT NULL DEFAULT 'USD',
		timezone varchar(100) NOT NULL DEFAULT 'UTC',
		status varchar(30) NOT NULL DEFAULT 'pending',
		payment_provider varchar(50),
		payment_intent_id varchar(255),
		cancel_token varchar(36) NOT NULL,
		google_event_id varchar(255),
		google_meet_link text,
		notes text,
		cancelled_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		UNIQUE KEY cancel_token (cancel_token)
	) $charset_collate;";

	// Blocked Dates Table
	$table_blocked = $wpdb->prefix . 'scheduler_blocked_dates';
	$sql[] = "CREATE TABLE $table_blocked (
		id varchar(36) NOT NULL,
		blocked_date date NOT NULL,
		service_id varchar(36),
		reason text,
		start_time time,
		end_time time,
		google_event_id varchar(255),
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( $sql as $query ) {
		dbDelta( $query );
	}
}
