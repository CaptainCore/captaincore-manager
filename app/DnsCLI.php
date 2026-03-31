<?php

namespace CaptainCore;

class DnsCLI {

	/**
	 * List DNS records for a domain.
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name (e.g., knutsenoutdoor.com)
	 *
	 * [--type=<type>]
	 * : Filter by record type (A, AAAA, CNAME, MX, TXT, SRV, SPF, ANAME, HTTP)
	 *
	 * [--name=<name>]
	 * : Filter by record name (substring match)
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore dns list example.com
	 *     wp captaincore dns list example.com --type=TXT
	 *     wp captaincore dns list example.com --name=dmarc
	 *     wp captaincore dns list example.com --format=json
	 *
	 * @subcommand list
	 * @when after_wp_load
	 */
	public function list_( $args, $assoc_args ) {
		$domain_name = $args[0];
		$format      = $assoc_args['format'] ?? 'table';
		$type_filter = isset( $assoc_args['type'] ) ? strtoupper( $assoc_args['type'] ) : null;
		$name_filter = $assoc_args['name'] ?? null;

		$domain = $this->resolve_domain( $domain_name );
		if ( ! $domain ) {
			return;
		}

		$records = $this->fetch_records( $domain->remote_id );
		if ( $records === false ) {
			return;
		}

		$rows = [];
		foreach ( $records as $record ) {
			if ( $type_filter && strtoupper( $record->type ) !== $type_filter ) {
				continue;
			}
			if ( $name_filter && stripos( $record->name, $name_filter ) === false ) {
				continue;
			}

			$values = $record->value ?? [];
			if ( ! is_array( $values ) ) {
				$values = [ $values ];
			}

			foreach ( $values as $v ) {
				$display_value = '';
				if ( is_object( $v ) ) {
					if ( isset( $v->value ) ) {
						$display_value = $v->value;
					} elseif ( isset( $v->server ) ) {
						$display_value = $v->server;
					} elseif ( isset( $v->host ) ) {
						$display_value = $v->host;
					} elseif ( isset( $v->url ) ) {
						$display_value = $v->url;
					}
				} else {
					$display_value = (string) $v;
				}

				$row = [
					'ID'    => $record->id,
					'Type'  => strtoupper( $record->type ),
					'Name'  => $record->name ?: '@',
					'Value' => $format === 'table' ? mb_substr( $display_value, 0, 80 ) : $display_value,
					'TTL'   => $record->ttl,
				];

				if ( strtoupper( $record->type ) === 'MX' && is_object( $v ) && isset( $v->priority ) ) {
					$row['Value'] = ( $format === 'table' ? mb_substr( $v->server, 0, 70 ) : $v->server );
				}

				$rows[] = $row;
			}
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No DNS records found.' );
			return;
		}

		$columns = [ 'ID', 'Type', 'Name', 'Value', 'TTL' ];

		\WP_CLI\Utils\format_items( $format, $rows, $columns );
	}

	/**
	 * Add a DNS record to a domain.
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name (e.g., knutsenoutdoor.com)
	 *
	 * --type=<type>
	 * : Record type (A, AAAA, CNAME, MX, TXT, SRV, SPF, ANAME, HTTP)
	 *
	 * --name=<name>
	 * : Record name (subdomain). Use @ or empty string for root.
	 *
	 * --value=<value>
	 * : Record value
	 *
	 * [--ttl=<ttl>]
	 * : TTL in seconds. Default 3600.
	 *
	 * [--priority=<priority>]
	 * : Priority for MX/SRV records. Default 10.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore dns add example.com --type=TXT --name="_dmarc.ghl" --value="v=DMARC1;p=none;"
	 *     wp captaincore dns add example.com --type=A --name="www" --value="192.168.1.1"
	 *     wp captaincore dns add example.com --type=MX --name="ghl" --value="mxa.mailgun.org" --priority=10
	 *     wp captaincore dns add example.com --type=CNAME --name="email.ghl" --value="mailgun.org"
	 *
	 * @when after_wp_load
	 */
	public function add( $args, $assoc_args ) {
		$domain_name  = $args[0];
		$record_type  = strtolower( $assoc_args['type'] );
		$record_name  = $assoc_args['name'] ?? '';
		$record_value = $assoc_args['value'];
		$record_ttl   = (int) ( $assoc_args['ttl'] ?? 3600 );
		$priority     = (int) ( $assoc_args['priority'] ?? 10 );

		if ( $record_name === '@' ) {
			$record_name = '';
		}

		$domain = $this->resolve_domain( $domain_name );
		if ( ! $domain ) {
			return;
		}

		$post_data = captaincore_format_dns_record_for_api(
			$record_type,
			$record_name,
			$this->build_value( $record_type, $record_value, $priority ),
			$record_ttl
		);

		$response = Remote\Constellix::post( "domains/{$domain->remote_id}/records", $post_data );

		if ( ! empty( $response->errors ) ) {
			$errors = is_object( $response->errors ) ? (array) $response->errors : $response->errors;
			\WP_CLI::error( "Failed to create record: " . implode( ', ', array_map( 'trim', (array) $errors ) ) );
			return;
		}

		$record_id = $response->data->id ?? 'unknown';
		\WP_CLI::success( "Created " . strtoupper( $record_type ) . " record '{$record_name}' (ID: {$record_id})" );
	}

	/**
	 * Delete a DNS record from a domain.
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name (e.g., knutsenoutdoor.com)
	 *
	 * <record_id>
	 * : Record ID to delete (from `wp captaincore dns list`)
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore dns delete example.com 64846032
	 *
	 * @when after_wp_load
	 */
	public function delete( $args, $assoc_args ) {
		$domain_name = $args[0];
		$record_id   = $args[1];

		$domain = $this->resolve_domain( $domain_name );
		if ( ! $domain ) {
			return;
		}

		$response = Remote\Constellix::delete( "domains/{$domain->remote_id}/records/{$record_id}" );

		if ( ! empty( $response->errors ) ) {
			\WP_CLI::error( "Failed to delete record: " . json_encode( $response->errors ) );
			return;
		}

		\WP_CLI::success( "Deleted record {$record_id}" );
	}

	/**
	 * Update a DNS record on a domain.
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name (e.g., knutsenoutdoor.com)
	 *
	 * <record_id>
	 * : Record ID to update (from `wp captaincore dns list`)
	 *
	 * --type=<type>
	 * : Record type (A, AAAA, CNAME, MX, TXT, SRV, SPF, ANAME, HTTP)
	 *
	 * --name=<name>
	 * : Record name (subdomain). Use @ or empty string for root.
	 *
	 * --value=<value>
	 * : Record value
	 *
	 * [--ttl=<ttl>]
	 * : TTL in seconds. Default 3600.
	 *
	 * [--priority=<priority>]
	 * : Priority for MX/SRV records. Default 10.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore dns update example.com 64846032 --type=TXT --name="_dmarc.ghl" --value="v=DMARC1;p=reject;"
	 *
	 * @when after_wp_load
	 */
	public function update( $args, $assoc_args ) {
		$domain_name  = $args[0];
		$record_id    = $args[1];
		$record_type  = strtolower( $assoc_args['type'] );
		$record_name  = $assoc_args['name'] ?? '';
		$record_ttl   = (int) ( $assoc_args['ttl'] ?? 3600 );
		$priority     = (int) ( $assoc_args['priority'] ?? 10 );
		$record_value = $assoc_args['value'];

		if ( $record_name === '@' ) {
			$record_name = '';
		}

		$domain = $this->resolve_domain( $domain_name );
		if ( ! $domain ) {
			return;
		}

		$post_data = captaincore_format_dns_record_for_api(
			$record_type,
			$record_name,
			$this->build_value( $record_type, $record_value, $priority ),
			$record_ttl
		);

		$response = Remote\Constellix::put( "domains/{$domain->remote_id}/records/{$record_id}", $post_data );

		if ( ! empty( $response->errors ) ) {
			$errors = is_object( $response->errors ) ? (array) $response->errors : $response->errors;
			\WP_CLI::error( "Failed to update record: " . implode( ', ', array_map( 'trim', (array) $errors ) ) );
			return;
		}

		\WP_CLI::success( "Updated record {$record_id}" );
	}

	/**
	 * Look up a domain and show its ID and DNS zone info.
	 *
	 * ## OPTIONS
	 *
	 * <domain>
	 * : Domain name (e.g., knutsenoutdoor.com)
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore dns lookup example.com
	 *
	 * @when after_wp_load
	 */
	public function lookup( $args, $assoc_args ) {
		$domain_name = $args[0];
		$domain      = $this->resolve_domain( $domain_name );
		if ( ! $domain ) {
			return;
		}

		$rows = [
			[
				'Field' => 'domain_id',
				'Value' => $domain->domain_id,
			],
			[
				'Field' => 'remote_id',
				'Value' => $domain->remote_id,
			],
			[
				'Field' => 'name',
				'Value' => $domain->name,
			],
			[
				'Field' => 'status',
				'Value' => $domain->status ?? 'n/a',
			],
		];

		\WP_CLI\Utils\format_items( 'table', $rows, [ 'Field', 'Value' ] );
	}

	/**
	 * Resolve a domain name to its CaptainCore domain record.
	 */
	private function resolve_domain( $domain_name ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'captaincore_domains';
		$domain = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE name = %s",
			strtolower( trim( $domain_name ) )
		) );

		if ( ! $domain ) {
			\WP_CLI::error( "Domain '{$domain_name}' not found." );
			return null;
		}

		if ( empty( $domain->remote_id ) ) {
			\WP_CLI::error( "No DNS zone configured for '{$domain_name}'." );
			return null;
		}

		return $domain;
	}

	/**
	 * Fetch DNS records from Constellix for a given remote domain ID.
	 */
	private function fetch_records( $remote_id ) {
		$records = Remote\Constellix::get( "domains/$remote_id/records?perPage=100" );

		if ( ! empty( $records->errors ) ) {
			\WP_CLI::error( "Error fetching DNS records: " . json_encode( $records->errors ) );
			return false;
		}

		$all_records = $records->data ?? [];

		// Handle pagination
		if ( ! empty( $records->meta->pagination->total ) ) {
			$steps = ceil( $records->meta->pagination->total / 100 );
			for ( $i = 1; $i < $steps; $i++ ) {
				$page = $i + 1;
				$additional = Remote\Constellix::get( "domains/$remote_id/records?page=$page&perPage=100" );
				if ( ! empty( $additional->data ) ) {
					$all_records = array_merge( $all_records, $additional->data );
				}
			}
		}

		// Sort by type then name
		if ( ! empty( $all_records ) ) {
			array_multisort(
				array_column( $all_records, 'type' ), SORT_ASC,
				array_column( $all_records, 'name' ), SORT_ASC,
				$all_records
			);
		}

		return $all_records;
	}

	/**
	 * Build the value structure expected by captaincore_format_dns_record_for_api.
	 */
	private function build_value( $record_type, $value, $priority = 10 ) {
		$record_type = strtolower( $record_type );

		if ( in_array( $record_type, [ 'a', 'aaaa', 'aname', 'cname', 'txt', 'spf' ] ) ) {
			return [ [ 'value' => $value ] ];
		}

		if ( $record_type === 'mx' ) {
			return [ [ 'server' => $value, 'priority' => $priority ] ];
		}

		if ( $record_type === 'http' ) {
			return $value;
		}

		return [ [ 'value' => $value ] ];
	}
}
