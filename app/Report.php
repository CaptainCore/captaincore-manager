<?php

namespace CaptainCore;

class Report {

    /**
     * Generate report data for given sites
     *
     * @param array  $site_ids   Array of site IDs
     * @param string $start_date Start date for stats (Y-m-d format)
     * @param string $end_date   End date for stats (Y-m-d format)
     * @return object Report data
     */
    public static function generate( $site_ids = [], $start_date = "", $end_date = "" ) {

        $sites_list        = [];
        $total_updates     = 0;
        $total_backups     = 0;
        $total_quicksaves  = 0;
        $total_visits      = 0;
        $total_pageviews   = 0;
        $total_storage      = 0;
        $earliest_backup    = null;
        $earliest_quicksave = null;
        $earliest_update    = null;
        $wordpress_versions = [];

        // For chart data - aggregate across all sites by date
        $stats_by_date = [];

        // For top pages - aggregate across all sites by pathname
        $pages_by_path = [];

        // For plugin/theme changes from quicksaves
        $all_plugin_updates  = [];
        $all_theme_updates   = [];
        $all_plugins_added   = [];
        $all_themes_added    = [];
        $all_plugins_removed = [];
        $all_themes_removed  = [];

        // For process logs
        $all_process_logs = [];

        // Convert dates to timestamps for Fathom
        $before = ! empty( $start_date ) ? strtotime( $start_date ) : strtotime( "-30 days" );
        $after  = ! empty( $end_date ) ? strtotime( $end_date ) : time();

        foreach ( $site_ids as $site_id ) {
            $site = new Site( $site_id );
            $site_data = Sites::get( $site_id );

            if ( empty( $site_data ) ) {
                continue;
            }

            $sites_list[] = $site_data->name;

            // Get WordPress core version and storage from production environment
            $environments = $site->environments();
            foreach ( $environments as $env ) {
                if ( strtolower( $env->environment ) === 'production' ) {
                    if ( ! empty( $env->core ) ) {
                        $wordpress_versions[] = $env->core;
                    }
                    if ( ! empty( $env->storage ) ) {
                        // Storage is in MB, convert to bytes for aggregation
                        $total_storage += (float) $env->storage;
                    }
                    break;
                }
            }

            // Count updates (all-time) and find earliest
            $update_logs = $site->update_logs( "production" );
            if ( is_array( $update_logs ) && count( $update_logs ) > 0 ) {
                $total_updates += count( $update_logs );
                // Find oldest update log
                $oldest_update = end( $update_logs );
                if ( ! empty( $oldest_update->created_at ) ) {
                    $update_time = is_numeric( $oldest_update->created_at ) ? (int) $oldest_update->created_at : strtotime( $oldest_update->created_at );
                    if ( $update_time && ( $earliest_update === null || $update_time < $earliest_update ) ) {
                        $earliest_update = $update_time;
                    }
                }
            }

            // Count backups (all-time) and find earliest
            $backups = $site->backups( "production" );
            if ( is_array( $backups ) && count( $backups ) > 0 ) {
                $total_backups += count( $backups );
                // Backups are sorted descending by time, so last item is oldest
                $oldest_backup = end( $backups );
                if ( ! empty( $oldest_backup->time ) ) {
                    // Convert to timestamp if it's a string
                    $backup_time = is_numeric( $oldest_backup->time ) ? (int) $oldest_backup->time : strtotime( $oldest_backup->time );
                    if ( $backup_time && ( $earliest_backup === null || $backup_time < $earliest_backup ) ) {
                        $earliest_backup = $backup_time;
                    }
                }
            }

            // Count quicksaves (all-time) and find earliest
            $quicksaves = $site->quicksaves( "production" );
            if ( is_array( $quicksaves ) && count( $quicksaves ) > 0 ) {
                $total_quicksaves += count( $quicksaves );
                // Quicksaves are sorted descending by created_at, so last item is oldest
                $oldest_quicksave = end( $quicksaves );
                if ( ! empty( $oldest_quicksave->created_at ) ) {
                    $qs_time = (int) $oldest_quicksave->created_at;
                    if ( $qs_time && ( $earliest_quicksave === null || $qs_time < $earliest_quicksave ) ) {
                        $earliest_quicksave = $qs_time;
                    }
                }
            }

            // Get stats for date range
            $stats = $site->stats( "production", $before, $after, "day" );
            if ( ! empty( $stats ) && ! empty( $stats['summary'] ) ) {
                $total_visits    += (int) $stats['summary']['visits'];
                $total_pageviews += (int) $stats['summary']['pageviews'];

                // Aggregate stats by date for chart
                if ( ! empty( $stats['items'] ) ) {
                    foreach ( $stats['items'] as $item ) {
                        $date = $item->date ?? $item['date'] ?? null;
                        if ( $date ) {
                            if ( ! isset( $stats_by_date[ $date ] ) ) {
                                $stats_by_date[ $date ] = [ 'visits' => 0, 'pageviews' => 0 ];
                            }
                            $stats_by_date[ $date ]['visits']    += (int) ( $item->visits ?? $item['visits'] ?? 0 );
                            $stats_by_date[ $date ]['pageviews'] += (int) ( $item->pageviews ?? $item['pageviews'] ?? 0 );
                        }
                    }
                }

                // Get top pages for this site
                $top_pages = $site->top_pages( "production", $before, $after, 20 );
                if ( ! empty( $top_pages ) && is_array( $top_pages ) ) {
                    foreach ( $top_pages as $page ) {
                        $path = $page->pathname ?? '';
                        if ( ! isset( $pages_by_path[ $path ] ) ) {
                            $pages_by_path[ $path ] = [ 'uniques' => 0, 'visits' => 0, 'pageviews' => 0 ];
                        }
                        $pages_by_path[ $path ]['uniques']   += (int) ( $page->uniques ?? 0 );
                        $pages_by_path[ $path ]['visits']    += (int) ( $page->visits ?? 0 );
                        $pages_by_path[ $path ]['pageviews'] += (int) ( $page->pageviews ?? 0 );
                    }
                }
            }

            // Get plugin/theme changes from quicksaves
            $quicksave_updates = self::get_quicksave_updates( $site_id, $before, $after );
            foreach ( $quicksave_updates['plugins'] as $plugin ) {
                $key = $plugin['name'];
                if ( ! isset( $all_plugin_updates[ $key ] ) ) {
                    $all_plugin_updates[ $key ] = $plugin;
                }
            }
            foreach ( $quicksave_updates['themes'] as $theme ) {
                $key = $theme['name'];
                if ( ! isset( $all_theme_updates[ $key ] ) ) {
                    $all_theme_updates[ $key ] = $theme;
                }
            }
            foreach ( $quicksave_updates['added_plugins'] as $plugin ) {
                $key = $plugin['name'];
                if ( ! isset( $all_plugins_added[ $key ] ) ) {
                    $all_plugins_added[ $key ] = $plugin;
                }
            }
            foreach ( $quicksave_updates['added_themes'] as $theme ) {
                $key = $theme['name'];
                if ( ! isset( $all_themes_added[ $key ] ) ) {
                    $all_themes_added[ $key ] = $theme;
                }
            }
            foreach ( $quicksave_updates['removed_plugins'] as $plugin ) {
                $key = $plugin['name'];
                if ( ! isset( $all_plugins_removed[ $key ] ) ) {
                    $all_plugins_removed[ $key ] = $plugin;
                }
            }
            foreach ( $quicksave_updates['removed_themes'] as $theme ) {
                $key = $theme['name'];
                if ( ! isset( $all_themes_removed[ $key ] ) ) {
                    $all_themes_removed[ $key ] = $theme;
                }
            }

            // Get process logs for date range
            $process_logs = $site->process_logs();
            foreach ( $process_logs as $log ) {
                if ( $log->created_at >= $before && $log->created_at <= $after ) {
                    $description = trim( strip_tags( $log->description ) );
                    // Use process name as fallback if description is empty
                    if ( empty( $description ) && ! empty( $log->name ) ) {
                        $description = $log->name;
                    }
                    $all_process_logs[] = [
                        'date'        => $log->created_at,
                        'author'      => $log->author,
                        'description' => $description,
                    ];
                }
            }
        }

        // Sort stats by date
        ksort( $stats_by_date );

        // Extract chart data arrays
        $chart_labels    = array_keys( $stats_by_date );
        $chart_visits    = array_column( $stats_by_date, 'visits' );
        $chart_pageviews = array_column( $stats_by_date, 'pageviews' );

        // Sort pages by pageviews descending and get top 10
        uasort( $pages_by_path, function( $a, $b ) {
            return $b['pageviews'] - $a['pageviews'];
        } );
        $top_pages = array_slice( $pages_by_path, 0, 10, true );

        // Format WordPress version(s)
        $unique_wp_versions = array_unique( $wordpress_versions );
        usort( $unique_wp_versions, 'version_compare' );
        $wordpress_version = '';
        if ( count( $unique_wp_versions ) === 1 ) {
            $wordpress_version = $unique_wp_versions[0];
        } elseif ( count( $unique_wp_versions ) > 1 ) {
            $wordpress_version = implode( ', ', $unique_wp_versions );
        }

        return (object) [
            'sites'           => $sites_list,
            'updates'         => $total_updates,
            'updates_since'   => $earliest_update ? date( 'F Y', $earliest_update ) : null,
            'backups'          => $total_backups,
            'backups_since'    => $earliest_backup ? date( 'F Y', $earliest_backup ) : null,
            'quicksaves'       => $total_quicksaves,
            'quicksaves_since' => $earliest_quicksave ? date( 'F Y', $earliest_quicksave ) : null,
            'visits'          => $total_visits,
            'pageviews'       => $total_pageviews,
            'storage'         => $total_storage,
            'start_date'      => date( 'F j, Y', $before ),
            'end_date'        => date( 'F j, Y', $after ),
            'chart_labels'    => $chart_labels,
            'chart_visits'    => $chart_visits,
            'chart_pageviews' => $chart_pageviews,
            'top_pages'       => $top_pages,
            'wordpress'        => $wordpress_version,
            'plugin_updates'   => array_values( $all_plugin_updates ),
            'theme_updates'    => array_values( $all_theme_updates ),
            'plugins_added'    => array_values( $all_plugins_added ),
            'themes_added'     => array_values( $all_themes_added ),
            'plugins_removed'  => array_values( $all_plugins_removed ),
            'themes_removed'   => array_values( $all_themes_removed ),
            'process_logs'     => $all_process_logs,
        ];
    }

    /**
     * Generate SVG chart for stats
     *
     * @param object $data Report data
     * @return string SVG markup
     */
    public static function generate_chart_svg( $data ) {
        $config          = Configurations::get();
        $primary_color   = $config->colors->primary ?? '#0D47A1';
        $secondary_color = $config->colors->secondary ?? '#90CAF9';

        // Chart dimensions
        $width        = 520;
        $height       = 230;
        $padding_left = 50;
        $padding_right = 20;
        $padding_top  = 20;
        $padding_bottom = 70; // Extra space for x-axis labels + legend

        $chart_width  = $width - $padding_left - $padding_right;
        $chart_height = $height - $padding_top - $padding_bottom;

        $labels    = $data->chart_labels;
        $pageviews = $data->chart_pageviews;
        $visits    = $data->chart_visits;

        if ( empty( $labels ) || count( $labels ) < 2 ) {
            return '';
        }

        $count = count( $labels );

        // Find max value for scaling
        $max_value = max( max( $pageviews ), max( $visits ) );
        $max_value = $max_value > 0 ? $max_value : 1;

        // Round up to nice number for y-axis
        $magnitude = pow( 10, floor( log10( $max_value ) ) );
        $max_value = ceil( $max_value / $magnitude ) * $magnitude;

        // Calculate points for each dataset
        $pageview_points = [];
        $visit_points    = [];

        for ( $i = 0; $i < $count; $i++ ) {
            $x = $padding_left + ( $i / ( $count - 1 ) ) * $chart_width;

            $pv_y = $padding_top + $chart_height - ( ( $pageviews[ $i ] / $max_value ) * $chart_height );
            $v_y  = $padding_top + $chart_height - ( ( $visits[ $i ] / $max_value ) * $chart_height );

            $pageview_points[] = round( $x, 1 ) . ',' . round( $pv_y, 1 );
            $visit_points[]    = round( $x, 1 ) . ',' . round( $v_y, 1 );
        }

        // Create area fill paths (closed polygon)
        $baseline = $padding_top + $chart_height;

        $pv_area_points = $pageview_points;
        array_unshift( $pv_area_points, $padding_left . ',' . $baseline );
        $pv_area_points[] = ( $padding_left + $chart_width ) . ',' . $baseline;

        $v_area_points = $visit_points;
        array_unshift( $v_area_points, $padding_left . ',' . $baseline );
        $v_area_points[] = ( $padding_left + $chart_width ) . ',' . $baseline;

        // Format labels for display (show ~5 labels max)
        $label_interval = max( 1, floor( $count / 5 ) );
        $x_labels_svg   = '';
        $x_label_y      = $padding_top + $chart_height + 20; // Position below chart area
        $last_label_i   = 0;

        for ( $i = 0; $i < $count; $i += $label_interval ) {
            $x = $padding_left + ( $i / ( $count - 1 ) ) * $chart_width;
            $label = date( 'M j', strtotime( $labels[ $i ] ) );
            $x_labels_svg .= "<text x='{$x}' y='{$x_label_y}' text-anchor='middle' fill='#718096' font-size='11'>{$label}</text>";
            $last_label_i = $i;
        }
        // Show last label only if it's far enough from the previous label (at least half an interval)
        $gap_to_end = $count - 1 - $last_label_i;
        if ( $gap_to_end >= $label_interval * 0.6 ) {
            $x = $padding_left + $chart_width;
            $label = date( 'M j', strtotime( $labels[ $count - 1 ] ) );
            $x_labels_svg .= "<text x='{$x}' y='{$x_label_y}' text-anchor='middle' fill='#718096' font-size='11'>{$label}</text>";
        }

        // Y-axis labels (0, mid, max)
        $y_labels_svg = '';
        $y_values = [ 0, $max_value / 2, $max_value ];
        foreach ( $y_values as $val ) {
            $y = $padding_top + $chart_height - ( ( $val / $max_value ) * $chart_height );
            $formatted = self::format_number_short( $val );
            $y_labels_svg .= "<text x='" . ( $padding_left - 10 ) . "' y='" . ( $y + 4 ) . "' text-anchor='end' fill='#718096' font-size='11'>{$formatted}</text>";
            // Grid line
            $y_labels_svg .= "<line x1='{$padding_left}' y1='{$y}' x2='" . ( $width - $padding_right ) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
        }

        // Convert hex to rgb for fill opacity
        $primary_rgb   = self::hex_to_rgb( $primary_color );
        $secondary_rgb = self::hex_to_rgb( $secondary_color );

        $svg = "
        <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$width} {$height}' style='width: 100%; height: auto; max-width: {$width}px;'>
            <!-- Background -->
            <rect width='{$width}' height='{$height}' fill='white'/>

            <!-- Grid lines -->
            {$y_labels_svg}

            <!-- Pageviews area fill -->
            <polygon points='" . implode( ' ', $pv_area_points ) . "' fill='rgba({$secondary_rgb}, 0.3)'/>

            <!-- Pageviews line -->
            <polyline points='" . implode( ' ', $pageview_points ) . "' fill='none' stroke='{$secondary_color}' stroke-width='2.5'/>

            <!-- Visits area fill -->
            <polygon points='" . implode( ' ', $v_area_points ) . "' fill='rgba({$primary_rgb}, 0.3)'/>

            <!-- Visits line -->
            <polyline points='" . implode( ' ', $visit_points ) . "' fill='none' stroke='{$primary_color}' stroke-width='2.5'/>

            <!-- X-axis labels -->
            {$x_labels_svg}

            <!-- Legend -->
            <rect x='" . ( $width / 2 - 80 ) . "' y='" . ( $height - 25 ) . "' width='12' height='12' fill='{$secondary_color}' rx='2'/>
            <text x='" . ( $width / 2 - 63 ) . "' y='" . ( $height - 15 ) . "' fill='#4a5568' font-size='11'>Pageviews</text>

            <rect x='" . ( $width / 2 + 20 ) . "' y='" . ( $height - 25 ) . "' width='12' height='12' fill='{$primary_color}' rx='2'/>
            <text x='" . ( $width / 2 + 37 ) . "' y='" . ( $height - 15 ) . "' fill='#4a5568' font-size='11'>Visitors</text>
        </svg>";

        return trim( $svg );
    }

    /**
     * Convert hex color to RGB string
     *
     * @param string $hex Hex color
     * @return string RGB values as "r, g, b"
     */
    private static function hex_to_rgb( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) == 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        return "{$r}, {$g}, {$b}";
    }

    /**
     * Format number to short form (1K, 10K, 1M)
     *
     * @param int $num Number to format
     * @return string Formatted number
     */
    private static function format_number_short( $num ) {
        if ( $num >= 1000000 ) {
            return round( $num / 1000000, 1 ) . 'M';
        }
        if ( $num >= 1000 ) {
            return round( $num / 1000, 1 ) . 'K';
        }
        return (string) $num;
    }

    /**
     * Format storage size (bytes to human readable)
     *
     * @param float $bytes Storage in bytes
     * @return string Formatted storage with HTML
     */
    private static function format_storage( $bytes ) {
        $kb = $bytes / 1024;
        $mb = $kb / 1024;
        $gb = $mb / 1024;

        if ( $gb >= 1 ) {
            return round( $gb, 2 ) . " <span style='font-size: 16px; font-weight: 400;'>GB</span>";
        }
        if ( $mb >= 1 ) {
            return round( $mb, 0 ) . " <span style='font-size: 16px; font-weight: 400;'>MB</span>";
        }
        return round( $kb, 0 ) . " <span style='font-size: 16px; font-weight: 400;'>KB</span>";
    }

    /**
     * Render report as HTML email
     *
     * @param object $data Report data from generate()
     * @return string HTML content
     */
    public static function render( $data ) {

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $logo_url    = $config->logo ?? '';
        $site_name   = get_bloginfo( 'name' );

        $sites_list = implode( ', ', $data->sites );

        // Format numbers with commas
        $updates_formatted    = number_format( $data->updates );
        $backups_formatted    = number_format( $data->backups );
        $quicksaves_formatted = number_format( $data->quicksaves );
        $visits_formatted     = number_format( $data->visits );
        $pageviews_formatted  = number_format( $data->pageviews );
        $storage_formatted    = self::format_storage( $data->storage );

        // Generate chart and stats HTML only if there's stats data
        $has_stats   = $data->visits > 0 || $data->pageviews > 0;
        $chart_html  = '';
        $stats_html  = '';

        if ( $has_stats ) {
            // Generate chart SVG if we have enough data points
            if ( ! empty( $data->chart_labels ) && count( $data->chart_labels ) > 1 ) {
                $chart_svg = self::generate_chart_svg( $data );
                $chart_html = "
                                    <!-- Stats Chart -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-bottom: 20px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 12px;'>Traffic Overview</div>
                                                    {$chart_svg}
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                ";
            }

            // Stats row (Visits / Pageviews / Storage)
            $stats_html = "
                                        <tr>
                                            <td width='33%' style='padding: 10px;'>
                                                <div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 8px;'>Visits</div>
                                                    <div style='font-size: 32px; font-weight: 700; color: {$brand_color};'>{$visits_formatted}</div>
                                                </div>
                                            </td>
                                            <td width='33%' style='padding: 10px;'>
                                                <div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 8px;'>Pageviews</div>
                                                    <div style='font-size: 32px; font-weight: 700; color: {$brand_color};'>{$pageviews_formatted}</div>
                                                </div>
                                            </td>
                                            <td width='33%' style='padding: 10px;'>
                                                <div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 8px;'>Storage</div>
                                                    <div style='font-size: 32px; font-weight: 700; color: {$brand_color};'>{$storage_formatted}</div>
                                                </div>
                                            </td>
                                        </tr>";
        }

        // Build top pages HTML if we have data
        $top_pages_html = '';
        if ( $has_stats && ! empty( $data->top_pages ) ) {
            $top_pages_rows = '';
            foreach ( $data->top_pages as $path => $page_data ) {
                $path_display = htmlspecialchars( $path ?: '/' );
                $uniques  = number_format( $page_data['uniques'] );
                $visitors = number_format( $page_data['visits'] );
                $views    = number_format( $page_data['pageviews'] );
                $top_pages_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; word-break: break-all; text-align: left;'>{$path_display}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$uniques}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$visitors}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$views}</td>
                    </tr>";
            }

            $top_pages_html = "
                                    <!-- Top Pages -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; padding: 15px 15px 10px; text-align: center;'>Top Pages</div>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        <tr style='background-color: #f7fafc;'>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: left;'>Page</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: right; width: 70px;'>Uniques</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: right; width: 70px;'>Visitors</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: right; width: 70px;'>Views</td>
                                                        </tr>
                                                        {$top_pages_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Build updates HTML if we have plugin or theme updates
        $updates_html = '';
        if ( ! empty( $data->plugin_updates ) || ! empty( $data->theme_updates ) ) {
            $updates_rows = '';

            // Add theme updates first
            foreach ( $data->theme_updates as $theme ) {
                $title       = htmlspecialchars( $theme['title'] ?: $theme['name'] );
                $old_version = htmlspecialchars( $theme['old_version'] );
                $new_version = htmlspecialchars( $theme['new_version'] );
                $updates_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; text-align: left;'>{$title}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 11px; color: #a0aec0; text-align: center; white-space: nowrap;'>Theme</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$old_version} → {$new_version}</td>
                    </tr>";
            }

            // Add plugin updates
            foreach ( $data->plugin_updates as $plugin ) {
                $title       = htmlspecialchars( $plugin['title'] ?: $plugin['name'] );
                $old_version = htmlspecialchars( $plugin['old_version'] );
                $new_version = htmlspecialchars( $plugin['new_version'] );
                $updates_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; text-align: left;'>{$title}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 11px; color: #a0aec0; text-align: center; white-space: nowrap;'>Plugin</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$old_version} → {$new_version}</td>
                    </tr>";
            }

            $total_updates = count( $data->plugin_updates ) + count( $data->theme_updates );
            $updates_html = "
                                    <!-- Updates -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; padding: 15px 15px 10px; text-align: center;'>Updated During Period ({$total_updates})</div>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        <tr style='background-color: #f7fafc;'>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: left;'>Name</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: center; width: 70px;'>Type</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: right; width: 120px;'>Version</td>
                                                        </tr>
                                                        {$updates_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Build added items HTML
        $added_html = '';
        if ( ! empty( $data->plugins_added ) || ! empty( $data->themes_added ) ) {
            $added_rows = '';

            foreach ( $data->themes_added as $theme ) {
                $title   = htmlspecialchars( $theme['title'] ?: $theme['name'] );
                $version = htmlspecialchars( $theme['version'] );
                $added_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; text-align: left;'>{$title}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 11px; color: #a0aec0; text-align: center; white-space: nowrap;'>Theme</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$version}</td>
                    </tr>";
            }

            foreach ( $data->plugins_added as $plugin ) {
                $title   = htmlspecialchars( $plugin['title'] ?: $plugin['name'] );
                $version = htmlspecialchars( $plugin['version'] );
                $added_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; text-align: left;'>{$title}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 11px; color: #a0aec0; text-align: center; white-space: nowrap;'>Plugin</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$version}</td>
                    </tr>";
            }

            $total_added = count( $data->plugins_added ) + count( $data->themes_added );
            $added_html = "
                                    <!-- Added -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 20px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #48bb78; padding: 15px 15px 10px; text-align: center;'>Added During Period ({$total_added})</div>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        <tr style='background-color: #f7fafc;'>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: left;'>Name</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: center; width: 70px;'>Type</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: right; width: 120px;'>Version</td>
                                                        </tr>
                                                        {$added_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Build removed items HTML
        $removed_html = '';
        if ( ! empty( $data->plugins_removed ) || ! empty( $data->themes_removed ) ) {
            $removed_rows = '';

            foreach ( $data->themes_removed as $theme ) {
                $title   = htmlspecialchars( $theme['title'] ?: $theme['name'] );
                $version = htmlspecialchars( $theme['version'] );
                $removed_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; text-align: left;'>{$title}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 11px; color: #a0aec0; text-align: center; white-space: nowrap;'>Theme</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$version}</td>
                    </tr>";
            }

            foreach ( $data->plugins_removed as $plugin ) {
                $title   = htmlspecialchars( $plugin['title'] ?: $plugin['name'] );
                $version = htmlspecialchars( $plugin['version'] );
                $removed_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; text-align: left;'>{$title}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 11px; color: #a0aec0; text-align: center; white-space: nowrap;'>Plugin</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>{$version}</td>
                    </tr>";
            }

            $total_removed = count( $data->plugins_removed ) + count( $data->themes_removed );
            $removed_html = "
                                    <!-- Removed -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 20px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #e53e3e; padding: 15px 15px 10px; text-align: center;'>Removed During Period ({$total_removed})</div>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        <tr style='background-color: #f7fafc;'>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: left;'>Name</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: center; width: 70px;'>Type</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: right; width: 120px;'>Version</td>
                                                        </tr>
                                                        {$removed_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Build process logs HTML
        $process_logs_html = '';
        if ( ! empty( $data->process_logs ) ) {
            $logs_rows = '';

            // Sort by date descending
            usort( $data->process_logs, function( $a, $b ) {
                return $b['date'] - $a['date'];
            } );

            foreach ( $data->process_logs as $log ) {
                $date        = date( 'M j', $log['date'] );
                $author      = htmlspecialchars( $log['author'] );
                $description = htmlspecialchars( $log['description'] );
                $logs_rows .= "
                    <tr>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 12px; color: #a0aec0; white-space: nowrap; vertical-align: top;'>{$date}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #4a5568; text-align: left;'>{$description}</td>
                        <td style='padding: 10px 12px; border-bottom: 1px solid #edf2f7; font-size: 12px; color: #718096; text-align: right; white-space: nowrap; vertical-align: top;'>{$author}</td>
                    </tr>";
            }

            $total_logs = count( $data->process_logs );
            $process_logs_html = "
                                    <!-- Process Logs -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; padding: 15px 15px 10px; text-align: center;'>Work Performed ({$total_logs})</div>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        <tr style='background-color: #f7fafc;'>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 60px;'>Date</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: left;'>Description</td>
                                                            <td style='padding: 8px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: right; width: 100px;'>By</td>
                                                        </tr>
                                                        {$logs_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Maintenance Report</title>
        </head>
        <body style='margin: 0; padding: 0; background-color: #f7fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #4a5568;'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr>
                    <td style='padding: 40px 20px; text-align: center;'>

                        <div style='margin-bottom: 30px;'>
                            <img src='{$logo_url}' alt='{$site_name}' style='max-height: 50px; width: auto;'>
                        </div>

                        <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); overflow: hidden;'>

                            <!-- Header Area -->
                            <tr>
                                <td style='padding: 40px; text-align: center; background-color: #ffffff; border-bottom: 1px solid #edf2f7;'>
                                    <h1 style='margin: 0 0 10px; font-size: 24px; font-weight: 800; color: #2d3748;'>Maintenance Report</h1>
                                    <p style='margin: 0; font-size: 14px; color: #718096;'>{$sites_list}</p>
                                    <p style='margin: 10px 0 0; font-size: 12px; color: #a0aec0;'>{$data->start_date} - {$data->end_date}</p>" . ( ! empty( $data->wordpress ) ? "<p style='margin: 8px 0 0; font-size: 12px; color: #a0aec0;'>WordPress {$data->wordpress}</p>" : "" ) . "
                                </td>
                            </tr>

                            <!-- Main Content Area -->
                            <tr>
                                <td style='padding: 40px;'>

                                    {$chart_html}

                                    <!-- Stats Grid -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                        {$stats_html}
                                        <tr>
                                            <td width='33%' style='padding: 10px;'>
                                                <div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 8px;'>Updates</div>
                                                    <div style='font-size: 28px; font-weight: 700; color: #2d3748;'>{$updates_formatted}</div>" . ( ! empty( $data->updates_since ) ? "<div style='font-size: 11px; color: #718096; margin-top: 6px;'>since {$data->updates_since}</div>" : "" ) . "
                                                </div>
                                            </td>
                                            <td width='33%' style='padding: 10px;'>
                                                <div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 8px;'>Backups</div>
                                                    <div style='font-size: 28px; font-weight: 700; color: #2d3748;'>{$backups_formatted}</div>" . ( ! empty( $data->backups_since ) ? "<div style='font-size: 11px; color: #718096; margin-top: 6px;'>since {$data->backups_since}</div>" : "" ) . "<div style='font-size: 10px; color: #a0aec0; margin-top: 4px;'>Backups each 24hrs</div>
                                                </div>
                                            </td>
                                            <td width='33%' style='padding: 10px;'>
                                                <div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 8px;'>Quicksaves</div>
                                                    <div style='font-size: 28px; font-weight: 700; color: #2d3748;'>{$quicksaves_formatted}</div>" . ( ! empty( $data->quicksaves_since ) ? "<div style='font-size: 11px; color: #718096; margin-top: 6px;'>since {$data->quicksaves_since}</div>" : "" ) . "
                                                </div>
                                            </td>
                                        </tr>
                                    </table>

                                    {$top_pages_html}

                                    {$updates_html}

                                    {$added_html}

                                    {$removed_html}

                                    {$process_logs_html}

                                </td>
                            </tr>

                            <!-- Internal Footer Area -->
                            <tr>
                                <td style='padding: 30px 40px; background-color: #f7fafc; border-top: 1px solid #edf2f7; text-align: center;'>
                                    <p style='margin: 0; font-size: 14px; color: #718096;'>
                                        Questions? <a href='mailto:" . get_option('admin_email') . "' style='color: {$brand_color}; text-decoration: none;'>Contact Support</a>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <div style='margin-top: 30px; font-size: 12px; color: #a0aec0;'>
                             <p style='margin: 0;'><a href='" . home_url() . "' style='color: #a0aec0; text-decoration: none;'>{$site_name}</a></p>
                        </div>

                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";

        return $message;
    }

    /**
     * Generate and send report email
     *
     * @param array  $site_ids   Array of site IDs
     * @param string $start_date Start date for stats
     * @param string $end_date   End date for stats
     * @param string $recipient  Email address to send to
     * @return bool Success
     */
    public static function send( $site_ids = [], $start_date = "", $end_date = "", $recipient = "" ) {

        if ( empty( $site_ids ) || empty( $recipient ) ) {
            return false;
        }

        $data    = self::generate( $site_ids, $start_date, $end_date );
        $html    = self::render( $data );
        $subject = "Maintenance Report - " . $data->start_date . " to " . $data->end_date;

        Mailer::send( $recipient, $subject, $html );

        return true;
    }

    /**
     * Get default recipient email from billing account
     *
     * @param array $site_ids Array of site IDs
     * @return string Email address
     */
    public static function get_default_recipient( $site_ids = [] ) {

        if ( empty( $site_ids ) ) {
            return "";
        }

        // Get account from first site
        $site = Sites::get( $site_ids[0] );

        if ( empty( $site ) || empty( $site->account_id ) ) {
            return "";
        }

        $account = ( new Account( $site->account_id, true ) )->get();

        if ( empty( $account ) || empty( $account->plan->billing_user_id ) ) {
            return "";
        }

        $user = get_user_by( 'id', $account->plan->billing_user_id );

        if ( empty( $user ) ) {
            return "";
        }

        return $user->user_email;
    }

    /**
     * Preview report HTML without sending
     *
     * @param array  $site_ids   Array of site IDs
     * @param string $start_date Start date for stats
     * @param string $end_date   End date for stats
     * @return string HTML content
     */
    public static function preview( $site_ids = [], $start_date = "", $end_date = "" ) {
        $data = self::generate( $site_ids, $start_date, $end_date );
        return self::render( $data );
    }

    /**
     * Get plugin/theme updates between two dates from quicksaves
     *
     * @param int $site_id Site ID
     * @param int $start_timestamp Start date timestamp
     * @param int $end_timestamp End date timestamp
     * @return array Array with 'plugins' and 'themes' that were updated
     */
    public static function get_quicksave_updates( $site_id, $start_timestamp, $end_timestamp ) {
        $site = new Site( $site_id );
        $quicksaves = $site->quicksaves( "production" );

        if ( empty( $quicksaves ) || ! is_array( $quicksaves ) ) {
            return [ 'plugins' => [], 'themes' => [] ];
        }

        // Find quicksave closest to start date (before or at start)
        $start_quicksave = null;
        $end_quicksave   = null;

        // Quicksaves are sorted descending by created_at, so iterate to find matches
        foreach ( $quicksaves as $qs ) {
            $created = (int) $qs->created_at;

            // Find the last quicksave before or at end date
            if ( $end_quicksave === null && $created <= $end_timestamp ) {
                $end_quicksave = $qs;
            }

            // Find the last quicksave before start date
            if ( $created < $start_timestamp ) {
                $start_quicksave = $qs;
                break;
            }
        }

        // If no start quicksave found, use the oldest available
        if ( $start_quicksave === null && count( $quicksaves ) > 0 ) {
            $start_quicksave = end( $quicksaves );
        }

        // If no end quicksave or same as start, no updates to report
        if ( $end_quicksave === null || $start_quicksave === null ) {
            return [ 'plugins' => [], 'themes' => [] ];
        }

        if ( $start_quicksave->hash === $end_quicksave->hash ) {
            return [ 'plugins' => [], 'themes' => [] ];
        }

        // Fetch full details of both quicksaves
        $qs_helper = new Quicksave( $site_id );
        $start_details = $qs_helper->get( $start_quicksave->hash, "production" );
        $end_details   = $qs_helper->get( $end_quicksave->hash, "production" );

        if ( empty( $start_details ) || empty( $end_details ) ) {
            return [ 'plugins' => [], 'themes' => [] ];
        }

        $updated_plugins = [];
        $updated_themes  = [];
        $added_plugins   = [];
        $added_themes    = [];
        $removed_plugins = [];
        $removed_themes  = [];

        // Build plugin lookup arrays
        $start_plugins = [];
        if ( ! empty( $start_details->plugins ) ) {
            foreach ( $start_details->plugins as $plugin ) {
                $start_plugins[ $plugin->name ] = $plugin;
            }
        }

        $end_plugins = [];
        if ( ! empty( $end_details->plugins ) ) {
            foreach ( $end_details->plugins as $plugin ) {
                $end_plugins[ $plugin->name ] = $plugin;

                if ( isset( $start_plugins[ $plugin->name ] ) ) {
                    // Plugin exists in both - check for update
                    $old_version = $start_plugins[ $plugin->name ]->version;
                    if ( version_compare( $plugin->version, $old_version, '>' ) ) {
                        $updated_plugins[] = [
                            'name'        => $plugin->name,
                            'title'       => $plugin->title,
                            'old_version' => $old_version,
                            'new_version' => $plugin->version,
                        ];
                    }
                } else {
                    // Plugin is new (added)
                    $added_plugins[] = [
                        'name'    => $plugin->name,
                        'title'   => $plugin->title,
                        'version' => $plugin->version,
                    ];
                }
            }
        }

        // Check for removed plugins
        foreach ( $start_plugins as $name => $plugin ) {
            if ( ! isset( $end_plugins[ $name ] ) ) {
                $removed_plugins[] = [
                    'name'    => $plugin->name,
                    'title'   => $plugin->title,
                    'version' => $plugin->version,
                ];
            }
        }

        // Build theme lookup arrays
        $start_themes = [];
        if ( ! empty( $start_details->themes ) ) {
            foreach ( $start_details->themes as $theme ) {
                $start_themes[ $theme->name ] = $theme;
            }
        }

        $end_themes = [];
        if ( ! empty( $end_details->themes ) ) {
            foreach ( $end_details->themes as $theme ) {
                $end_themes[ $theme->name ] = $theme;

                if ( isset( $start_themes[ $theme->name ] ) ) {
                    // Theme exists in both - check for update
                    $old_version = $start_themes[ $theme->name ]->version;
                    if ( version_compare( $theme->version, $old_version, '>' ) ) {
                        $updated_themes[] = [
                            'name'        => $theme->name,
                            'title'       => $theme->title,
                            'old_version' => $old_version,
                            'new_version' => $theme->version,
                        ];
                    }
                } else {
                    // Theme is new (added)
                    $added_themes[] = [
                        'name'    => $theme->name,
                        'title'   => $theme->title,
                        'version' => $theme->version,
                    ];
                }
            }
        }

        // Check for removed themes
        foreach ( $start_themes as $name => $theme ) {
            if ( ! isset( $end_themes[ $name ] ) ) {
                $removed_themes[] = [
                    'name'    => $theme->name,
                    'title'   => $theme->title,
                    'version' => $theme->version,
                ];
            }
        }

        return [
            'plugins'         => $updated_plugins,
            'themes'          => $updated_themes,
            'added_plugins'   => $added_plugins,
            'added_themes'    => $added_themes,
            'removed_plugins' => $removed_plugins,
            'removed_themes'  => $removed_themes,
        ];
    }

}
