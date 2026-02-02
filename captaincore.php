<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://captaincore.io
 * @since             0.1.0
 * @package           Captaincore
 *
 * @wordpress-plugin
 * Plugin Name:       CaptainCore Manager
 * Plugin URI:        https://captaincore.io
 * Description:       WordPress management toolkit for geeky maintenance professionals.
 * Version:           0.18.0
 * Author:            Austin Ginder
 * Author URI:        https://austinginder.com
 * License:           MIT License
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       captaincore
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function activate_captaincore() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-captaincore-activator.php';
	Captaincore_Activator::activate();
	flush_rewrite_rules();
}

function deactivate_captaincore() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-captaincore-deactivator.php';
	Captaincore_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_captaincore' );
register_deactivation_hook( __FILE__, 'deactivate_captaincore' );
require plugin_dir_path( __FILE__ ) . 'includes/class-captaincore.php';
require plugin_dir_path( __FILE__ ) . 'app/Router.php';
new CaptainCore\Router();

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */

( new Captaincore )->run();

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require 'includes/Parsedown.php';

function captaincore_cron_run() {
	CaptainCore\Accounts::auto_switch_plans();
    CaptainCore\Accounts::process_renewals();
    CaptainCore\Accounts::process_pending_ach_payments();
    CaptainCore\Scripts::run_scheduled();
}
add_action( 'captaincore_cron', 'captaincore_cron_run' );

// Hook to run Mailgun verify at a later time
add_action( 'schedule_mailgun_verify', '\CaptainCore\Providers\Mailgun::verify', 10, 3 );

// Hook to run Mailgun setup retry if rate-limited
add_action( 'schedule_mailgun_retry', '\CaptainCore\Providers\Mailgun::setup', 10, 1 );

/* -------------------------------------------------------------------------
 *  WP-CLI COMMANDS
 * ------------------------------------------------------------------------- */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'captaincore web-risk-check', [ 'CaptainCore\WebRiskCheck', 'run' ] );
	WP_CLI::add_command( 'captaincore scheduled-reports', 'CaptainCore\ScheduledReportsCLI' );
}

/* -------------------------------------------------------------------------
 *  CUSTOM EMAIL TRIGGERS
 * ------------------------------------------------------------------------- */

function captaincore_failed_notify( $order_id, $old_status, $new_status ){
	echo "Woocommerce  $order_id, $old_status, $new_status ";
    if ( $new_status == 'failed' and $old_status != "failed" ){
		$order      = wc_get_order( $order_id );
		$account_id = $order->get_meta( "captaincore_account_id" );
		( new CaptainCore\Account( $account_id, true ) )->failed_notify( $order_id );
    }
}
add_action( 'woocommerce_order_status_changed', 'captaincore_failed_notify', 10, 3);

// 1. Customer Receipt (When order is marked completed)
add_action( 'woocommerce_order_status_completed', function( $order_id ) {
    $order = wc_get_order( $order_id );
    // Ensure we don't send duplicates if status bounces, check meta
    if ( $order && $order->get_meta( 'captaincore_account_id' ) && ! $order->get_meta( '_captaincore_receipt_sent' ) ) {
        \CaptainCore\Mailer::send_customer_receipt( $order_id );
        $order->update_meta_data( '_captaincore_receipt_sent', 'true' );
        $order->save();
    }
}, 20, 1 );

// 2. Customer Refund Notification (When a refund is created)
add_action( 'woocommerce_order_refunded', function( $order_id, $refund_id ) {
    $order = wc_get_order( $order_id );
    // Only send for CaptainCore managed orders
    if ( $order && $order->get_meta( 'captaincore_account_id' ) ) {
        \CaptainCore\Mailer::send_customer_refund( $order_id, $refund_id );
    }
}, 10, 2 );

// Disable WooCommerce refund emails for CaptainCore managed orders
add_filter( 'woocommerce_email_enabled_customer_refunded_order', function( $enabled, $order ) {
    if ( $order && $order->get_meta( 'captaincore_account_id' ) ) {
        return false;
    }
    return $enabled;
}, 10, 2 );

// 3. Newsletter Email (When a post is published)
function captaincore_send_newsletter_on_publish( $new_status, $old_status, $post ) {
    // Only trigger on new publish, not updates to already published posts
    if ( $new_status !== 'publish' || $old_status === 'publish' ) {
        return;
    }

    // Only for standard posts
    if ( $post->post_type !== 'post' ) {
        return;
    }

    // Prevent duplicate sends
    if ( get_post_meta( $post->ID, '_captaincore_newsletter_sent', true ) ) {
        return;
    }

    // Get all email subscribers
    $subscribers = get_users( [ 'role' => 'email_subscriber' ] );

    if ( empty( $subscribers ) ) {
        return;
    }

    // Send to each subscriber
    foreach ( $subscribers as $user ) {
        \CaptainCore\Mailer::send_new_post_notification( $post->ID, $user );
    }

    // Store send history as JSON for admin display
    $send_history = [
        'sent_at'          => current_time( 'mysql' ),
        'recipient_count'  => count( $subscribers ),
    ];
    update_post_meta( $post->ID, '_captaincore_newsletter_sent', wp_json_encode( $send_history ) );
}
add_action( 'transition_post_status', 'captaincore_send_newsletter_on_publish', 10, 3 );

/* -------------------------------------------------------------------------
 *  EMAIL SUBSCRIPTION MANAGEMENT (Legacy wp-signup.php handlers)
 *  Note: New unsubscribe links use REST API at /wp-json/captaincore/v1/email/subscription
 *  These handlers remain for backward compatibility with old email links.
 * ------------------------------------------------------------------------- */

/**
 * Manage subscription to email newsletters (subscribe/unsubscribe via token)
 * Legacy handler - redirects to REST API endpoint
 */
function captaincore_subscription_management() {
    global $pagenow;

    if ( 'wp-signup.php' !== $pagenow || ! isset( $_GET['id'], $_GET['email'], $_GET['token'], $_GET['action'] ) ) {
        return;
    }

    // Redirect to REST API endpoint for processing
    $redirect_url = add_query_arg( [
        'id'     => absint( $_GET['id'] ),
        'email'  => sanitize_email( $_GET['email'] ),
        'token'  => sanitize_text_field( $_GET['token'] ),
        'action' => sanitize_text_field( $_GET['action'] )
    ], rest_url( 'captaincore/v1/email/subscription' ) );

    wp_redirect( $redirect_url );
    exit;
}
add_action( 'init', 'captaincore_subscription_management' );

/**
 * Manage subscription signup via Gravity Forms confirmation
 */
function captaincore_subscription_signup() {
    global $pagenow;

    if ( 'wp-signup.php' !== $pagenow || ! isset( $_GET['entry_email'], $_GET['entry_id'], $_GET['action'] ) ) {
        return;
    }

    if ( ! class_exists( 'GFAPI' ) ) {
        return;
    }

    $entry   = GFAPI::get_entry( absint( $_GET['entry_id'] ) );
    $form_id = $entry['form_id'] ?? null;

    // Verify Form ID #4 (newsletter signup form)
    if ( $form_id != '4' ) {
        wp_die( "<p style='text-align:center'>Invalid email subscription signup link.</p>", "Manage subscription" );
    }

    $email = sanitize_email( $_GET['entry_email'] );
    if ( ! $entry || empty( $entry[2] ) || $entry[2] !== $email ) {
        wp_die( "<p style='text-align:center'>Invalid email subscription signup link.</p>", "Manage subscription" );
    }

    $user = get_user_by( 'email', $email );

    // Create user if needed
    if ( ! $user ) {
        $user_id = wp_insert_user( [
            'user_login' => $email,
            'user_email' => $email,
            'role'       => 'email_subscriber',
        ] );
        $user = get_user_by( 'ID', $user_id );
    }

    $user_token = wp_hash( $user->user_registered );
    $user_id    = $user->ID;
    $user_email = $user->user_email;
    $base_url   = rest_url( 'captaincore/v1/email/subscription' );

    if ( $_GET['action'] === 'subscribe_confirm' ) {
        $user->add_role( 'email_subscriber' );
        $unsubscribe_url = add_query_arg( [
            'id'     => $user_id,
            'email'  => $user_email,
            'token'  => $user_token,
            'action' => 'unsubscribe'
        ], $base_url );
        $html = "<p style='text-align:center'>Your email <strong>{$email}</strong> has been subscribed.<br>
            <small>Click here to <a href='{$unsubscribe_url}'>unsubscribe</a></small></p>";
        wp_die( $html, "Subscribe to email newsletters" );
    }
}
add_action( 'init', 'captaincore_subscription_signup' );

/**
 * Register meta box for newsletter send history on post edit screen
 */
function captaincore_newsletter_meta_box() {
    add_meta_box(
        'captaincore_newsletter_status',
        'Newsletter Status',
        'captaincore_newsletter_meta_box_callback',
        'post',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'captaincore_newsletter_meta_box' );

/**
 * Display newsletter send history in meta box
 */
function captaincore_newsletter_meta_box_callback( $post ) {
    $send_history   = get_post_meta( $post->ID, '_captaincore_newsletter_sent', true );
    $editor_history = get_post_meta( $post->ID, '_captaincore_newsletter_sent_editors', true );
    $current_user   = wp_get_current_user();
    
    // Show subscriber count
    $subscribers = get_users( [ 'role' => 'email_subscriber' ] );
    $count = count( $subscribers );

    if ( empty( $send_history ) ) {
        echo '<p style="color: #666;">Newsletter has not been sent for this post.</p>';
        
        // Show editor review status if sent
        if ( ! empty( $editor_history ) ) {
            $editor_data = json_decode( $editor_history, true );
            if ( is_array( $editor_data ) && isset( $editor_data['sent_at'] ) ) {
                $formatted_date = date_i18n( 'F j, Y \a\t g:i a', strtotime( $editor_data['sent_at'] ) );
                echo '<div style="background: #e7f3ff; border: 1px solid #b3d7ff; padding: 8px 10px; border-radius: 4px; margin-bottom: 12px;">';
                echo '<p style="margin: 0; font-size: 12px; color: #004085;">';
                echo '<strong>Sent to editors for review</strong><br>';
                echo '<span style="color: #666;">' . esc_html( $formatted_date ) . '</span>';
                echo '</p>';
                echo '</div>';
            }
        }
        
        if ( $count > 0 ) {
            echo '<p style="color: #666; font-size: 12px;">There are currently <strong>' . $count . '</strong> email subscribers.</p>';
        }
    } else {
        $history = json_decode( $send_history, true );

        if ( is_array( $history ) && isset( $history['sent_at'] ) ) {
            $sent_at         = $history['sent_at'];
            $recipient_count = $history['recipient_count'] ?? 0;
            $formatted_date  = date_i18n( 'F j, Y \a\t g:i a', strtotime( $sent_at ) );

            echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; margin-bottom: 12px;">';
            echo '<p style="margin: 0 0 5px; color: #155724;"><strong>Sent successfully</strong></p>';
            echo '<p style="margin: 0; font-size: 12px; color: #155724;">';
            echo 'Sent <strong>' . $recipient_count . '</strong> email' . ( $recipient_count !== 1 ? 's' : '' ) . '<br>';
            echo '<span style="color: #666;">' . esc_html( $formatted_date ) . '</span>';
            echo '</p>';
            echo '</div>';
        } else {
            // Legacy format (just timestamp string)
            echo '<p style="color: #155724; margin-bottom: 12px;">Newsletter sent on ' . esc_html( $send_history ) . '</p>';
        }
    }

    // Manual send section (admin only)
    if ( current_user_can( 'manage_options' ) ) {
        wp_nonce_field( 'captaincore_newsletter_send', 'captaincore_newsletter_nonce' );
        
        // Get editor count
        $editors = get_users( [ 'role' => 'editor' ] );
        $editor_count = count( $editors );
        
        echo '<div style="border-top: 1px solid #ddd; padding-top: 12px; margin-top: 12px;">';
        echo '<p style="margin: 0 0 8px; font-weight: 600; font-size: 12px;">Manual Send</p>';
        
        // Preview to self
        echo '<div style="margin-bottom: 10px;">';
        echo '<button type="button" class="button" id="captaincore-send-preview" data-post-id="' . esc_attr( $post->ID ) . '" style="width: 100%;">Send Preview to Me</button>';
        echo '<p style="margin: 4px 0 0; font-size: 11px; color: #666;">Sends to: ' . esc_html( $current_user->user_email ) . '</p>';
        echo '</div>';
        
        // Send to editors for review
        if ( $editor_count > 0 ) {
            echo '<div style="margin-bottom: 10px;">';
            echo '<button type="button" class="button" id="captaincore-send-editors" data-post-id="' . esc_attr( $post->ID ) . '" style="width: 100%;">Send to Editors (' . $editor_count . ')</button>';
            echo '<p style="margin: 4px 0 0; font-size: 11px; color: #666;">Send preview to editors for review.</p>';
            echo '</div>';
        }
        
        // Send to all subscribers
        if ( $count > 0 ) {
            echo '<div style="margin-bottom: 10px;">';
            echo '<button type="button" class="button" id="captaincore-send-all" data-post-id="' . esc_attr( $post->ID ) . '" style="width: 100%;">Send to All Subscribers (' . $count . ')</button>';
            echo '<p style="margin: 4px 0 0; font-size: 11px; color: #666;">This will send immediately to all subscribers.</p>';
            echo '</div>';
        }
        
        // Send to custom email
        echo '<div style="margin-top: 15px; padding-top: 12px; border-top: 1px solid #ddd;">';
        echo '<p style="margin: 0 0 8px; font-weight: 600; font-size: 12px;">Send to Email</p>';
        echo '<div style="display: flex; gap: 5px;">';
        echo '<input type="email" id="captaincore-custom-email" placeholder="email@example.com" style="flex: 1; min-width: 0;">';
        echo '<button type="button" class="button" id="captaincore-send-custom" data-post-id="' . esc_attr( $post->ID ) . '">Send</button>';
        echo '</div>';
        echo '</div>';
        
        echo '<div id="captaincore-newsletter-status" style="margin-top: 10px;"></div>';
        echo '</div>';
        
        // Inline JavaScript for AJAX
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#captaincore-send-preview').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var statusDiv = $('#captaincore-newsletter-status');
                
                button.prop('disabled', true).text('Sending...');
                statusDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'captaincore_send_newsletter_preview',
                        post_id: postId,
                        send_to: 'preview',
                        nonce: $('#captaincore_newsletter_nonce').val()
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Send Preview to Me');
                        if (response.success) {
                            statusDiv.html('<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #155724;">' + response.data.message + '</div>');
                        } else {
                            statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Send Preview to Me');
                        statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">An error occurred. Please try again.</div>');
                    }
                });
            });
            
            $('#captaincore-send-editors').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var statusDiv = $('#captaincore-newsletter-status');
                
                button.prop('disabled', true).text('Sending...');
                statusDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'captaincore_send_newsletter_preview',
                        post_id: postId,
                        send_to: 'editors',
                        nonce: $('#captaincore_newsletter_nonce').val()
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Send to Editors');
                        if (response.success) {
                            statusDiv.html('<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #155724;">' + response.data.message + '</div>');
                        } else {
                            statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Send to Editors');
                        statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">An error occurred. Please try again.</div>');
                    }
                });
            });
            
            $('#captaincore-send-all').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var statusDiv = $('#captaincore-newsletter-status');
                
                if (!confirm('Are you sure you want to send this newsletter to all subscribers? This cannot be undone.')) {
                    return;
                }
                
                button.prop('disabled', true).text('Sending...');
                statusDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'captaincore_send_newsletter_preview',
                        post_id: postId,
                        send_to: 'all',
                        nonce: $('#captaincore_newsletter_nonce').val()
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Send to All Subscribers');
                        if (response.success) {
                            statusDiv.html('<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #155724;">' + response.data.message + '</div>');
                            // Reload the page to show updated send history
                            setTimeout(function() { location.reload(); }, 2000);
                        } else {
                            statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Send to All Subscribers');
                        statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">An error occurred. Please try again.</div>');
                    }
                });
            });
            
            $('#captaincore-send-custom').on('click', function() {
                var button = $(this);
                var postId = button.data('post-id');
                var statusDiv = $('#captaincore-newsletter-status');
                var emailInput = $('#captaincore-custom-email');
                var email = emailInput.val().trim();
                
                if (!email) {
                    statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">Please enter an email address.</div>');
                    return;
                }
                
                button.prop('disabled', true).text('Sending...');
                statusDiv.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'captaincore_send_newsletter_preview',
                        post_id: postId,
                        send_to: 'custom',
                        email: email,
                        nonce: $('#captaincore_newsletter_nonce').val()
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Send');
                        if (response.success) {
                            statusDiv.html('<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #155724;">' + response.data.message + '</div>');
                            emailInput.val('');
                        } else {
                            statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        button.prop('disabled', false).text('Send');
                        statusDiv.html('<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; border-radius: 4px; font-size: 12px; color: #721c24;">An error occurred. Please try again.</div>');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

/**
 * AJAX handler for sending newsletter preview/manual send
 */
function captaincore_send_newsletter_preview_ajax() {
    // Verify nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'captaincore_newsletter_send' ) ) {
        wp_send_json_error( [ 'message' => 'Security check failed.' ] );
    }
    
    // Check permissions
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'You do not have permission to perform this action.' ] );
    }
    
    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $send_to = isset( $_POST['send_to'] ) ? sanitize_text_field( $_POST['send_to'] ) : 'preview';
    
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Invalid post ID.' ] );
    }
    
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'post' ) {
        wp_send_json_error( [ 'message' => 'Post not found.' ] );
    }
    
    if ( $send_to === 'preview' ) {
        // Send only to the current admin user
        $current_user = wp_get_current_user();
        \CaptainCore\Mailer::send_new_post_notification( $post_id, $current_user );
        wp_send_json_success( [ 'message' => 'Preview sent to ' . $current_user->user_email ] );
        
    } elseif ( $send_to === 'custom' ) {
        // Send to a custom email address
        $email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
        
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
        }
        
        // Create a temporary user object for the mailer
        $temp_user = (object) [
            'user_email' => $email,
            'ID'         => 0,
        ];
        
        \CaptainCore\Mailer::send_new_post_notification( $post_id, $temp_user );
        wp_send_json_success( [ 'message' => 'Preview sent to ' . $email ] );
        
    } elseif ( $send_to === 'editors' ) {
        // Send to all editors for review
        $editors = get_users( [ 'role' => 'editor' ] );
        
        if ( empty( $editors ) ) {
            wp_send_json_error( [ 'message' => 'No editors found.' ] );
        }
        
        $emails = [];
        foreach ( $editors as $user ) {
            \CaptainCore\Mailer::send_new_post_notification( $post_id, $user, 'review' );
            $emails[] = $user->user_email;
        }
        
        // Save editor send history
        $editor_history = [
            'sent_at'         => current_time( 'mysql' ),
            'recipient_count' => count( $editors ),
            'emails'          => $emails,
        ];
        update_post_meta( $post_id, '_captaincore_newsletter_sent_editors', wp_json_encode( $editor_history ) );
        
        wp_send_json_success( [ 'message' => 'Preview sent to ' . count( $editors ) . ' editor(s): ' . implode( ', ', $emails ) ] );
        
    } elseif ( $send_to === 'all' ) {
        // Send to all subscribers
        $subscribers = get_users( [ 'role' => 'email_subscriber' ] );
        
        if ( empty( $subscribers ) ) {
            wp_send_json_error( [ 'message' => 'No subscribers found.' ] );
        }
        
        foreach ( $subscribers as $user ) {
            \CaptainCore\Mailer::send_new_post_notification( $post_id, $user );
        }
        
        // Update send history
        $send_history = [
            'sent_at'          => current_time( 'mysql' ),
            'recipient_count'  => count( $subscribers ),
            'manual'           => true,
        ];
        update_post_meta( $post_id, '_captaincore_newsletter_sent', wp_json_encode( $send_history ) );
        
        wp_send_json_success( [ 'message' => 'Newsletter sent to ' . count( $subscribers ) . ' subscriber(s).' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Invalid send option.' ] );
    }
}
add_action( 'wp_ajax_captaincore_send_newsletter_preview', 'captaincore_send_newsletter_preview_ajax' );

function captaincore_missive_func( WP_REST_Request $request ) {

	$key        = $request->get_header('X-Hook-Signature');
	
	if ( empty( $key ) ) {
		return "Bad Request";
	}

	$computed_signature = 'sha256=' . hash_hmac( "sha256", $request->get_body(), CAPTAINCORE_MISSIVE_API );
	if ( ! hash_equals( $computed_signature, $key ) ) {
		return "Bad Request";
	}

	$errors     = [];
	$missive    = json_decode( $request->get_body() );
	$subject    = empty( $missive->latest_message->subject ) ? $missive->message->subject : $missive->latest_message->subject;

	if ( $subject == "Email Health Check" ) {
		$message    = explode( " ", $missive->message->preview);
		if ( count( $message ) != 2 ) {
			return;
		}
		$site       = $message[0];
		$site_id    = is_string( $site ) ? explode( "-", $site )[0] : "";
		$token      = $message[1];
		$site_check = CaptainCore\Sites::get( $site_id );
	
		if ( ! $site_check ) { 
			return;
		}

		if ( ! is_numeric( $token ) || ! (int) $token == $token ) {
			return;
		}

		CaptainCore\Run::CLI( "email-health response $site $token received" );

		return;
	}

	if ( $subject == "Action is required to renew your SSL certificate" ) {
	$message_id = $missive->latest_message->id;
	$message    = CaptainCore\Remote\Missive::get( "messages/$message_id")->messages->body;

	preg_match('/TXT record for (.+) in MyKinsta/', $message, $matches );
	$domain     = $matches[1];
	$response   = ( new CaptainCore\Domains )->add_verification_record( $domain );
	$errors     = implode( ", ", $errors );

	CaptainCore\Remote\Missive::post( "posts", [ "posts" => [ 
		"conversation"  => $missive->conversation->id,
		"notification"  => [ "title" => "", "body" => "" ],
		"username"      => "CaptainCore Bot", 
		"username_icon" => "https://captaincore.io/logo.png",
		"markdown"      => $response
	] ] );

	return;
	}
}

function captaincore_api_func( WP_REST_Request $request ) {

	$post          = json_decode( file_get_contents( 'php://input' ) );
	$archive       = empty( $post->archive ) ? "" : $post->archive;
	$command       = empty( $post->command ) ? "" : $post->command;
	$environment   = empty( $post->environment ) ? "" : $post->environment;
	$storage       = empty( $post->storage ) ? "" : $post->storage;
	$visits        = empty( $post->visits ) ? "" : $post->visits;
	$email         = empty( $post->email ) ? "" : $post->email;
	$server        = empty( $post->server ) ? "" : $post->server;
	$core          = empty( $post->core ) ? "" : $post->core;
	$plugins       = empty( $post->plugins ) ? "" : $post->plugins;
	$themes        = empty( $post->themes ) ? "" : $post->themes;
	$users         = empty( $post->users ) ? "" : $post->users;
	$fathom        = empty( $post->fathom ) ? "" : $post->fathom;
	$home_url      = empty( $post->home_url ) ? "" : $post->home_url;
	$subsite_count = empty( $post->subsite_count ) ? "" : $post->subsite_count;
	$git_status    = empty( $post->git_status ) ? "" : trim( base64_decode( $post->git_status ) );
	$token_key     = empty( $post->token_key ) ? "" : $post->token_key;
	$data          = empty( $post->data ) ? "" : $post->data;
	$site_id       = empty( $post->site_id ) ? "" : $post->site_id;
	$user_id       = empty( $post->user_id ) ? "" : $post->user_id;
	$notes         = empty( $post->notes ) ? "" : $post->notes;
	$response      = "";

	// Error if token not valid
	if ( $post->token != CAPTAINCORE_CLI_TOKEN ) {
		// Create the response object
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 404 ] );
	}

	// Error if site not valid
	$current_site = CaptainCore\Sites::get( $site_id );
	if ( empty( $current_site ) && $site_id != "" && $command != "default-get" && $command != "configuration-get" ) {
		return new WP_Error( 'command_invalid', "Invalid Command for $site_id", [ 'status' => 404 ] );
	}

	$site_name      = $current_site->site;
	$domain_name    = $current_site->name;
	$environment_id = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );

	// Copy site
	if ( $command == 'copy' and $email ) {

		$site_source      = get_the_title( $post->site_source_id );
		$site_destination = get_the_title( $post->site_destination_id );
		$business_name    = get_field('business_name', 'option');

		// Send out completed email notice
        $subject = "$business_name - Copy site ($site_source) to ($site_destination) completed";
        $message = "Completed copying <strong>$site_source</strong> to <strong>$site_destination</strong>.";
        $url     = "http://$site_destination";

        \CaptainCore\Mailer::send_process_completed( $email, $subject, "Copy Completed", $site_destination, $message, $url );

		echo 'copy-site email sent';

	}

	// Production deploy to staging
	if ( $command == 'production-to-staging' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name   = get_the_title( $site_id );
		$db            = new CaptainCore\Site( $site_id );
		$site          = $db->get();
		$link          = $site->environments[1]["link"];

		// Send out completed email notice
        $subject = "$business_name - Deploy to Staging ($domain_name)";
        $message = "Deploy to staging completed for <strong>$domain_name</strong>.";

        \CaptainCore\Mailer::send_process_completed( $email, $subject, "Deploy Completed", "Staging Environment", $message, $link );

		echo 'production-to-staging email sent';

	}

	// Kinsta staging deploy to production
	if ( $command == 'staging-to-production' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name   = get_the_title( $site_id );
		$db            = new CaptainCore\Site( $site_id );
		$site          = $db->get();
		$link          = $site->environments[0]["link"];

		// Send out completed email notice
        $subject = "$business_name - Deploy to Production ($domain_name)";
        $message = "Deploy to production completed for <strong>$domain_name</strong>.";

        \CaptainCore\Mailer::send_process_completed( $email, $subject, "Deploy Completed", "Production Environment", $message, $link );

		echo 'staging-to-production email sent';

	}

	// Generate a new snapshot.
	if ( $command == 'snapshot-add' ) {

		$snapshot_check = ( new CaptainCore\Snapshots )->get( $post->data->snapshot_id );
		// Insert new snapshot
		if ( empty( $snapshot_check ) ) {
			( new CaptainCore\Snapshots )->insert( (array) $post->data );
		} else {
			// Update existing quicksave
			( new CaptainCore\Snapshots )->update( (array) $post->data, [ "snapshot_id" => $post->data->snapshot_id ] );
		}
	
		$response = [
			"response"  => "Snapshot added for $site_id",
			"snapshot" => $post->data,
		];

		// Send out snapshot email
		captaincore_download_snapshot_email( $post->data->snapshot_id );

	}

	// Load Token Key
	if ( $command == 'token' and isset( $token_key ) ) {
		( new CaptainCore\Sites )->update( [ "token" => $token_key ], [ "site_id" => $site_id ] );
		echo "Adding token key. \n";
	}

	// Update Fathom
	if ( $command == 'update-fathom' and ! empty( $post->data ) ) {
		
		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		$environment         = strtolower( $current_environment->environment );
		$details             = ( isset( $current_environment->details ) ? json_decode( $current_environment->details ) : (object) [] );
		$details->fathom     = $post->data->fathom;
		( new CaptainCore\Environments )->update( [ 
			 "details"    => json_encode( $details ),
			], [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"        => "Completed update-fathom for $site_id",
			"environment"     => $post->data,
		];

	}

	if ( $command == 'update-site' and ! empty( $post->data ) ) {

		$current_site = ( new CaptainCore\Sites )->get( $post->data->site_id );
		( new CaptainCore\Sites )->update( (array) $post->data, [ "site_id" => $post->data->site_id ] );

		$response = [
			"response" => "Completed update-site for $site_id",
			"site"     => $post->data,
		];
		
	}


	if ( $command == 'update-environment' and ! empty( $post->data ) ) {
		
		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );

		// Ensure screenshot_base is up to date with latest capture
		$capture = CaptainCore\Captures::latest_capture( [ "site_id" => $site_id, "environment_id" => $post->data->environment_id ] );
		if ( ! empty( $capture ) ) {
			$created_at       = strtotime( $capture->created_at );
			$git_commit_short = substr( $capture->git_commit, 0, 7 );
			// Use incoming details if present, otherwise fallback to DB details
			$details_json = ! empty( $post->data->details ) ? $post->data->details : ( $current_environment->details ?? '{}' );
			$details = json_decode( $details_json );
			$details->screenshot_base = "{$created_at}_{$git_commit_short}";
			$post->data->details = json_encode( $details );
			$post->data->screenshot = true;
		}

		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		// Rebuild the cache for this site so listings match details
		CaptainCore\Sites::update_environments_cache( $site_id );
		
		// Mark Site as updated
		( new CaptainCore\Sites )->update( [ "updated_at" => $post->data->updated_at ], [ "site_id" => $site_id ] );

		$response = [
			"response"        => "Completed update-environment for $site_id",
			"environment"     => $post->data,
		];

	}

	// Sync site data
	if ( $command == 'sync-data' and ! empty( $post->data ) ) {
		
		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		$environment_name    = strtolower( $current_environment->environment );
		$capture             = CaptainCore\Captures::latest_capture( [ "site_id" => $site_id, "environment_id" => $post->data->environment_id ] );
		if ( ! empty( $capture ) ) {
			$created_at       = strtotime( $capture->created_at );
			$git_commit_short = substr( $capture->git_commit, 0, 7 );
			$details          = isset( $current_environment->details ) ? json_decode( $current_environment->details ) : (object) [];
			$details->screenshot_base = "{$created_at}_{$git_commit_short}";
			$post->data->details = json_encode( $details );
			$post->data->screenshot = true;
		}

		// Update the specific environment record
		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		// Rebuild the cache for this site using the central method
		CaptainCore\Sites::update_environments_cache( $site_id );

		$current_site = CaptainCore\Sites::get( $site_id );
		$details      = json_decode( $current_site->details );

		unset( $details->connection_errors );

		if ( $current_environment->environment == "Production" ) {
			$details->core     = $post->data->core;
			$details->subsites = $post->data->subsite_count;
		}

		if ( ! empty( $post->data->home_url ) && $current_environment->environment == "Production" ) {
			$details->home_url = $post->data->home_url;
			$home_url = str_replace( [ "http://www.", "https://www.", "http://", "https://", "www." ], "", $post->data->home_url );
			$current_site->name = $home_url;
		}

		// Mark Site as updated and save the compiled details
		CaptainCore\Sites::update( [ 
			"name"       => $current_site->name,
			"updated_at" => $post->data->updated_at,
			"details"    => json_encode( $details )
		], [ "site_id" => $site_id ] );

		$response = [
			"response"        => "Completed sync-data and cached environments for $site_id",
			"environment"     => $post->data,
		];
	}

	// Add capture
	if ( $command == 'new-capture' ) {

		$environment_id = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );
		$captures       = new CaptainCore\Captures();
		$capture_lookup = $captures->where( [ "site_id" => $site_id, "environment_id" => $environment_id ] );
		if ( count( $capture_lookup ) > 0 ) {
			$current_capture_pages = json_decode( $capture_lookup[0]->pages );
		}

		$git_commit_short = substr( $data->git_commit, 0, 7 );
		$image_ending     = "_{$data->created_at}_{$git_commit_short}.jpg";
		$capture_pages    = explode( ",", $data->capture_pages );
		$captured_pages   = explode( ",", $data->captured_pages );
		$pages = [];
		foreach( $capture_pages as $page ) {
			$page_name = str_replace( "/", "#", $page );

			// Add page with new screenshot
			if ( in_array( $page, $captured_pages ) ) {
				$pages[] = [
					"name"  => $page,
					"image" => "{$page_name}{$image_ending}",
				];
				continue;
			}

			// Lookup current image from DB
			$current_image = "";
			foreach($current_capture_pages as $current_capture_page) {
				if ($page == $current_capture_page->name) {
					$current_image = $current_capture_page->image;
					break;
				}
			}

			// Otherwise add image to current screenshot
			$pages[] = [
				"name"  => $page,
				"image" => $current_image,
			];
		}

		// Format for mysql timestamp format. Changes "1530817828" to "2018-06-20 09:15:20"
		$epoch      = $data->created_at;
		$created_at = new DateTime("@$epoch");  // convert UNIX timestamp to PHP DateTime
		$created_at = $created_at->format('Y-m-d H:i:s'); // output = 2017-01-01 00:00:00

		$new_capture = [
			'site_id'        => $site_id,
			'environment_id' => $environment_id,
			'created_at'     => $created_at,
			'git_commit'     => $data->git_commit,
			'pages'          => json_encode( $pages ),
		];

		( new CaptainCore\Captures )->insert( $new_capture );

		// Update pointer to new thumbnails for environment
		$environment              = ( new CaptainCore\Environments )->get( $environment_id );
		$details                  = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
		$details->screenshot_base = "{$data->created_at}_${git_commit_short}";
		( new CaptainCore\Environments )->update( [ "screenshot" => true, "details" => json_encode( $details ) ], [ "environment_id" => $environment_id ] );

		// Sync cache to ensure Listings page sees the new capture immediately
		CaptainCore\Sites::update_environments_cache( $site_id );

	}

	if ( $command == 'site-get-raw' ) {
		$site = new CaptainCore\Site( $post->site_id );
		$response = [
			"response" => "Fetching site {$post->site_id}",
			"site"     => $site->get_raw(),
		];
	}

	if ( $command == 'site-delete' ) {
		( new CaptainCore\Sites )->delete( $post->site_id );
		$response = [
			"response" => "Delete site {$post->site_id}"
		];
	}

	if ( $command == 'account-get-raw' ) {
		$account = new CaptainCore\Account( $post->account_id, true );
		$response = [
			"response" => "Fetching account {$post->account_id}",
			"account"  => $account->get_raw(),
		];
	}

	if ( $command == 'configuration-get' ) {
		$configurations = ( new CaptainCore\Configurations )->get();
		$response       = [
			"response"       => "Fetching configurations",
			"configurations" => $configurations,
		];
	}

	if ( $command == 'default-get' ) {
		$defaults = ( new CaptainCore\Defaults )->get();
		$response = [
			"response" => "Fetching global defaults",
			"defaults" => $defaults,
		];
	}

	// Updates visits and storage usage
	if ( $command == 'usage-update' ) {

		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"    => "Completed usage-update for $site_id",
			"environment" => $post->data,
		];

		( new CaptainCore\Site( $current_environment->site_id ) )->update_details();

	}

	return $response;

}

/**
 * REST API callback to update a process log entry.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_update_log_entry_func( WP_REST_Request $request ) {
	$process_log        = (object) $request->get_json_params();
	$site_ids           = array_column( $process_log->websites, 'site_id' );
	$process_log_update = [
		'process_id'  => $process_log->process,
		'user_id'     => get_current_user_id(),
		'description' => str_replace( "\'", "'", $process_log->description_raw ),
		'public'      => $process_log->public,
		'status'      => $process_log->status,
		'updated_at'  => date( 'Y-m-d H:i:s' ),
	];
	( new CaptainCore\ProcessLogs )->update( (array) $process_log_update, [ "process_log_id" => $process_log->process_log_id ] );
	( new CaptainCore\ProcessLog( $process_log->process_log_id) )->assign_sites( $site_ids );
	$timelines = [];
	foreach ( $site_ids as $site_id ) {
		$timelines[ $site_id ] = ( new CaptainCore\Site( $site_id ) )->process_logs();
	}
	return new WP_REST_Response( $timelines, 200 );
}

function captaincore_accounts_func( $request ) {
	return ( new CaptainCore\Accounts )->list();
}

function captaincore_accounts_create_func( $request ) {
	$name        = $request->get_param( "name" );
	$time_now    = date("Y-m-d H:i:s");
	$new_account = [ 
		"name"       => trim( $name ),
		"status"     => "active",
		"created_at" => $time_now,
		"updated_at" => $time_now,
		"defaults"   => json_encode( [ 
			"email"    => "",
			"timezone" => "",
			"recipes"  => [],
			"users"    => [],
		] )
	];
	if ( defined( 'CAPTAINCORE_CUSTOM_DOMAIN' ) ) {
		$account_portal = CaptainCore\AccountPortals::current();
		$new_account["account_portal_id"] = $account_portal->account_portal_id;
	}
	$account_id = ( new CaptainCore\Accounts )->insert( $new_account );
	( new CaptainCore\Account( $account_id, true ) )->calculate_totals();
	( new CaptainCore\Account( $account_id, true ) )->sync();
	return $account_id;
}


function captaincore_configurations_func( $request ) {
	return ( new CaptainCore\Configurations )->get();
}

function captaincore_configurations_update_func( $request ) {
	$configurations = $request->get_param( "configurations" );
	return ( new CaptainCore\Configurations )->update( $configurations );
}

function captaincore_subscriptions_func( $request ) {
	return ( new CaptainCore\User )->subscriptions();
}

function captaincore_subscription_details_func( $request ) {
    $account_id = $request->get_param( "id" );
    return ( new CaptainCore\User )->get_subscription_details( $account_id );
}

function captaincore_upcoming_subscriptions_func( $request ) {
	return ( new CaptainCore\User )->upcoming_subscriptions();
}

function captaincore_billing_func( $request ) {
	return ( new CaptainCore\User )->billing();
}

function captaincore_provider_new_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	return ( new CaptainCore\Provider )->create( $provider );
}

function captaincore_provider_update_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider_id = $request->get_param( "id" );
	$provider    = $request->get_param( "provider" );
	unset( $provider["provider_id"] );
	unset( $provider["created_at"] );
	$provider["updated_at"]  = date("Y-m-d H:i:s");
	$provider["credentials"] = json_encode( $provider["credentials"] );
	return ( new CaptainCore\Providers )->update( $provider, [ "provider_id" => $provider_id ] );
}

function captaincore_provider_delete_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider_id = $request->get_param( "id" );
	return ( new CaptainCore\Providers )->delete( $provider_id );
}

function captaincore_provider_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return [];
	}
	return ( new CaptainCore\Provider )->all();
}

function captaincore_provider_verify_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	return ( new CaptainCore\Provider( $provider ) )->verify();
}

function captaincore_provider_themes_func( $request ) {
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::themes();
}

function captaincore_provider_theme_download_func( $request ) {
	$theme_id = $request->get_param( "id" );
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::download_theme( $theme_id );
}

function captaincore_provider_plugin_download_func( $request ) {
	$plugin_id = $request->get_param( "id" );
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::download_plugin( $plugin_id );
}

function captaincore_provider_plugins_func( $request ) {
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::plugins();
}

function captaincore_provider_connect_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	$token    = $request['token'];
	return ( new CaptainCore\Provider( $provider ) )->update_token( $token );
}

function captaincore_provider_new_site_func( $request ) {
	$user     = (object) ( new CaptainCore\User )->fetch();
	$provider = $request->get_param( "provider" );
	$site     = (object) $request['site'];
	$errors   = [];
	if ( empty( $site->name ) ) {
		$errors[] = "Missing name";
	}
	if ( ! empty( $site->name ) && ( strlen( $site->name ) < 5 || strlen( $site->name ) > 32 ) ) {
		$errors[] = "Name must be between 5 and 32 characters in length";
	}
	if ( empty( $site->domain ) ) {
		$errors[] = "Missing domain";
	}
	if ( ! ( new CaptainCore\User )->is_admin() && ! captaincore_verify_permissions_account( $site->account_id ) ){ 
		$errors[] = "Permission denied";
	}
	if ( ! ( new CaptainCore\User )->is_admin() && ! empty( $site->provider_id ) && ( $site->provider_id != "1" ) ) {
		$provider_lookup = CaptainCore\Providers::get( $site->provider_id );
		if ( $provider_lookup->user_id != $user->user_id ) {
			$errors[] = "Permission denied";
		}
	}
	if ( ! empty( $errors ) ) {
		return [
			'errors' => $errors,
		];
	}

	return ( new CaptainCore\Provider( $provider ) )->new_site( $site );
}

function captaincore_provider_deploy_to_staging_func( $request ) {
	$site_id = $request['site_id'];
	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	CaptainCore\ProcessLog::insert( "Deploy production to staging", $site_id );
	return ( new CaptainCore\Provider( $provider ) )->deploy_to_staging( $site_id );
}

function captaincore_provider_deploy_to_production_func( $request ) {
	$site_id = $request['site_id'];
	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	CaptainCore\ProcessLog::insert( "Deploy staging to production", $site_id );
	return ( new CaptainCore\Provider( $provider ) )->deploy_to_production( $site_id );
}

function captaincore_provider_actions_check_func( $request ) {
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\ProviderAction )->check();
}

function captaincore_provider_actions_run_func( $request ) {
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\ProviderAction( $request['id'] ) )->run();
}

function captaincore_provider_actions_func( $request ) {
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\ProviderAction )->active();
}

/**
 * Generic REST callback to get domains for a site's environment.
 */
function captaincore_site_domains_get_func( WP_REST_Request $request ) {
    $site_id = $request['id'];
    $env_name = $request['environment'];

    if ( ! captaincore_verify_permissions( $site_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    $site = ( new CaptainCore\Sites )->get( $site_id );
    if ( ! $site || empty( $site->provider ) ) {
        return new WP_Error( 'no_provider', 'Site has no provider configured.', [ 'status' => 400 ] );
    }

	if ( empty($site->provider_id) ) {
		$provider = ( new \CaptainCore\Provider( $site->provider ) );
	} else {
		$provider = new \CaptainCore\Provider( $site->provider_id );
	}
    $response = $provider->get_domains( $site_id, $env_name );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return new WP_REST_Response( $response, 200 );
}

/**
 * Generic REST callback to add a domain to a site's environment.
 */
function captaincore_site_domain_add_func( WP_REST_Request $request ) {
    $site_id  = $request['id'];
    $env_name = $request['environment'];
    $params   = $request->get_json_params();

    if ( ! captaincore_verify_permissions( $site_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    $site = ( new CaptainCore\Sites )->get( $site_id );
    if ( ! $site || empty( $site->provider ) ) {
        return new WP_Error( 'no_provider', 'Site has no provider configured.', [ 'status' => 400 ] );
    }

	if ( empty($site->provider_id) ) {
		$provider = ( new \CaptainCore\Provider( $site->provider ) );
	} else {
		$provider = new \CaptainCore\Provider( $site->provider_id );
	}
    $response = $provider->add_domain( $site_id, $env_name, $params );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return new WP_REST_Response( $response, 202 );
}

/**
 * Generic REST callback to delete a domain from a site's environment.
 */
function captaincore_site_domain_delete_func( WP_REST_Request $request ) {
    $site_id  = $request['id'];
    $env_name = $request['environment'];
    $params   = $request->get_json_params();

    if ( ! captaincore_verify_permissions( $site_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    $site = ( new CaptainCore\Sites )->get( $site_id );
    if ( ! $site || empty( $site->provider ) ) {
        return new WP_Error( 'no_provider', 'Site has no provider configured.', [ 'status' => 400 ] );
    }

	if ( empty($site->provider_id) ) {
		$provider = ( new \CaptainCore\Provider( $site->provider ) );
	} else {
		$provider = new \CaptainCore\Provider( $site->provider_id );
	}
    $response = $provider->delete_domain( $site_id, $env_name, $params );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return new WP_REST_Response( $response, 202 );
}

/**
 * Generic REST callback to set the primary domain for a site's environment.
 */
function captaincore_site_domain_primary_func( WP_REST_Request $request ) {
    $site_id  = $request['id'];
    $env_name = $request['environment'];
    $params   = $request->get_json_params();

    if ( ! captaincore_verify_permissions( $site_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    $site = ( new CaptainCore\Sites )->get( $site_id );
    if ( ! $site || empty( $site->provider ) ) {
        return new WP_Error( 'no_provider', 'Site has no provider configured.', [ 'status' => 400 ] );
    }

    $provider = new \CaptainCore\Provider( $site->provider_id );
    $response = $provider->set_primary_domain( $site_id, $env_name, $params );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return new WP_REST_Response( $response, 202 );
}

function captaincore_schedule_script_func( $request ) {
	$environment_id = $request['environment_id'];
	$code           = $request['code'];
	$run_at         = (object) $request['run_at'];
	$timestamp      = new DateTime("$run_at->date $run_at->time", new DateTimeZone($run_at->timezone));
	$timestamp->setTimezone(new DateTimeZone('UTC'));
	$time_now       = date("Y-m-d H:i:s");
	$details 		= [
		"run_at" => $timestamp->getTimestamp()
	];
	$new_script = CaptainCore\Scripts::insert( [
		"environment_id" => $environment_id,
		"user_id"        => get_current_user_id(),
		"code"           => $code,
		"details"        => json_encode( $details ),
		"status"		 => "scheduled",
		"created_at"     => $time_now,
		"updated_at"     => $time_now,
	] );
	return $new_script;
}

function captaincore_update_script_func( $request ) {
	$script_id = $request['id'];
	$script    = CaptainCore\Scripts::get( $script_id );
	$site_id   = CaptainCore\Environments::get( $script->environment_id )->site_id;
	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$details   = json_decode( $script->details );
	$run_at    = (object) $request['run_at'];
	$time_now  = date("Y-m-d H:i:s");
	$timestamp = new DateTime("$run_at->date $run_at->time", new DateTimeZone($run_at->timezone));
	$timestamp->setTimezone(new DateTimeZone('UTC'));
	$details->run_at = $timestamp->getTimestamp();
	return ( new CaptainCore\Scripts )->update( [ "code" => $request['code'], 'details' => json_encode( $details ) ], [ "script_id" => $script_id ] );
}

function captaincore_delete_script_func( $request ) {
	$script_id = $request['id'];
	$script    = CaptainCore\Scripts::get( $script_id );
	$site_id   = CaptainCore\Environments::get( $script->environment_id )->site_id;
	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\Scripts )->delete( $script_id );
}

function captaincore_sites_func( $request ) {
	return ( new CaptainCore\Sites )->list();
}

function captaincore_site_update_func( $request ) {
	$site_id         = $request['id'];
	$updated_details = $request['details'];

	if ( ! is_numeric ( $site_id ) ) {
		$site = ( new CaptainCore\Sites )->where( [ "site" => $site_id ] );
		if ( count( $site ) == 1 ) {
			$site_id = $site[0]->site_id;
		}
	}

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}

	$site    = CaptainCore\Sites::get( $site_id );
	$details = empty( $site->details ) ? (object) [] : json_decode( $site->details );
	
	foreach ( $updated_details as $field => $value ) {
		$details->$field = $value;
		
		if ( $field == "removed" ) {
			$user = (object) ( new CaptainCore\User )->fetch();
			// $value is boolean: true (remove) or false (cancel removal)
			\CaptainCore\Mailer::send_site_removal_request( $site, $user, $value );
		}
	}

	$query = CaptainCore\Sites::update(["details" => json_encode( $details ) ], [ "site_id" => $site_id ]);
	return;
}
function captaincore_site_delete_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'permission_denied', 'Permission denied', [ 'status' => 403 ] );
	}

	$site = new CaptainCore\Site( $site_id );
	$site_data = $site->get();
	$name = $site_data->name;

	captaincore_run_background_command( "site delete {$site_data->site}" );

	// Remove from CaptainCore Manager Database
	$site->mark_inactive();

	return [ 
		"site_id" => $site_id,
		"message" => "Site $name deleted." 
	];
}

function captaincore_site_func( $request ) {

	$site_id     = $request['site'];
	$extras      = empty( $request['extras'] ) ? [] : $request['extras'];
	$environment = "production";

	if( strpos( $request['site'], "-" ) !== false ) {
		$split       = explode( "-", $site_id );
		$site_id     = $split[0];
		$environment = $split[1];
	}

	if( strpos( $request['site'], "@" ) !== false ) {
		$split       = explode( "@", $site_id );
		$site_id     = $split[0];
		$provider    = $split[1];
	}

	if( strpos( $environment, "@" ) !== false ) {
		$split       = explode( "@", $environment );
		$environment = $split[0];
		$provider    = $split[1];
	}

	$format  = empty( $request->get_param( "format" ) ) ? "json" : $request->get_param( "format" );
	$field   = empty( $request->get_param( "field" ) ) ? "" : $request->get_param( "field" );

	if ( ! is_numeric ( $site_id ) ) {
		$site = ( new CaptainCore\Sites )->where( [ "site" => $site_id ] );
		if ( count( $site ) == 1 ) {
			$site_id = $site[0]->site_id;
		}
	}

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}

	$site = ( new CaptainCore\Site( $site_id, $environment ) )->get( $format, $field, $extras );

	if ( ! empty( $field ) ) {
		if ( empty ( $site->{$field} ) ) {
			return;
		}
		return [ 
			"format" => "field",
			"data"   => $site->{$field}
		];
	}

	return $site;

}

function captaincore_me_update_pins_func( WP_REST_Request $request ) {
    $pins = $request->get_json_params();
    $user_id = get_current_user_id();
    
    // Basic validation
    if ( ! is_array( $pins ) ) {
        return new WP_Error( 'invalid_data', 'Pins must be an array.', [ 'status' => 400 ] );
    }

    update_user_meta( $user_id, 'captaincore_pinned_environments', $pins );

    return new WP_REST_Response( [ 'success' => true, 'pins' => $pins ], 200 );
}

/**
 * Generalized REST API for bulk site operations.
 */
function captaincore_bulk_tools_func( WP_REST_Request $request ) {
    $params       = $request->get_json_params();
    $tool         = isset( $params['tool'] ) ? $params['tool'] : '';
    $environments = isset( $params['environments'] ) ? $params['environments'] : [];
    $extra_params = isset( $params['params'] ) ? $params['params'] : [];
    $targets      = [];

    if ( empty( $tool ) || empty( $environments ) ) {
        return new WP_Error( 'missing_data', 'Tool and at least one environment are required', [ 'status' => 400 ] );
    }

    foreach ( $environments as $environment_id ) {
        $env_obj = CaptainCore\Environments::get( $environment_id );
        if ( $env_obj ) {
            $site_obj = CaptainCore\Sites::get( $env_obj->site_id );
            // Security: Check individual site permissions
            if ( captaincore_verify_permissions( $site_obj->site_id ) ) {
                $targets[] = "{$site_obj->site}-" . strtolower( $env_obj->environment );
            }
        }
    }

    if ( empty( $targets ) ) {
        return new WP_Error( 'invalid_targets', 'No valid targets found or permission denied.', [ 'status' => 403 ] );
    }

    $target_string = implode( " ", array_unique( $targets ) );

    // Map the tool name to the CLI command
    switch ( $tool ) {
        case 'sync-data':
            $command = "sync-data $target_string";
            break;
        case 'deploy-defaults':
            $command = "site deploy-defaults $target_string";
            break;
        case 'activate':
            $command = "activate $target_string";
            break;
        case 'deactivate':
            $name    = escapeshellarg( $extra_params['business_name'] ?? '' );
            $link    = escapeshellarg( $extra_params['business_link'] ?? '' );
            $command = "deactivate $target_string --name=$name --link=$link";
            break;
        case 'apply-https':
            $script  = ( ! empty( $extra_params['www'] ) ) ? 'apply-https-with-www' : 'apply-https';
            $command = "ssh $target_string --script=$script";
            break;
		case 'launch':
			$domain_raw = $extra_params['domain'] ?? '';
			$domain = preg_replace( '#^https?://#', '', $domain_raw );
			$domain = rtrim( $domain, '/' );
			if ( ! preg_match( '/^[a-z0-9\-\.]+$/i', $domain ) ) {
				return new WP_Error( 'invalid_domain', 'Invalid domain name format.', [ 'status' => 400 ] );
			}
			$command = "ssh $target_string --script=launch -- --domain=$domain";
			break;
        case 'scan-errors':
            $command = "scan-errors $target_string";
            break;
        case 'backup':
            $command = "backup $target_string";
            break;
        case 'snapshot':
            $command = "snapshot generate $target_string";
            break;
        default:
            return new WP_Error( 'invalid_tool', 'The requested tool is not supported.', [ 'status' => 400 ] );
    }

    return CaptainCore\Run::task( $command );
}

function captaincore_run_code_func( WP_REST_Request $request ) {
    $params       = $request->get_json_params();
    $code         = isset( $params['code'] ) ? $params['code'] : '';
    $environments = isset( $params['environments'] ) ? $params['environments'] : [];
    $targets      = [];

    if ( empty( $code ) ) {
        return new WP_Error( 'missing_code', 'Code is required', [ 'status' => 400 ] );
    }

    if ( empty( $environments ) ) {
        return new WP_Error( 'missing_environments', 'At least one environment is required', [ 'status' => 400 ] );
    }

    foreach ( $environments as $env_data ) {
        $environment_id = null;
        $site_obj       = null;
        $env_name       = null;

        // 1. Handle simple integer ID (e.g. [3365, 3358])
        if ( is_numeric( $env_data ) ) {
            $environment_id = $env_data;
        } 
        // 2. Handle Object/Array structure
        elseif ( is_array( $env_data ) || is_object( $env_data ) ) {
            $env_data = (object) $env_data;
            
            if ( ! empty( $env_data->environment_id ) ) {
                $environment_id = $env_data->environment_id;
            } elseif ( ! empty( $env_data->enviroment_id ) ) { // Handle common frontend typo
                $environment_id = $env_data->enviroment_id;
            } elseif ( ! empty( $env_data->site_id ) && ! empty( $env_data->environment ) ) {
                // Fallback: Direct site_id + environment name provided
                if ( captaincore_verify_permissions( $env_data->site_id ) ) {
                    $site_obj = CaptainCore\Sites::get( $env_data->site_id );
                    $env_name = strtolower( $env_data->environment );
                }
            }
        }

        // If we found an Environment ID, look up the details from the DB
        if ( $environment_id ) {
            $env_obj = CaptainCore\Environments::get( $environment_id );
            if ( $env_obj ) {
                $site_obj = CaptainCore\Sites::get( $env_obj->site_id );
                $env_name = strtolower( $env_obj->environment );
                
                // Security Check
                if ( ! captaincore_verify_permissions( $site_obj->site_id ) ) {
                    continue; 
                }
            }
        }

        // Construct the target string (e.g., "my-site-production")
        if ( $site_obj && $env_name ) {
            $targets[] = "{$site_obj->site}-{$env_name}";
        }
    }

    if ( empty( $targets ) ) {
        return new WP_Error( 'invalid_targets', 'No valid targets found or permission denied.', [ 'status' => 403 ] );
    }

    // Dedup targets and format command
    $targets       = array_unique( $targets );
    $target_string = implode( " ", $targets );
    $encoded_code  = base64_encode( stripslashes_deep( $code ) );
    
    // Dispatch to CLI
    $command = "run $target_string --code=$encoded_code";

    return CaptainCore\Run::task( $command );
}

function captaincore_site_analytics_func( $request ) {
	$site_id     = $request['id'];
	$before      = strtotime( $request['from_at'] );
	$after       = strtotime( $request['to_at'] );
	$grouping    = strtolower( $request['grouping'] );
	$environment = $request['environment'];
	$fathom_id   = $request['fathom_id'];
	return ( new CaptainCore\Site( $site_id ) )->stats( $environment, $before, $after, $grouping, $fathom_id );
	//return "( new CaptainCore\Site( $site_id ) )->stats( $environment, $before, $after, $grouping, $fathom_id )";
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
    return ( new CaptainCore\Domain( $domain_id ) )->set_contacts( $request['contacts'] );
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		echo json_encode( [ "error" => $error_message ] );
		wp_die();
		return;
	}
	echo json_encode( $response ); 
}

function captaincore_domain_check_func( $request ) {
	return $request['domain'];
	return new WP_REST_Response(null, 200);
	return new WP_REST_Response(null, 403);
	$authorized_domains = ( new CaptainCore\Domains )->authorized_domains();
	if ( in_array( $request['domain'], $authorized_domains ) ) {
		return "Domain Authorized";
	}
	return new WP_REST_Response(null, 503);
}

function captaincore_domain_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	return ( new CaptainCore\Domain( $domain_id ) )->fetch();
}

function captaincore_domain_privacy_func( $request ) {
	$domain_id = $request['id'];
	$status    = $request['status'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	if ( $status == "on" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->privacy_on();
	}
	if ( $status == "off" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->privacy_off();
	}
	return new WP_Error( 'request_invalid', 'Invalid Request', [ 'status' => 404 ] );
}

function captaincore_domain_lock_func( $request ) {
	$domain_id = $request['id'];
	$status    = $request['status'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	if ( $status == "on" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->lock();
	}
	if ( $status == "off" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->unlock();
	}
	return new WP_Error( 'request_invalid', 'Invalid Request', [ 'status' => 404 ] );
}

function captaincore_domain_update_contacts_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
    return ( new CaptainCore\Domain( $domain_id ) )->set_contacts( $request['contacts'] );
}

function captaincore_domain_update_nameservers_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
    return ( new CaptainCore\Domain( $domain_id ) )->set_nameservers( $request['nameservers'] );
}

function captaincore_domain_auth_code_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	$domain    = ( new CaptainCore\Domains )->get( $domain_id );

	if ( empty( $domain->provider_id ) ) {
		return new WP_Error( 'no_domain', 'No records', [ 'status' => 200 ] );
	}

	return ( new CaptainCore\Domain( $domain_id ) )->auth_code();
}

/**
 * REST API callback to update a domain's account link based on a site.
 * Verifies user has permission for both the domain and the site.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_domain_update_site_link_func( WP_REST_Request $request ) {
    $domain_id = $request['id'];
    $site_id   = $request->get_param('site_id');

    if ( empty($site_id) ) {
        return new WP_Error( 'missing_param', 'Site ID is required.', [ 'status' => 400 ] );
    }

    // Verify permissions for both domain and site
    if ( ! ( new \CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'permission_denied_domain', 'Permission denied for this domain.', [ 'status' => 403 ] );
    }
    if ( ! ( new \CaptainCore\Sites )->verify( $site_id ) ) {
        return new WP_Error( 'permission_denied_site', 'Permission denied for the selected site.', [ 'status' => 403 ] );
    }

    // Get the site to find its customer account
    $site = \CaptainCore\Sites::get( $site_id );
    if ( ! $site || empty( $site->customer_id ) || $site->customer_id == "0" ) {
        return new WP_Error( 'no_customer_account', 'The selected site does not have a valid customer account linked.', [ 'status' => 400 ] );
    }

    $account_id_to_link = $site->customer_id;

    // Use the existing assign_accounts method to link the domain to this single account
    // This will overwrite any existing links, which matches the client-side's expectation.
    ( new \CaptainCore\Domain( $domain_id ) )->assign_accounts( [ $account_id_to_link ] );

    return new WP_REST_Response( [ 'message' => 'Domain billing account updated successfully.' ], 200 );
}

function captaincore_dns_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	$remote_id = ( new CaptainCore\Domains )->get( $domain_id )->remote_id;

	if ( empty( $remote_id ) ) {
		return new WP_Error( 'no_zone', 'No DNS zone found for this domain.', [ 'status' => 404 ] );
	}

	$domain  = CaptainCore\Remote\Constellix::get( "domains/$remote_id" );
	$records = CaptainCore\Remote\Constellix::get( "domains/$remote_id/records?perPage=100" );

	// Check for errors from Constellix
	if ( ! empty( $records->errors ) ) {
		return new WP_Error( 'constellix_error', 'Error fetching DNS records.', [ 'status' => 500, 'details' => $records->errors ] );
	}

	// Handle pagination if there are more records
	if ( ! empty( $records->meta->pagination->total ) ) {
		$steps = ceil( $records->meta->pagination->total / 100 );
		for ($i = 1; $i < $steps; $i++) {
			$page = $i + 1;
			$additional_records = CaptainCore\Remote\Constellix::get( "domains/$remote_id/records?page=$page&perPage=100" );
			if ( ! empty( $additional_records->data ) ) {
				$records->data = array_merge($records->data, $additional_records->data);
			}
		}
	}

	// Sort records if data exists
	if ( ! empty( $records->data ) && is_array( $records->data ) ) {
		array_multisort( array_column( $records->data, 'type' ), SORT_ASC, array_column( $records->data, 'name' ), SORT_ASC, $records->data );
	}

	return [ 
		"records"     => $records->data ?? [], 
		"nameservers" => $domain->data->nameservers ?? [] 
	];
}

/**
 * Formats DNS record data for the Constellix API.
 *
 * @param string $record_type   The type of the DNS record (A, AAAA, CNAME, etc.).
 * @param string $record_name   The name of the DNS record.
 * @param mixed  $record_value  The value of the DNS record.
 * @param int    $record_ttl    The TTL for the DNS record.
 * @return array The formatted post data for the API request.
 */
function captaincore_format_dns_record_for_api( $record_type, $record_name, $record_value, $record_ttl ) {
    $record_type = strtolower( $record_type );
    $post_data   = [
        'name' => $record_name,
        'type' => $record_type,
        'ttl'  => $record_ttl,
    ];

    if ( in_array( $record_type, [ 'a', 'aaaa', 'aname', 'cname', 'txt', 'spf' ] ) ) {
        $records = [];
        foreach ( (array) $record_value as $record ) {
            $records[] = [
                'value'   => stripslashes( $record['value'] ),
                'enabled' => true,
            ];
        }
        $post_data['value'] = $records;
    } elseif ( $record_type == 'mx' ) {
        $mx_records = [];
        foreach ( (array) $record_value as $mx_record ) {
            $mx_records[] = [
                'server'   => $mx_record['server'],
                'priority' => $mx_record['priority'],
                'enabled'  => true,
            ];
        }
        $post_data['value'] = $mx_records;
    } elseif ( $record_type == 'srv' ) {
        $srv_records = [];
        foreach ( (array) $record_value as $srv_record ) {
            $srv_records[] = [
                'host'     => $srv_record['host'],
                'priority' => $srv_record['priority'],
                'weight'   => $srv_record['weight'],
                'port'     => $srv_record['port'],
                'enabled'  => true,
            ];
        }
        $post_data['value'] = $srv_records;
    } elseif ( $record_type == 'http' ) {
        $post_data['value'] = [
            'hard'         => true,
            'url'          => $record_value,
            'redirectType' => '301',
        ];
    } else {
        $post_data['value'] = $record_value;
    }

    return $post_data;
}

/**
 * REST API callback to create a DNS record.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_create_dns_record( WP_REST_Request $request ) {
    $domain_id    = $request['id'];
	$remote_id    = ( new CaptainCore\Domains )->get( $domain_id )->remote_id;
    $record_type  = $request->get_param( 'type' );
    $record_name  = $request->get_param( 'name' );
    $record_value = $request->get_param( 'value' );
    $record_ttl   = $request->get_param( 'ttl' );

    $post_data = captaincore_format_dns_record_for_api( $record_type, $record_name, $record_value, $record_ttl );
    $response  = CaptainCore\Remote\Constellix::post( "domains/$remote_id/records", $post_data );

    if ( ! empty( $response->errors ) ) {
        $errors = is_object( $response->errors ) ? (array) $response->errors : $response->errors;
        return new WP_Error( 'constellix_error', 'Failed to create DNS record.', [ 'status' => 400, 'details' => $errors ] );
    }

    return new WP_REST_Response( $response, 201 );
}

/**
 * REST API callback to update a DNS record.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_update_dns_record( WP_REST_Request $request ) {
    $domain_id    = $request['id'];
	$remote_id    = ( new CaptainCore\Domains )->get( $domain_id )->remote_id;
    $record_id    = $request['record_id'];
    $record_type  = $request->get_param( 'type' );
    $record_name  = $request->get_param( 'name' );
    $record_value = $request->get_param( 'value' );
    $record_ttl   = $request->get_param( 'ttl' );

    $post_data = captaincore_format_dns_record_for_api( $record_type, $record_name, $record_value, $record_ttl );
    $response  = CaptainCore\Remote\Constellix::put( "domains/$remote_id/records/$record_id", $post_data );

    if ( ! empty( $response->errors ) ) {
        $errors = is_object( $response->errors ) ? (array) $response->errors : $response->errors;
        return new WP_Error( 'constellix_error', 'Failed to update DNS record.', [ 'status' => 400, 'details' => $errors ] );
    }

    return new WP_REST_Response( $response, 200 );
}

/**
 * REST API callback to delete a DNS record.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_delete_dns_record( WP_REST_Request $request ) {
    $domain_id = $request['id'];
	$remote_id    = ( new CaptainCore\Domains )->get( $domain_id )->remote_id;
    $record_id = $request['record_id'];

    $response = CaptainCore\Remote\Constellix::delete( "domains/$remote_id/records/$record_id" );

    if ( ! empty( $response->errors ) ) {
        return new WP_Error( 'constellix_error', 'Failed to delete DNS record.', [ 'status' => 400, 'details' => $response->errors ] );
    }

    return new WP_REST_Response( [ 'message' => 'Record deleted successfully.' ], 200 );
}

function captaincore_domains_func( $request ) {
	return ( new CaptainCore\Domains() )->list();
}

function captaincore_domain_zone_func( $request ) {
	$domain_id = $request->get_param( "id" );
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	return CaptainCore\Domain::zone( $domain_id );
}
function captaincore_domain_zone_import_func( $request ) {
	$domain = $request->get_param( "domain" );
	$zone   = $request->get_param( "zone" );
	$lines  = explode( "\n", $zone );
	foreach( $lines as $line ) {
	if ( str_contains( $line, "\$ORIGIN" ) ) {
		$domain = str_replace( "\$ORIGIN", "", $line );
		$domain = trim( $domain );
		$domain = trim( $domain, "." );
		}
	}
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return CaptainCore\Domains::records( $domain, $zone );
}

/**
 * REST API callback to get Mailgun domain status and optionally trigger verification.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_domain_email_forwarding_status_func( WP_REST_Request $request ) {
    $domain_id = $request['id'];
    $verify    = $request->get_param('verify'); // Check if 'verify' query param is set

    // Verify user permissions for this domain
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
    }

    $domain = ( new CaptainCore\Domains )->get( $domain_id );
    if ( empty( $domain->name ) ) {
        return new WP_Error( 'no_domain', 'Domain not found.', [ 'status' => 404 ] );
    }

    $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

    // If verify=true, trigger a re-check with Mailgun
    if ( $verify ) {
        // This endpoint triggers a DNS verification check in Mailgun
        \CaptainCore\Remote\Mailgun::put( "v4/domains/{$domain->name}/verify" );
    }

    // Get the domain status from Mailgun
    $mailgun_response = \CaptainCore\Remote\Mailgun::get( "v4/domains/{$domain->name}" );
    
    // Check if domain exists in Mailgun
    $domain_exists = ! empty( $mailgun_response->domain ) || ( empty( $mailgun_response->message ) || $mailgun_response->message !== "Domain not found" );
    
    // Check for MX record validity from Mailgun's receiving_dns_records
    $has_mx_record = false;
    if ( ! empty( $mailgun_response->receiving_dns_records ) ) {
        foreach ( $mailgun_response->receiving_dns_records as $record ) {
            if ( $record->record_type === 'MX' && $record->valid === 'valid' ) {
                $has_mx_record = true;
                break;
            }
        }
    }

    // Build response compatible with the frontend
    $response = (object) [
        'id'                   => $domain->name,
        'name'                 => $domain->name,
        'has_mx_record'        => $has_mx_record,
        'has_txt_record'       => true, // Mailgun doesn't require TXT for inbound routing
        'forwarding_active'    => ! empty( $details->mailgun_forwarding_id ),
        'mailgun_domain'       => $mailgun_response->domain ?? null,
        'receiving_dns_records'=> $mailgun_response->receiving_dns_records ?? [],
        'sending_dns_records'  => $mailgun_response->sending_dns_records ?? [],
        'state'                => $mailgun_response->domain->state ?? 'unknown',
    ];

    return new WP_REST_Response( $response, 200 );
}

/**
 * Fetches email forwarding logs from Mailgun for a domain.
 * This uses the root domain (e.g., example.com) for inbound/forwarding events.
 */
function captaincore_domain_email_forwarding_logs_func( WP_REST_Request $request ) {
    $domain_id = $request['id'];
    $params    = $request->get_query_params();

    // Verify user permissions for this domain
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
    }

    $domain = ( new CaptainCore\Domains )->get( $domain_id );
    if ( empty( $domain->name ) ) {
        return new WP_Error( 'no_domain', 'Domain not found.', [ 'status' => 404 ] );
    }

    $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

    if ( empty( $details->mailgun_forwarding_id ) ) {
        return new WP_Error( 'forwarding_not_configured', 'Email forwarding not configured for this domain.', [ 'status' => 404 ] );
    }

    // Use the root domain name for fetching forwarding/inbound events
    $zone = $domain->name;

    // If paging URL is provided, use it directly
    if ( ! empty( $params['page_url'] ) ) {
        $response = \CaptainCore\Remote\Mailgun::page( $zone, $params['page_url'] );
    } else {
        // Fetch events - for forwarding, we want to see stored, accepted, delivered, failed events
        $event_filter = $params['event'] ?? 'stored OR accepted OR delivered OR failed';
        $response = \CaptainCore\Remote\Mailgun::get( "v3/$zone/events", [ 
            'event' => $event_filter,
            'limit' => 100 
        ] );
    }

    return new WP_REST_Response( $response, 200 );
}

/**
 * Delete email forwarding for a domain (admin only).
 * This deletes the Mailgun domain used for forwarding and all associated routes.
 */
function captaincore_domain_email_forwarding_delete_func( WP_REST_Request $request ) {
    $domain_id = $request['id'];

    // Verify user permissions for this domain
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
    }

    $domain = ( new CaptainCore\Domains )->get( $domain_id );
    if ( empty( $domain->name ) ) {
        return new WP_Error( 'no_domain', 'Domain not found.', [ 'status' => 404 ] );
    }

    $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

    if ( empty( $details->mailgun_forwarding_id ) ) {
        return new WP_Error( 'forwarding_not_configured', 'Email forwarding not configured for this domain.', [ 'status' => 404 ] );
    }

    // Delete the Mailgun domain (this also deletes all routes associated with it)
    $zone = $domain->name;
    $response = \CaptainCore\Remote\Mailgun::delete( "v3/domains/$zone" );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    // Update local domain details to remove forwarding info
    unset( $details->mailgun_forwarding_id );
    
    // Update the domain in the database
    ( new CaptainCore\Domains )->update( [ 'details' => json_encode( $details ) ], [ 'domain_id' => $domain_id ] );

    // Get updated domain for response
    $updated_domain = ( new CaptainCore\Domains )->get( $domain_id );

    return new WP_REST_Response( [
        'message' => 'Email forwarding deleted successfully.',
        'domain'  => [
            'domain_id' => $domain_id,
            'name'      => $domain->name,
            'details'   => json_decode( $updated_domain->details ),
        ],
    ], 200 );
}

function captaincore_recipes_func( $request ) {
	return ( new CaptainCore\Recipes() )->list();
}

function captaincore_recipes_delete_func( $request ) {
	$recipe_id = $request->get_param( "id" );
	if ( ! ( new CaptainCore\Recipes )->verify( $recipe_id ) ) {
		return new WP_Error( 'permission_denied', "You do not have permission to delete this recipe.", [ 'status' => 403 ] );
	}
	
	$result = ( new CaptainCore\Recipes )->delete( $recipe_id );

	if ( $result ) {
		CaptainCore\Run::CLI( "recipe delete {$recipe_id}" );
		return;
	}
	
	return new WP_Error( 'delete_failed', "Failed to delete recipe.", [ 'status' => 500 ] );
}

function captaincore_running_func( $request ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
		}

		$data = [ 
			'timeout' => 45,
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'token'        => CAPTAINCORE_CLI_TOKEN 
			],
			'body'        => json_encode( [ "command" => "running list" ] ), 
			'method'      => 'POST', 
			'data_format' => 'body' 
		];

		// Add command to dispatch server
		$response  = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
		$processes = json_decode( $response["body"]);

		usort( $processes, function($a, $b) { return strcmp($b->created_at, $a->created_at); });
		
		return $processes;

	} 

	return [];
}

function captaincore_site_phpmyadmin_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id, $environment );
	return $site->fetch_phpmyadmin();
}

/**
 * REST Callback: Get Push Targets for a specific environment
 */
function captaincore_get_push_targets_func( WP_REST_Request $request ) {
	$site_id        = (int) $request['site_id'];
	$environment_id = (int) $request['environment_id'];

	// Verify permission for the source site
	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'permission_denied', 'Permission denied for source site.', [ 'status' => 403 ] );
	}

	$source_site = CaptainCore\Sites::get( $site_id );
	if ( ! $source_site ) {
		return new WP_Error( 'no_provider', 'Source site has no provider configured.', [ 'status' => 400 ] );
	}

	$provider = new CaptainCore\Provider( $source_site->provider );
	$response = $provider->get_push_targets( $site_id, $environment_id );

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	
	return new WP_REST_Response( $response, 200 );
}


/**
 * REST Callback: Initiate Environment Push
 */
function captaincore_push_environment_func( WP_REST_Request $request ) {
	$params = $request->get_json_params();

	$source_environment_id   = (int) $params['source_environment_id']; // CaptainCore Environment ID
	$target_environment_id   = (int) $params['target_environment_id']; // CaptainCore Environment ID

	// Get source environment and site to find the provider
	$source_env  = CaptainCore\Environments::get( $source_environment_id );
	$target_env  = CaptainCore\Environments::get( $target_environment_id );
	if ( ! $source_env ) {
		return new WP_Error( 'invalid_source', 'Invalid source environment.', [ 'status' => 400 ] );
	}
	if ( ! $target_env ) {
		return new WP_Error( 'invalid_target', 'Invalid target environment.', [ 'status' => 400 ] );
	}

	$source_site = CaptainCore\Sites::get( $source_env->site_id );
	$target_site = CaptainCore\Sites::get( $target_env->site_id );
	if ( ! $source_site ) {
		return new WP_Error( 'no_site', 'Source site not found.', [ 'status' => 400 ] );
	}
	if ( ! $target_site ) {
		return new WP_Error( 'no_site', 'Target site not found.', [ 'status' => 400 ] );
	}

	// Verify permission for the source site
	if ( ! captaincore_verify_permissions( $source_site->site_id ) ) {
		return new WP_Error( 'permission_denied', 'Permission denied for source site.', [ 'status' => 403 ] );
	}
	if ( ! captaincore_verify_permissions( $target_site->site_id ) ) {
		return new WP_Error( 'permission_denied', 'Permission denied for target site.', [ 'status' => 403 ] );
	}

	// Use the Provider class instance
	$provider = new CaptainCore\Provider( $source_site->provider );

	// Call the push_environment method
	$response = $provider->push_environment( $source_environment_id, $target_environment_id );

	if ( is_wp_error( $response ) ) {
		return $response; // Propagate WP_Error
	}

	// Check Kinsta API response structure for success/operation_id
	if ( isset( $response->operation_id ) ) {
		// Add action for tracking
		 $action_details = (object) [
			"command"                   => "push_environment",
			"message"                   => "Pushing {$source_site->name} ({$source_env->environment}) to target environment",
			"source_site_id"            => $source_site->site_id,
			"source_environment_id"     => $source_environment_id,
			"target_environment_id" 	=> $target_environment_id,
			"provider_id"               => $source_site->provider_id,
		];
		// Assumes Kinsta for now, this part could also be abstracted
		CaptainCore\Providers\Kinsta::add_action( $response->operation_id, $action_details );

		return new WP_REST_Response( [ 'operation_id' => $response->operation_id, 'message' => $response->message ?? 'Push operation started.' ], 202 ); // Accepted
	} elseif ( isset( $response->message ) ) {
		 return new WP_REST_Response( [ 'message' => $response->message ], 200 );
	} else {
		return new WP_Error( 'api_error', 'Unexpected response from provider API during push.', [ 'status' => 500, 'details' => $response ] );
	}
}

/**
 * REST Callback: List Environments
 */
function captaincore_environments_func( WP_REST_Request $request ) {

	global $wpdb;

	// --- Get Allowed Sites for Current User ---
	$user_sites = new \CaptainCore\Sites(); // Instantiate to get user-specific permissions
	$allowed_site_ids = $user_sites->site_ids(); // Get the array of site IDs the user can access

	// If the user has no allowed sites, return early
	if ( empty( $allowed_site_ids ) ) {
		return [];
	}

	// --- Prepare Database Query ---
	$sites_table        = $wpdb->prefix . 'captaincore_sites';
	$environments_table = $wpdb->prefix . 'captaincore_environments';

	// Prepare base WHERE clauses safely
	$where_clauses = [
		$wpdb->prepare( "s.provider = %s", "kinsta" ),
		$wpdb->prepare( "s.status = %s", "active" ),
		$wpdb->prepare( "e.environment_id != %d", $source_environment_id ),
	];

	// --- Add User Permission Filter ---
	// Safely create the IN clause for allowed site IDs
	$allowed_ids_sql = implode( ',', array_map( 'intval', $allowed_site_ids ) ); // Ensure integers
	$where_clauses[] = "s.site_id IN ( $allowed_ids_sql )"; // Add the IN clause

	$where_sql = implode( ' AND ', $where_clauses );

	// Construct the final optimized query string
	// Note: $allowed_ids_sql is already sanitized by array_map('intval', ...)
	$sql = "SELECT s.site_id, s.name, e.environment, e.environment_id, e.home_url
			FROM $environments_table e
			INNER JOIN $sites_table s ON e.site_id = s.site_id
			WHERE $where_sql
			ORDER BY s.name ASC";

	$results = $wpdb->get_results( $sql ); // Execute the query

	// Map results directly to the target format
	$targets = [];
	if ( $results ) {
		foreach ( $results as $row ) {
			$targets[] = [
				'site_id'        => (int) $row->site_id,
				'name'           => $row->name,
				'environment'    => $row->environment,
				'environment_id' => (int) $row->environment_id,
				'home_url'       => $row->home_url ?? $row->name,
			];
		}
	}

	return $targets;
}

function captaincore_site_magiclogin_func( $request ) {
	$site_id     = $request['id'];
	$user_id     = $request['user_id'];
	$environment = $request['environment'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment_id = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );
	$environment    = ( new CaptainCore\Environments )->get( $environment_id );
	$current_email  = ( new CaptainCore\User )->fetch()["email"];
	$users          = json_decode( $environment->users );

	// Match user by ID
	if ( ! empty( $user_id ) ) {
		foreach ( $users as $user ) {
			if ( $user->ID == $user_id ) {
				$login = $user->user_login;
				break;
			}
		}
	}

	// Attempt to match current user to WordPress user
	if ( empty( $login ) ) {
		foreach ( $users as $user ) {
			if ( strpos( $user->roles, 'administrator' ) !== false && $user->user_email == $current_email ) {
				$login = $user->user_login;
				break;
			}
		}
	}

	if ( empty( $login ) ) {
		$current_user_domain = array_pop(explode('@', $current_email));
		// Attempt to match current user to a similar WordPress user
		foreach ( $users as $user ) {
			$user_domain = array_pop(explode('@', $user->user_email));
			if ( strpos( $user->roles, 'administrator') !== false && $user_domain == $current_user_domain ) {
				$login = $user->user_login;
				break;
			}
		}

		// Select random WordPress admin with same first name
		if ( empty( $login ) ) {
			$current_email_name = array_shift(explode('@', $current_email));
			foreach ( $users as $user ) {
				$user_email_name = array_shift(explode('@', $user->user_email));
				if ( strpos( $user->roles, 'administrator' ) !== false && $user_email_name == $current_email_name ) {
					$login = $user->user_login;
					break;
				}
			}
		}

		// Select random WordPress admin
		if ( empty( $login ) ) { 
			foreach ( $users as $user ) {
				if ( strpos( $user->roles, 'administrator' ) !== false ) {
					$login = $user->user_login;
					break;
				}
			}
		}
	}

	$args     = [
		"timeout" => 45,
		"body"    => json_encode( [
				"command"    => "login",
				"user_login" => $login,
				"token"      => $environment->token,
			] ),
		"method"    => 'POST',
		"sslverify" => false,
	];
	$response  = wp_remote_post( "{$environment->home_url}/wp-admin/admin-ajax.php?action=captaincore_quick_login", $args );
	$login_url = trim( $response["body"] );
	return $login_url;
}

function captaincore_processes_func( $request ) {
	return ( new CaptainCore\Processes )->list();
}

function captaincore_users_func( $request ) {
	
	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {
		return ( new CaptainCore\Users() )->list();
	} 
	return [];

}

function captaincore_keys_func( $request ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {
		return ( new CaptainCore\Keys )->all( "title", "ASC" );
	} 
	return [];

}

function captaincore_defaults_func( $request ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {
		return ( new CaptainCore\Defaults )->get();
	}
	return [];

}

function captaincore_site_snapshots_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$results = ( new CaptainCore\Site( $site_id ))->snapshots();
	return $results;
}

function captaincore_filters_sites_func( WP_REST_Request $request ) {
    $filters       = $request->get_json_params();
    $sites_handler = new CaptainCore\Sites();
    $results       = $sites_handler->fetch_sites_matching_filters( $filters ); 
    return [ "results" => $results ];
}

function captaincore_filter_versions_func( $request ) {
	$name     = str_replace( "%20", " ", $request['name'] );
	$filters  = explode( ",", $name );
	$response = ( new CaptainCore\Environments )->filters_for_versions( $filters );
	return $response;
}

function captaincore_filter_statuses_func( $request ) {
	$name     = str_replace( "%20", " ", $request['name'] );
	$filters  = explode( ",", $name );
	$response = ( new CaptainCore\Environments )->filters_for_statuses( $filters );
	return $response;
}

function captaincore_filters_func( $request ) {

	$themes  = empty( $request['themes'] ) ? [] : $request['themes'];
	$plugins = empty( $request['plugins'] ) ? [] : $request['plugins'];
	$core    = empty( $request['core'] ) ? [] : $request['core'];
	if ( is_string( $core ) ) {
		$core = [ $core ];
	}

	$theme_filters  = ( new CaptainCore\Environments )->filters_for_themes( $themes );
	$plugin_filters = ( new CaptainCore\Environments )->filters_for_plugins( $plugins );
	$core_filters   = ( new CaptainCore\Environments )->filters_for_core();
	$sites          = ( new CaptainCore\Sites )->fetch_sites_matching_filters( $request['themes'], $request['plugins'], $core );
	$response = [
		"filters" => [
			"themes"        => $theme_filters,
			"plugins"       => $plugin_filters,
			"core"          => $core_filters,
			"core_versions" => $core
		],
		"sites" => $sites
	];
	return $response;
}

function captaincore_filter_sites_func( $request ) {
	$name     = str_replace( "%20", " ", $request['name'] );
	$statuses = $request['statuses'];
	$statuses = explode( ",", $statuses );
	$versions = $request['versions'];
	$versions = explode( ",", $versions );
	foreach ($statuses as $key => $value) {
		$value = explode( "+", $value );
		$statuses[ $key ] = [
			"type" => $value[2],
			"slug" => $value[1],
			"name" => $value[0],
		];
	}
	foreach ($versions as $key => $value) {
		$value = explode( "+", $value );
		$versions[ $key ] = [
			"type" => $value[2],
			"slug" => $value[1],
			"name" => $value[0],
		];
	}
	$sites = ( new CaptainCore\Sites )->fetch_sites_matching_versions_statuses( [
		"filter"   => $name,
		"versions" => $versions,
		"statuses" => $statuses,
	] );
	$response = [
		"filters" => $filters,
		"sites" => $sites
	];
	return $response;
}

function captaincore_site_captures_new_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = strtolower( $request['environment'] );
	$site        = new CaptainCore\Site( $site_id );
	
	// Remote Sync
	captaincore_run_background_command( "capture $site_id-$environment" );
	return $site_id;
}

function captaincore_site_grant_access_func( $request ) {
	$site_id             = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$account_ids         = $request['account_ids'];
	if ( is_string( $account_ids ) ) {
		$account_ids = [ $account_ids ];
	}

	foreach ( $account_ids as $account_id ) {
		if ( ! ( new CaptainCore\User )->is_admin() && ! captaincore_verify_permissions_account( $account_id ) ){ 
			return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
		}
	}

	$site                = new CaptainCore\Site( $site_id );
	$accountsite         = new CaptainCore\AccountSite();
	$current_account_ids = array_column ( $accountsite->where( [ "site_id" => $site_id ] ), "account_id" );
	$account_ids         = array_unique(array_merge( $account_ids, $current_account_ids ) );
	$site->assign_accounts( $account_ids );
	return $site_id;
}

function captaincore_site_environment_monitor_update_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment     = $request['environment'];
	$environment_id  = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );
	$time_now        = date("Y-m-d H:i:s");
	$environment_update = [
		'monitor_enabled' => $request['monitor'],
		'updated_at'      => $time_now,
	];

	( new CaptainCore\Environments )->update( $environment_update, [ "environment_id" => $environment_id ] );

	captaincore_run_background_command( "site sync $site_id" );

}

function captaincore_site_captures_update_func( $request ) {
	$site_id     = $request['id'];
	$auth        = empty( $request['auth'] ) ? "" : $request['auth'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	$time_now    = date("Y-m-d H:i:s");
	$pages       = $request['pages'];

	// Make sure home page is added
	$home_found = false;
	foreach ( $pages as $page ) {
		if ( $page["page"] == "/" ) {
			$home_found = true;
		}
	}
	if ( ! $home_found ) {
		array_unshift( $pages, [ "page" => "/" ] );
	}

	$pages = json_encode( $pages );

	// Saves update settings for a site
	$environment_update = [
		'capture_pages' => $pages,
		'updated_at'    => $time_now,
	];

	$environment_id  = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );
	
	if ( ! empty( $auth['username'] ) ) {
		$fetch         = ( new CaptainCore\Environments )->get( $environment_id );
		$details       = ( isset( $fetch->details ) ? json_decode( $fetch->details ) : (object) [] );
		$details->auth = $auth;
		$environment_update['details'] = json_encode( $details );
	}

	( new CaptainCore\Environments )->update( $environment_update, [ "environment_id" => $environment_id ] );

	// Remote Sync
	captaincore_run_background_command( "site sync $site_id" );
	return $site->captures( $environment );
}

function captaincore_site_backup_update_func( $request ) {
	$site_id  = $request['id'];
	$settings = (object) $request->get_param( 'settings' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$site     = ( new CaptainCore\Sites() )->get( $site_id );
	$time_now = date("Y-m-d H:i:s");
	$details  = ( empty( $site->details ) ) ? (object) [] : json_decode( $site->details );

	$details->backup_settings = [
		"active"   => $settings->active,
		"interval" => $settings->interval,
		"mode"     => $settings->mode
	];
	
	// Saves update settings for a site
	$site_update = [
		'details'    => json_encode( $details ),
		'updated_at' => $time_now,
	];

	( new CaptainCore\Sites )->update( $site_update, [ "site_id" => $site_id ] );

	// Remote Sync
	captaincore_run_background_command( "site sync $site_id" );
	return ( new CaptainCore\Site( $site_id ) )->fetch()->backup_settings;
}

function captaincore_site_snapshot_download_func( $request ) {
	$site_id       = $request['id'];
	$token         = $request['token'];
	$snapshot_id   = $request['snapshot_id'];
	$snapshot_name = $request['snapshot_name'] . ".zip";

	// Verify Snapshot link is valid
	$db = new CaptainCore\Snapshots();
	$snapshot = $db->get( $snapshot_id );

	if ( $snapshot->snapshot_name != $snapshot_name || $snapshot->site_id != $site_id || $snapshot->token != $token ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$snapshot_url = captaincore_snapshot_download_link( $snapshot_id  );
	header('Location: ' . $snapshot_url);
	exit;
}

function captaincore_update_logs_get_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$hash_before = $request['hash_before'];
	$hash_after  = $request['hash_after'];
	$environment = $request['environment'];
	$command     = "update-log get $site_id-$environment $hash_before $hash_after";
	$response    = CaptainCore\Run::CLI( $command );
	return json_decode( $response );
}

function captaincore_quicksaves_get_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$hash        = $request['hash'];
	$environment = $request['environment'];
	return ( new CaptainCore\Quicksave( $site_id ) )->get( $hash, $environment );
}

function captaincore_quicksaves_changed_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );
	$match       = $request->get_param( 'match' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$hash        = $request['hash'];
	return ( new CaptainCore\Quicksave( $site_id ) )->changed( $hash, $environment, $match );
}

function captaincore_quicksaves_filediff_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );
	$file        = $request->get_param( 'file' );
	$hash        = $request['hash'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	return ( new CaptainCore\Quicksave( $site_id ) )->filediff( $hash, $environment, $file );
}

function captaincore_quicksaves_rollback_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = strtolower( $request->get_param( 'environment' ) );
	$type        = $request->get_param( 'type' );
	$value       = empty( $request->get_param( 'value' ) ) ? "" : $request->get_param( 'value' );
	$version     = $request->get_param( 'version' );
	$hash        = $request['hash'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	return ( new CaptainCore\Quicksave( $site_id ) )->rollback( $hash, $environment, $version, $type, $value );
}

function captaincore_site_backups_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	return $site->backups( $environment );
}

function captaincore_site_sync_data_func( $request ) {
	$site_id     = $request['id'];
	$environment = $request['environment'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	$site        = ( new CaptainCore\Sites )->get( $site_id );

	return CaptainCore\Run::task( "sync-data {$site->site}-{$environment}" );
}

function captaincore_quicksaves_search_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );
	$search      = $request->get_param( 'search' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	return ( new CaptainCore\Quicksave( $site_id ) )->search( $search, $environment );
}

function captaincore_site_backups_get_func( $request ) {
	$site_id = $request['id'];
	$file    = $request->get_param( 'file' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$backup_id   = $request['backup_id'];
	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	if ( ! empty( $file ) ) {
		return $site->backup_show_file( $backup_id, $file, $environment );
	}
	return $site->backup_get( $backup_id, $environment );
}

function captaincore_site_invite_preview_func( $request ) {
    $site_id = $request['id'];
    
    // 1. Security Check: Does current user have access to this specific site?
    if ( ! captaincore_verify_permissions( $site_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    $site = CaptainCore\Sites::get( $site_id );
    if ( ! $site ) {
        return new WP_Error( 'not_found', 'Site not found.', [ 'status' => 404 ] );
    }

    // 2. Resolve to Customer Account (permissions are linked to accounts)
    $account_id = $site->customer_id;
    if ( empty( $account_id ) ) {
        $account_id = $site->account_id;
    }

    // 3. Calculate Scope (Run as admin privilege to get accurate counts of what is being shared)
    $account_obj = new CaptainCore\Account( $account_id, true );
    
    $all_sites   = $account_obj->sites();
    $all_domains = $account_obj->domains();
    $account_rec = CaptainCore\Accounts::get( $account_id );

    $site_count   = count( $all_sites );
    $domain_count = count( $all_domains );

    // 4. Check if current user has full access to this account
    // This determines if they see the specific list or just the counts
    $user_id = get_current_user_id();
    $user    = new CaptainCore\User( $user_id );
    $has_account_access = $user->verify_accounts( [ $account_id ] );

    return [
        'site_name'          => $site->name,
        'account_name'       => $account_rec->name,
        'total_sites'        => $site_count,
        'total_domains'      => $domain_count,
        'has_account_access' => $has_account_access,
        'sites_list'         => $has_account_access ? $all_sites : [], 
        'domains_list'       => $has_account_access ? $all_domains : [],
    ];
}

function captaincore_site_invite_func( $request ) {
    $site_id = $request['id'];
    $email   = $request->get_param('email');

    if ( ! is_email( $email ) ) {
        return new WP_Error( 'invalid_email', 'Invalid email address.', [ 'status' => 400 ] );
    }

    // 1. Security Check
    if ( ! captaincore_verify_permissions( $site_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    $site = CaptainCore\Sites::get( $site_id );
    
    // 2. Resolve Account
    $account_id = $site->customer_id ? $site->customer_id : $site->account_id;

    // 3. Perform Invite
    $account_obj = new CaptainCore\Account( $account_id, true );
    $account_obj->invite( $email );

    // 4. Return Generic Success Message (Privacy Protection)
    // We ignore the specific return from invite() to prevent user enumeration
    return [ 
        "message" => "Invitation sent successfully." 
    ];
}

function captaincore_site_logs_list_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = ( new CaptainCore\Sites )->get( $site_id );
	$response    = CaptainCore\Run::CLI( "logs list {$site->site}-$environment" );
	return json_decode( $response );
}

function captaincore_site_logs_fetch_func( $request ) {
	$site_id = $request['id'];
	
	// Get parameters from the POST body
	$params = $request->get_json_params();
	$file   = $params['file'] ?? '';
	$limit  = $params['limit'] ?? '1000'; // Default limit if not provided

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = ( new CaptainCore\Sites )->get( $site_id );
	return CaptainCore\Run::CLI( "logs get {$site->site}-$environment --file=\"$file\" --limit=$limit" );
}

function captaincore_site_environments_get_func( $request ) {
	$site_id = $request['id'];
	$site    = new CaptainCore\Site( $site_id );
	return $site->environments();
}

/**
 * Handles Mailgun setup request.
 */
function captaincore_mailgun_setup( WP_REST_Request $request ) {
    $domain_id   = $request->get_param( 'id' );
    $full_domain = $request->get_param( 'domain' );

	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

    // 1. Run the setup (this remains the same)
    $setup_result = CaptainCore\Providers\Mailgun::setup( $full_domain );
    
    // 2. Fetch the newly created zone using the correct method
    $zone_details = CaptainCore\Remote\Mailgun::get( "v4/domains/$full_domain" );

    // 3. Add details to the domain
    $domain = ( new \CaptainCore\Domains )->get( $domain_id );
    if ( ! $domain ) {
        return new WP_Error( 'domain_not_found', 'Domain not found in CaptainCore.', [ 'status' => 404 ] );
    }

    // Decode existing details or create new object
    $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

    // Add new data to the details object
    $details->mailgun_id   = $zone_details->domain->id;
    $details->mailgun_zone = $zone_details->domain->name;

   \CaptainCore\Domains::update(
        [ 'details' => json_encode( $details ) ],
        [ 'domain_id' => $domain_id ]
    );

    // Re-fetch the updated domain
    $updated_domain = ( new \CaptainCore\Domain( $domain_id ) )->fetch();

    // Return the full domain object along with a success message
    return new WP_REST_Response( 
        [ 
            'success' => true, 
            'message' => 'Mailgun zone created and DNS records are being added.',
            'domain'  => $updated_domain
        ], 
        200 
    );
}

/**
 * Handles fetching Mailgun domain details by CaptainCore domain ID.
 */
function captaincore_get_domain_mailgun_details( WP_REST_Request $request ) {
    $domain_id = $request->get_param( 'id' );

    // Verify user permissions for this domain
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    // Get the domain record from the DB
    $domain = ( new \CaptainCore\Domains )->get( $domain_id );
    if ( ! $domain ) {
        return new WP_Error( 'domain_not_found', 'Domain not found in CaptainCore.', [ 'status' => 404 ] );
    }

    // Decode existing details or create new object
    $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

    // Get the Mailgun domain name from details
    $domain_name = $details->mailgun_zone ?? null;

    if ( empty( $domain_name ) ) {
        return new WP_Error( 'mailgun_not_configured', 'Mailgun is not configured for this domain.', [ 'status' => 404 ] );
    }

    // Call the Mailgun API using the existing wrapper
    $response = \CaptainCore\Remote\Mailgun::get( "v4/domains/{$domain_name}", [ 'h:with_dns' => 'true' ] );

    if ( is_wp_error( $response ) || ( isset( $response->message ) && $response->message == "Domain not found" ) ) {
        return new WP_Error( 'mailgun_api_error', 'Domain not found in Mailgun.', [ 'status' => 404, 'details' => $response ] );
    }

    if ( isset( $response->domain ) && $domain->name && $domain_name ) {
        
        // $domain->name is the root domain (e.g., "austinginder.com")
        $root_domain = $domain->name; 
        
        // $domain_name is the mailgun zone (e.g., "mg.austinginder.com")
        $mailgun_zone_name = $domain_name; 

        // Helper function to clean the record name
        $cleanup_record_name = function( $record ) use ( $root_domain ) {
            if ( ! empty( $record->name ) ) { 
                $record->original_name = $record->name;
                $name = rtrim( $record->name, '.' ); // Remove trailing dot

                // Remove the root domain suffix (e.g., ".austinginder.com")
                $suffix = '.' . $root_domain;
                if ( substr( $name, -strlen( $suffix ) ) === $suffix ) {
                    $name = substr( $name, 0, -strlen( $suffix ) );
                }
                
                // Handle the case where the name *was* the root domain
                if ( $name === $root_domain ) {
                     $name = '@'; // Use '@' for root
                }

                $record->name = $name;
            }
            return $record;
        };

        // Apply the cleanup to sending records (which HAVE a 'name' property)
        if ( ! empty( $response->sending_dns_records ) && is_array( $response->sending_dns_records ) ) {
            $response->sending_dns_records = array_map( $cleanup_record_name, $response->sending_dns_records );
        }

        // Manually add the zone name to receiving records (which LACK a 'name' property)
        // BEFORE cleaning them.
        if ( ! empty( $response->receiving_dns_records ) && is_array( $response->receiving_dns_records ) ) {
            foreach( $response->receiving_dns_records as $record ) {
                if ( ! isset( $record->name ) ) {
                    $record->name = $mailgun_zone_name; // Add the missing 'name'
                }
            }
            // Now that they have names, apply the same cleanup
            $response->receiving_dns_records = array_map( $cleanup_record_name, $response->receiving_dns_records );
        }
    }

    // Return the modified domain object
    if ( isset( $response ) ) {
         return new WP_REST_Response( $response, 200 );
    }

    return new WP_Error( 'mailgun_api_error', 'Unexpected response from Mailgun.', [ 'status' => 500, 'details' => $response ] );
}

/**
 * Handles Mailgun deletion request.
 */
function captaincore_mailgun_delete( WP_REST_Request $request ) {
    $domain_id = $request->get_param( 'id' );

    // Verify user permissions for this domain
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
    }

    $domain = ( new \CaptainCore\Domains )->get( $domain_id );
    if ( ! $domain ) {
        return new WP_Error( 'domain_not_found', 'Domain not found in CaptainCore.', [ 'status' => 404 ] );
    }

    $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

    // Get the Mailgun domain name from details
    $domain_name = $details->mailgun_zone ?? null;

    if ( empty( $domain_name ) ) {
        // Nothing to delete, just return success
        return new WP_REST_Response( [ 'message' => 'No Mailgun zone configured.' ], 200 );
    }

    // Call the Mailgun API to delete the domain
    $response = \CaptainCore\Remote\Mailgun::delete( "v3/domains/{$domain_name}" );

    // Check for errors.
    if ( is_wp_error( $response ) ) {
        // Handle transport-level errors (curl, etc.)
        return new \WP_Error( 'mailgun_api_error', 'Failed to delete domain from Mailgun.', [ 'status' => 500, 'details' => $response ] );
    }

    // Check for API-level errors
    if ( isset( $response->message ) ) {
        // These are acceptable messages from Mailgun that mean "success" or "already done"
        $success_messages = [
            "Domain not found",
            "Domain will be deleted in the background",
            "Domain has been deleted", // The original synchronous success message
        ];

        if ( ! in_array( $response->message, $success_messages ) ) {
            // It's a real error message we don't recognize
            return new \WP_Error( 'mailgun_api_error', 'Failed to delete domain from Mailgun.', [ 'status' => 500, 'details' => $response ] );
        }
    }

    // Deletion was successful, asynchronous, or domain didn't exist, so clear our local data
    unset( $details->mailgun_id );
    unset( $details->mailgun_zone );
    unset( $details->mailgun_smtp_password );

   \CaptainCore\Domains::update(
        [ 'details' => json_encode( $details ) ],
        [ 'domain_id' => $domain_id ]
    );

    // Re-fetch the updated domain to send back (with full data)
    $updated_domain = ( new \CaptainCore\Domain( $domain_id ) )->fetch();

    return new WP_REST_Response( 
        [ 
            'success' => true, 
            'message' => 'Mailgun zone deleted successfully.',
            'domain'  => $updated_domain
        ], 
        200 
    );
}

/**
 * Handles Mailgun deploy request.
 */
function captaincore_mailgun_deploy( WP_REST_Request $request ) {
    $site_id   = $request->get_param( 'site_id' );
    $domain_id = $request->get_param( 'id' );
    $from_name = $request->get_param( 'from_name' );
	$environment_name = $request->get_param( 'environment' );
	if ( empty( $from_name ) ) {
        return new WP_Error( 'from_name_required', 'The "Send From Name" cannot be empty.', [ 'status' => 400 ] );
    }

	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

    $site = \CaptainCore\Sites::get( $site_id );
    if ( ! $site->site ) {
        return new WP_Error( 'site_not_found', 'Site not found', [ 'status' => 404 ] );
    }

	$site_slug = $site->site;
            
	// Check if environment is staging and append suffix
	if ( $environment_name && strtolower( $environment_name ) != "production" ) {
		$site_slug = "{$site_slug}-" . strtolower( $environment_name );
	}

	$domain = ( new CaptainCore\Domains )->get( $domain_id );
    $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

	if ( empty( $details->mailgun_smtp_password ) ) {
		// 1. Generate random password via Mailgun API
		$password = wp_generate_password(32, false);
		$response = \CaptainCore\Remote\Mailgun::put( "v4/domains/$details->mailgun_zone", [ "smtp_password" => $password ] );

		// 2. Store password to the domain details (optional but recommended)
		$details->mailgun_smtp_password = $password;
		( new CaptainCore\Domains )->update( [ "details" => json_encode( $details ) ], [ "domain_id" => $domain_id ] );
	}

    // 3. Fetch GravitySMTP credentials
    $credentials  = ( new \CaptainCore\Provider( "gravitysmtp" ) )->credentials();
    $license      = '';
    $download_url = '';

    foreach ( $credentials as $credential ) {
        if ( $credential->name == "license" ) {
            $license = $credential->value;
        }
        if ( $credential->name == "download_url" ) {
            $download_url = $credential->value;
        }
    }

    // 4. Run the deploy command (this remains the same)
    $command = sprintf(
        "ssh %s --script=deploy-mailgun -- --key=%s --name=%s --domain=%s --password=%s --gravitysmtp_zip=%s",
        $site_slug,
        json_encode( $license ),
        json_encode( $from_name ),
        json_encode( $details->mailgun_zone ),
        json_encode( $details->mailgun_smtp_password ),
        json_encode( $download_url )
    );

    $result = CaptainCore\Run::CLI( $command );

    return new WP_REST_Response( [ 'success' => true, 'output' => $result ], 200 );
}

function captaincore_domain_mailgun_events_func( $request ) {
    $domain_id = $request['id'];
    $params    = $request->get_query_params();
    
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
    }

    $domain  = ( new CaptainCore\Domains )->get( $domain_id );
    $details = json_decode( $domain->details );
    
    if ( empty( $details->mailgun_zone ) ) {
        return new WP_Error( 'mailgun_not_configured', 'Mailgun not configured for this domain.', [ 'status' => 404 ] );
    }
    
    $zone = $details->mailgun_zone;

    // If paging URL is provided, use it directly (decoding it first if necessary)
    if ( ! empty( $params['page_url'] ) ) {
        $response = CaptainCore\Remote\Mailgun::get( "", [], $params['page_url'] ); // You might need to adjust Remote\Mailgun::get to handle full URLs or use a specific method
        // Note: CaptainCore\Remote\Mailgun::page() exists but seems designed for the AJAX handler. 
        // A simpler approach for the existing Mailgun::get class:
        // If passing a full URL to Mailgun::get isn't supported, use Mailgun::page logic here.
        $response = CaptainCore\Remote\Mailgun::page( $zone, $params['page_url'] );
    } else {
        $response = CaptainCore\Remote\Mailgun::get( "v3/$zone/events", $params );
    }
    
    return $response;
}

/**
 * Handles Mailgun verification request.
 */
function captaincore_mailgun_verify( WP_REST_Request $request ) {
    $domain_id = $request->get_param( 'id' );
    $verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$domain = CaptainCore\Domains::get( $domain_id );
	$details = isset( $domain->details ) ? json_decode( $domain->details ) : (object) [];

	if ( empty( $details->mailgun_zone ) ) {
		return new WP_Error( 'mailgun_zone_missing', 'Mailgun zone not found.', [ 'status' => 404 ] );
	}

    $result = CaptainCore\Remote\Mailgun::put( "v4/domains/{$details->mailgun_zone}/verify" );
    
    if ( is_wp_error( $result ) || ! isset( $result->domain ) ) {
        // Handle potential error from Mailgun
        return new WP_Error( 'mailgun_api_error', 'Failed to verify domain.', [ 'status' => 500, 'details' => $result ] );
    }
    
    return new WP_REST_Response( $result, 200 );
}

/**
 * List Mailgun Suppressions (Bounces, Unsubscribes, Complaints, Allowlist)
 */
function captaincore_domain_mailgun_suppressions_list( WP_REST_Request $request ) {
    $domain_id = $request['id'];
    $type      = $request['type']; // bounces, unsubscribes, complaints, whitelists

    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
    }

    $domain = ( new CaptainCore\Domains )->get( $domain_id );
    $details = json_decode( $domain->details );

    if ( empty( $details->mailgun_zone ) ) {
        return new WP_Error( 'mailgun_not_configured', 'Mailgun not configured for this domain.', [ 'status' => 404 ] );
    }

    $zone = $details->mailgun_zone;
    $endpoint = "";

    // Map internal types to Mailgun API endpoints
    switch ( $type ) {
        case 'bounces':
            $endpoint = "v3/$zone/bounces";
            break;
        case 'unsubscribes':
            $endpoint = "v3/$zone/unsubscribes";
            break;
        case 'complaints':
            $endpoint = "v3/$zone/complaints";
            break;
        case 'whitelists': // "Allowlist" in UI, "whitelists" in API v3
            $endpoint = "v3/$zone/whitelists";
            break;
        default:
            return new WP_Error( 'invalid_type', 'Invalid suppression type.', [ 'status' => 400 ] );
    }

    // Fetch from Mailgun using the wrapper
    $response = CaptainCore\Remote\Mailgun::get( $endpoint, [ 'limit' => 1000 ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return new WP_REST_Response( $response, 200 );
}

/**
 * Delete a specific Mailgun Suppression
 */
function captaincore_domain_mailgun_suppressions_delete( WP_REST_Request $request ) {
    $domain_id = $request['id'];
    $type      = $request['type'];
    $address   = $request->get_param('address');

    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
    }

    $domain = ( new CaptainCore\Domains )->get( $domain_id );
    $details = json_decode( $domain->details );

    if ( empty( $details->mailgun_zone ) ) {
        return new WP_Error( 'mailgun_not_configured', 'Mailgun not configured for this domain.', [ 'status' => 404 ] );
    }

    $zone = $details->mailgun_zone;
    $endpoint = "";

    // Map internal types to Mailgun API endpoints
    switch ( $type ) {
        case 'bounces':
            $endpoint = "v3/$zone/bounces/" . urlencode( $address );
            break;
        case 'unsubscribes':
            $endpoint = "v3/$zone/unsubscribes/" . urlencode( $address );
            break;
        case 'complaints':
            $endpoint = "v3/$zone/complaints/" . urlencode( $address );
            break;
        case 'whitelists':
            $endpoint = "v3/$zone/whitelists/" . urlencode( $address );
            break;
        default:
            return new WP_Error( 'invalid_type', 'Invalid suppression type.', [ 'status' => 400 ] );
    }

    // Execute Delete
    $response = CaptainCore\Remote\Mailgun::delete( $endpoint );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    return new WP_REST_Response( $response, 200 );
}

/**
 * REST API callback to activate a DNS zone for a domain.
 * This re-uses the fetch_remote_id logic which creates a zone if one doesn't exist.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_domain_activate_dns_zone_func( WP_REST_Request $request ) {
    $domain_id = $request['id'];

    // Verify user permissions for this domain
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    // Check if user is admin - admins can link to existing DNS zones
    $current_user = wp_get_current_user();
    $is_admin = in_array( 'administrator', $current_user->roles );

    $domain = new \CaptainCore\Domain( $domain_id );
    $remote_id = $domain->fetch_remote_id( $is_admin );

    if ( is_array( $remote_id ) && isset( $remote_id["errors"] ) ) {
        return new WP_Error( 'activation_failed', 'Failed to activate DNS zone.', [ 'status' => 500, 'details' => $remote_id["errors"] ] );
    }

    if ( empty( $remote_id ) ) {
        return new WP_Error( 'activation_failed', 'Could not retrieve or create remote ID.', [ 'status' => 500 ] );
    }

    // Refetch the domain to confirm the remote_id is set
    $updated_domain = ( new \CaptainCore\Domains )->get( $domain_id );

    return new WP_REST_Response( [ 'message' => 'DNS zone activated successfully.', 'remote_id' => $updated_domain->remote_id ], 200 );
}

/**
 * REST API callback to delete a DNS zone from Constellix and update local record.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object.
 */
function captaincore_domain_delete_dns_zone_func( WP_REST_Request $request ) {
    $domain_id = $request['id'];

    // Verify user permissions for this domain
    if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
        return new WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
    }

    $domain = ( new \CaptainCore\Domains )->get( $domain_id );
    if ( ! $domain || empty( $domain->remote_id ) ) {
        return new WP_Error( 'zone_not_found', 'No active DNS zone found for this domain.', [ 'status' => 404 ] );
    }

    // Call Constellix to delete the domain zone
    $response = \CaptainCore\Remote\Constellix::delete( "domains/{$domain->remote_id}" );

    // Check for errors from Constellix
    if ( is_wp_error( $response ) || ( isset( $response->errors ) && ! empty( $response->errors ) ) ) {
        // Even if it's "Domain not found", we'll proceed to clear our local record.
        // But if it's another error, we might want to be cautious.
        if ( ! ( isset( $response->errors ) && strpos( $response->errors[0], 'Domain not found' ) !== false ) ) {
            // Log or handle other potential deletion errors if needed
        }
    }

    // Regardless of remote error (especially if it's "not found"), clear the local remote_id
    \CaptainCore\Domains::update(
        [ 'remote_id' => null ],
        [ 'domain_id' => $domain_id ]
    );

    return new WP_REST_Response( [ 'message' => 'DNS zone deleted successfully.' ], 200 );
}

function captaincore_site_captures_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	return $site->captures( $environment );
}


function captaincore_update_logs_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	if ( ! empty( $environment ) ) {
		$results = ( new CaptainCore\Site( $site_id ))->update_logs( $environment );
	return $results;
}

	$results = ( new CaptainCore\Site( $site_id ))->update_logs();
	return $results;
}

function captaincore_quicksaves_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	if ( ! empty( $environment ) ) {
	$results = ( new CaptainCore\Site( $site_id ))->quicksaves( $environment );
		return $results;
	}

	$results = ( new CaptainCore\Site( $site_id ))->quicksaves();
	return $results;
}

/**
 * Checks if a user is logged in for REST API endpoints.
 *
 * @return bool True if the user is logged in, false otherwise.
 */
function captaincore_permission_check() {
    return is_user_logged_in();
}

/**
 * Permission check for admin-only API endpoints.
 *
 * @return bool True if the user is logged in and is an admin, false otherwise.
 */
function captaincore_admin_permission_check() {
    if ( ! is_user_logged_in() ) {
        return false;
    }
    return ( new CaptainCore\User )->is_admin();
}

add_action( 'rest_api_init', 'captaincore_register_rest_endpoints' );
function captaincore_register_rest_endpoints() {

	// Custom endpoint for CaptainCore API
	register_rest_route(
		'captaincore/v1', '/api', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_api_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/process-logs/(?P<id>[\d]+)', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_update_log_entry_func',
			'permission_callback' => function (WP_REST_Request $request) {
				$log_update = (object) $request->get_json_params();
				if ( empty( $log_update->websites ) ) {
					return new WP_Error( 'missing_sites', 'Associated sites are required.', [ 'status' => 400 ] );
				}
				$site_ids = array_column( $log_update->websites, 'site_id' );
				return captaincore_verify_permissions( $site_ids );
			},
		]
	);

	register_rest_route(
		'captaincore/v1', '/missive', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_missive_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/login', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_login_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/archive', [
			'methods'             => 'GET',
			'callback'            => function( $request ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return new WP_Error( 'rest_forbidden', 'Forbidden', [ 'status' => 403 ] );
				}
				// Returns JSON directly from Rclone
				$response = \CaptainCore\Run::CLI( "archive list" );
				return json_decode( $response );
			},
			'permission_callback' => 'captaincore_permission_check',
		]
	);

	register_rest_route(
		'captaincore/v1', '/archive/share', [
			'methods'             => 'POST',
			'callback'            => function( $request ) {
				if ( ! current_user_can( 'manage_options' ) ) {
					return new WP_Error( 'rest_forbidden', 'Forbidden', [ 'status' => 403 ] );
				}
				
				$file = $request->get_param( 'file' );
				
				// Returns the raw URL string from b2 command
				$response = \CaptainCore\Run::CLI( "archive share $file" );
				return [ 'link' => trim($response) ];
			},
			'permission_callback' => 'captaincore_permission_check',
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_quicksaves_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/update-logs', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_update_logs_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/update-logs/(?P<hash_before>[a-zA-Z0-9-]+)_(?P<hash_after>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_update_logs_get_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/search', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_quicksaves_search_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_quicksaves_get_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)/changed', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_quicksaves_changed_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)/filediff', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_quicksaves_filediff_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)/rollback', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_quicksaves_rollback_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	
	register_rest_route(
		'captaincore/v1', '/run/code', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_run_code_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/scripts/schedule', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_schedule_script_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/scripts/(?P<id>[\d]+)', [
			'methods'             => 'DELETE',
			'callback'            => 'captaincore_delete_script_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/scripts/(?P<id>[\d]+)', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_update_script_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/analytics', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_analytics_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/backups', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_backups_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/backups/(?P<backup_id>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_backups_get_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
        'captaincore/v1', '/sites/(?P<id>[\d]+)/invite-preview', [
            'methods'             => 'GET',
            'callback'            => 'captaincore_site_invite_preview_func',
            'permission_callback' => 'captaincore_permission_check',
        ]
    );

    register_rest_route(
        'captaincore/v1', '/sites/(?P<id>[\d]+)/invite', [
            'methods'             => 'POST',
            'callback'            => 'captaincore_site_invite_func',
            'permission_callback' => 'captaincore_permission_check',
        ]
    );

	register_rest_route( 'captaincore/v1', '/sites/bulk-tools', [
		'methods'             => 'POST',
		'callback'            => 'captaincore_bulk_tools_func',
		'permission_callback' => 'captaincore_permission_check',
	]);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/logs', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_logs_list_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/logs/fetch', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_logs_fetch_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/environments', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_environments_get_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/grant-access', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_grant_access_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun', [
		'methods'  => 'GET',
		'callback' => 'captaincore_get_domain_mailgun_details',
		'permission_callback' => 'captaincore_permission_check', 
	] );

	register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun', [
        'methods'  => 'DELETE',
        'callback' => 'captaincore_mailgun_delete',
        'permission_callback' => 'captaincore_permission_check',
    ] );

	register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun/setup', [
        'methods'  => 'POST',
        'callback' => 'captaincore_mailgun_setup',
        'permission_callback' => 'captaincore_permission_check',
    ] );

    register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun/verify', [
        'methods'  => 'POST',
        'callback' => 'captaincore_mailgun_verify',
        'permission_callback' => 'captaincore_permission_check',
    ] );

    register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun/deploy', [
        'methods'  => 'POST',
        'callback' => 'captaincore_mailgun_deploy',
        'permission_callback' => 'captaincore_permission_check',
    ] );

	register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun/events', [
        'methods'  => 'GET',
        'callback' => 'captaincore_domain_mailgun_events_func',
        'permission_callback' => 'captaincore_permission_check',
    ] );

	register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun/suppressions/(?P<type>[a-z]+)', [
        'methods'  => 'GET',
        'callback' => 'captaincore_domain_mailgun_suppressions_list',
        'permission_callback' => 'captaincore_permission_check',
    ] );

    register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/mailgun/suppressions/(?P<type>[a-z]+)', [
        'methods'  => 'DELETE',
        'callback' => 'captaincore_domain_mailgun_suppressions_delete',
        'permission_callback' => 'captaincore_permission_check',
        'args'     => [
            'address' => [ 'required' => true ]
        ]
    ] );

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/sync/data', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_sync_data_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures/new', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_captures_new_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/monitor', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_environment_monitor_update_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_captures_update_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/backup', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_backup_update_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_update_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)', [
			'methods'             => 'DELETE',
			'callback'            => 'captaincore_site_delete_func',
			'permission_callback' => 'captaincore_permission_check',
		]
	);
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_captures_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for CaptainCore site/<id>/snapshots
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_snapshots_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots/(?P<snapshot_id>[\d]+)-(?P<token>[a-zA-Z0-9-]+)/(?P<snapshot_name>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_snapshot_download_func',
			'permission_callback' => '__return_true', // Public endpoint
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites', [
			'methods'             => 'POST',
			'callback'            => function (WP_REST_Request $request) {
				return ( new CaptainCore\Site )->create( $request["site"] );
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<site>[a-zA-Z0-9-]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/sites/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_sites_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/phpmyadmin', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_phpmyadmin_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/magiclogin', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_magiclogin_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<site_id>[\d]+)/environments/(?P<environment_id>[\d]+)/push-targets', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_get_push_targets_func',
			'permission_callback' => 'captaincore_permission_check',
			'args'                => [
				'site_id' => [
					'validate_callback' => function($param, $request, $key) {
						return is_numeric($param);
					}
				],
				'environment_id' => [
					'validate_callback' => function($param, $request, $key) {
						return is_numeric($param);
					}
				],
			],
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/environments/push', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_push_environment_func',
			'permission_callback' => 'captaincore_permission_check',
			 'args'                => [
				'source_environment_id' => [ 'required' => true, 'type' => 'integer' ],
				'target_environment_id' => [ 'required' => true, 'type' => 'integer' ],
			],
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/magiclogin/(?P<user_id>[\d]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_magiclogin_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Generic Domain Management (Replaces old Kinsta-specific routes)
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/domains', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_site_domains_get_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/domains', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_site_domain_add_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/domains', [
			'methods'             => 'DELETE',
			'callback'            => 'captaincore_site_domain_delete_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/domains/primary', [
			'methods'             => 'PUT',
			'callback'            => 'captaincore_site_domain_primary_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_provider_new_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<id>[a-zA-Z0-9-]+)', [
			'methods'             => 'PUT',
			'callback'            => 'captaincore_provider_update_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<id>[a-zA-Z0-9-]+)', [
			'methods'             => 'DELETE',
			'callback'            => 'captaincore_provider_delete_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/verify', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_verify_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/themes', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_themes_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/plugins', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_plugins_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/theme/(?P<id>[a-zA-Z0-9-]+)/download', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_theme_download_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/plugin/(?P<id>[a-zA-Z0-9-]+)/download', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_plugin_download_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/connect', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_provider_connect_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/new-site', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_provider_new_site_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/deploy-to-staging', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_provider_deploy_to_staging_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/deploy-to-production', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_provider_deploy_to_production_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions/check', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_actions_check_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions/(?P<id>[\d]+)/run', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_actions_run_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_provider_actions_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/environments', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_environments_func',
			'permission_callback' => 'captaincore_permission_check',
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/', [
			'methods'             => 'GET',
			'callback'            => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->fetch();
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
        'captaincore/v1', '/me/pins', [
            'methods'             => 'POST',
            'callback'            => 'captaincore_me_update_pins_func',
            'permission_callback' => 'captaincore_permission_check',
        ]
    );

	register_rest_route(
		'captaincore/v1', '/me/tfa_activate', [
			'methods'             => 'GET',
			'callback'            => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_activate();
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_validate', [
			'methods'             => 'POST',
			'callback'            => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_activate_verify( $request['token'] );
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_deactivate', [
			'methods'             => 'GET',
			'callback'            => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_deactivate();
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/email-subscriber', [
			'methods'             => 'POST',
			'callback'            => function (WP_REST_Request $request) {
				$user    = wp_get_current_user();
				$enabled = $request->get_param( 'enabled' );
				
				if ( $enabled ) {
					$user->add_role( 'email_subscriber' );
					return [ 'success' => true, 'message' => 'You will now receive blog post notifications.' ];
				} else {
					$user->remove_role( 'email_subscriber' );
					return [ 'success' => true, 'message' => 'You will no longer receive blog post notifications.' ];
				}
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/sites', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_filters_sites_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,|_%]+)/versions/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_filter_versions_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,|_%]+)/statuses/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_filter_statuses_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,+_%)]+)/sites/versions=(?:(?P<versions>[a-zA-Z0-9-,+\.|]+))?/statuses=(?:(?P<statuses>[a-zA-Z0-9-,+\.|]+))?', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_filter_sites_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_filters_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/dns/(?P<id>[\d]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_dns_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Endpoint to create a new DNS record for a domain
    register_rest_route( 'captaincore/v1', '/dns/(?P<id>[\d]+)/records', [
        'methods'             => 'POST',
        'callback'            => 'captaincore_create_dns_record',
        'permission_callback' => function( $request ) {
			$domain_id = $request['id'];
			$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
			if ( ! $verify ) {
				return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
			}
			return true;
        },
        'args'                => [
            'type'   => [ 'required' => true, 'type' => 'string' ],
            'name'   => [ 'required' => true, 'type' => 'string' ],
            'value'  => [ 'required' => true ],
            'ttl'    => [ 'required' => true, 'type' => 'integer', 'default' => 3600 ],
        ],
    ] );

    // Endpoint to update an existing DNS record
    register_rest_route( 'captaincore/v1', '/dns/(?P<id>[\d]+)/records/(?P<record_id>[\d]+)', [
        'methods'             => 'PUT',
        'callback'            => 'captaincore_update_dns_record',
        'permission_callback' => function( $request ) {
            $domain_id = $request['id'];
			$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
			if ( ! $verify ) {
				return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
			}
			return true;
        },
        'args'                => [
            'type'   => [ 'required' => true, 'type' => 'string' ],
            'name'   => [ 'required' => true, 'type' => 'string' ],
            'value'  => [ 'required' => true ],
            'ttl'    => [ 'required' => true, 'type' => 'integer' ],
        ],
    ] );

    // Endpoint to delete a DNS record
    register_rest_route( 'captaincore/v1', '/dns/(?P<id>[\d]+)/records/(?P<record_id>[\d]+)', [
        'methods'             => 'DELETE',
        'callback'            => 'captaincore_delete_dns_record',
        'permission_callback' => function( $request ) {
            $domain_id = $request['id'];
			$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
			if ( ! $verify ) {
				return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
			}
			return true;
        },
    ] );

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domain_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/contacts', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_domain_update_contacts_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/nameservers', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_domain_update_nameservers_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/lock_(?P<status>[a-zA-Z0-9-,|_%]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domain_lock_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/privacy_(?P<status>[a-zA-Z0-9-,|_%]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domain_privacy_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/auth_code', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domain_auth_code_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/update-site-link', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_domain_update_site_link_func',
			'permission_callback' => 'captaincore_permission_check',
			'args'                => [
				'id' => [
					'validate_callback' => function($param) { return is_numeric($param); }
				],
				'site_id' => [
					'required' => true,
					'validate_callback' => function($param) { return is_numeric($param); }
				],
			],
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/activate-forward-email', [
			'methods'             => 'POST',
			'callback'            => function( WP_REST_Request $request ) {
				$domain_id = $request['id'];
				if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
					return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
				}
				// Get 'overwrite_mx' param from the request body
				$params = $request->get_json_params();
				$overwrite_mx = $params['overwrite_mx'] ?? false;
				
				return ( new CaptainCore\Domain( $domain_id ) )->activate_email_forwarding( $overwrite_mx );
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/email-forwards', [
			'methods'             => 'GET',
			'callback'            => function( WP_REST_Request $request ) {
				$domain_id = $request['id'];
				if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
					return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
				}
				return ( new CaptainCore\Domain( $domain_id ) )->get_email_forwards();
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/email-forwards', [
			'methods'             => 'POST',
			'callback'            => function( WP_REST_Request $request ) {
				$domain_id = $request['id'];
				if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
					return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
				}
				$alias_input = $request->get_json_params();
				return ( new CaptainCore\Domain( $domain_id ) )->add_email_forward( $alias_input );
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/email-forwards/(?P<alias_id>[a-zA-Z0-9-]+)', [
			'methods'             => 'PUT',
			'callback'            => function( WP_REST_Request $request ) {
				$domain_id = $request['id'];
				$alias_id  = $request['alias_id'];
				if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
					return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
				}
				$alias_input = $request->get_json_params();
				return ( new CaptainCore\Domain( $domain_id ) )->update_email_forward( $alias_id, $alias_input );
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/email-forwards/(?P<alias_id>[a-zA-Z0-9-]+)', [
			'methods'             => 'DELETE',
			'callback'            => function( WP_REST_Request $request ) {
				$domain_id = $request['id'];
				$alias_id  = $request['alias_id'];
				if ( ! ( new CaptainCore\Domains )->verify( $domain_id ) ) {
					return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
				}
				return ( new CaptainCore\Domain( $domain_id ) )->delete_email_forward( $alias_id );
			},
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	
	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/email-forwarding/status', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domain_email_forwarding_status_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/email-forwarding/logs', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domain_email_forwarding_logs_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/email-forwarding', [
			'methods'             => 'DELETE',
			'callback'            => 'captaincore_domain_email_forwarding_delete_func',
			'permission_callback' => 'captaincore_admin_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/recipes/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_recipes_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/recipes/(?P<id>[a-zA-Z0-9-]+)', [
			'methods'             => 'DELETE',
			'callback'            => 'captaincore_recipes_delete_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/running/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_running_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for recipes
	register_rest_route(
		'captaincore/v1', '/processes/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_processes_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for domains
	register_rest_route(
		'captaincore/v1', '/domains/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domains_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/domains/(?P<id>[\d]+)/zone', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_domain_zone_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/domains/import', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_domain_zone_import_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

    register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/activate-dns-zone', [
        'methods'             => 'POST',
        'callback'            => 'captaincore_domain_activate_dns_zone_func',
        'permission_callback' => 'captaincore_permission_check',
    ] );

    register_rest_route( 'captaincore/v1', '/domain/(?P<id>[\d]+)/dns-zone', [
        'methods'             => 'DELETE',
        'callback'            => 'captaincore_domain_delete_dns_zone_func',
        'permission_callback' => 'captaincore_permission_check',
    ] );

	register_rest_route(
		'captaincore/v1', '/users/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_users_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/users/(?P<id>[\d]+)/accounts', [
			'methods'             => "GET",
			'callback'            => function( WP_REST_Request $request ) {
				$user     = new CaptainCore\User;
				if ( ! $user->is_admin() ) {
					return new WP_REST_Response( [], 403 );
				}

				$user_id = (int) $request->get_param( 'id' );

				if ( empty( $user_id ) ) {
					return new WP_Error( 'no_user_id', 'Invalid user ID.', [ 'status' => 404 ] );
				}

				$user = ( new CaptainCore\User( $user_id, true ) )->fetch();
				$account_ids = $user["account_ids"];

				return new WP_REST_Response( $account_ids, 200 );
			},
			'permission_callback' => 'captaincore_permission_check',
		]
	);

	// Custom endpoint for CaptainCore accounts
	register_rest_route(
		'captaincore/v1', '/accounts/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_accounts_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/accounts/', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_accounts_create_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for CaptainCore configurations
	register_rest_route(
		'captaincore/v1', '/configurations/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_configurations_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);
	register_rest_route(
		'captaincore/v1', '/configurations/', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_configurations_update_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/billing/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_billing_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/subscriptions/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_subscriptions_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/subscriptions/(?P<id>[\d]+)', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_subscription_details_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/upcoming_subscriptions/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_upcoming_subscriptions_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for keys
	register_rest_route(
		'captaincore/v1', '/keys/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_keys_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Custom endpoint for defaults
	register_rest_route(
		'captaincore/v1', '/defaults/', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_defaults_func',
			'permission_callback' => 'captaincore_permission_check',
			'show_in_index'       => false,
		]
	);

	// Report endpoints (admin only)
	register_rest_route(
		'captaincore/v1', '/report/send', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_report_send_func',
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/report/preview', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_report_preview_func',
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/report/default-recipient', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_report_default_recipient_func',
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/scheduled-reports', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_scheduled_reports_list_func',
			'permission_callback' => function() {
				return is_user_logged_in();
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/scheduled-reports', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_scheduled_reports_create_func',
			'permission_callback' => function() {
				return is_user_logged_in();
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/scheduled-reports/(?P<id>\d+)', [
			'methods'             => 'PUT',
			'callback'            => 'captaincore_scheduled_reports_update_func',
			'permission_callback' => function() {
				return is_user_logged_in();
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/scheduled-reports/(?P<id>\d+)', [
			'methods'             => 'DELETE',
			'callback'            => 'captaincore_scheduled_reports_delete_func',
			'permission_callback' => function() {
				return is_user_logged_in();
			},
			'show_in_index'       => false,
		]
	);

	// Web Risk Logs endpoint (admin only)
	register_rest_route(
		'captaincore/v1', '/web-risk-logs', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_web_risk_logs_func',
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
			'show_in_index'       => false,
		]
	);

	// Email subscription management (public endpoint)
	register_rest_route(
		'captaincore/v1', '/email/subscription', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_email_subscription_func',
			'permission_callback' => '__return_true',
			'show_in_index'       => false,
		]
	);

	// ACH Bank Account Payment Methods
	register_rest_route(
		'captaincore/v1', '/billing/ach/setup-intent', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_ach_create_setup_intent',
			'permission_callback' => function() {
				return is_user_logged_in();
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/billing/ach/payment-method', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_ach_add_payment_method',
			'permission_callback' => function() {
				return is_user_logged_in();
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/billing/ach/verify', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_ach_verify_bank_account',
			'permission_callback' => function() {
				return is_user_logged_in();
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/billing/ach/pending', [
			'methods'             => 'GET',
			'callback'            => 'captaincore_ach_get_pending_verifications',
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
			'show_in_index'       => false,
		]
	);

	register_rest_route(
		'captaincore/v1', '/billing/ach/admin-verify', [
			'methods'             => 'POST',
			'callback'            => 'captaincore_ach_admin_verify_bank_account',
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
			'show_in_index'       => false,
		]
	);

};

/**
 * REST endpoint: Create SetupIntent for ACH bank account collection
 */
function captaincore_ach_create_setup_intent( WP_REST_Request $request ) {
	$user = new CaptainCore\User();
	$result = $user->create_ach_setup_intent();
	
	if ( isset( $result->error ) ) {
		return new WP_Error( 'setup_intent_error', $result->error, [ 'status' => 400 ] );
	}
	
	return rest_ensure_response( $result );
}

/**
 * REST endpoint: Add ACH payment method after SetupIntent completion
 */
function captaincore_ach_add_payment_method( WP_REST_Request $request ) {
	
	$params = $request->get_json_params();
	$setup_intent_id = sanitize_text_field( $params['setup_intent_id'] ?? '' );
	
	if ( empty( $setup_intent_id ) ) {
		return new WP_Error( 'missing_param', 'setup_intent_id is required', [ 'status' => 400 ] );
	}
	
	$user = new CaptainCore\User();
	$result = $user->add_ach_payment_method( $setup_intent_id );
	
	if ( isset( $result->error ) ) {
		return new WP_Error( 'add_payment_error', $result->error, [ 'status' => 400 ] );
	}
	
	return rest_ensure_response( $result );
}

/**
 * REST endpoint: Verify bank account using micro-deposit amounts
 */
function captaincore_ach_verify_bank_account( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	$token_id = $params['token_id'] ?? '';
	$amounts = $params['amounts'] ?? [];
	
	// Handle both ACH tokens (string like "ach_xxx") and legacy WC tokens (integer)
	if ( is_numeric( $token_id ) ) {
		$token_id = intval( $token_id );
	} else {
		$token_id = sanitize_text_field( $token_id );
	}
	
	if ( empty( $token_id ) ) {
		return new WP_Error( 'missing_param', 'token_id is required', [ 'status' => 400 ] );
	}
	
	if ( ! is_array( $amounts ) || count( $amounts ) !== 2 ) {
		return new WP_Error( 'invalid_amounts', 'Two deposit amounts are required', [ 'status' => 400 ] );
	}
	
	$amounts = array_map( 'intval', $amounts );
	
	$user = new CaptainCore\User();
	$result = $user->verify_bank_account( $token_id, $amounts );
	
	if ( isset( $result->error ) ) {
		return new WP_Error( 'verify_error', $result->error, [ 'status' => 400 ] );
	}
	
	return rest_ensure_response( $result );
}

/**
 * REST endpoint: Get all pending ACH verifications (admin only)
 */
function captaincore_ach_get_pending_verifications( WP_REST_Request $request ) {
	$pending = CaptainCore\User::get_pending_ach_verifications();
	return rest_ensure_response( $pending );
}

/**
 * REST endpoint: Admin verify bank account on behalf of customer
 */
function captaincore_ach_admin_verify_bank_account( WP_REST_Request $request ) {
	$params = $request->get_json_params();
	$token_id = $params['token_id'] ?? '';
	$user_id = intval( $params['user_id'] ?? 0 );
	$amounts = $params['amounts'] ?? [];
	
	// Handle both ACH tokens (string like "ach_xxx") and legacy WC tokens (integer)
	if ( is_numeric( $token_id ) ) {
		$token_id = intval( $token_id );
	} else {
		$token_id = sanitize_text_field( $token_id );
	}
	
	if ( empty( $token_id ) ) {
		return new WP_Error( 'missing_param', 'token_id is required', [ 'status' => 400 ] );
	}
	
	if ( ! is_array( $amounts ) || count( $amounts ) !== 2 ) {
		return new WP_Error( 'invalid_amounts', 'Two deposit amounts are required', [ 'status' => 400 ] );
	}
	
	$amounts = array_map( 'intval', $amounts );
	
	// For ACH tokens stored in user meta, we need the user_id
	if ( is_string( $token_id ) && strpos( $token_id, 'ach_' ) === 0 ) {
		if ( empty( $user_id ) ) {
			return new WP_Error( 'missing_param', 'user_id is required for ACH tokens', [ 'status' => 400 ] );
		}
		$token_owner = new CaptainCore\User( $user_id, true );
	} else {
		// Get the WC token to find the owner
		$token = WC_Payment_Tokens::get( $token_id );
		if ( ! $token ) {
			return new WP_Error( 'token_not_found', 'Payment token not found', [ 'status' => 404 ] );
		}
		$token_owner = new CaptainCore\User( $token->get_user_id(), true );
	}
	
	$result = $token_owner->verify_bank_account( $token_id, $amounts );
	
	if ( isset( $result->error ) ) {
		return new WP_Error( 'verify_error', $result->error, [ 'status' => 400 ] );
	}
	
	return rest_ensure_response( $result );
}

/**
 * REST endpoint: Send maintenance report email
 */
function captaincore_report_send_func( WP_REST_Request $request ) {
	$params     = $request->get_json_params();
	$site_ids   = $params['site_ids'] ?? [];
	$start_date = $params['start_date'] ?? '';
	$end_date   = $params['end_date'] ?? '';
	$recipient  = $params['recipient'] ?? '';

	if ( empty( $site_ids ) ) {
		return new WP_Error( 'missing_sites', 'At least one site is required.', [ 'status' => 400 ] );
	}

	if ( empty( $recipient ) ) {
		return new WP_Error( 'missing_recipient', 'Recipient email is required.', [ 'status' => 400 ] );
	}

	$result = CaptainCore\Report::send( $site_ids, $start_date, $end_date, $recipient );

	if ( $result ) {
		return [ 'success' => true, 'message' => "Report sent to {$recipient}" ];
	}

	return new WP_Error( 'send_failed', 'Failed to send report.', [ 'status' => 500 ] );
}

/**
 * REST endpoint: Preview maintenance report HTML
 */
function captaincore_report_preview_func( WP_REST_Request $request ) {
	$params     = $request->get_json_params();
	$site_ids   = $params['site_ids'] ?? [];
	$start_date = $params['start_date'] ?? '';
	$end_date   = $params['end_date'] ?? '';

	if ( empty( $site_ids ) ) {
		return new WP_Error( 'missing_sites', 'At least one site is required.', [ 'status' => 400 ] );
	}

	$html = CaptainCore\Report::preview( $site_ids, $start_date, $end_date );

	return [ 'html' => $html ];
}

/**
 * REST endpoint: Get default recipient email from billing account
 */
function captaincore_report_default_recipient_func( WP_REST_Request $request ) {
	$params   = $request->get_json_params();
	$site_ids = $params['site_ids'] ?? [];

	if ( empty( $site_ids ) ) {
		return new WP_Error( 'missing_sites', 'At least one site is required.', [ 'status' => 400 ] );
	}

	$email = CaptainCore\Report::get_default_recipient( $site_ids );

	return [ 'email' => $email ];
}

/**
 * REST endpoint: List all scheduled reports
 */
function captaincore_scheduled_reports_list_func( WP_REST_Request $request ) {
	$reports     = CaptainCore\ScheduledReports::all();
	$user_id     = get_current_user_id();
	$is_admin    = current_user_can( 'manage_options' );
	$result      = [];

	// Decode site_ids for each report and add site names
	foreach ( $reports as $report ) {
		// Non-admins can only see their own reports
		if ( ! $is_admin && (int) $report->user_id !== $user_id ) {
			continue;
		}

		$report->site_ids = json_decode( $report->site_ids, true );
		$report->site_names = [];
		foreach ( $report->site_ids as $site_id ) {
			$site = new CaptainCore\Site( $site_id );
			if ( $site->get() ) {
				$report->site_names[] = $site->get()->name;
			}
		}
		$result[] = $report;
	}

	return $result;
}

/**
 * REST endpoint: Create scheduled report
 */
function captaincore_scheduled_reports_create_func( WP_REST_Request $request ) {
	$params = $request->get_json_params();

	$site_ids  = $params['site_ids'] ?? [];
	$interval  = $params['interval'] ?? 'monthly';
	$recipient = $params['recipient'] ?? '';

	if ( empty( $site_ids ) ) {
		return new WP_Error( 'missing_sites', 'At least one site is required.', [ 'status' => 400 ] );
	}

	if ( empty( $recipient ) ) {
		return new WP_Error( 'missing_recipient', 'Recipient email is required.', [ 'status' => 400 ] );
	}

	// Verify user has access to all requested sites (unless admin)
	if ( ! current_user_can( 'manage_options' ) ) {
		$user_sites       = new CaptainCore\Sites();
		$allowed_site_ids = $user_sites->site_ids();

		foreach ( $site_ids as $site_id ) {
			if ( ! in_array( (int) $site_id, $allowed_site_ids ) ) {
				return new WP_Error( 'unauthorized_site', 'You do not have access to one or more of the selected sites.', [ 'status' => 403 ] );
			}
		}
	}

	$id = CaptainCore\ScheduledReports::create( [
		'site_ids'  => $site_ids,
		'interval'  => $interval,
		'recipient' => $recipient,
	] );

	return [ 'success' => true, 'id' => $id ];
}

/**
 * REST endpoint: Update scheduled report
 */
function captaincore_scheduled_reports_update_func( WP_REST_Request $request ) {
	$id       = $request->get_param( 'id' );
	$params   = $request->get_json_params();
	$user_id  = get_current_user_id();
	$is_admin = current_user_can( 'manage_options' );

	// Get the existing report
	$report = CaptainCore\ScheduledReports::get( $id );
	if ( ! $report ) {
		return new WP_Error( 'not_found', 'Scheduled report not found.', [ 'status' => 404 ] );
	}

	// Non-admins can only update their own reports
	if ( ! $is_admin && (int) $report->user_id !== $user_id ) {
		return new WP_Error( 'unauthorized', 'You do not have permission to update this report.', [ 'status' => 403 ] );
	}

	$update_data = [];
	if ( isset( $params['site_ids'] ) ) {
		// Verify user has access to all requested sites (unless admin)
		if ( ! $is_admin ) {
			$user_sites       = new CaptainCore\Sites();
			$allowed_site_ids = $user_sites->site_ids();

			foreach ( $params['site_ids'] as $site_id ) {
				if ( ! in_array( (int) $site_id, $allowed_site_ids ) ) {
					return new WP_Error( 'unauthorized_site', 'You do not have access to one or more of the selected sites.', [ 'status' => 403 ] );
				}
			}
		}
		$update_data['site_ids'] = $params['site_ids'];
	}
	if ( isset( $params['interval'] ) ) {
		$update_data['interval'] = $params['interval'];
	}
	if ( isset( $params['recipient'] ) ) {
		$update_data['recipient'] = $params['recipient'];
	}

	CaptainCore\ScheduledReports::update_report( $id, $update_data );

	return [ 'success' => true ];
}

/**
 * REST endpoint: Delete scheduled report
 */
function captaincore_scheduled_reports_delete_func( WP_REST_Request $request ) {
	$id       = $request->get_param( 'id' );
	$user_id  = get_current_user_id();
	$is_admin = current_user_can( 'manage_options' );

	// Get the existing report
	$report = CaptainCore\ScheduledReports::get( $id );
	if ( ! $report ) {
		return new WP_Error( 'not_found', 'Scheduled report not found.', [ 'status' => 404 ] );
	}

	// Non-admins can only delete their own reports
	if ( ! $is_admin && (int) $report->user_id !== $user_id ) {
		return new WP_Error( 'unauthorized', 'You do not have permission to delete this report.', [ 'status' => 403 ] );
	}

	CaptainCore\ScheduledReports::delete_report( $id );

	return [ 'success' => true ];
}

/**
 * REST endpoint: List Web Risk check logs
 */
function captaincore_web_risk_logs_func( WP_REST_Request $request ) {
	return ( new CaptainCore\WebRiskLogs() )->list();
}

/**
 * REST endpoint: Email subscription management (subscribe/unsubscribe)
 * Returns HTML response data that will be served by the pre_serve filter
 */
function captaincore_email_subscription_func( WP_REST_Request $request ) {
	$user_id = absint( $request->get_param( 'id' ) );
	$email   = sanitize_email( $request->get_param( 'email' ) );
	$token   = sanitize_text_field( $request->get_param( 'token' ) );
	$action  = sanitize_text_field( $request->get_param( 'action' ) );

	if ( ! $user_id || ! $email || ! $token || ! $action ) {
		return [ 'html' => captaincore_email_subscription_page( 'Invalid email subscription link.', 'Manage subscription' ) ];
	}

	$user = get_user_by( 'ID', $user_id );
	if ( ! $user || $user->user_email !== $email ) {
		return [ 'html' => captaincore_email_subscription_page( 'Invalid email subscription token.', 'Manage subscription' ) ];
	}

	$user_token = wp_hash( $user->user_registered );
	if ( $token !== $user_token ) {
		return [ 'html' => captaincore_email_subscription_page( 'Invalid email subscription token.', 'Manage subscription' ) ];
	}

	$user_email  = $user->user_email;
	$base_url    = rest_url( 'captaincore/v1/email/subscription' );

	if ( $action === 'subscribe' ) {
		$user->add_role( 'email_subscriber' );
		$unsubscribe_url = add_query_arg( [
			'id'     => $user_id,
			'email'  => $user_email,
			'token'  => $user_token,
			'action' => 'unsubscribe'
		], $base_url );
		$html = "Your email <strong>{$email}</strong> has been subscribed.<br>
			<small>Click here to <a href='{$unsubscribe_url}'>unsubscribe</a></small>";
		return [ 'html' => captaincore_email_subscription_page( $html, 'Subscribe to email newsletters' ) ];
	}

	if ( $action === 'unsubscribe' ) {
		$user->remove_role( 'email_subscriber' );
		$subscribe_url = add_query_arg( [
			'id'     => $user_id,
			'email'  => $user_email,
			'token'  => $user_token,
			'action' => 'subscribe'
		], $base_url );
		$html = "Your email <strong>{$email}</strong> has been unsubscribed.<br>
			<small>Click here to <a href='{$subscribe_url}'>resubscribe</a></small>";
		return [ 'html' => captaincore_email_subscription_page( $html, 'Unsubscribe from email newsletters' ) ];
	}

	return [ 'html' => captaincore_email_subscription_page( 'Invalid action.', 'Manage subscription' ) ];
}

/**
 * Serve HTML response for email subscription endpoint instead of JSON
 */
function captaincore_email_subscription_serve_html( $served, $result, $request, $server ) {
	if ( $request->get_route() !== '/captaincore/v1/email/subscription' ) {
		return $served;
	}

	$data = $result->get_data();
	if ( ! isset( $data['html'] ) ) {
		return $served;
	}

	header( 'Content-Type: text/html; charset=UTF-8' );
	echo $data['html'];
	return true;
}
add_filter( 'rest_pre_serve_request', 'captaincore_email_subscription_serve_html', 10, 4 );

/**
 * Generate a simple HTML page for email subscription responses
 */
function captaincore_email_subscription_page( $message, $title ) {
	$site_name = get_bloginfo( 'name' );
	return "<!DOCTYPE html>
<html>
<head>
	<meta charset='UTF-8'>
	<meta name='viewport' content='width=device-width, initial-scale=1.0'>
	<title>{$title} - {$site_name}</title>
	<style>
		body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background: #f5f5f5; margin: 0; padding: 40px 20px; }
		.container { max-width: 500px; margin: 0 auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
		h1 { font-size: 24px; color: #333; margin: 0 0 20px; }
		p { color: #666; line-height: 1.6; }
		a { color: #0073aa; }
		small { color: #999; }
	</style>
</head>
<body>
	<div class='container'>
		<h1>{$title}</h1>
		<p>{$message}</p>
	</div>
</body>
</html>";
}

function captaincore_login_func( WP_REST_Request $request ) {

	$post = json_decode( file_get_contents( 'php://input' ) );

	if ( $post->command == "reset" ) {

		$user_data = get_user_by( 'login', $post->login->user_login );
		if ( ! $user_data ) {
			$user_data = get_user_by( 'email', $post->login->user_login );
		}
		if ( ! $user_data ) {
			return;
		}

		// Generate the key
		$key = get_password_reset_key( $user_data );

		if ( is_wp_error( $key ) ) {
			return $key;
		}

		// Use the Custom CaptainCore Mailer
		CaptainCore\Mailer::send_password_reset( $user_data, $key );

		return true;
	}

	if ( $post->command == "signIn" ) {
		$credentials = [
			"user_login"    => $post->login->user_login,
			"user_password" => $post->login->user_password,
			"remember"      => true,
		];

		$current_user = wp_authenticate( $post->login->user_login, $post->login->user_password ); 

		if ( $current_user->ID === null ) {
			return [ "errors" => "Login failed." ];
		}

		$tfa_enabled = (bool) get_user_meta( $current_user->ID, 'captaincore_2fa_enabled', true );
		if ( $tfa_enabled && empty( $post->login->tfa_code ) ) {
			return [ "info" =>  "Enter one time password." ];
		}
		if ( $tfa_enabled ) {
			$tfa_enabled_check = ( new CaptainCore\User( $current_user->ID, true ) )->tfa_login( $post->login->tfa_code );
			if ( ! $tfa_enabled_check ) {
				return [ "errors" =>  "One time password is invalid." ];
			}
		}
		if ( function_exists( "wpgraphql_cors_signon" ) ) {
			wpgraphql_cors_signon( $credentials, true );
		} else {
			wp_signon( $credentials );
		} 
		return [ "message" =>  "Logged in." ];
	}

	if ( $post->command == "signOut" ) {
		wp_logout();
	}

	if ( $post->command == "createAccount" ) {

		$errors   = [];
		$password = $post->login->password;
		$invites  = new CaptainCore\Invites();
		$results  = $invites->where( [
			"account_id" => $post->invite->account,
			"token"      => $post->invite->token,
		 ] );
		if ( count( $results ) == "1" ) {
			$record = $results[0];

			if (strlen($password) < 8) {
				$errors[] = "Password too short!";
			}
		
			if (!preg_match("#[0-9]+#", $password)) {
				$errors[] = "Password must include at least one number!";
			}
		
			if (!preg_match("#[a-zA-Z]+#", $password)) {
				$errors[] = "Password must include at least one letter!";
			}     
		
			if ( count($errors) > 0 ) {
				return [ "errors" => $errors ];
			}

			// Add account ID to current user
			$userdata = array(
				'user_login' => $record->email,
				'user_email' => $record->email,
				'user_pass'  => $password,
			);
			
			// Generate new user
			$user_id = wp_insert_user( $userdata );

			// Assign permission to account
			( new CaptainCore\User( $user_id, true ) )->assign_accounts( [ $record->account_id ] );

			$account = new CaptainCore\Account( $record->account_id, true );
			$account->calculate_totals();

			$invite = new CaptainCore\Invite( $record->invite_id );
			$invite->mark_accepted();

			// Sign into new account
			$credentials = [
				"user_login"    => $record->email,
				"user_password" => $password,
				"remember"      => true,
			];
	
			$current_user = wp_signon( $credentials );

			return [ "message" => "New account created." ];
		}
		return  [ "error" => "Account already taken or invalid invite." ];
	}

}

function human_filesize( $bytes, $decimals = 2 ) {
	$size   = [ 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ];
	$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );
	return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
}

// Checks current user for valid permissions
function captaincore_verify_permissions( $site_id ) {
	return ( new CaptainCore\Sites )->verify( $site_id );
}

// Checks current user for valid permissions
function captaincore_verify_permissions_account( $account_id ) {
	return ( new CaptainCore\User )->verify_accounts( [ $account_id ] );
}

// Processes install events (new install, remove install, setup configs)
add_action( 'wp_ajax_captaincore_dns', 'captaincore_dns_action_callback' );

function captaincore_dns_action_callback() {
	global $wpdb;

	$domain_id      = intval( $_POST['domain_key'] );
	$record_updates = $_POST['record_updates'];
	$responses      = [];

	foreach ( $record_updates as $record_update ) {

		$record_id     = $record_update['record_id'];
		$record_type   = strtolower($record_update['record_type']);
		$record_name   = $record_update['record_name'];
		$record_value  = $record_update['record_value'];
		$record_ttl    = $record_update['record_ttl'];
		$record_status = $record_update['record_status'];

		if ( $record_status == 'new-record' ) {
			if ( $record_type == 'mx' ) {

				// Formats MX records into array which API can read
				$mx_records = [];
				foreach ( $record_value as $mx_record ) {
					$mx_records[] = [
						'server'   => $mx_record['server'],
						'priority' => $mx_record['priority'],
						'enabled'  => true,
					];
				}

				$post = [
					'name'  => $record_name,
					'type'  => $record_type,
					'ttl'   => $record_ttl,
					'value' => $mx_records,
				];

			} elseif ( $record_type == 'txt' or $record_type == 'a' or $record_type == 'aname' or $record_type == 'cname' or $record_type == 'aaaa' or $record_type == 'spf' ) {

				// Formats A and TXT records into array which API can read
				$records = [];
				foreach ( $record_value as $record ) {
					$records[] = [
						'value'   => stripslashes( $record['value'] ),
						'enabled' => true,
					];
				}

				$post = [
					'type'  => $record_type,
					'name'  => "$record_name",
					'ttl'   => $record_ttl,
					'value' => $records,
				];

				$record_value = $records;

			} elseif ( $record_type == 'http' ) {

				$post = [
					'name'  => $record_name,
					'type'  => $record_type,
					'ttl'   => $record_ttl,
					'value' => [
						'hard'         => true,
						'url'          => $record_value,
						'redirectType' => '301',
					],	
				];

				$record_value = [
					'hard'         => true,
					'url'          => $record_value,
					'redirectType' => '301',
				];

			} elseif ( $record_type == 'srv' ) {

				// Formats SRV records into array which API can read
				$srv_records = [];
				foreach ( $record_value as $srv_record ) {
					$srv_records[] = [
						'enabled'  => true,
						'host'     => $srv_record['host'],
						'priority' => $srv_record['priority'],
						'weight'   => $srv_record['weight'],
						'port'     => $srv_record['port'],
					];
				}

				$post = [
					'type'  => $record_type,
					'name'  => $record_name,
					'ttl'   => $record_ttl,
					'value' => $srv_records,
				];

			} else {
				$post = [
					'type'  => $record_type,
					'name'  => $record_name,
					'ttl'   => $record_ttl,
					'value' => $record_value,
				];

			}
			$response = CaptainCore\Remote\Constellix::post( "domains/$domain_id/records", $post );

			if ( ! empty( $response->errors->general ) ) {
				$response->errors = $response->errors->general;
			}

			if ( ! empty( $response->errors ) && is_object( $response->errors ) ) {
				$errors = "";
				foreach( $response->errors as $key => $value ){
					$value  = implode( " and ", $value );
					$errors = "{$errors}{$key}: {$value} ";
				}
				$response->errors = $errors;
			}

			$response->record_status = "new-record";
			$response->record_id     = $response->data->id;
			$response->record_name   = $record_name;
			$response->record_value  = $record_value;
			$response->type          = $record_type;

			$responses[] = $response;

		}

		if ( $record_status == 'edit-record' ) {
			if ( $record_type == 'mx' ) {

				// Formats MX records into array which API can read
				$mx_records = [];
				foreach ( $record_value as $mx_record ) {
					$mx_records[] = [
						'server'   => $mx_record['server'],
						'priority' => $mx_record['priority'],
						'enabled'  => true,
					];
				}

				$post = [
					'name'  => $record_name,
					'type'  => $record_type,
					'ttl'   => $record_ttl,
					'value' => $mx_records,
				];

			} elseif ( $record_type == 'txt' or $record_type == 'a' or $record_type == 'aname' or $record_type == 'cname' or $record_type == 'aaaa' or $record_type == 'spf' ) {
				// Formats A and TXT records into array which API can read
				$records = [];
				foreach ( $record_value as $record ) {
					$value = is_string( $record['value'] ) ? stripslashes( $record['value'] ) : $record['value'];
					if ( is_array( $record ) && ! empty( $record["value"] ) ) {
						$record['value'] = stripslashes($record['value']);
					}
					if ( is_array( $record ) && ! empty( $record["enabled"] ) ) {
						$record["enabled"] = true;
						$records[] = $record;
						continue;
					}
					// Wrap TXT value in double quotes if not currently
					if ( $record_type == 'txt' and $value[0] != '"' and $value[-1] != '"' ) {
						$value = "\"{$value}\"";
					}
					$records[] = $value;
				}

				$post = [
					'name'  => "$record_name",
					'type'  => $record_type,
					'ttl'   => $record_ttl,
					'value' => $records,
				];

			} elseif ( $record_type == 'http' ) {

				$post = [
					'name'  => $record_name,
					'type'  => $record_type,
					'ttl'   => $record_ttl,
					'value' => [
						'hard'         => true,
						'url'          => $record_value,
						'redirectType' => '301',
					],	
				];

			} elseif ( $record_type == 'cname' ) {

				$post = array(
					'name' => $record_name,
					'host' => $record_value,
					'ttl'  => $record_ttl,
				);

			} elseif ( $record_type == 'srv' ) {

				// Formats SRV records into array which API can read
				$srv_records = [];
				foreach ( $record_value as $srv_record ) {
					$srv_records[] = [
						'host'     => $srv_record['host'],
						'priority' => $srv_record['priority'],
						'weight'   => $srv_record['weight'],
						'port'     => $srv_record['port'],
						'enabled'  => true,
					];
				}

				$post = [
					'type'  => $record_type,
					'name'  => $record_name,
					'ttl'   => $record_ttl,
					'value' => $srv_records,
				];

			} else {
				$post = [
					'type'  => $record_type,
					'name'  => $record_name,
					'ttl'   => $record_ttl,
					'value' => [
						[
							'value'   => stripslashes( $record_value ),
							'enabled' => true,
						],
					],
				];

			}
			$response                = CaptainCore\Remote\Constellix::put( "domains/$domain_id/records/$record_id", $post );
			$response->domain_id     = $domain_id;
			$response->record_id     = $record_id;
			$response->record_type   = $record_type;
			$response->record_status = $record_status;
			$responses[]             = $response;
		}

		if ( $record_status == 'remove-record' ) {
			$response                = CaptainCore\Remote\Constellix::delete( "domains/$domain_id/records/$record_id" );
			$response->domain_id     = $domain_id;
			$response->record_id     = $record_id;
			$response->record_type   = $record_type;
			$response->record_status = $record_status;
			$responses[]             = $response;
		}
	}
	//$responses = rtrim( $responses, ',' ) . ']';

	echo json_encode( $responses ) ;

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_captaincore_local', 'captaincore_local_action_callback' );
function captaincore_local_action_callback() {
	global $wpdb;
	$cmd   = $_POST['command'];
	$value = $_POST['value'];
	
	if ( $cmd == "connect" ) { 
		$connect = (object) $_POST['connect'];
		// Disable https when debug enabled
		add_filter( 'https_ssl_verify', '__return_false' );
		
		$domain = get_option( "home" );
		$domain = str_replace( "http://www.", "", $domain );
		$domain = str_replace( "https://www.", "", $domain );
		$domain = str_replace( "http://", "", $domain );
		$domain = str_replace( "https://", "", $domain );
		$auth   = md5( AUTH_KEY );

		$data = [
			'timeout' => 45,
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'token'        => $connect->token 
			], 
			'body'        => json_encode( [ "command" => "connection add $domain $auth {$connect->token}" ] ), 
			'method'      => 'POST', 
			'data_format' => 'body' 
		];

		// Add command to dispatch server
		$response = wp_remote_post( "https://{$connect->address}/tasks", $data );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			echo $response["body"];
		}
		wp_die(); // this is required to terminate immediately and return a proper response

	}

	if ( $cmd == 'newUser' ) {
		$account  = (object) $value;
		$response = (object) [];
		$errors   = [];

		if ( $account->login == "" ) {
			$errors[] = "Username name can't be empty.";
		}

		if ( $account->login != "" && username_exists( $account->login ) ) {
			$errors[] = "Username is taken.";
		}
		
		if ( ! filter_var( $account->email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = "Email address is not valid.";
		}

		if ( filter_var( $account->email, FILTER_VALIDATE_EMAIL ) && email_exists( $account->email ) ) {
			$errors[] = "Email address is taken.";
		}

		if ( count($errors) == 0 ) {
			$result = wp_insert_user( array(
				'first_name'   => $account->first_name,
				'last_name'    => $account->last_name,
				'user_email'   => $account->email,
				'user_login'   => $account->login,
				'role'         => 'subscriber'
			) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				( new CaptainCore\User( $result, true ) )->assign_accounts( $account->account_ids );
				CaptainCore\Mailer::notify_new_user( $result );
			}
		}

		if ( count($errors) > 0 ) {
			$response->errors = $errors;
		}

		echo json_encode( $response );
	}

	if ( $cmd == 'updateAccount' ) {
		$user_id  = get_current_user_id();
		$account  = (object) $value;
		$response = (object) [];
		$errors   = [];

		if ( $account->display_name == "" ) {
			$errors[] = "Display name can't be empty.";
		}

		if ( ! filter_var($account->email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = "Email address is not valid.";
		}
		
		// If new password sent then valid it.
		if ( $account->new_password != "" ) {

			$password = $account->new_password;

			if (strlen($password) < 8) {
				$errors[] = "Password too short!";
			}
		
			if (!preg_match("#[0-9]+#", $password)) {
				$errors[] = "Password must include at least one number!";
			}
		
			if (!preg_match("#[a-zA-Z]+#", $password)) {
				$errors[] = "Password must include at least one letter!";
			}
			
		}

		if ( count($errors) == 0 ) {
			// Update user submitted info
			$result = wp_update_user( array( 
				'ID'           => $user_id, 
				'display_name' => $account->display_name,
				'user_email'   => $account->email,
			) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		// Passed checks so update the password.
		if ( count($errors) == 0 && $account->new_password != "") {
			$result = wp_update_user( array( 
				'ID'        => $user_id, 
				'user_pass' => $account->new_password,
			) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		if ( count($errors) > 0 ) {
			$response->errors = $errors;
		}

		$response->profile = $account;
		unset ( $response->profile->new_password );
		echo json_encode( $response );
	}

	if ( $cmd == 'downloadPDF' ) {
		$order            = wc_get_order( $value );
		$order_data       = (object) $order->get_data();
		$order_items      = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
		$order_line_items = "";
		foreach ( $order_items as $item_id => $item ) {
			$subtotal          = str_replace( "<bdi>", "", $order->get_formatted_line_subtotal( $item ) );
			$subtotal          = str_replace( "</bdi>", "", $subtotal );
			$details           = $item->get_meta_data()[0]->get_data();
			if ( $details['key'] == "Details" ) {
				$description = $details['value'];
			}
			$order_line_items .= "<tr><td width=\"536\">{$item->get_quantity()}x {$item->get_name()}<br /><small>{$description}</small></td><td>{$subtotal}</td></tr>";
		}

		$refunds = $order->get_refunds();
		foreach ( $refunds as $item ) {
			$description       = $item->get_post_title();
			$subtotal          = str_replace( "<bdi>", "", "-".$item->get_formatted_refund_amount() );
			$subtotal          = str_replace( "</bdi>", "", $subtotal );
			$order_line_items .= "<tr><td width=\"536\">1x Refund<br /><small>{$description}</small></td><td>{$subtotal}</td></tr>";
			$order_data->total = $order_data->total - $item->get_amount();
		}

		$payment_method_title  = $order->get_payment_method_title();
		if ( empty( $payment_method_title ) ) {
			// Fallback to gateway title if order doesn't have a custom title
			$payment_gateways     = WC()->payment_gateways->payment_gateways();
			$payment_method       = $order->get_payment_method();
			$payment_method_title = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_title() : "Check";
		}
		$payment_method_string = sprintf(
			__( 'Payment via %s', 'woocommerce' ),
			esc_html( $payment_method_title )
		);

		if ( $order->get_date_paid() ) {
			$paid_on = sprintf(
				__( 'Paid on %1$s @ %2$s', 'woocommerce' ),
				wc_format_datetime( $order->get_date_paid() ),
				wc_format_datetime( $order->get_date_paid(), get_option( 'time_format' ) )
			);
		}

		$response = (object) [
			"order_id"       => $order_data->id,
			"created_at"     => $order_data->date_created->getTimestamp(),
			"status"         => $order_data->status,
			"line_items"     => $order_line_items,
			"payment_method" => $payment_method_string,
			"paid_on"        => $paid_on,
			"total"          => number_format( (float) $order_data->total, 2, '.', '' ),
		];

		$account_id        = $order->get_meta( 'captaincore_account_id' );
		$account           = ( new CaptainCore\Accounts )->get( $account_id );
		$customer_billing  = ( new CaptainCore\Account( $account_id ) )->get_billing();
		$customer_country  = WC()->countries->countries[ $customer_billing->country ];
		$store_raw_country = get_option( 'woocommerce_default_country' );
		$split_country     = explode( ":", $store_raw_country );
		$store_country     = WC()->countries->countries[ $split_country[0] ];
		$store_state       = $split_country[1];
		$store_city        = get_option( 'woocommerce_store_city' );
		$store_postcode    = get_option( 'woocommerce_store_postcode' );
		$store_address     = get_option( 'blogname' ) . "<br />" . 
							 get_option( 'woocommerce_store_address' ) . "<br/ >" . 
							 get_option( 'woocommerce_store_address_2' ). "<br /> 
							 $store_city, $store_state $store_postcode<br />
							 $store_country";
		$customer_address  = "<strong>{$account->name}</strong><br />";
		if ( ! empty( $customer_billing->first_name ) || ! empty( $customer_billing->last_name ) ) {
			$customer_address .= "{$customer_billing->first_name} {$customer_billing->last_name}<br>";
		}
		$customer_address .= "{$customer_billing->address_1}<br>";
		if ( ! empty( $customer_billing->address_2 ) ) {
			$customer_address .= "{$customer_billing->address_2}<br>";
		}
		$customer_address .= "{$customer_billing->city}, {$customer_billing->state} {$customer_billing->postcode}<br>
							  {$customer_country}";
		$created_at = $order_data->date_created->date( 'M jS Y' );
		$html2pdf   = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en');
		$html       = <<<HEREDOC
<style type="text/css">
p { font-size:16px; }
table { border-collapse: collapse; font-size:16px; }
img { margin-bottom: 1em; }
hr { height:1px;border-width:0;color: #59595b;background-color: #59595b; }
th, td { padding: 4px 16px; border-bottom: 1px solid #59595b; vertical-align: top; }
</style>
<page backtop="20px" backbottom="20px" backleft="20px" backright="20px">
<p><img width="155" src="https://anchor.host/wp-content/uploads/2015/01/logo.png" alt="Anchor Hosting"></p>
<hr />
<h2>Invoice #{$order_data->id}</h2>
<table cellspacing="0" style="width:100%;">
<tbody>
<tr>
   <td style="width:50%;">
   		{$store_address}
   </td>
   <td style="width:50%;">
		{$customer_address}
   </td>
</tr>
</tbody>
</table>
<p>Order was created on <strong>{$created_at}</strong> and is currently <strong>{$response->status} payment</strong>.</p>
<br /><br />
<table cellspacing="0">
<thead>
	<tr><th><span>Services</span></th><th><span>Amount</span></th></tr>
</thead>
<tbody>
	$order_line_items
	<tr><td style="text-align:right;">Total:</td><td>\${$response->total}</td></tr>
</tbody>
</table>
</page>
HEREDOC;
		$html2pdf->setTestTdInOnePage( false );
		$html2pdf->writeHTML( $html );
		$html2pdf->output();
		wp_die();
	}
	if ( $cmd == 'fetchInvoice' ) {
		$order            = wc_get_order( $value );
		$order_data       = (object) $order->get_data();
		$order_items      = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
		$order_line_items = [];
		foreach ( $order_items as $item_id => $item ) {
			$order_line_items[] = [
				"name"        => $item->get_name(),
				"quantity"    => $item->get_quantity(),
				"description" => $item->get_meta_data(),
				"total"       => $order->get_formatted_line_subtotal( $item ),
			];
		}

		$refunds = $order->get_refunds();
		foreach ( $refunds as $item ) {
			$order_line_items[] = [
				"name"        => "Refund",
				"quantity"    => "1",
				"description" => $item->get_post_title(),
				"total"       => "-".$item->get_formatted_refund_amount(),
			];
			$order_data->total = $order_data->total - $item->get_amount();
		}

		$payment_method_title  = $order->get_payment_method_title();
		if ( empty( $payment_method_title ) ) {
			// Fallback to gateway title if order doesn't have a custom title
			$payment_gateways     = WC()->payment_gateways->payment_gateways();
			$payment_method       = $order->get_payment_method();
			$payment_method_title = isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_title() : "Check";
		}
		$payment_method_string = sprintf(
			__( 'Payment via %s', 'woocommerce' ),
			esc_html( $payment_method_title )
		);

		if ( $order->get_date_paid() ) {
			$paid_on = sprintf(
				__( 'Paid on %1$s @ %2$s', 'woocommerce' ),
				wc_format_datetime( $order->get_date_paid() ),
				wc_format_datetime( $order->get_date_paid(), get_option( 'time_format' ) )
			);
		}

		$response = [
			"order_id"       => $order_data->id,
			"created_at"     => $order_data->date_created->getTimestamp(),
			"status"         => $order_data->status,
			"line_items"     => $order_line_items,
			"payment_method" => $payment_method_string,
			"paid_on"        => $paid_on,
			"total"          => number_format( (float) $order_data->total, 2, '.', '' ),
		];
		echo json_encode( $response );
	}
	if ( $cmd == 'fetchAccount' ) {
		$account = new CaptainCore\Account( $value );
		$account->calculate_usage();
		$account->calculate_totals();
		echo json_encode( $account->fetch() );
	}
	if ( $cmd == 'fetchUser' ) {
		$user = new CaptainCore\User( $value, true );
		echo json_encode( $user->fetch() );
	}
	if ( $cmd == 'saveUser' ) {
		$response = ( new CaptainCore\Users )->update( $value );
		echo json_encode( $response );
	}
	if ( $cmd == 'fetchInvite' ) {
		$invite = (object) $value;
		$invites = new CaptainCore\Invites();
		$results = $invites->where( array( 
			"account_id" => $invite->account,
			"token"      => $invite->token,
		) );
		if ( count( $results ) == "1" ) {
			$account = new CaptainCore\Account( $invite->account, true );
			echo json_encode( $account->fetch() );
		}
	}
	if ( $cmd == 'removeAccountAccess' ) {
		$user_id     = $value;
		$user        = ( new CaptainCore\User( $user_id, true ) );
		$account_id  = $_POST['account'];
		$account_ids = $user->accounts();
		if ( empty( $account_ids ) ) {
			$account_ids = [];
		}
		if ( ( $key = array_search( $account_id, $account_ids ) ) !== false ) {
			unset( $account_ids[$key] );
		}
		( new CaptainCore\User( $user_id, true ) )->assign_accounts( array_unique( $account_ids ) );

		$account = new CaptainCore\Account( $account_id );
		$account->calculate_totals();
	}
	if ( $cmd == 'acceptInvite' ) {
		$invite = (object) $value;
		$invites = new CaptainCore\Invites();
		$results = $invites->where( [
			"account_id" => $invite->account,
			"token"      => $invite->token,
		] );
		
		if ( count( $results ) == "1" ) {
			// Add account ID to current user
			$user       = new CaptainCore\User;
			$accounts   = $user->accounts();
			$accounts[] = $invite->account;
			$user->assign_accounts( array_unique( $accounts ) );

			$account = new CaptainCore\Account( $invite->account );
			$account->calculate_totals();

			$invite = new CaptainCore\Invite( $results[0]->invite_id );
			$invite->mark_accepted();
		}
	}

	if ( $cmd == 'saveDefaults' ) {
		$user     = new CaptainCore\User;
		$accounts = $user->accounts();
		$record   = (object) $value;
		if ( ! in_array( $record->account_id, $accounts ) && ! $user->is_admin() ) {
			echo json_encode( "Permission denied" );
			wp_die();
		}
		
		if ( ! isset( $record->defaults["users"] ) ) {
			$record->defaults["users"] = [];
		}
		if ( ! isset( $record->defaults["recipes"] ) ) {
			$record->defaults["recipes"] = [];
		}
		$account = new CaptainCore\Accounts();
		$account->update( [ "defaults" => json_encode( $record->defaults ) ], [ "account_id" => $record->account_id ] );
		( new CaptainCore\Account( $record->account_id, true ) )->sync();
		echo json_encode( "Record updated." );
	}

	if ( $cmd == 'saveGlobalConfigurations' ) {
		$user = new CaptainCore\User;
		if ( ! $user->is_admin() ) { 
			echo json_encode( "Permission denied" );
			wp_die();
		}
		$value = (object) $value;
		if ( isset( $value->dns_introduction ) ) {
			$value->dns_introduction = str_replace( "\'", "'", $value->dns_introduction );
		}
		update_site_option( 'captaincore_configurations', json_encode( $value ) );
		( new CaptainCore\Configurations )->sync();
		echo json_encode( "Global configurations updated." );
	}

	if ( $cmd == 'saveGlobalDefaults' ) {
		$user = new CaptainCore\User;
		if ( ! $user->is_admin() ) { 
			echo json_encode( "Permission denied" );
			wp_die();
		}
		update_site_option( 'captaincore_defaults', json_encode( $value ) );
		( new CaptainCore\Defaults )->sync();
		echo json_encode( "Global defaults updated." );
	}

	wp_die();

}

add_action( 'wp_ajax_captaincore_user', 'captaincore_user_action_callback' );
function captaincore_user_action_callback() {
	global $wpdb;
	$user = new CaptainCore\User;
	$cmd  = $_POST['command'];
	$everyone_commands = [
		'fetchRequestedSites',
	];

	if ( ! $user->is_admin() && ! in_array( $cmd, $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	if ( $cmd == 'fetchRequestedSites' ) {;
		echo json_encode( $user->fetch_requested_sites() );
	};

	wp_die();

}

add_action( 'wp_ajax_captaincore_account', 'captaincore_account_action_callback' );
function captaincore_account_action_callback() {
	global $wpdb;
	$user = new CaptainCore\User;
	$cmd  = $_POST['command'];
	$everyone_commands = [
		'addDomain',
		'deleteDomain',
		'requestSite',
		'payInvoice',
		'setAsPrimary',
		'addPaymentMethod',
		'deletePaymentMethod',
		'deleteRequestSite',
		'cancelPlan',
		'updateBilling',
	];

	if ( $cmd == 'updateBilling' ) {
		$request  = (object) $_POST['value'];
		$customer = new WC_Customer(  $user->user_id() );
		$customer->set_billing_address_1( $request->address_1 );
		$customer->set_billing_address_2( $request->address_2 );
		$customer->set_billing_city( $request->city );
		$customer->set_billing_company( $request->company );
		$customer->set_billing_country( $request->country );
		$customer->set_billing_email( $request->email );
		$customer->set_billing_first_name( $request->first_name );
		$customer->set_billing_last_name( $request->last_name );
		$customer->set_billing_phone( $request->phone );
		$customer->set_billing_postcode( $request->postcode );
		$customer->set_billing_state( $request->state );
		$customer->save();
	};

	if ( $cmd == 'cancelPlan' ) {

		$current_subscription = (object) $_POST['value'];
		$current_user         = $user->fetch();
		$billing              = $user->billing();
		if ( $current_subscription->account_id == "" || $current_subscription->name == "" ) {
			wp_die();
		}
		foreach ( $billing->subscriptions as $subscription ) {
			if ( $subscription->account_id == $current_subscription->account_id && $subscription->name == $current_subscription->name  ) {
				CaptainCore\Mailer::send_cancel_plan_request( $current_subscription, $current_user );
			}
		}

	}

	if ( $cmd == 'requestPlanChanges' ) {
		$current_user = $user->fetch();
		$subscription = (object) $_POST['value'];
		CaptainCore\Mailer::send_plan_change_request( $subscription, $current_user );
	}

	$account_id = intval( $_POST['account_id'] );

	// Only proceed if have permission to particular account id.
	if ( ! $user->is_admin() && isset( $account_id ) && ! captaincore_verify_permissions_account( $account_id ) && ! in_array( $_POST['command'], $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	if ( $cmd == 'deleteDomain' ) {
		$response = ( new CaptainCore\Domains )->delete_domain( $_POST['value'] );
		echo json_encode( $response );
	};

	if ( $cmd == 'addDomain' ) {

		$errors = [];
		$name   = trim( $_POST['value'] );
		// Get the new flag. Default to true if not set.
        $create_dns_zone = isset( $_POST['create_dns_zone'] ) ? ( $_POST['create_dns_zone'] === 'true' || $_POST['create_dns_zone'] === '1' ) : true;

		// If results still exists then give an error
		if ( $name == "" ) {
			$errors[] = "Domain can't be empty.";
		}

		// If user is not admin, get account_id from the selected site's customer_id
		if ( ! $user->is_admin() ) {
			$site_id = intval( $_POST['site_id'] );
			if ( empty( $site_id ) ) {
				$errors[] = "Website must be selected.";
			} else {
				// Verify user has permission to this site
				if ( ! captaincore_verify_permissions( $site_id ) ) {
					$errors[] = "Permission denied for selected site.";
				} else {
					$site = CaptainCore\Sites::get( $site_id );
					if ( $site && ! empty( $site->customer_id ) ) {
						$account_id = $site->customer_id;
					} else {
						$errors[] = "Selected site does not have a valid customer assigned.";
					}
				}
			}
		}

		// Check for duplicate domain.
		$domain_exists = ( new CaptainCore\Domains )->where( [ "name" => $name ] );

		// If results still exists then give an error
		if ( count( $domain_exists ) > 0 ) {
			$errors[] = "Domain has already been added.";
		}

		if ( empty( $account_id ) ) { 
			$errors[] = "Account can't be empty.";
		}

		// If any errors then bail
		if ( count( $errors ) > 0 ) {
			echo json_encode( [ "errors" => $errors ] );
			wp_die();
		}

		$time_now = date("Y-m-d H:i:s");

		// Insert domain
		$domain_id = ( new CaptainCore\Domains )->insert( [
			"name"       => $name,
			'updated_at' => $time_now,
			'created_at' => $time_now,
		] );

		// Assign domain to account
		( new CaptainCore\Domain( $domain_id ) )->insert_accounts( [ $account_id ] );

		$remote_id = null;

		// Conditionally execute remote code
		if ( $create_dns_zone ) {
			// Administrators can link to existing DNS zones, regular users cannot
			$link_existing = $user->is_admin();
			$response = ( new CaptainCore\Domain( $domain_id ) )->fetch_remote_id( $link_existing );
			if ( is_array( $response ) && isset( $response["errors"] ) ) { // Check if $response is an error array
				foreach ( $response["errors"] as $error ) {
					$errors[] = $error;
				}
				echo json_encode( [ "errors" => $errors ] );
				wp_die();
			}
			$remote_id = $response; // Store the remote_id if successful
		}

		echo json_encode( [ "name" => $name, "domain_id" => $domain_id, "remote_id" => $remote_id ] );

	}

	if ( $cmd == 'sendAccountInvite' ) {
		$account  = new CaptainCore\Account( $account_id );
		$response = $account->invite( $_POST['invite'] );
		echo json_encode( $response );
	}

	if ( $cmd == 'deleteInvite' ) {
		$account  = new CaptainCore\Account( $account_id );
		$response = $account->invite_delete( $_POST['value'] );
		echo "Invite deleted.";
	}

	if ( $cmd == 'payInvoice' ) {
		// Pay with new credit card
		if ( isset( $_POST['source_id'] ) ) {
			$response       = $user->add_payment_method( $_POST['source_id'] );
			if ( isset( $response->error ) ) {
				echo json_encode( $response );
				wp_die();
			}
			$payment_tokens = WC_Payment_Tokens::get_customer_tokens( $user->user_id() );
			foreach ( $payment_tokens as $payment_token ) { 
				if( $payment_token->get_token() == $_POST['source_id'] ) {
                    // Capture response
					$result = $user->pay_invoice( $_POST['value'], $payment_token->get_id() );
					$user->set_as_primary( $payment_token->get_id() );
                    // Echo response
                    echo json_encode( $result );
				}
			}
			wp_die();
		}
		// Pay with existing credit card
        // Capture response
		$result = $user->pay_invoice( $_POST['value'], $_POST['payment_id'] );
		$user->set_as_primary( $_POST['payment_id'] );
        // Echo response
        echo json_encode( $result );
	};

	if ( $cmd == 'setAsPrimary' ) {
		$user->set_as_primary( $_POST['value'] );
	};

	if ( $cmd == 'addPaymentMethod' ) {
		$response = $user->add_payment_method( $_POST['value'] );
		echo json_encode( $response );
	};

	if ( $cmd == 'deletePaymentMethod' ) {
		$user->delete_payment_method( $_POST['value'] );
	};

	if ( $cmd == 'requestSite' ) {
		$user->request_site( $_POST['value'] );
		echo json_encode( $user->fetch_requested_sites() );
	};

	if ( $cmd == 'backRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->back_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};

	if ( $cmd == 'continueRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->continue_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};
	
	if ( $cmd == 'updateRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->update_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};

	if ( $cmd == 'deleteRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->delete_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};

	wp_die(); // this is required to terminate immediately and return a proper response

}

add_action( 'wp_ajax_captaincore_ajax', 'captaincore_ajax_action_callback' );
function captaincore_ajax_action_callback() {
	global $wpdb;
	$user = new CaptainCore\User;

	$everyone_commands = [
		'newRecipe',
		'updateRecipe',
		'updateSiteAccount',
		'requestSite',
		'mailgun'
	];

	if ( is_array( $_POST['post_id'] ) ) {
		$post_ids       = [];
		$post_ids_array = $_POST['post_id'];
		foreach ( $post_ids_array as $id ) {
			$post_ids[] = intval( $id );
		}
	} else {
		$post_id = intval( $_POST['post_id'] );
	}

	// Only proceed if have permission to particular site id.
	if ( ! $user->is_admin() && isset( $post_id ) && ! captaincore_verify_permissions( $post_id ) && ! in_array( $_POST['command'], $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	// Only proceed if have permission to particular site id.
	if ( ! $user->is_admin() && isset( $post_ids ) && ! captaincore_verify_permissions( $post_ids ) && ! in_array( $_POST['command'], $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	// Only proceed if access to command 
	$admin_commands = [
		'fetchConfigs',
		'updateLogEntry',
		'newLogEntry',
		'newKey',
		'updateKey',
		'deleteKey',
		'setKeyAsPrimary',
		'newProcess',
		'saveProcess',
		'fetchProcess',
		'fetchProcessRaw',
		'fetchProcessLogs',
		'listenProcesses',
		'updateFathom',
		'updateMailgun',
		'updatePlan',
		'updateDomainAccount',
		'createSiteAccount',
		'updateSite',
		'deleteAccount'
	];
	if ( ! $user->is_admin() && in_array( $_POST['command'], $admin_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	$cmd       = $_POST['command'];
	if ( isset($_POST['value']) ){
		$value = $_POST['value'];
	}
	
	$fetch          = (new CaptainCore\Site( $post_id ))->get();
	$site           = $fetch->site;
	$environment    = isset( $_POST['environment'] ) ? $_POST['environment'] : '';
	$remote_command = false;

	if ( $cmd == 'mailgun' ) {
		$mailgun  = $fetch->mailgun;
		$response             = (object) [];
		$response->items      = [];
		$response->pagination = [];
		if ( isset( $_POST['page'] ) ) {
			$domains = CaptainCore\Remote\Mailgun::page( $mailgun, $_POST['page'] );

		} else {
			$domains = CaptainCore\Remote\Mailgun::get( "v3/$mailgun/events", [ "event" => "accepted OR rejected OR delivered OR failed OR complained", 'limit' => 300 ] );
		}
		foreach ( $domains->items as $item ) {
			$description = $item->recipient;
			if ( $item->message->headers->from ) {
				$from        = $item->message->headers->from;
				$description = "{$from} -> {$description}";
			}
			$response->items[] = [
				"timestamp"   => $item->timestamp,
				"event"       => $item->event,
				"description" => $description,
				"message"     => $item->message,
			];
			$response->pagination["next"]     = $domains->paging->next;
		}
		echo json_encode( $response );
	}

	if ( $cmd == 'fetchLink' ) {
		// Fetch snapshot details
		$in_24hrs = date("Y-m-d H:i:s", strtotime ( date("Y-m-d H:i:s")."+24 hours" ) );

		// Generate new token
		$token = bin2hex( openssl_random_pseudo_bytes( 16 ) );
		( new CaptainCore\Snapshots )->update( [
			"token"       => $token,
			"expires_at"  => $in_24hrs 
		],[ 
			"snapshot_id" => $value 
		] );
		echo json_encode( [ 
			"token"       => $token,
			"expires_at"  => $in_24hrs
		] );
	}

	if ( $cmd == 'fetchPlugins' ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$arguments = array(
			'per_page' => 9,
			'page'     => $_POST['page'],
			'browse'   => 'popular', 
			'is_ssl'   => true,
		);
		if ( $value ) {
			$arguments['search'] = $value;
			unset( $arguments['browse'] );
		}
		$response = plugins_api( 'query_plugins', $arguments );

		echo json_encode( $response ); 
	};

	if ( $cmd == 'fetchThemes' ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$arguments = array(
			'per_page' => 9,
			'page'     => $_POST['page'],
			'browse'   => 'popular', 
			'is_ssl'   => true,
		);
		if ( $value ) {
			$arguments['search'] = $value;
			unset( $arguments['browse'] );
	}
		$response = themes_api( 'query_themes', $arguments );

		echo json_encode( $response );
	};

	if ( $cmd == 'shareStats' ) {
		$sharing        = $_POST['sharing'];
		$share_password = $_POST['share_password'];
		$fathom_id      = $_POST['fathom_id'];
		$response       = ( new CaptainCore\Site( $post_id ) )->stats_sharing( $fathom_id, $sharing, $share_password );
	}

	if ( $cmd == 'fetchStats' ) {
		$before    = strtotime( $_POST['from_at'] );
		$after     = strtotime( $_POST['to_at'] );
		$grouping  = strtolower( $_POST['grouping'] );
		$fathom_id = $_POST['fathom_id'];
		$response  = ( new CaptainCore\Site( $post_id ) )->stats( $environment, $before, $after, $grouping, $fathom_id );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo json_encode( [ "error" => $error_message ] );
			wp_die();
			return;
		}
		echo json_encode( $response ); 
	}

	if ( $cmd == 'fetchConfigs' ) {
		$remote_command = true;
		$command = "configs fetch vars";
	};

	if ( $cmd == 'newKey' ) {
		$key      = (object) $value;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		$new_key = [
			'user_id'    => $user_id,
			'title'      => $key->title,
			'updated_at' => $time_now,
			'created_at' => $time_now,
			'main'       => 0,
		];

		$look_for_default = ( new CaptainCore\Keys )->where( [ "user_id" => $user_id, "main" => "1" ] );
		if ( empty( $look_for_default ) ) {
			$new_key[ "main" ] = 1;
		}

		$key_id         = ( new CaptainCore\Keys )->insert( $new_key );
		$remote_command = true;
		$silence        = true;
		$ssh_key        = base64_encode( stripslashes_deep( $key->key ) );
		$command        = "key add $ssh_key --id=$key_id";
	}

	if ( $cmd == 'setKeyAsPrimary' ) {
		$key      = (object) $value;
		$key_id   = $key->key_id;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		$look_for_default = ( new CaptainCore\Keys )->where( [ "user_id" => $user_id, "main" => "1" ] );
		if ( ! empty( $look_for_default ) ) {
			foreach( $look_for_default as $key_primary ) {
				( new CaptainCore\Keys )->update( [ 'main' => 0 ], [ "key_id" => $key_primary->key_id ] );
			}
		}

		$key_update = [
			'main'       => 1,
			'updated_at' => $time_now,
		];

		( new CaptainCore\Keys )->update( $key_update, [ "key_id" => $key_id ] );

		$configurations = ( new CaptainCore\Configurations )->get();
		$configurations->default_key = $key_id;
		update_site_option( 'captaincore_configurations', json_encode( $configurations ) );
		( new CaptainCore\Configurations )->sync();
	}

	if ( $cmd == 'updateKey' ) {
		$key      = (object) $value;
		$key_id   = $key->key_id;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		$key_update = [
			'title'      => $key->title,
			'updated_at' => $time_now,
		];

		$look_for_default = ( new CaptainCore\Keys )->where( [ "user_id" => $user_id, "main" => "1" ] );
		if ( empty( $look_for_default ) ) {
			$key_update[ "main" ] = 1;
		}

		( new CaptainCore\Keys )->update( $key_update, [ "key_id" => $key_id ] );

		$remote_command = true;
		$silence        = true;
		$ssh_key        = base64_encode( stripslashes_deep( $key->key ) );
		$command        = "key add $ssh_key --id={$key_id}";
	}

	if ( $cmd == 'deleteKey' ) {
		$key_id   = $value;
		$time_now = date("Y-m-d H:i:s");

		( new CaptainCore\Keys )->delete( $key_id );

		$remote_command = true;
		$silence        = true;
		$ssh_key        = base64_encode( stripslashes_deep( $key->key ) );
		$command        = "key delete --id={$key_id}";
	}

	if ( $cmd == 'listenProcesses' ) {
		$run_in_background = true;
		$remote_command    = true;
		$command           = "running listen";
	}

	if ( $cmd == 'newProcess' ) {
		$timenow             = date( 'Y-m-d H:i:s' );
		$process             = (object) $value;
		$process->user_id    = get_current_user_id();
		$process->created_at = $timenow;
		$process->updated_at = $timenow;
		unset( $process->show );
		$process_id = ( new CaptainCore\Processes )->insert( (array) $process );
		$process_inserted = ( new CaptainCore\Processes )->get( $process_id );
		echo json_encode( $process_inserted );
	}

	if ( $cmd == 'saveProcess' ) {
		$process              = (object) $value;
		$process->name        = str_replace( "\'", "'", $process->name );
		$process->description = str_replace( "\'", "'", $process->description );
		$process->updated_at  = date( 'Y-m-d H:i:s' );
		( new CaptainCore\Processes )->update( (array) $process, [ "process_id" => $process->process_id ] );
		$process_updated = ( new CaptainCore\Processes )->get( $process->process_id );
		echo json_encode( $process_updated );
	}

	if ( $cmd == 'fetchProcess' ) {
		$process = ( new CaptainCore\Process( $post_id ) )->get();
		echo json_encode( $process );
	}

	if ( $cmd == 'fetchProcessRaw' ) {
		$process = ( new CaptainCore\Processes )->get( $post_id );
		$process->roles = (int) $process->roles;
		echo json_encode( $process );
	}

	if ( $cmd == 'fetchProcessLog' ) {
		$process_log = ( new CaptainCore\ProcessLog( $value ) )->get();
		echo json_encode( $process_log );
	}

	if ( $cmd == 'fetchProcessLogs' ) {
		$process_logs = ( new CaptainCore\ProcessLogs )->list();
		echo json_encode( $process_logs );
	}

	if ( $cmd == 'newLogEntry' ) {
		$process_id = $_POST['process_id'];
		$time_now   = date( 'Y-m-d H:i:s' );
		$value      = str_replace( "\'", "'", $value );
		$process_log_new = (object) [
			"process_id"   => $_POST['process_id'],
			'user_id'      => get_current_user_id(),
			'public'       => 1,
			'description'  => $value,
			'status'       => 'completed',
			'created_at'   => $time_now,
			'updated_at'   => $time_now,
			'completed_at' => $time_now
		];
		$process_log = new CaptainCore\ProcessLogs();
		$process_log_id_new = $process_log->insert( (array) $process_log_new );
		( new CaptainCore\ProcessLog( $process_log_id_new ) )->assign_sites( $post_ids );
		$process_logs = ( new CaptainCore\Site( $post_id ) )->process_logs();
		$timelines = [];
		foreach ( $post_ids as $post_id ) {
			$timelines[ $post_id ] = ( new CaptainCore\Site( $post_id ) )->process_logs();
		}
		echo json_encode( $timelines ) ;
	}

	if ( $cmd == 'updateLogEntry' ) {
		$process_log_update              = (object) $_POST['log'];
		$site_ids                        = array_column( $process_log_update->websites, 'site_id' );
		$process_log_update->user_id     = get_current_user_id();
		$process_log_update->description = str_replace( "\'", "'", $process_log_update->description_raw );
		$process_log_update->created_at  = $process_log_update->created_at_raw;
		$process_log_update->updated_at  = date( 'Y-m-d H:i:s' );
		unset( $process_log_update->created_at_raw );
		unset( $process_log_update->name );
		unset( $process_log_update->author );
		unset( $process_log_update->websites );
		unset( $process_log_update->description_raw );
		( new CaptainCore\ProcessLogs )->update( (array) $process_log_update, [ "process_log_id" => $process_log_update->process_log_id ] );
		( new CaptainCore\ProcessLog( $process_log_update->process_log_id) )->assign_sites( $site_ids );
		$timelines = [];
		foreach ( $site_ids as $site_id ) {
			$timelines[ $site_id ] = ( new CaptainCore\Site( $site_id ) )->process_logs();
		}
		echo json_encode( $timelines );
	}

	if ( $cmd == 'timeline' ) {
		$process_logs = ( new CaptainCore\Site( $post_id ) )->process_logs();
		echo json_encode( $process_logs ) ;
	}

	if ( $cmd == 'createSiteAccount' ) {
		$time_now = date("Y-m-d H:i:s");
		$defaults = [ 
			"email"    => "",
			"timezone" => "",
			"recipes"  => [],
			"users"    => [],
		];
		$account_id = ( new CaptainCore\Accounts )->insert( [ 
			"name"       => trim( $value ),
			"status"     => "active",
			"created_at" => $time_now,
			"updated_at" => $time_now,
			"defaults"   => json_encode( $defaults ),
		] );
		( new CaptainCore\Account( $account_id, true ) )->calculate_totals();
		( new CaptainCore\Account( $account_id, true ) )->sync();
		echo json_encode( $account_id );
	}

	if ( $cmd == 'updateSiteAccount' ) {
		$account = (object) $value;
		if ( ! $user->verify_account_owner( $account->account_id ) ) {
			echo "Permission denied";
			wp_die();
			return;
		}

		( new CaptainCore\Accounts )->update( [ "name" => trim( $account->name ), "billing_user_id" => $account->billing_user_id ], [ "account_id" => $account->account_id ] );
		( new CaptainCore\Account( $account->account_id ) )->sync();
		echo json_encode( $account ) ;
	}

	if ( $cmd == 'updateDomainAccount' ) {
		$domain_id   = $_POST['domain_id'];
		$provider_id = $_POST['provider_id'];
		( new CaptainCore\Domain( $domain_id ) )->assign_accounts( $value );
		CaptainCore\Domains::update( [ "provider_id" => $provider_id ], [ "domain_id" => $domain_id ] );
	}

	if ( $cmd == 'newRecipe' ) {

		$recipe   = (object) $value;
		$time_now = date("Y-m-d H:i:s");

		$new_recipe = [
			'user_id'        => get_current_user_id(),
			'title'          => $recipe->title,
			'updated_at'     => $time_now,
			'created_at'     => $time_now,
			'content'        => stripslashes_deep( $recipe->content ),
			'public'         => 0
		];

		if ( $user->is_admin() ) {
			$new_recipe["public"] = $recipe->public;
		}

		$db_recipes = new CaptainCore\Recipes();
		$recipe_id = $db_recipes->insert( $new_recipe );
		echo json_encode( $db_recipes->list() );

		$remote_command = true;
		$silence = true;
		$recipe = ( new CaptainCore\Recipes )->get( $recipe_id );
		$recipe = base64_encode( json_encode( $recipe ) );
		$command = "recipe add $recipe --format=base64";

	}

	if ( $cmd == 'updateRecipe' ) {

		$recipe   = (object) $value;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		if ( ! $user->is_admin() && $recipe->user_id != $user_id ) {
			echo "Permission denied";
			wp_die();
			return;
		}

		$recipe_update = [
			'title'      => $recipe->title,
			'updated_at' => $time_now,
			'content'    => stripslashes_deep( $recipe->content ),
			'public'     => 0
		];

		if ( $user->is_admin() ) {
			$recipe_update["public"] = $recipe->public;
		}

		$db_recipes = new CaptainCore\Recipes();
		$db_recipes->update( $recipe_update, [ "recipe_id" => $recipe->recipe_id ] );

		echo json_encode( $db_recipes->list() );

		$remote_command = true;
		$silence = true;
		$recipe  = ( new CaptainCore\Recipes )->get( $recipe->recipe_id );
		$recipe  = base64_encode( json_encode( $recipe ) );
		$command = "recipe add $recipe --format=base64";

	}

	if ( $cmd == 'usage-breakdown' ) {
		$site            = ( new CaptainCore\Site( $post_id ) )->get();
		$account         = new CaptainCore\Account( $site->account_id, true );
		$usage_breakdown = $account->usage_breakdown();
		echo json_encode( $usage_breakdown ) ;
	}

	if ( $cmd == 'updateMailgun' ) {
		$site = new CaptainCore\Site( $post_id );
		$site->update_mailgun( $value );
	}

	if ( $cmd == 'updateFathom' ) {

		// Append environment if needed
		if ( $environment == "Staging" ) {
			$site = "{$site}-staging";
		}

		$time_now = date("Y-m-d H:i:s");
		$data     = (object) $value;

		$environment_id = ( new CaptainCore\Site( $post_id ) )->fetch_environment_id( $environment );
		$environment    = ( new CaptainCore\Environments )->get( $environment_id );
		( new CaptainCore\Environments )->update( [ 'fathom' => json_encode( $data->fathom_lite ) ], [ "environment_id" => $environment->environment_id ] );
		
		$details         = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
		$details->fathom = $data->fathom;
		( new CaptainCore\Environments )->update( [ 
			 "details"    => json_encode( $details ),
			 "updated_at" => $time_now,
			], [ "environment_id" => $environment->environment_id ] );

		( new CaptainCore\Site( $post_id ) )->sync();

		$run_in_background = true;
		$remote_command    = true;
		$command           = "stats-deploy $site";
	}

	if ( $cmd == 'updatePlan' ) {
		( new CaptainCore\Accounts )->update_plan( $value["plan"], $post_id );
	}

	if ( $cmd == 'updateSettings' ) {
		// Saves update settings for a site
		$environment_update = [
			'updates_enabled'         => $value["updates_enabled"],
			'updates_exclude_themes'  => implode(",", $value["updates_exclude_themes"] ?? []),
			'updates_exclude_plugins' => implode(",", $value["updates_exclude_plugins"] ?? []),
			'updated_at'              => date("Y-m-d H:i:s") 
		];
		$environment_id = ( new CaptainCore\Site( $post_id ) )->fetch_environment_id( $environment );
		( new CaptainCore\Environments )->update( $environment_update, [ "environment_id" => $environment_id ] );
		$command           = "site sync $post_id";
		$remote_command    = true;
		$run_in_background = true;
	}

	if ( $cmd == 'updateSite' ) {
		// Updates site
		$site     = new CaptainCore\Site( $value["site_id"] );
		$response = $site->update( $value );
		echo json_encode( $response );
	}

	if ( $cmd == 'deleteAccount' ) {
		// Delete site on CaptainCore CLI
		captaincore_run_background_command( "account delete $post_id" );

		// Delete account locally
		$account = new CaptainCore\Account( $post_id, true );
		$account->delete();
	}

	if ( $cmd == 'fetch-site-details' ) {
		$site        = new CaptainCore\Site( $post_id );
		$account     = $site->account();
		$domains     = $site->domains();
		$shared_with = $site->shared_with();
		$site        = $site->fetch();
		echo json_encode( [
			"site"        => $site,
			"account"     => $account,
			"domains"     => $domains,
			"shared_with" => $shared_with,
		] );
	}
	if ( $cmd == 'fetch-site' ) {
		$sites = [];
		if ( is_array( $post_ids ) && count( $post_ids ) > 0 ) {
			foreach( $post_ids as $id ) {
				$site    = new CaptainCore\Site( $id );
				$sites[] = $site->fetch();
			}
		} else {
			$site    = new CaptainCore\Site( $post_id );
			$sites[] = $site->fetch();
		}
		echo json_encode( $sites );
	}

	if ( $cmd == 'fetch-users' ) {
		$results = ( new CaptainCore\Site( $post_id ))->users();
		echo json_encode($results);
	}

	if ( $cmd == 'fetch-update-logs' ) {
		$results = ( new CaptainCore\Site( $post_id ))->update_logs();
		echo json_encode($results);
	}

	if ( $remote_command ) {

		// Disable https when debug enabled
		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
		}

		$data = [
			'timeout' => 45,
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8', 
				'token'        => CAPTAINCORE_CLI_TOKEN 
			), 
			'body'        => json_encode( [ "command" => $command ] ), 
			'method'      => 'POST', 
			'data_format' => 'body' 
		];

		if ( $run_in_background ) {

			// Add command to dispatch server
			$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/tasks", $data );
			$response = json_decode( $response["body"] );
			
			// Response with task id
			if ( $response && $response->token ) { 
				echo $response->token; 
			}

			wp_die(); // this is required to terminate immediately and return a proper response
		}

		// Add command to dispatch server
		$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
		$response = $response["body"];

		// Store results in wp_options.captaincore_settings
		if ( $cmd == "fetchConfigs" ) {
			$captaincore_settings = json_decode( $response );
			unset($captaincore_settings->websites);
			update_option("captaincore_settings", $captaincore_settings );
		}

		// Store results in wp_options.captaincore_settings
		if ( $cmd == "newKey" ||  $cmd == "updateKey" ) {
			$key_update = [
				'fingerprint' => $response,
			];
	
			$db = new CaptainCore\Keys();
			$db->update( $key_update, [ "key_id" => $key_id ] );
			echo json_encode( $db->get( $key_id ) );
		}

		if ( $silence ) {
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		echo $response;
		
		wp_die(); // this is required to terminate immediately and return a proper response

	}

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_captaincore_install', 'captaincore_install_action_callback' );
function captaincore_install_action_callback() {
	global $wpdb;

	// Assign post id
	$post_id = intval( $_POST['post_id'] );

	// Many sites found, check permissions
	if ( is_array( $_POST['post_id'] ) ) {
		$post_ids       = [];
		foreach ( $_POST['post_id'] as $id ) {

			// Checks permissions
			if ( ! captaincore_verify_permissions( $id ) ) {
				echo 'Permission denied';
				wp_die(); // this is required to terminate immediately and return a proper response
				return;
			}

			$post_ids[] = intval( $id );
		}

		// Patch in the first from the post_ids
		$post_id = $post_ids[0];

	}

	// Checks permissions
	if ( ! captaincore_verify_permissions( $post_id ) ) {
		echo 'Permission denied';
		wp_die(); // this is required to terminate immediately and return a proper response
		return;
	}

	$cmd          = $_POST['command'];
	$value        = $_POST['value'];
	$version      = $_POST['version'];
	$commit       = $_POST['commit'];
	$hash         = $_POST['hash'];
	$arguments    = $_POST['arguments'];
	$filters      = $_POST['filters'];
	$addon_type   = $_POST['addon_type'];
	$date         = $_POST['date'];
	$name         = $_POST['name'];
	$environment  = $_POST['environment'];
	$backup_id    = $_POST['backup_id'];
	$link         = $_POST['link'];
	$background   = $_POST['background'];
	$job_id       = $_POST['job_id'];
	$notes        = $_POST['notes'];
	$subject      = isset($_POST['subject']) ? $_POST['subject'] : '';
	$status_msg   = isset($_POST['status_msg']) ? $_POST['status_msg'] : '';
	$action_text  = isset($_POST['action_text']) ? $_POST['action_text'] : '';
	$fetch        = (object) ( new CaptainCore\Site( $post_id ) )->get();
	$site         = $fetch->site;
	$provider     = $fetch->provider;
	$domain       = $fetch->name;

	$partners = get_field( 'partner', $post_id );
	if ( $partners && is_string( $partners ) ) {
		$preloadusers = implode( ',', $partners );
	}

	// Append environment if needed
	if ( $environment == "Staging" ) {
		$site = "{$site}-staging";
	}

	// Append provider if exists
	if ( $provider != '' ) {
		$site = $site . '@' . $provider;
	}

	// If many sites, fetch their names
	if ( is_array( $post_ids ) && count ( $post_ids ) > 0 ) {
		$site_names = [];
		foreach( $post_ids as $id ) {

			$fetch     = ( new CaptainCore\Site( $id ) );
			$site_name = $fetch->get()->site;

			if ( $environment == "Production" or $environment == "Both" ) {
				$site_names[] = $site_name;
			}

			$address_staging = $fetch->environments()[1]->address;

			// Add staging if needed
			if ( isset( $address_staging ) && $address_staging != "" ) {
				if ( $environment == "Staging" or $environment == "Both" ) {
					$site_names[] = "{$site_name}-staging";
				}
			}
		}
		$site = implode( " ", $site_names );
	}

	$run_in_background_silent = false;
	$run_in_background        = false;

	if ( $background ) {
		$run_in_background = true;
	}
	if ( $cmd == 'new' ) {
		$command = "site sync $post_id --update-extras";
		$run_in_background = true;
	}
	if ( $cmd == 'deploy-defaults' ) {
		$command = "site deploy-defaults $site";
		$run_in_background = true;
	}
	if ( $cmd == 'update' ) {
		$command = "site sync $post_id";
		$run_in_background = true;
	}
	if ( $cmd == 'update-wp' ) {
		$command = "update $site";
		$run_in_background = true;
	}
	if ( $cmd == 'update-fetch' ) {
		$command = "update-fetch $site";

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_UPDATES;
		}
	}
	if ( $cmd == 'users-fetch' ) {
		$command = "ssh $site --command='wp user list --format=json'";

		$run_in_background = true;

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_USERS;
		}
	}
	if ( $cmd == 'copy' ) {
		// Find destination site and verify we have permission to it
		if ( captaincore_verify_permissions( $value ) ) {
			$current_user = wp_get_current_user();
			$email        = $current_user->user_email;
			$run_in_background = true;
			$site_destination = get_field( 'site', $value );
			$command   = "copy $site $site_destination --email=$email";
		}
	}
	if ( $cmd == 'migrate' ) {
		$run_in_background = true;
		$value = urlencode( $value );
		$command = "ssh $site --script=migrate -- --url=\"$value\"";
		if ( $_POST['update_urls'] == "true" ) {
			$command = "$command --update-urls";
		}
	}
	if ( $cmd == 'recipe' ) {
		$run_in_background = true;
		$command     = "ssh $site --recipe=$value";
		$recipe_name = ( new CaptainCore\Recipes )->get( $value )->title;
		CaptainCore\ProcessLog::insert( $recipe_name, $post_id );
	}
	if ( $cmd == 'launch' ) {
		$run_in_background = true;
		$command = "ssh $site --script=launch -- --domain=$value";
	}
	if ( $cmd == 'reset-permissions' ) {
		$run_in_background = true;
		$command = "ssh $site --script=reset-permissions";
		CaptainCore\ProcessLog::insert( "Reset file permissions", $post_id );
	}
	if ( $cmd == 'apply-https' ) {
		$run_in_background = true;
		$command = "ssh $site --script=apply-https";
		CaptainCore\ProcessLog::insert( "Updated internal urls to HTTPS", $post_id );
	}
	if ( $cmd == 'apply-https-with-www' ) {
		$run_in_background = true;
		$command = "ssh $site --script=apply-https-with-www";
		CaptainCore\ProcessLog::insert( "Updated internal urls to HTTPS with www", $post_id );
	}
	if ( $cmd == 'production-to-staging' ) {
		$run_in_background = true;
		if ( $value ) {
			$command = "site copy-to-staging $site --email=$value";
		} else {
			$command = "site copy-to-staging $site";
		}
	}
	if ( $cmd == 'staging-to-production' ) {
		$run_in_background = true;
		if ( $value ) {
			$command = "site copy-to-production $site --email=$value";
		} else {
			$command = "site copy-to-production $site";
		}
	}
	if ( $cmd == 'scan-errors' ) {
		$run_in_background = true;
		$command = "scan-errors $site";
	}
	if ( $cmd == 'sync-data' ) {
		$run_in_background = true;
		$command = "sync-data $site";
	}
	if ( $cmd == 'remove' ) {
		$command = "site delete $site";
	}
	if ( $cmd == 'quick_backup' ) {
		$run_in_background = true;
		$command   = "quicksave generate $site";
	}
	if ( $cmd == 'backup' ) {
		$run_in_background = true;
		$command = "backup $site";
	}
	if ( $cmd == 'snapshot' ) {
		$run_in_background = true;
		$user_id = get_current_user_id();
		if ( $date && $value ) {
			$command = "snapshot generate $site --email=$value --rollback=\"$date\" --user-id=$user_id --notes=\"$notes\"";
		} elseif ( $value ) {
			$command = "snapshot generate $site --email=$value --user-id=$user_id --notes=\"$notes\"";
		} else {
			$command = "snapshot generate $site --user-id=$user_id --notes=\"$notes\"";
		}
		if ( $filters ) {
			$filters = implode(",", $filters); 
			$command = $command . " --filter={$filters}";
		}
	}
	if ( $cmd == 'deactivate' ) {
		$run_in_background = true;
		
		$subject     = $subject;
		$status_msg  = $status_msg;
		$action_text = $action_text;

		// Construct command with new arguments
		$command = "deactivate $site --name=\"$name\" --link=\"$link\" --subject=\"$subject\" --status=\"$status_msg\" --action=\"$action_text\"";
		
		CaptainCore\ProcessLog::insert( "Suspended website", $post_id );
	}
	if ( $cmd == 'activate' ) {
		$run_in_background = true;
		$command           = "activate $site";
		CaptainCore\ProcessLog::insert( "Restored website", $post_id );
	}

	if ( $cmd == 'view_quicksave_changes' ) {
		$command = "quicksave show-changes $site $value";
	}

	if ( $cmd == 'run' ) {
		$code    = base64_encode( stripslashes_deep( $value ) );
		$command = "run $site --code=$code";
	}

	if ( $cmd == 'backup_download' ) {
		$run_in_background_silent = true;
		$value             = (object) $value;
		$current_user      = wp_get_current_user();
		$email             = $current_user->user_email;
		$payload           = [
			"files"       => json_decode( stripslashes_deep( $value->files ) ),
			"directories" => json_decode ( stripslashes_deep( $value->directories ) ),
		];
		$payload           = base64_encode( json_encode( $payload ) );
		$command =  "backup download $site $value->backup_id --email=$email --payload='$payload'";
	}

	if ( $cmd == 'manage' ) {
		$run_in_background = true;
		if ( is_int($post_id) ) {
			$command = "$value $site --" . $arguments['value'] . '="' . stripslashes($arguments['input']) . '"';
		}
	}

	if ( $cmd == 'quicksave_file_diff' ) {
		$command = "quicksave file-diff $site $commit $value --html";
	}

	if ( $cmd == 'rollback' ) {
		$run_in_background = true;
		$command           = "quicksave rollback $site $commit --version=$version --$addon_type=$value";
	}

	if ( $cmd == 'quicksave_rollback' ) {
		$run_in_background = true;
		$command           = "quicksave rollback $site $commit --version=$version --all";
	}

	if ( $cmd == 'quicksave_file_restore' ) {
		$run_in_background = true;
		$command           = "quicksave rollback $site $hash --version=this --file=$value";
	}

	// Disable https when debug enabled
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		add_filter( 'https_ssl_verify', '__return_false' );
	}

	$data = [ 
		'timeout' => 45,
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8',
			'token'        => CAPTAINCORE_CLI_TOKEN
		],
		'body' => json_encode( [
			"command" => $command
		] ),
		'method'      => 'POST',
		'data_format' => 'body'
	];

	if ( $cmd == 'job-fetch' ) {

		$data['body'] = "";
		$data['method'] = "GET";

		// Add command to dispatch server
		$response = wp_remote_get( CAPTAINCORE_CLI_ADDRESS . "/task/${job_id}", $data );
		$response = json_decode( $response["body"] );
		
		// Response with task id
		if ( $response && $response->Status == "Completed" ) { 
			echo json_encode( [
				"response" => $response->Response,
				"status"   => "Completed",
				"job_id"   => $job_id
			] );
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		echo "Job ID $job_id is still running.";

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	if ( $run_in_background_silent ) {

		$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run/background", $data );
		
		if ( is_wp_error( $response ) ) {
			// If the request has failed, show the error message
			echo $response->get_error_message();
			wp_die();
		}

		$response = json_decode( $response["body"] );

		if ( $response && $response->token ) { 
			echo $response->token;
		}

		wp_die(); // this is required to terminate immediately and return a proper response
	}
		

	if ( $run_in_background ) {

		// Add command to dispatch server
		$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/tasks", $data );
		
		if ( is_wp_error( $response ) ) {
			// If the request has failed, show the error message
			echo $response->get_error_message();
			wp_die();
		}

		$response = json_decode( $response["body"] );

		// Response with token for task
		if ( $response && $response->token ) { 
			echo $response->token;
		}

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	// Add command to dispatch server
	$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		$response = "Something went wrong: $error_message";
	} else {
		$response = $response["body"];
	}
	
	echo $response;
	
	wp_die(); // this is required to terminate immediately and return a proper response
}

function captaincore_run_background_command( $command ) {
        
	// Disable https when debug enabled
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		add_filter( 'https_ssl_verify', '__return_false' );
	}

	$data = [
		'timeout' => 45,
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8', 
			'token'        => CAPTAINCORE_CLI_TOKEN 
		],
		'body'        => json_encode( [ "command" => $command ]), 
		'method'      => 'POST', 
		'data_format' => 'body'
	];

	// Add command to dispatch server
	$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run/background", $data );
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		return "Something went wrong: $error_message";
	}

	return $response["body"];
}

function captaincore_download_snapshot_email( $snapshot_id ) {

	// Fetch snapshot details
	$snapshot = ( new CaptainCore\Snapshots )->get( $snapshot_id );
	$domain   = ( new CaptainCore\Sites )->get( $snapshot->site_id )->name;

	// Generate download url to snapshot
	$home_url     = home_url();
	$file_name    = substr($snapshot->snapshot_name, 0, -4);
	$download_url = "{$home_url}/wp-json/captaincore/v1/site/{$snapshot->site_id}/snapshots/{$snapshot->snapshot_id}-{$snapshot->token}/{$file_name}";

    // Use Mailer
    \CaptainCore\Mailer::send_snapshot_ready( $snapshot->email, $domain, $snapshot_id, $download_url );
}

function captaincore_snapshot_download_link( $snapshot_id ) {
	$command = "snapshot fetch-link $snapshot_id";

	// Disable https when debug enabled
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		add_filter( 'https_ssl_verify', '__return_false' );
	}

	$data = [ 
		'timeout' => 45,
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8', 
			'token'        => CAPTAINCORE_CLI_TOKEN 
		],
		'body'        => json_encode( [ "command" => $command ] ), 
		'method'      => 'POST', 
		'data_format' => 'body' 
	];

	// Add command to dispatch server
	$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );

	return $response["body"];
}

// allow SVGs
function cc_mime_types( $mimes ) {
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}
add_filter( 'upload_mimes', 'cc_mime_types' );

// Custom payment link for speedy checkout
function captaincore_get_checkout_payment_url( $payment_url ) {

	// Current $payment_url is
	// https://captcore-sitename.com/checkout/order-pay/1918?pay_for_order=true&key=wc_order_576c79296c346&subscription_renewal=true
	// Replace with
	// https://captcore-sitename.com/checkout-express/1918/?pay_for_order=true&key=wc_order_576c79296c346&subscription_renewal=true
	$home_url        = esc_url( home_url( '/' ) );
	$new_payment_url = str_replace( $home_url . 'checkout/order-pay/', $home_url . 'checkout-express/', $payment_url );

	return $new_payment_url;
}

// Checks subscription for additional emails
add_filter( 'woocommerce_email_recipient_customer_completed_order', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_completed_renewal_order', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_renewal_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );

function woocommerce_email_customer_invoice_add_recipients( $recipient, $order ) {

	// Finds CaptainCore account
	$account_id = $order->get_meta( 'captaincore_account_id' );
	$account    = ( new CaptainCore\Accounts )->get( $account_id );
	if ( $account ) {
		$plan   = json_decode( $account->plan );
		if ( ! empty( $plan->additional_emails ) ) {
			$recipient .= ", {$plan->additional_emails}";
		}
	}

	return $recipient;
}
function my_acf_input_admin_footer() {
?>
<script type="text/javascript">
	acf.add_action('ready', function( $el ){

	// $el will be equivalent to $('body')

	// find a specific field
	staging_address = jQuery('#acf-field_57b7a2532cc5f');

	if(staging_address) {

		function sync_button() {
			// Copy production address to staging field
			jQuery('#acf-field_57b7a25d2cc60').val(jQuery('#acf-field_5619c94518f1c').val());

			// Copy production username to staging field
			if (jQuery('#acf-field_5619c94518f1c').val().includes(".kinsta.") ) {
				jQuery('#acf-field_57b7a2642cc61').val(jQuery('#acf-field_5619c97c18f1d').val() );
			} else {
				jQuery('#acf-field_57b7a2642cc61').val(jQuery('#acf-field_5619c97c18f1d').val() + "-staging");
			}

			// Copy production password to staging field (If Kinsta address)
			if (jQuery('#acf-field_5619c94518f1c').val().includes(".kinsta.") ) {
				jQuery('#acf-field_57b7a26b2cc62').val(jQuery('#acf-field_5619c98218f1e').val());
			}

			// Copy production protocol to staging field
			jQuery('#acf-field_57b7a2712cc63').val(jQuery('#acf-field_5619c98918f1f').val());

			// Copy production port to staging field
			jQuery('#acf-field_57b7a2772cc64').val(jQuery('#acf-field_5619c99d18f20').val());

			// Copy production database info to staging fields
			jQuery('#acf-field_5a90ba0c6c61a').val(jQuery('#acf-field_5a69f0a6e9686').val());
			jQuery('#acf-field_5a90ba1e6c61b').val(jQuery('#acf-field_5a69f0cce9687').val());

			// Copy production home directory to staging field
			jQuery('#acf-field_5845da68fc2c9').val(jQuery('#acf-field_58422bd538c32').val());
		}

		jQuery('.acf-field.acf-field-text.acf-field-57b7a25d2cc60').before('<div class="sync-button acf-field acf-field-text"><a href="#">Preload from Production</a></div>');
		jQuery('.sync-button a').click(function(e) {
			sync_button();
			return false;
		});
	}

	// do something to $field

});
</script>
<style>
.acf-postbox.seamless > .acf-fields > .acf-field.sync-button {
	position: absolute;
	right: 10px;
	padding-top: 10px;
	z-index: 9999;
}

</style>
<?php

}

add_action( 'acf/input/admin_footer', 'my_acf_input_admin_footer' );

add_filter(
	'query_vars', function( $vars ) {
		$vars[] = 'tag__in';
		$vars[] = 'tag__not_in';
		return $vars;
	}
);

// Custom filesize function
function captaincore_human_filesize( $size, $precision = 2 ) {
	$units = [ 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ];
	$step  = 1024;
	$i     = 0;
	while ( ( $size / $step ) > 0.9 ) {
		$size = $size / $step;
		$i++;
	}
	return round( $size, $precision ) . $units[ $i ];
}

// Adds ACF Option page
if( function_exists('acf_add_options_page') ) {
	acf_add_options_page();
}

function sort_by_name($a, $b) {
    return strcmp($a["name"], $b["name"]);
}

function captaincore_fetch_socket_address() {
	$captaincore_cli_address = ( defined( "CAPTAINCORE_CLI_ADDRESS" ) ? CAPTAINCORE_CLI_ADDRESS : "" );
	$socket_address          = str_replace( "https://", "wss://", $captaincore_cli_address );
	if ( defined( 'CAPTAINCORE_CLI_SOCKET_ADDRESS' ) ) {
		$socket_address = "wss://" . CAPTAINCORE_CLI_SOCKET_ADDRESS;
	}
	return $socket_address;
}

/* -------------------------------------------------------------------------
 *  CUSTOM LOGIN URL REDIRECT
 * ------------------------------------------------------------------------- */

add_filter( 'login_url', 'captaincore_override_login_url', 10, 3 );

function captaincore_override_login_url( $login_url, $redirect, $force_reauth ) {
    
    // Do not override URL if generating via WP-CLI.
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        return $login_url;
    }

    // 1. Get the CaptainCore mount path (e.g., "/account/")
    $configurations = \CaptainCore\Configurations::get();
    $path           = isset( $configurations->path ) ? $configurations->path : '/account/';
    
    // 2. Build the new login URL (e.g., https://anchor.localhost/account/login)
    // The Vue router defined in templates/core.php handles the '/login' endpoint
    $custom_login_url = home_url( untrailingslashit( $path ) . '/login' );

    // 3. Preserve redirect parameters if they exist
    if ( ! empty( $redirect ) ) {
        $custom_login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $custom_login_url );
    }

    return $custom_login_url;
}

/* -------------------------------------------------------------------------
 *  REDIRECTS FOR LOGGED IN USERS
 * ------------------------------------------------------------------------- */

// Intercept access to WooCommerce "My Account" page
add_action( 'template_redirect', 'captaincore_redirect_woo_to_app' );

function captaincore_redirect_woo_to_app() {
    // Check if on WooCommerce Account page and user is logged in
    if ( function_exists( 'is_account_page' ) && is_account_page() && is_user_logged_in() ) {
        
        $configurations = \CaptainCore\Configurations::get();
        $path           = isset( $configurations->path ) ? $configurations->path : '/account/';

        wp_redirect( home_url( $path ) );
        exit;
    }
}

/* -------------------------------------------------------------------------
 *  OVERRIDE DEFAULT WP PASSWORD RESET EMAIL
 * ------------------------------------------------------------------------- */

add_filter( 'retrieve_password_message', 'captaincore_custom_password_reset_email', 10, 4 );

function captaincore_custom_password_reset_email( $message, $key, $user_login, $user_data ) {
    
    // Trigger our custom HTML email
    CaptainCore\Mailer::send_password_reset( $user_data, $key );

    // Return false to prevent WordPress from sending the default plain-text email
    return false;
}

/* -------------------------------------------------------------------------
 *  CUSTOM LOGIN PAGE BRANDING
 * ------------------------------------------------------------------------- */

// 1. Change the Logo Image and Button Colors using CSS
add_action( 'login_enqueue_scripts', 'captaincore_login_stylesheet' );
function captaincore_login_stylesheet() {
    $config = \CaptainCore\Configurations::get();
    
    // Fetch settings or set defaults
    $logo_url      = ! empty( $config->logo ) ? $config->logo : '';
    $brand_color   = ! empty( $config->colors->primary ) ? $config->colors->primary : '#0073aa'; // Default WP Blue
    $accent_color  = ! empty( $config->colors->accent ) ? $config->colors->accent : '#f0f0f1';
    
    ?>
    <style type="text/css">
        /* Background Styling */
        body.login {
            background-color: #f7fafc; /* Optional: Change page background */
        }

        /* Custom Logo */
        #login h1 a, .login h1 a {
            background-image: url(<?php echo esc_url( $logo_url ); ?>);
            height: 80px;
            width: 320px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            padding-bottom: 30px;
        }

        /* Primary Buttons (Login, Save Password, Generate) */
        .wp-core-ui .button-primary {
            background-color: <?php echo esc_attr( $brand_color ); ?> !important;
            border-color: <?php echo esc_attr( $brand_color ); ?> !important;
            text-shadow: none !important;
            box-shadow: none !important;
            transition: opacity 0.3s ease;
        }
        .wp-core-ui .button-primary:hover,
        .wp-core-ui .button-primary:focus {
            opacity: 0.9;
        }

        /* Secondary Buttons */
        .wp-core-ui .button-secondary {
            color: <?php echo esc_attr( $brand_color ); ?> !important;
            border-color: <?php echo esc_attr( $brand_color ); ?> !important;
        }

        /* Links (Lost Password, Back to Site) */
        .login #nav a, .login #backtoblog a {
            color: #718096 !important;
            transition: color 0.3s ease;
        }
        .login #nav a:hover, .login #backtoblog a:hover {
            color: <?php echo esc_attr( $brand_color ); ?> !important;
        }

        /* Form Input Focus */
        input[type=text]:focus, 
        input[type=password]:focus, 
        input[type=email]:focus {
            border-color: <?php echo esc_attr( $brand_color ); ?> !important;
            box-shadow: 0 0 0 1px <?php echo esc_attr( $brand_color ); ?> !important;
        }
    </style>
    <?php
}

// 2. Change the Logo Link URL (Clicking logo goes to homepage instead of wordpress.org)
add_filter( 'login_headerurl', 'captaincore_login_logo_url' );
function captaincore_login_logo_url() {
    return home_url();
}

// 3. Change the Logo Title Text
add_filter( 'login_headertext', 'captaincore_login_logo_url_title' );
function captaincore_login_logo_url_title() {
    return get_bloginfo( 'name' );
}

function captaincore_head_content() {
    ob_start();
    do_action('wp_head');
    return ob_get_clean();
}

function captaincore_header_content_extracted() {
	$output = "<script type='text/javascript'>\n/* <![CDATA[ */\n";
	$head = captaincore_head_content();
	preg_match_all('/(var wpApiSettings.+)/', $head, $results );
	if ( isset( $results ) && $results[0] ) {
		foreach( $results[0] as $match ) {
			$output = $output . $match . "\n";
		}
	}
	$output = $output . "</script>\n";
	preg_match_all('/(<link rel="(icon|apple-touch-icon).+)/', $head, $results );
	if ( isset( $results ) && $results[0] ) {
		foreach( $results[0] as $match ) {
			$output = $output . $match . "\n";
		}
	}
	echo $output;
}

function captaincore_footer_content() {
    ob_start();
    do_action( 'wp_footer' );
    return ob_get_clean();
}

function captaincore_footer_content_extracted() {
	$output = [];
	$footer = captaincore_footer_content();
	preg_match_all('/<p id="user_switching_switch_on" .+><a href="(.+?)">(.+)<\/a><\/p>/', $footer, $results );
	if ( isset( $results ) && $results[1] ) {
		foreach( $results[1] as $match ) {
			$output[] = $match;
		}
	}
	if ( isset( $results ) && $results[2] ) {
		foreach( $results[2] as $match ) {
			$output[] = $match;
		}
	}
	if ( empty( $output ) ) {
		return json_encode( [
			"switch_to_link" => "",
			"switch_to_text" => ""
		] );
	}
	return json_encode( [
		"switch_to_link" => html_entity_decode( $output[0] ),
		"switch_to_text" => $output[1]
	] );
}