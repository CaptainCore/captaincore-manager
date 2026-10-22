<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the renewal cron is going to invoice, account by account and month by
 * month, without generating anything.
 *
 * The line items follow Account::generate_order(), which is what actually cuts
 * the invoice: the plan (or per-site count), the $2 managed WordPress sites,
 * addons, usage overages, then the one-time charges and credits. Charges and
 * credits only ride the first renewal, since Accounts::process_renewals()
 * clears them once it invoices; a negative first invoice carries over as a
 * credit, the way generate_order() records over_payment. Later renewals
 * assume today's usage holds. An account without a billing user gets no
 * invoice at all (generate_order() returns early), so it is listed but left
 * out of the totals.
 *
 * Everything comes from the accounts rows plus one site query, so the whole
 * fleet prices in well under a second; User::subscriptions() loads every
 * account in full and takes seconds.
 */
class RenewalForecast {

	const MAINTENANCE_PRICE = 2;
	const UNITS             = [ 1 => 'month', 3 => 'quarter', 6 => 'six months', 12 => 'year' ];

	/**
	 * @param array $accounts Rows from Accounts::with_renewals() or Accounts::renewals().
	 * @param int   $months   Calendar months to cover. The window starts with the
	 *                        current month only on the 1st, the day renewals run;
	 *                        from the 2nd on it starts with next month.
	 */
	public static function build( $accounts, $months = 12 ) {
		$months      = max( 1, min( 36, (int) $months ) );
		$now         = time();
		$start       = strtotime( gmdate( 'Y-m-01 00:00:00', $now ) );
		if ( gmdate( 'j', $now ) !== '1' ) {
			$start = strtotime( '+1 month', $start );
		}
		$end         = strtotime( "+{$months} month", $start );
		$sites       = self::sites_by_account( array_column( (array) $accounts, 'account_id' ) );
		$pricing     = Configurations::get()->usage_pricing ?? (object) [];
		$billers     = self::existing_users( $accounts );
		$buckets     = [];
		for ( $i = 0; $i < $months; $i++ ) {
			$ts = strtotime( "+{$i} month", $start );
			$buckets[ gmdate( 'M Y', $ts ) ] = [ 'key' => gmdate( 'M Y', $ts ), 'month' => gmdate( 'Y-m', $ts ), 'revenue' => 0, 'transactions' => 0, 'renewals' => [] ];
		}
		$subscriptions = [];
		$skipped       = [];

		foreach ( (array) $accounts as $row ) {
			$plan = json_decode( $row->plan ?? '' );
			if ( ! is_object( $plan ) || empty( $plan->next_renewal ) || ! strtotime( $plan->next_renewal ) ) {
				continue;
			}
			$account_id = (int) $row->account_id;
			$interval   = (int) ( $plan->interval ?? 0 ) ?: 12;
			$estimate   = self::estimate( $plan, $sites[ $account_id ] ?? [], $pricing, $interval );
			$billable   = ! empty( $plan->billing_user_id ) && isset( $billers[ (int) $plan->billing_user_id ] );
			$auto_pay   = ( $plan->auto_pay ?? '' ) === 'true' || ( $plan->auto_pay ?? '' ) === true;
			$sub        = [
				'account_id'   => $account_id,
				'name'         => $row->name,
				'plan'         => (string) ( $plan->name ?? '' ),
				'billing_mode' => ( $plan->billing_mode ?? '' ) === 'per_site' ? 'per_site' : 'standard',
				'interval'     => (string) $interval,
				'next_renewal' => $plan->next_renewal,
				'auto_pay'     => $auto_pay,
				'billable'     => $billable,
				'total'        => round( max( 0, $estimate['total'] ), 2 ),
				'recurring'    => round( $estimate['recurring'], 2 ),
				'one_time'     => round( $estimate['one_time'], 2 ),
				'lines'        => $estimate['lines'],
			];
			$subscriptions[] = $sub;
			if ( ! $billable ) {
				$skipped[] = [ 'account_id' => $account_id, 'name' => $row->name, 'reason' => 'No billing user, so the renewal will not invoice.' ];
				continue;
			}

			// Walk the renewal dates the way the cron advances them: each one is
			// +interval months from the one before, not from the first.
			$ts      = strtotime( $plan->next_renewal );
			$carry   = 0;
			$first   = true;
			$guard   = 0;
			while ( $ts < $end && $guard++ < 400 ) {
				$overdue = $ts < $now;
				$when    = $overdue ? $now : $ts;
				$amount  = $first ? $estimate['total'] : $estimate['recurring'];
				$amount -= $carry;
				$carry   = $amount < 0 ? abs( $amount ) : 0;
				$amount  = round( max( 0, $amount ), 2 );
				// Anything due before the window opens (overdue, or later this
				// month) still bills, so it counts in the first month.
				$key = gmdate( 'M Y', max( $when, $start ) );
				if ( isset( $buckets[ $key ] ) ) {
					$buckets[ $key ]['revenue'] += $amount;
					$buckets[ $key ]['transactions']++;
					$buckets[ $key ]['renewals'][] = [
						'account_id' => $account_id,
						'name'       => $row->name,
						'interval'   => (string) $interval,
						'date'       => gmdate( 'Y-m-d', $when ),
						'total'      => $amount,
						'one_time'   => $first && abs( $estimate['one_time'] ) > 0,
						'auto_pay'   => $auto_pay,
						'overdue'    => $overdue,
					];
				}
				$first = false;
				$ts    = strtotime( "+{$interval} month", $ts );
			}
		}

		$response = [ 'months' => [], 'revenue' => [], 'transactions' => [], 'renewals' => [] ];
		foreach ( $buckets as $key => $b ) {
			usort( $b['renewals'], function( $x, $y ) {
				return strcmp( $x['date'], $y['date'] ) ?: strcasecmp( $x['name'], $y['name'] );
			} );
			$revenue                         = round( $b['revenue'], 2 );
			$response['months'][]            = [ 'key' => $key, 'month' => $b['month'], 'revenue' => $revenue, 'transactions' => $b['transactions'] ];
			$response['revenue'][ $key ]      = $revenue;
			$response['transactions'][ $key ] = $b['transactions'];
			$response['renewals'][ $key ]     = $b['renewals'];
		}
		usort( $subscriptions, function( $x, $y ) {
			return strcmp( $x['next_renewal'], $y['next_renewal'] ) ?: strcasecmp( $x['name'], $y['name'] );
		} );
		$response['subscriptions'] = $subscriptions;
		$response['skipped']       = $skipped;
		$response['total']         = round( array_sum( $response['revenue'] ), 2 );
		$response['generated_at']  = gmdate( 'Y-m-d H:i:s', $now );
		return $response;
	}

	/**
	 * One renewal's line items for a plan, in generate_order() order.
	 *
	 * @param object $plan     Decoded plan JSON.
	 * @param array  $sites    The account's active sites: provider_id, tenant.
	 * @param object $pricing  configurations.usage_pricing.
	 * @param int    $interval Billing interval in months.
	 * @return array{total: float, recurring: float, one_time: float, lines: array}
	 */
	public static function estimate( $plan, $sites, $pricing, $interval ) {
		$lines  = [];
		$num    = function( $v ) { return is_numeric( $v ) ? (float) $v : 0.0; };
		$add    = function( $type, $name, $qty, $price, $one_time = false ) use ( &$lines ) {
			$lines[] = [ 'type' => $type, 'name' => (string) $name, 'quantity' => $qty, 'price' => round( $price, 2 ), 'total' => round( $qty * $price, 2 ), 'one_time' => $one_time ];
		};
		$price  = $num( $plan->price ?? 0 );
		$hosted = 0;
		$maint  = 0;
		foreach ( $sites as $site ) {
			if ( empty( $site['provider_id'] ) || $site['provider_id'] == '1' ) {
				// Freighter tenants ride on their host's plan (Account::billing_sites()).
				if ( ! $site['tenant'] ) {
					$hosted++;
				}
				continue;
			}
			$maint++;
		}

		$per_site = ( $plan->billing_mode ?? '' ) === 'per_site';
		if ( $per_site ) {
			$add( 'Plan', 'Per site', $hosted, $price );
		} else {
			$add( 'Plan', ( $plan->name ?? '' ) ?: 'Plan', 1, $price );
		}
		if ( $maint ) {
			$add( 'Addon', 'Managed WordPress sites', $maint, self::MAINTENANCE_PRICE );
		}
		foreach ( (array) ( $plan->addons ?? [] ) as $addon ) {
			if ( ! empty( $addon->required ) ) {
				continue;
			}
			$add( 'Addon', $addon->name ?? '', (int) ( $addon->quantity ?? 0 ), $num( $addon->price ?? 0 ) );
		}

		// Usage overages, standard mode only. A blank limit is "no limit set"
		// and prices nothing, matching the Plan tab estimate.
		if ( ! $per_site ) {
			$usage  = (object) ( $plan->usage ?? [] );
			$limits = (object) ( $plan->limits ?? [] );
			$unit   = function( $key ) use ( $pricing, $interval, $num ) {
				$c    = (object) ( $pricing->{$key} ?? [] );
				$cost = $num( $c->cost ?? 0 );
				$ci   = (int) ( $c->interval ?? 0 );
				return ( ! $ci || $ci === $interval ) ? $cost : $cost / ( $ci / $interval );
			};
			$has = function( $v ) { return $v !== null && $v !== '' && is_numeric( $v ); };
			if ( $has( $limits->sites ?? null ) && (int) ( $usage->sites ?? 0 ) > (int) $limits->sites ) {
				$add( 'Usage', 'Extra sites', (int) $usage->sites - (int) $limits->sites, $unit( 'sites' ) );
			}
			if ( $has( $limits->storage ?? null ) && $num( $usage->storage ?? 0 ) > (float) $limits->storage * 1073741824 ) {
				$step = $num( $pricing->storage->quantity ?? 0 ) ?: 10;
				$qty  = (int) ceil( ( $num( $usage->storage ) / 1073741824 - (float) $limits->storage ) / $step );
				$add( 'Usage', 'Extra storage', $qty, $unit( 'storage' ) );
			}
			if ( $has( $limits->visits ?? null ) && (int) ( $usage->visits ?? 0 ) > (int) $limits->visits ) {
				$step = (int) ( $pricing->traffic->quantity ?? 0 ) ?: 1000000;
				$qty  = (int) ceil( ( (int) $usage->visits - (int) $limits->visits ) / $step );
				$add( 'Usage', 'Extra visits', $qty, $unit( 'traffic' ) );
			}
		}
		$recurring = array_sum( array_column( $lines, 'total' ) );

		$one_time = 0;
		foreach ( (array) ( $plan->charges ?? [] ) as $charge ) {
			$qty = (int) ( $charge->quantity ?? 0 );
			$add( 'Charge', $charge->name ?? '', $qty, $num( $charge->price ?? 0 ), true );
			$one_time += $qty * $num( $charge->price ?? 0 );
		}
		foreach ( (array) ( $plan->credits ?? [] ) as $credit ) {
			$qty = (int) ( $credit->quantity ?? 0 );
			$amt = abs( $qty * $num( $credit->price ?? 0 ) );
			$add( 'Credit', $credit->name ?? '', $qty, -abs( $num( $credit->price ?? 0 ) ), true );
			$one_time -= $amt;
		}

		return [ 'total' => $recurring + $one_time, 'recurring' => $recurring, 'one_time' => $one_time, 'lines' => $lines ];
	}

	/**
	 * Active sites grouped by account, from one query.
	 *
	 * @return array<int, array<int, array{provider_id: string, tenant: bool}>>
	 */
	private static function sites_by_account( $account_ids ) {
		global $wpdb;
		$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $account_ids ) ) ) );
		if ( empty( $ids ) ) {
			return [];
		}
		$table = $wpdb->base_prefix . 'captaincore_sites';
		$in    = implode( ',', $ids );
		$rows  = $wpdb->get_results( "SELECT account_id, provider_id, details FROM $table WHERE status = 'active' AND account_id IN ($in)" );
		$out   = [];
		foreach ( $rows as $r ) {
			$out[ (int) $r->account_id ][] = [ 'provider_id' => (string) $r->provider_id, 'tenant' => Network::is_tenant( $r->details ) ];
		}
		return $out;
	}

	/** Billing user ids that still exist, keyed by id. */
	private static function existing_users( $accounts ) {
		$ids = [];
		foreach ( (array) $accounts as $row ) {
			$plan = json_decode( $row->plan ?? '' );
			if ( is_object( $plan ) && ! empty( $plan->billing_user_id ) ) {
				$ids[] = (int) $plan->billing_user_id;
			}
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( empty( $ids ) ) {
			return [];
		}
		return array_flip( array_map( 'intval', get_users( [ 'include' => $ids, 'fields' => 'ID', 'blog_id' => 0 ] ) ) );
	}

}
