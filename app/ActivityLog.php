<?php

namespace CaptainCore;

class ActivityLog {

    public static function log( $action, $entity_type, $entity_id = null, $entity_name = null, $description = '', $context = [], $account_id = null ) {
        $time_now   = date( 'Y-m-d H:i:s' );
        $user_id    = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        $data = [
            'user_id'     => $user_id,
            'action'      => $action,
            'entity_type' => $entity_type,
            'description' => $description,
            'created_at'  => $time_now,
        ];

        if ( $ip_address !== null ) {
            $data['ip_address'] = $ip_address;
        }
        if ( $account_id !== null ) {
            $data['account_id'] = $account_id;
        }
        if ( $entity_id !== null ) {
            $data['entity_id'] = $entity_id;
        }
        if ( $entity_name !== null ) {
            $data['entity_name'] = $entity_name;
        }
        if ( ! empty( $context ) ) {
            $data['context'] = json_encode( $context );
        }

        ( new ActivityLogs )->insert( $data );
    }

    public static function fetch( $filters = [], $per_page = 50, $page = 1 ) {
        global $wpdb;
        $table  = "{$wpdb->prefix}captaincore_activity_logs";
        $where  = [];
        $values = [];

        if ( ! empty( $filters['action'] ) ) {
            $where[]  = 'action = %s';
            $values[] = $filters['action'];
        }
        if ( ! empty( $filters['entity_type'] ) ) {
            $where[]  = 'entity_type = %s';
            $values[] = $filters['entity_type'];
        }
        if ( ! empty( $filters['user_id'] ) ) {
            $where[]  = 'user_id = %d';
            $values[] = $filters['user_id'];
        }
        if ( ! empty( $filters['account_id'] ) ) {
            $where[]  = 'account_id = %d';
            $values[] = $filters['account_id'];
        }
        if ( ! empty( $filters['account_ids'] ) && is_array( $filters['account_ids'] ) ) {
            $placeholders = implode( ', ', array_fill( 0, count( $filters['account_ids'] ), '%d' ) );
            $where[]      = "account_id IN ($placeholders)";
            $values       = array_merge( $values, $filters['account_ids'] );
        }
        if ( ! empty( $filters['date_from'] ) ) {
            $where[]  = 'created_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        if ( ! empty( $filters['date_to'] ) ) {
            $where[]  = 'created_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }

        $where_sql = '';
        if ( ! empty( $where ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where );
        }

        $offset = ( $page - 1 ) * $per_page;

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table $where_sql";
        if ( ! empty( $values ) ) {
            $count_sql = $wpdb->prepare( $count_sql, $values );
        }
        $total = (int) $wpdb->get_var( $count_sql );

        // Get paginated results
        $sql = "SELECT * FROM $table $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $query_values   = array_merge( $values, [ $per_page, $offset ] );
        $items = $wpdb->get_results( $wpdb->prepare( $sql, $query_values ) );

        // Enrich with user display names
        foreach ( $items as &$item ) {
            $item->user_name = $item->user_id ? get_the_author_meta( 'display_name', $item->user_id ) : 'System';
            if ( $item->context ) {
                $item->context = json_decode( $item->context );
            }
            $item->created_at_raw = $item->created_at;
            $item->created_at     = strtotime( $item->created_at );
        }

        return [
            'items' => $items,
            'total' => $total,
            'page'  => $page,
            'pages' => ceil( $total / $per_page ),
        ];
    }

}
