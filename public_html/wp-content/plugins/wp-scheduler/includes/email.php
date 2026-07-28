<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Base styles for HTML emails.
 */
function wp_scheduler_get_email_styles() {
	return '
		body{margin:0;padding:0;background:#f8f8f8;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
		.wrap{max-width:520px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #e4e4e4}
		.header{padding:32px 40px 24px;border-bottom:1px solid #f0f0f0}
		.body{padding:32px 40px}
		.detail-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f8f8f8}
		.label{font-size:12px;color:#9a9a9a;font-weight:500}
		.value{font-size:13px;color:#1a1a1a;font-weight:500;text-align:right}
		.btn{display:inline-block;background:#1a1a1a;color:#fff;text-decoration:none;padding:12px 24px;border-radius:100px;font-size:13px;font-weight:500;margin-top:24px}
		.btn-ghost{display:inline-block;color:#9a9a9a;text-decoration:none;font-size:12px;margin-top:16px}
		.footer{padding:20px 40px;text-align:center;font-size:11px;color:#b4b4b4}
		.meet-box{margin-top:24px;background:#f8f8f8;border-radius:12px;padding:16px 20px}
		.meet-label{margin:0 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#9a9a9a;font-weight:600}
	';
}

/**
 * Wrap content in the template.
 */
function wp_scheduler_email_wrap( $header, $body, $footer = '' ) {
	$styles = wp_scheduler_get_email_styles();
	$footer = $footer ? $footer : 'This email was sent automatically. Please do not reply.';
	return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>' . $styles . '</style></head><body>
<div class="wrap">
  <div class="header">' . $header . '</div>
  <div class="body">' . $body . '</div>
  <div class="footer">' . esc_html( $footer ) . '</div>
</div></body></html>';
}

function wp_scheduler_email_detail_row( $label, $value, $last = false ) {
	$style = $last ? 'border-bottom:none' : '';
	return '<div class="detail-row" style="' . $style . '">
		<span class="label">' . esc_html( $label ) . '</span><span class="value">' . esc_html( $value ) . '</span>
	</div>';
}

/**
 * Send email using wp_mail.
 */
function wp_scheduler_send_email( $to, $subject, $html ) {
	$from_email = get_option( 'wp_scheduler_email_from', get_option( 'admin_email' ) );
	$from_name  = get_option( 'wp_scheduler_email_from_name', get_bloginfo( 'name' ) );

	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		sprintf( 'From: %s <%s>', $from_name, $from_email ),
	);

	wp_mail( $to, $subject, $html, $headers );
}

/**
 * Send appointment confirmation email to client.
 */
function wp_scheduler_send_confirmation_email( $d ) {
	$header = '
		<p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#9a9a9a;font-weight:600">Booking Confirmed</p>
		<h1 style="margin:8px 0 0;font-size:22px;font-weight:600;color:#1a1a1a">' . esc_html( $d['serviceName'] ) . '</h1>';

	$custom_template = get_option( 'wp_scheduler_email_template_confirmation', '' );

	if ( ! empty( $custom_template ) ) {
		$body = str_replace(
			array( '{clientName}', '{serviceName}', '{date}', '{startTime}', '{endTime}', '{price}', '{meetLink}', '{cancelLink}' ),
			array(
				esc_html( $d['clientName'] ),
				esc_html( $d['serviceName'] ),
				esc_html( $d['date'] ),
				esc_html( $d['startTime'] ),
				esc_html( $d['endTime'] ),
				esc_html( $d['price'] ),
				! empty( $d['meetLink'] ) ? esc_url( $d['meetLink'] ) : '',
				! empty( $d['cancelLink'] ) ? esc_url( $d['cancelLink'] ) : ''
			),
			$custom_template
		);
	} else {
		$body = '
			<p style="margin:0 0 20px;font-size:14px;color:#5a5a5a">Hi ' . esc_html( $d['clientName'] ) . ', your booking is confirmed.</p>
			<div>
				' . wp_scheduler_email_detail_row( 'Date', $d['date'] ) . '
				' . wp_scheduler_email_detail_row( 'Time', $d['startTime'] . ' – ' . $d['endTime'] ) . '
				' . wp_scheduler_email_detail_row( 'Service', $d['serviceName'] ) . '
				' . wp_scheduler_email_detail_row( 'Amount', $d['price'], true ) . '
			</div>';

		if ( ! empty( $d['meetLink'] ) ) {
			$body .= '
			<div class="meet-box">
				<p class="meet-label">Google Meet</p>
				<a href="' . esc_url( $d['meetLink'] ) . '" style="font-size:13px;color:#1a1a1a;word-break:break-all">' . esc_html( $d['meetLink'] ) . '</a>
			</div>
			<a class="btn" href="' . esc_url( $d['meetLink'] ) . '">Join Meeting</a>';
		}

		if ( ! empty( $d['cancelLink'] ) ) {
			$body .= '<br><a class="btn-ghost" href="' . esc_url( $d['cancelLink'] ) . '">Cancel appointment</a>';
		}
	}

	$html = wp_scheduler_email_wrap( $header, $body );

	wp_scheduler_send_email( $d['clientEmail'], sprintf( 'Confirmed: %s on %s', $d['serviceName'], $d['date'] ), $html );
}

/**
 * Send appointment reminder email to client.
 */
function wp_scheduler_send_reminder_email( $d ) {
	$header = '
		<p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#9a9a9a;font-weight:600">Reminder</p>
		<h1 style="margin:8px 0 0;font-size:22px;font-weight:600;color:#1a1a1a">Your appointment is tomorrow</h1>';

	$body = '
		<p style="margin:0 0 20px;font-size:14px;color:#5a5a5a">Hi ' . esc_html( $d['clientName'] ) . ', just a reminder about your upcoming session.</p>
		<div>
			' . wp_scheduler_email_detail_row( 'Service', $d['serviceName'] ) . '
			' . wp_scheduler_email_detail_row( 'Date', $d['date'] ) . '
			' . wp_scheduler_email_detail_row( 'Time', $d['startTime'] . ' – ' . $d['endTime'], true ) . '
		</div>';

	if ( ! empty( $d['meetLink'] ) ) {
		$body .= '
		<div class="meet-box">
			<p class="meet-label">Google Meet</p>
			<a href="' . esc_url( $d['meetLink'] ) . '" style="font-size:13px;color:#1a1a1a;word-break:break-all">' . esc_html( $d['meetLink'] ) . '</a>
		</div>
		<a class="btn" href="' . esc_url( $d['meetLink'] ) . '">Join Meeting</a>';
	}

	if ( ! empty( $d['cancelLink'] ) ) {
		$body .= '<br><a class="btn-ghost" href="' . esc_url( $d['cancelLink'] ) . '">Cancel appointment</a>';
	}

	$html = wp_scheduler_email_wrap( $header, $body );
	wp_scheduler_send_email( $d['clientEmail'], sprintf( 'Reminder: %s tomorrow at %s', $d['serviceName'], $d['startTime'] ), $html );
}

/**
 * Send admin notification email about new booking.
 */
function wp_scheduler_send_admin_notification_email( $d ) {
	$admin_email = get_option( 'wp_scheduler_admin_email', get_option( 'admin_email' ) );
	if ( ! $admin_email ) {
		return;
	}

	$header = '
		<p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#9a9a9a;font-weight:600">New Booking</p>
		<h1 style="margin:8px 0 0;font-size:22px;font-weight:600;color:#1a1a1a">' . esc_html( $d['serviceName'] ) . '</h1>';

	$body = '
		<p style="margin:0 0 20px;font-size:14px;color:#5a5a5a">A new appointment has been booked.</p>
		<div>
			' . wp_scheduler_email_detail_row( 'Client', $d['clientName'] ) . '
			' . wp_scheduler_email_detail_row( 'Email', $d['clientEmail'] ) . '
			' . ( ! empty( $d['clientPhone'] ) ? wp_scheduler_email_detail_row( 'Phone', $d['clientPhone'] ) : '' ) . '
			' . wp_scheduler_email_detail_row( 'Date', $d['date'] ) . '
			' . wp_scheduler_email_detail_row( 'Time', $d['startTime'] . ' – ' . $d['endTime'] ) . '
			' . wp_scheduler_email_detail_row( 'Amount', $d['price'], empty( $d['notes'] ) ) . '
			' . ( ! empty( $d['notes'] ) ? wp_scheduler_email_detail_row( 'Notes', $d['notes'], true ) : '' ) . '
		</div>';

	if ( ! empty( $d['meetLink'] ) ) {
		$body .= '
		<div class="meet-box" style="margin-top:20px;background:#f0f8ff;border:1px solid #cce5ff;padding:15px;border-radius:8px;">
			<p class="meet-label" style="margin:0 0 5px;font-weight:bold;color:#004085;">Enlace de Google Meet</p>
			<a href="' . esc_url( $d['meetLink'] ) . '" style="color:#0056b3;text-decoration:none;word-break:break-all;">' . esc_html( $d['meetLink'] ) . '</a>
		</div>';
	}

	if ( ! empty( $d['calendarLink'] ) ) {
		$body .= '
		<div style="margin-top:15px;">
			<a href="' . esc_url( $d['calendarLink'] ) . '" style="color:#0056b3;text-decoration:none;font-size:13px;">Ver en Google Calendar &rarr;</a>
		</div>';
	}

	$html = wp_scheduler_email_wrap( $header, $body, 'Booking system notification.' );
	wp_scheduler_send_email( $admin_email, sprintf( 'New booking: %s — %s on %s', $d['clientName'], $d['serviceName'], $d['date'] ), $html );
}

/**
 * Send cancellation email to client.
 */
function wp_scheduler_send_cancellation_email( $d ) {
	$header = '
		<p style="margin:0;font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#9a9a9a;font-weight:600">Appointment Cancelled</p>
		<h1 style="margin:8px 0 0;font-size:22px;font-weight:600;color:#1a1a1a">' . esc_html( $d['serviceName'] ) . '</h1>';

	$body = '
		<p style="margin:0 0 20px;font-size:14px;color:#5a5a5a">Hi ' . esc_html( $d['clientName'] ) . ', your appointment has been successfully cancelled.</p>
		<div>
			' . wp_scheduler_email_detail_row( 'Service', $d['serviceName'] ) . '
			' . wp_scheduler_email_detail_row( 'Date', $d['date'] ) . '
			' . wp_scheduler_email_detail_row( 'Time', $d['startTime'], true ) . '
		</div>
		<p style="margin-top:24px;font-size:13px;color:#9a9a9a">If you\'d like to book a new session, you can do so at any time.</p>';

	$html = wp_scheduler_email_wrap( $header, $body );
	wp_scheduler_send_email( $d['clientEmail'], sprintf( 'Cancelled: %s on %s', $d['serviceName'], $d['date'] ), $html );
}
