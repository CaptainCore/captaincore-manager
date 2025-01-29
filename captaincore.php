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
}

function deactivate_captaincore() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-captaincore-deactivator.php';
	Captaincore_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_captaincore' );
register_deactivation_hook( __FILE__, 'deactivate_captaincore' );
require plugin_dir_path( __FILE__ ) . 'includes/class-captaincore.php';

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
    CaptainCore\Accounts::process_renewals();
    CaptainCore\Scripts::run_scheduled();
}
add_action( 'captaincore_cron', 'captaincore_cron_run' );

// Hook to run Mailgun verify at a later time
add_action( 'schedule_mailgun_verify', '\CaptainCore\Providers\Mailgun::verify', 10, 3 );

function captaincore_failed_notify( $order_id, $old_status, $new_status ){
	echo "Woocommerce  $order_id, $old_status, $new_status ";
    if ( $new_status == 'failed' and $old_status != "failed" ){
		$order      = wc_get_order( $order_id );
		$account_id = $order->get_meta( "captaincore_account_id" );
		( new CaptainCore\Account( $account_id, true ) )->failed_notify();
    }
}
add_action( 'woocommerce_order_status_changed', 'captaincore_failed_notify', 10, 3);


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
	$current_site = ( new CaptainCore\Sites )->get( $site_id );
	if ( $current_site == "" && $site_id != "" && $command != "default-get" && $command != "configuration-get" ) {
		return new WP_Error( 'command_invalid', 'Invalid Command', [ 'status' => 404 ] );
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
		$to      = $email;
		$subject = "$business_name - Copy site ($site_source) to ($site_destination) completed";
		$body    = "Completed copying $site_source to $site_destination.<br /><br /><a href=\"http://$site_destination\">$site_destination</a>";
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $to, $subject, $body, $headers );

		echo 'copy-site email sent';

	}

	// Production deploy to staging
	if ( $command == 'production-to-staging' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name = get_the_title( $site_id );
		$db          = new CaptainCore\Site( $site_id );
		$site        = $db->get();
		$link        = $site->environments[1]["link"];

		// Send out completed email notice
		$to      = $email;
		$subject = "$business_name - Deploy to Staging ($domain_name)";
		$body    = 'Deploy to staging completed for ' . $domain_name . '.<br /><br /><a href="' . $link . '">' . $link . '</a>';
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $to, $subject, $body, $headers );

		echo 'production-to-staging email sent';

	}

	// Kinsta staging deploy to production
	if ( $command == 'staging-to-production' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name = get_the_title( $site_id );
		$db          = new CaptainCore\Site( $site_id );
		$site        = $db->get();
		$link        = $site->environments[0]["link"];

		// Send out completed email notice
		$to      = $email;
		$subject = "$business_name - Deploy to Production ($domain_name)";
		$body    = 'Deploy to production completed for ' . $domain_name . '.<br /><br /><a href="' . $link . '">' . $link . '</a>';
		$headers =  [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $to, $subject, $body, $headers );

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
		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"        => "Completed update-environment for $site_id",
			"environment"     => $post->data,
		];
		
		// Mark Site as updated
		( new CaptainCore\Sites )->update( [ "updated_at" => $post->data->updated_at ], [ "site_id" => $site_id ] );

	}

	// Sync site data
	if ( $command == 'sync-data' and ! empty( $post->data ) ) {
		
		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		$environment         = strtolower( $current_environment->environment );
		$upload_dir          = wp_upload_dir();
		$screenshot_check    = $upload_dir['basedir'] . "/screenshots/{$site_name}_{$site_id}/$environment/screenshot-800.png";
		if ( file_exists( $screenshot_check ) ) {
			$environment_update['screenshot'] = true;
		} else {
			$environment_update['screenshot'] = false;
		}
		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"        => "Completed sync-data for $site_id",
			"environment"     => $post->data,
		];

		$current_site = CaptainCore\Sites::get( $site_id );
		$details      = json_decode( $current_site->details );

		unset( $details->connection_errors );

		if ( $current_environment->environment == "Production" ) {
			$details->core     = $post->data->core;
			$details->subsites = $post->data->subsite_count;
		}

		if ( ! empty( $post->data->home_url ) ) {
			$home_url = str_replace( "http://www.", "", $post->data->home_url );
			$home_url = str_replace( "https://www.", "", $home_url );
			$home_url = str_replace( "http://", "", $home_url );
			$home_url = str_replace( "https://", "", $home_url );
			$home_url = str_replace( "www.", "", $home_url );
			$current_site->name = $home_url;
		}

		// Mark Site as updated
		CaptainCore\Sites::update( [ 
			"name"       => $current_site->name,
			"updated_at" => $post->data->updated_at,
			"details"    => json_encode( $details )
		], [ "site_id" => $site_id ] );

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

		// Update pointer to new thumbnails for site
		if ( $environment == "production" ) {
			$site                     = ( new CaptainCore\Sites )->get( $site_id );
			$details                  = json_decode( $site->details );
			$details->screenshot_base = "{$data->created_at}_${git_commit_short}";
			( new CaptainCore\Sites )->update( [ "screenshot" => true, "details" => json_encode( $details ) ], [ "site_id" => $site_id ] );
		}
		// Update pointer to new thumbnails for environment
		$environment              = ( new CaptainCore\Environments )->get( $environment_id );
		$details                  = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
		$details->screenshot_base = "{$data->created_at}_${git_commit_short}";
		( new CaptainCore\Environments )->update( [ "screenshot" => true, "details" => json_encode( $details ) ], [ "environment_id" => $environment_id ] );

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

function captaincore_accounts_func( $request ) {
	return ( new CaptainCore\Accounts )->list();
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
			$title = ( new CaptainCore\Configurations )->get()->name;
			$user = (object) ( new CaptainCore\User )->fetch();
			if ( $value == true ) {
				$subject = "$title - Site Removal Request";
				$message = "Site {$site->name} #{$site_id} has been requested to be removed.<br /><br />Requested By: {$user->name} #{$user->user_id}";
			}
			if ( $value != true ) {
				$subject = "$title - Cancel Site Removal Request";
				$message = "Site {$site->name} #{$site_id} has been requested to keep. Disregard previous removal request.<br /><br />Requested By: {$user->name} #{$user->user_id}";
			}
			wp_mail(
				get_option( "admin_email" ),
				$subject,
				$message,
				[
					'Content-Type: text/html; charset=UTF-8',
					"Reply-To: {$user->name} <{$user->email}>"
				],
			);
		}
	}
	$query = CaptainCore\Sites::update([
			"details" => json_encode( $details )
		], [
			"site_id" => $site_id
		]);
	return;
}

function captaincore_site_func( $request ) {

	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$site = new CaptainCore\Site( $site_id );
	return $site->get();

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

function captaincore_dns_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	$remote_id = ( new CaptainCore\Domains )->get( $domain_id )->remote_id;

	$domain  = CaptainCore\Remote\Constellix::get( "domains/$remote_id" );
	$records = CaptainCore\Remote\Constellix::get( "domains/$remote_id/records?perPage=100" );
	$steps   = ceil( $records->meta->pagination->total / 100 );
	for ($i = 1; $i < $steps; $i++) {
		$page = $i + 1;
		$additional_records = CaptainCore\Remote\Constellix::get( "domains/$remote_id/records?page=$page&perPage=100" );
		$records->data = array_merge($records->data, $additional_records->data);
	}

	if ( ! $records->errors ) {
		array_multisort( array_column( $records->data, 'type' ), SORT_ASC, array_column( $records->data, 'name' ), SORT_ASC, $records->data );
	}

	return [ 
		"records"     => $records->data, 
		"nameservers" => $domain->data->nameservers 
	];
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

function captaincore_recipes_func( $request ) {
	return ( new CaptainCore\Recipes() )->list();
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
	$site        = new CaptainCore\Site( $site_id );
	return $site->fetch_phpmyadmin();
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
	$response = $sites;
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
	$file    = $request['file'];
	$limit   = $request['limit'];

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

function captaincore_site_mailgun_func( $request ) {
	$site_id = $request['id'];
	$name    = $request['name'];
	// pull domain from site
	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	$site    = ( new CaptainCore\Sites )->get( $site_id );
	CaptainCore\Providers\Mailgun::setup( $site->name );
	CaptainCore\Run::CLI( "ssh $site->site --script=deploy-mailgun -- --key=\"" . MAILGUN_API_KEY . "\" --domain=$site->name --name=\"$name\"" );
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

add_action( 'rest_api_init', 'captaincore_register_rest_endpoints' );

function captaincore_register_rest_endpoints() {

	// Custom endpoint for CaptainCore API
	register_rest_route(
		'captaincore/v1', '/api', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_api_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore API
	register_rest_route(
		'captaincore/v1', '/missive', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_missive_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/login', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_login_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_quicksaves_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/update-logs', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_update_logs_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/update-logs/(?P<hash_before>[a-zA-Z0-9-]+)_(?P<hash_after>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_update_logs_get_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/search', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_quicksaves_search_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_quicksaves_get_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)/changed', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_quicksaves_changed_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)/filediff', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_quicksaves_filediff_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/(?P<hash>[a-zA-Z0-9-]+)/rollback', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_quicksaves_rollback_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/scripts/schedule', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_schedule_script_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/scripts/(?P<id>[\d]+)', [
			'methods'       => 'DELETE',
			'callback'      => 'captaincore_delete_script_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/scripts/(?P<id>[\d]+)', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_update_script_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/analytics', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_analytics_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<site-id>/<environment>/backups
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/backups', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_backups_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<site-id>/<environment>/backups/<backup-id>
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/backups/(?P<backup_id>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_backups_get_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/logs', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_logs_list_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/logs/fetch', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_logs_fetch_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/environments', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_environments_get_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/grant-access', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_grant_access_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/mailgun/setup', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_mailgun_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/sync/data', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_sync_data_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures/new', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_captures_new_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/monitor', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_environment_monitor_update_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_captures_update_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/backup', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_backup_update_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_update_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_captures_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<id>/snapshots
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_snapshots_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots/(?P<snapshot_id>[\d]+)-(?P<token>[a-zA-Z0-9-]+)/(?P<snapshot_name>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_snapshot_download_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/sites/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_sites_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/phpmyadmin', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_phpmyadmin_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/magiclogin', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_magiclogin_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/magiclogin/(?P<user_id>[\d]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_magiclogin_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_new_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<id>[a-zA-Z0-9-]+)', [
			'methods'       => 'PUT',
			'callback'      => 'captaincore_provider_update_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<id>[a-zA-Z0-9-]+)', [
			'methods'       => 'DELETE',
			'callback'      => 'captaincore_provider_delete_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/verify', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_verify_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/themes', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_themes_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/plugins', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_plugins_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/theme/(?P<id>[a-zA-Z0-9-]+)/download', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_theme_download_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/plugin/(?P<id>[a-zA-Z0-9-]+)/download', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_plugin_download_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/connect', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_connect_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/new-site', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_new_site_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/deploy-to-staging', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_deploy_to_staging_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/deploy-to-production', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_deploy_to_production_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions/check', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_actions_check_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions/(?P<id>[\d]+)/run', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_actions_run_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_actions_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/', [
			'methods'       => 'GET',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->fetch();
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_activate', [
			'methods'       => 'GET',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_activate();
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_validate', [
			'methods'       => 'POST',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_activate_verify( $request['token'] );
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_deactivate', [
			'methods'       => 'GET',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_deactivate();
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,|_%]+)/versions/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_filter_versions_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,|_%]+)/statuses/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_filter_statuses_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,+_%)]+)/sites/versions=(?:(?P<versions>[a-zA-Z0-9-,+\.|]+))?/statuses=(?:(?P<statuses>[a-zA-Z0-9-,+\.|]+))?', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_filter_sites_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/dns/(?P<id>[\d]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_dns_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/contacts', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_domain_update_contacts_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/nameservers', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_domain_update_nameservers_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/lock_(?P<status>[a-zA-Z0-9-,|_%]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_lock_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/privacy_(?P<status>[a-zA-Z0-9-,|_%]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_privacy_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/auth_code', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_auth_code_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/recipes/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_recipes_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/running/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_running_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for recipes
	register_rest_route(
		'captaincore/v1', '/processes/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_processes_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for domains
	register_rest_route(
		'captaincore/v1', '/domains/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domains_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/domains/(?P<id>[\d]+)/zone', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_zone_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/domains/import', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_domain_zone_import_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for domains
	register_rest_route(
		'captaincore/v1', '/users/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_users_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore accounts
	register_rest_route(
		'captaincore/v1', '/accounts/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_accounts_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore configurations
	register_rest_route(
		'captaincore/v1', '/configurations/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_configurations_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/configurations/', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_configurations_update_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore billing
	register_rest_route(
		'captaincore/v1', '/billing/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_billing_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/subscriptions/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_subscriptions_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/upcoming_subscriptions/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_upcoming_subscriptions_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for keys
	register_rest_route(
		'captaincore/v1', '/keys/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_keys_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for defaults
	register_rest_route(
		'captaincore/v1', '/defaults/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_defaults_func',
			'show_in_index' => false
		]
	);

};

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

		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
	
		// Redefining user_login ensures we return the right case in the email.
		$key        = get_password_reset_key( $user_data );
	
		if ( is_wp_error( $key ) ) {
			return $key;
		}
	
		if ( is_multisite() ) {
			$site_name = get_network()->site_name;
		} else {
			/*
			 * The blogname option is escaped with esc_html on the way into the database
			 * in sanitize_option we want to reverse this for the plain text arena of emails.
			 */
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}
	
		$message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
		/* translators: %s: site name */
		$message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
		/* translators: %s: user login */
		$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . ">\r\n";
	
		/* translators: Password reset notification email subject. %s: Site title */
		$title = sprintf( __( '[%s] Password Reset' ), $site_name );
	
		/**
		 * Filters the subject of the password reset email.
		 *
		 * @since 2.8.0
		 * @since 4.4.0 Added the `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $title      Default email title.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );
	
		/**
		 * Filters the message body of the password reset mail.
		 *
		 * If the filtered message is empty, the password reset email will not be sent.
		 *
		 * @since 2.8.0
		 * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $message    Default mail message.
		 * @param string  $key        The activation key.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );
	
		if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
			wp_die( __( 'The email could not be sent.' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.' ) );
		}

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

function checkApiAuth( $result ) {

	if ( ! empty( $result ) ) {
			return $result;
	}

	global $wp;

	// Strips first part of endpoint
	$endpoint_all = str_replace( 'wp-json/wp/v2/', '', $wp->request );
	if ( strpos( $wp->request, 'wp-json/captaincore/v1' ) !== false ) {
		return $result;
	}

	// Breaks apart endpoint into array
	$endpoint_all = explode( '/', $endpoint_all );

	// Grabs only the first part of the endpoint
	$endpoint = $endpoint_all[0];

	// User not logged in so do custom token auth
	if ( ! is_user_logged_in() ) {

		if ( $endpoint == 'posts' ) {
			return $result;
		}

		// User not logged in and no valid bypass token found
		return new WP_Error( 'rest_not_logged_in', 'You are not currently logged in.', array( 'status' => 401 ) );

	}
	return $result;

}
add_filter( 'rest_authentication_errors', 'checkApiAuth' );

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
				wp_new_user_notification( $result, null, 'user' );
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

		$payment_gateways      = WC()->payment_gateways->payment_gateways();
		$payment_method        = $order->get_payment_method();
		$payment_method_string = sprintf(
			__( 'Payment via %s', 'woocommerce' ),
			esc_html( isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_title() : "Check" )
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

		$payment_gateways      = WC()->payment_gateways->payment_gateways();
		$payment_method        = $order->get_payment_method();
		$payment_method_string = sprintf(
			__( 'Payment via %s', 'woocommerce' ),
			esc_html( isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_title() : "Check" )
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
				
				// Build email
				$to      = get_option( 'admin_email' );
				$subject = "Request cancel plan '{$current_subscription->name}'";
				$body    = "Request cancel plan '{$current_subscription->name}' #{$current_subscription->account_id} from {$current_user['name']}, <a href='mailto:{$current_user['email']}'>{$current_user['email']}</a>.";
				$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

				// Send email
				wp_mail( $to, $subject, $body, $headers );
			}
		}

	}

	if ( $cmd == 'requestPlanChanges' ) {
		$current_user = $user->fetch();
		$subscription = (object) $_POST['value'];
		
		// Build email
		$to      = get_option( 'admin_email' );
		$subject = "Request plan change from {$current_user['name']} <{$current_user['email']}>";
		$body    = "Change subscription '{$subscription->name}' to {$subscription->plan['name']} and {$subscription->plan['interval']} interval.";
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Send email
		wp_mail( $to, $subject, $body, $headers );
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

		// If results still exists then give an error
		if ( $name == "" ) {
			$errors[] = "Domain can't be empty.";
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

		// Execute remote code
		$response = ( new CaptainCore\Domain( $domain_id ) )->fetch_remote_id();
		if ( is_array( $response ) ) {
			foreach ( $response["errors"] as $error ) {
				$errors[] = $error;
			}
			echo json_encode( [ "errors" => $errors ] );
			wp_die();
		}

		echo json_encode( [ "name" => $name, "domain_id" => $domain_id, "remote_id" => $response ] );

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
			$payment_tokens = WC_Payment_Tokens::get_customer_tokens( $user->user_id() );
			foreach ( $payment_tokens as $payment_token ) { 
				if( $payment_token->get_token() == $_POST['source_id'] ) {
					$user->pay_invoice( $_POST['value'], $payment_token->get_id() );
					$user->set_as_primary( $payment_token->get_id() );
				}
			}
			wp_die();
		}
		// Pay with existing credit card
		$user->pay_invoice( $_POST['value'], $_POST['payment_id'] );
		$user->set_as_primary( $_POST['payment_id'] );
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
		'newSite',
		'createSiteAccount',
		'updateSite', 
		'deleteSite',
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
	$environment    = $_POST['environment'];
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
			'updates_exclude_themes'  => implode(",", $value["updates_exclude_themes"]),
			'updates_exclude_plugins' => implode(",", $value["updates_exclude_plugins"]),
			'updated_at'              => date("Y-m-d H:i:s") 
		];
		$environment_id = ( new CaptainCore\Site( $post_id ) )->fetch_environment_id( $environment );
		( new CaptainCore\Environments )->update( $environment_update, [ "environment_id" => $environment_id ] );
		$command           = "site sync $post_id";
		$remote_command    = true;
		$run_in_background = true;
	}

	if ( $cmd == 'newSite' ) {
		// Create new site
		$site     = new CaptainCore\Site();
		$response = $site->create( $value );
		echo json_encode( $response );
	}

	if ( $cmd == 'updateSite' ) {
		// Updates site
		$site     = new CaptainCore\Site( $value["site_id"] );
		$response = $site->update( $value );
		echo json_encode( $response );
	}

	if ( $cmd == 'deleteSite' ) {
		// Delete site on CaptainCore CLI
		captaincore_run_background_command( "site delete $site" );

		// Delete site locally
		$site = new CaptainCore\Site( $post_id );
		$site->mark_inactive();
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
		$shared_with = $site->shared_with();
		$site        = $site->fetch();
		echo json_encode( [
			"site"        => $site,
			"account"     => $account,
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
		$command           = "deactivate $site --name=\"$name\" --link=\"$link\"";
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
		$run_in_background = true;
		$value             = (object) $value;
		$current_user      = wp_get_current_user();
		$email             = $current_user->user_email;
		$payload           = [
			"files"       => json_decode( stripslashes_deep( $value->files ) ),
			"directories" => json_decode ( stripslashes_deep( $value->directories ) ),
		];
		$payload           = base64_encode( json_encode( $payload ) );
		captaincore_run_background_command( "backup download $site $value->backup_id --email=$email --payload='$payload'" );
		echo "Generating downloadable zip.";
		wp_die();
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

	// Build email
	$company = get_field( 'business_name', 'option' );
	$to      = $snapshot->email;
	$subject = "$company - Snapshot #$snapshot_id";
	$body    = "Snapshot #{$snapshot_id} for {$domain}. Expires after 1 week.<br /><br /><a href=\"{$download_url}\">Download Snapshot</a>";
	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

	// Send email
	wp_mail( $to, $subject, $body, $headers );

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

// Load custom WooCommerce templates from plugin's woocommerce directory
function captaincore_plugin_path() {
	// gets the absolute path to this plugin directory
	return untrailingslashit( plugin_dir_path( __FILE__ ) );
}
add_filter( 'woocommerce_locate_template', 'captaincore_woocommerce_locate_template', 10, 3 );

function captaincore_woocommerce_locate_template( $template, $template_name, $template_path ) {
	global $woocommerce;

	$_template = $template;

	if ( ! $template_path ) {
		$template_path = $woocommerce->template_url;
	}

	$plugin_path = captaincore_plugin_path() . '/woocommerce/';

	// Look within passed path within the theme - this is priority
	$template = locate_template(

		array(
			$template_path . $template_name,
			$template_name,
		)
	);

	// Modification: Get the template from this plugin, if it exists
	if ( ! $template && file_exists( $plugin_path . $template_name ) ) {
		$template = $plugin_path . $template_name;
	}

	// Use default template
	if ( ! $template ) {
		$template = $_template;
	}

	// Return what we found
	return $template;
}

// Hook in custom page templates
class PageTemplater {

	/**
	 * A reference to an instance of this class.
	 */
	private static $instance;

	/**
	 * The array of templates that this plugin tracks.
	 */
	protected $templates;

	/**
	 * Returns an instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new PageTemplater();
		}

		return self::$instance;

	}

	/**
	 * Initializes the plugin by setting filters and administration functions.
	 */
	private function __construct() {

		$this->templates = [];

		// Add a filter to the attributes metabox to inject template into the cache.
		if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {

			// 4.6 and older
			add_filter(
				'page_attributes_dropdown_pages_args',
				array( $this, 'register_project_templates' )
			);

		} else {

			// Add a filter to the wp 4.7 version attributes metabox
			add_filter(
				'theme_page_templates', [ $this, 'add_new_template' ]
			);

		}

		// Add a filter to the save post to inject out template into the page cache
		add_filter( 'wp_insert_post_data', array( $this, 'register_project_templates' ) );

		// Add a filter to the template include to determine if the page has our
		// template assigned and return it's path
		add_filter( 'template_include', array( $this, 'view_project_template' ) );

		// Add your templates to this array.
		$this->templates = [ 'templates/page-checkout-express.php' => 'Checkout Express' ];

	}

	/**
	 * Adds our template to the page dropdown for v4.7+
	 */
	public function add_new_template( $posts_templates ) {
		$posts_templates = array_merge( $posts_templates, $this->templates );
		return $posts_templates;
	}

	/**
	 * Adds our template to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doens't really exist.
	 */
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list.
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = [];
		}

		// New cache, therefore remove the old one
		wp_cache_delete( $cache_key, 'themes' );

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	}

	/**
	 * Checks if the template is assigned to the page
	 */
	public function view_project_template( $template ) {

		// Get global post
		global $post;

		// Return template if post is empty
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined
		if ( ! isset(
			$this->templates[ get_post_meta(
				$post->ID, '_wp_page_template', true
			) ]
		) ) {
			return $template;
		}

		$file = plugin_dir_path( __FILE__ ) . get_post_meta(
			$post->ID, '_wp_page_template', true
		);

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} else {
			echo $file;
		}

		// Return template
		return $template;

	}

}
add_action( 'plugins_loaded', [ 'PageTemplater', 'get_instance' ], 10 );

/* Filter the single_template with our custom function*/

// Hooks in custom post template from plugin
add_filter( 'single_template', 'captaincore_custom_template' );

function captaincore_custom_template( $single ) {

	global $wp_query, $post;

	/* Checks for single template by post type */
	if ( $post->post_type == 'captcore_process' ) {
		if ( file_exists( plugin_dir_path( __FILE__ ) . '/templates/single-captcore_process.php' ) ) {
			return plugin_dir_path( __FILE__ ) . '/templates/single-captcore_process.php';
		}
	}

	return $single;

}

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

// Load custom template for web requests going to "/account" or "/account/<..>/..."
add_filter( 'template_include', 'load_captaincore_template' );
function load_captaincore_template( $original_template ) {
  global $wp;
  $configurations    = CaptainCore\Configurations::fetch();
  $request           = explode( '/', $wp->request );
  $current_page      = current( $request );
  $captaincore_pages = ['accounts', 'billing', 'cookbook', 'configurations', 'connect', 'defaults', 'domains', 'handbook', 'health', 'keys', 'login', 'profile', 'sites', 'subscriptions', 'users'];
  if ( class_exists( 'WooCommerce' ) && is_account_page() && end( $request ) == 'my-account' ) {
	wp_redirect( $configurations->path );
  }
  if ( $configurations->path == "/" && in_array( $current_page, $captaincore_pages ) ) {
	header('X-Frame-Options: SAMEORIGIN'); 
    return plugin_dir_path( __FILE__ ) . 'templates/core.php';
  }

  $page = trim( $configurations->path, "/" );
  if ( ( is_page( $page ) || current( $request ) == $page ) && count( $request ) == 1 ) {
	header('X-Frame-Options: SAMEORIGIN'); 
    return plugin_dir_path( __FILE__ ) . 'templates/core.php';
  }
  if ( ( is_page( $page ) || current( $request ) == $page ) && count( $request ) > 1 && in_array( $request[1], $captaincore_pages ) ) {
	header('X-Frame-Options: SAMEORIGIN'); 
	return plugin_dir_path( __FILE__ ) . 'templates/core.php';
}
  return $original_template;
}

// Makes sure that any request going to CaptainCore pages will respond with a proper 200 http code
add_action('init', 'captaincore_rewrite');
function captaincore_rewrite() {
    global $wp_rewrite;
	add_rewrite_rule( '^checkout-express/([^/]*)/?', 'index.php?pagename=checkout-express&callback=$matches[1]', 'top' );
	add_rewrite_tag( '%site%', '([^&]+)' );
	add_rewrite_tag( '%sitetoken%', '([^&]+)' );
	add_rewrite_tag( '%callback%', '([^&]+)' );

	$configurations    = CaptainCore\Configurations::fetch();
	$captaincore_pages = ['accounts', 'billing', 'cookbook', 'configurations', 'connect', 'defaults', 'domains', 'handbook', 'health', 'keys', 'login', 'profile', 'sites', 'subscriptions', 'users'];
	if ( $configurations->path == "/" ) {
		foreach( $captaincore_pages as $captaincore_page ) {
			add_rewrite_rule( "^$captaincore_page/?",'index.php','top');
			add_rewrite_endpoint( $captaincore_page, EP_PERMALINK | EP_PAGES );
		}
	} else {
		$custom_path = trim( $configurations->path, '"' );
		add_rewrite_rule( "^$custom_path/?",'index.php','top');
		add_rewrite_endpoint( $custom_path, EP_PERMALINK | EP_PAGES );
	}
	$wp_rewrite->flush_rules();
}

// Disable 404 redirects when unknown request goes to "/account/<..>/..." which allows a custom template to load. See https://wordpress.stackexchange.com/questions/3326/301-redirect-instead-of-404-when-url-is-a-prefix-of-a-post-or-page-name
add_filter('redirect_canonical', 'disable_404_redirection_for_captaincore');
function disable_404_redirection_for_captaincore($redirect_url) {
	global $wp;
	$configurations    = CaptainCore\Configurations::fetch();
	$captaincore_pages = ['accounts', 'billing', 'cookbook', 'configurations', 'connect', 'defaults', 'domains', 'handbook', 'health', 'keys', 'login', 'profile', 'sites', 'subscriptions', 'users'];
	if ( $configurations->path == "/" ) {
		foreach( $captaincore_pages as $captaincore_page ) {
			if ( strpos( $wp->request, "{$current_page}/" ) !== false ) {
				return false;
			}
		}
	}
	if ( strpos( $wp->request, "checkout-express/" ) !== false ) {
		return false;
	}
	$custom_path = trim($configurations->path, '/'). "/";
	if ( strpos( $wp->request, $custom_path ) !== false ) {
		foreach( $captaincore_pages as $captaincore_page ) {
			if ( strpos( $wp->request, "{$custom_path}/{$current_page}/" ) !== false ) {
				return false;
			}
		}
	}
    return $redirect_url;
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
	return json_encode( [
		"switch_to_link" => html_entity_decode( $output[0] ),
		"switch_to_text" => $output[1]
	] );
}