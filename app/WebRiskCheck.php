<?php
/**
 * Google Web Risk Check
 *
 * Checks all production site URLs against Google's Web Risk API
 * and sends email notifications if threats are detected.
 *
 * @author   Austin Ginder
 */

namespace CaptainCore;

use CaptainCore\Remote\GoogleWebRisk;

class WebRiskCheck {

	/**
	 * Run the web risk check via WP-CLI.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be checked without calling the API.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore web-risk-check
	 *     wp captaincore web-risk-check --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public static function run( $args = [], $assoc_args = [] ) {
		$dry_run = isset( $assoc_args['dry-run'] );

		// Check if API key is configured (skip for dry-run)
		if ( ! $dry_run && ! defined( 'GOOGLE_WEB_RISK_API_KEY' ) ) {
			\WP_CLI::error( 'GOOGLE_WEB_RISK_API_KEY constant is not defined.' );
			return;
		}

		$sites = self::get_production_urls();

		if ( empty( $sites ) ) {
			\WP_CLI::log( 'No production URLs to check.' );
			return;
		}

		\WP_CLI::log( sprintf( 'Found %d production URLs to check.', count( $sites ) ) );

		if ( $dry_run ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( '--- Dry Run Mode ---' );
			\WP_CLI::log( 'The following URLs would be checked:' );
			\WP_CLI::log( '' );

			foreach ( $sites as $site ) {
				\WP_CLI::log( sprintf( '  [%d] %s - %s', $site->site_id, $site->name, $site->home_url ) );
			}

			\WP_CLI::log( '' );
			\WP_CLI::success( sprintf( 'Dry run complete. %d sites would be checked.', count( $sites ) ) );
			return;
		}

		$threats_found = [];
		$errors        = [];
		$progress      = \WP_CLI\Utils\make_progress_bar( 'Checking URLs', count( $sites ) );

		foreach ( $sites as $site ) {
			$result = GoogleWebRisk::get( $site->home_url );

			if ( is_wp_error( $result ) ) {
				$errors[] = [
					'site_id'  => $site->site_id,
					'name'     => $site->name,
					'home_url' => $site->home_url,
					'error'    => $result->get_error_message(),
				];
				$progress->tick();
				continue;
			}

			if ( isset( $result->threat ) && ! empty( $result->threat ) ) {
				$threats_found[] = [
					'site_id'      => $site->site_id,
					'site_name'    => $site->name,
					'home_url'     => $site->home_url,
					'threat_types' => $result->threat->threatTypes ?? [],
					'expire_time'  => $result->threat->expireTime ?? null,
				];
			}

			$progress->tick();
		}

		$progress->finish();

		// Report any API errors
		if ( ! empty( $errors ) ) {
			\WP_CLI::warning( sprintf( '%d site(s) had API errors:', count( $errors ) ) );
			foreach ( $errors as $error ) {
				\WP_CLI::log( sprintf( '  - %s: %s', $error['name'], $error['error'] ) );
			}
		}

		// No threats found
		if ( empty( $threats_found ) ) {
			\WP_CLI::success( sprintf( 'All %d sites are clean. No threats detected.', count( $sites ) ) );
			return;
		}

		// Threats found - display and send email
		\WP_CLI::warning( sprintf( 'Found %d site(s) with threats!', count( $threats_found ) ) );

		foreach ( $threats_found as $threat ) {
			$types = implode( ', ', $threat['threat_types'] );
			\WP_CLI::log( sprintf( '  - %s (%s): %s', $threat['site_name'], $threat['home_url'], $types ) );
		}

		// Send email notification
		self::send_summary_email( $threats_found, count( $sites ) );

		\WP_CLI::success( 'Summary email sent to administrator.' );
	}

	/**
	 * Get all production URLs from environments table.
	 *
	 * @return array Array of site objects with site_id, name, and home_url.
	 */
	private static function get_production_urls() {
		global $wpdb;

		$table_environments = $wpdb->prefix . 'captaincore_environments';
		$table_sites        = $wpdb->prefix . 'captaincore_sites';

		return $wpdb->get_results( "
			SELECT e.site_id, e.home_url, s.name 
			FROM {$table_environments} e
			JOIN {$table_sites} s ON e.site_id = s.site_id
			WHERE e.environment = 'Production' 
			AND e.home_url IS NOT NULL 
			AND e.home_url != ''
			AND s.status = 'active'
			ORDER BY s.name ASC
		" );
	}

	/**
	 * Send summary email using Mailer.
	 *
	 * @param array $threats       Array of threat data.
	 * @param int   $total_checked Total number of sites checked.
	 */
	private static function send_summary_email( $threats, $total_checked ) {
		$admin_email = get_option( 'admin_email' );
		$subject     = 'Google Web Risk Summary';
		$headline    = 'Web Risk Check Results';
		$subheadline = sprintf(
			'%d threat(s) detected out of %d sites checked',
			count( $threats ),
			$total_checked
		);

		// Build HTML table of threats
		$table_rows = '';
		foreach ( $threats as $threat ) {
			$threat_types_str = implode( ', ', $threat['threat_types'] );
			$threat_labels    = self::format_threat_types( $threat['threat_types'] );

			$table_rows .= sprintf(
				'<tr>
					<td style="padding: 12px; border-bottom: 1px solid #edf2f7; vertical-align: top;">
						<strong style="color: #2d3748;">%s</strong><br>
						<a href="%s" style="color: #718096; font-size: 13px; text-decoration: none;">%s</a>
					</td>
					<td style="padding: 12px; border-bottom: 1px solid #edf2f7; vertical-align: top;">%s</td>
				</tr>',
				esc_html( $threat['site_name'] ),
				esc_url( $threat['home_url'] ),
				esc_html( $threat['home_url'] ),
				$threat_labels
			);
		}

		$content = sprintf(
			'<p style="margin-bottom: 25px; line-height: 1.6; color: #4a5568;">
				The daily Web Risk check has completed. The following site(s) were flagged by Google\'s Web Risk API:
			</p>
			<table style="width: 100%%; border-collapse: collapse; margin-bottom: 25px;">
				<thead>
					<tr style="background-color: #f7fafc;">
						<th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">Site</th>
						<th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0; color: #4a5568; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em;">Threat Types</th>
					</tr>
				</thead>
				<tbody>%s</tbody>
			</table>
			<div style="background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-top: 20px;">
				<h4 style="margin: 0 0 10px; font-size: 11px; text-transform: uppercase; color: #a0aec0; letter-spacing: 0.05em;">Threat Type Reference</h4>
				<ul style="margin: 0; padding-left: 20px; color: #4a5568; font-size: 13px; line-height: 1.8;">
					<li><strong>MALWARE</strong> - Site may be distributing malicious software</li>
					<li><strong>SOCIAL_ENGINEERING</strong> - Site may be involved in phishing or deceptive practices</li>
					<li><strong>UNWANTED_SOFTWARE</strong> - Site may be distributing unwanted software</li>
				</ul>
			</div>',
			$table_rows
		);

		Mailer::send_process_completed(
			$admin_email,
			$subject,
			$headline,
			$subheadline,
			$content,
			''
		);
	}

	/**
	 * Format threat types with colored badges.
	 *
	 * @param array $threat_types Array of threat type strings.
	 * @return string HTML badges.
	 */
	private static function format_threat_types( $threat_types ) {
		$badges = [];

		$colors = [
			'MALWARE'                            => '#e53e3e',
			'SOCIAL_ENGINEERING'                 => '#dd6b20',
			'UNWANTED_SOFTWARE'                  => '#d69e2e',
			'SOCIAL_ENGINEERING_EXTENDED_COVERAGE' => '#dd6b20',
		];

		foreach ( $threat_types as $type ) {
			$color    = $colors[ $type ] ?? '#718096';
			$label    = str_replace( '_', ' ', $type );
			$badges[] = sprintf(
				'<span style="display: inline-block; background-color: %s; color: #ffffff; font-size: 11px; font-weight: 600; padding: 4px 8px; border-radius: 4px; margin: 2px 0;">%s</span>',
				$color,
				esc_html( $label )
			);
		}

		return implode( ' ', $badges );
	}
}
