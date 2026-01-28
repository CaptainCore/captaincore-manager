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

        // For visual captures
        $all_visual_captures = [];

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

            // Count updates up to end date and find earliest
            $update_logs = $site->update_logs( "production" );
            if ( is_array( $update_logs ) && count( $update_logs ) > 0 ) {
                foreach ( $update_logs as $log ) {
                    if ( ! empty( $log->created_at ) ) {
                        $update_time = is_numeric( $log->created_at ) ? (int) $log->created_at : strtotime( $log->created_at );
                        // Only count updates on or before the report end date
                        if ( $update_time && $update_time <= $after ) {
                            $total_updates++;
                            if ( $earliest_update === null || $update_time < $earliest_update ) {
                                $earliest_update = $update_time;
                            }
                        }
                    }
                }
            }

            // Count backups up to end date and find earliest
            $backups = $site->backups( "production" );
            if ( is_array( $backups ) && count( $backups ) > 0 ) {
                foreach ( $backups as $backup ) {
                    if ( ! empty( $backup->time ) ) {
                        $backup_time = is_numeric( $backup->time ) ? (int) $backup->time : strtotime( $backup->time );
                        // Only count backups on or before the report end date
                        if ( $backup_time && $backup_time <= $after ) {
                            $total_backups++;
                            if ( $earliest_backup === null || $backup_time < $earliest_backup ) {
                                $earliest_backup = $backup_time;
                            }
                        }
                    }
                }
            }

            // Count quicksaves up to end date and find earliest
            $quicksaves = $site->quicksaves( "production" );
            if ( is_array( $quicksaves ) && count( $quicksaves ) > 0 ) {
                foreach ( $quicksaves as $qs ) {
                    if ( ! empty( $qs->created_at ) ) {
                        $qs_time = (int) $qs->created_at;
                        // Only count quicksaves on or before the report end date
                        if ( $qs_time && $qs_time <= $after ) {
                            $total_quicksaves++;
                            if ( $earliest_quicksave === null || $qs_time < $earliest_quicksave ) {
                                $earliest_quicksave = $qs_time;
                            }
                        }
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

            // Get visual captures for date range
            $captures = $site->captures( "production" );
            $upload_uri = get_option( 'options_remote_upload_uri' );
            foreach ( $captures as $capture ) {
                $capture_timestamp = strtotime( $capture->created_at );
                if ( $capture_timestamp >= $before && $capture_timestamp <= $after ) {
                    // Find homepage capture image
                    $homepage_image = null;
                    if ( ! empty( $capture->pages ) ) {
                        foreach ( $capture->pages as $page ) {
                            if ( $page->name === '/' ) {
                                $homepage_image = $page->image;
                                break;
                            }
                        }
                        // Fallback to first page if no homepage
                        if ( ! $homepage_image && ! empty( $capture->pages[0]->image ) ) {
                            $homepage_image = $capture->pages[0]->image;
                        }
                    }

                    if ( $homepage_image ) {
                        $image_url = "{$upload_uri}{$site_data->site}_{$site_data->site_id}/production/captures/{$homepage_image}";
                        $all_visual_captures[] = [
                            'date'  => $capture_timestamp,
                            'url'   => $image_url,
                            'site'  => $site_data->name,
                        ];
                    }
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

        // Sort visual captures by date descending
        usort( $all_visual_captures, function( $a, $b ) {
            return $b['date'] - $a['date'];
        } );

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
            'visual_captures'  => $all_visual_captures,
        ];
    }

    /**
     * Generate chart as WebP using GD library
     *
     * @param object $data Report data
     * @return array|string Array with 'image' and 'cid' keys, or empty string if failed
     */
    public static function generate_chart_image( $data ) {
        $result = [
            'cid'   => 'chart-' . md5( serialize( $data->chart_labels ) ),
            'image' => null,
        ];

        // Generate WebP directly using GD library
        if ( extension_loaded( 'gd' ) ) {
            try {
                $image_data = self::generate_chart_webp_gd( $data );
                if ( ! empty( $image_data ) ) {
                    $result['image'] = $image_data;
                }
            } catch ( \Exception $e ) {
                // GD failed, chart will be skipped
            }
        }

        return $result;
    }

    /**
     * Generate chart WebP using GD library
     *
     * @param object $data Report data
     * @return string|null WebP binary data or null on failure
     */
    private static function generate_chart_webp_gd( $data ) {
        $config          = Configurations::get();
        $primary_color   = $config->colors->primary ?? '#0D47A1';
        $secondary_color = $config->colors->secondary ?? '#90CAF9';

        // Chart dimensions at 2x for retina displays
        $scale        = 2;
        $width        = 520 * $scale;
        $height       = 180 * $scale;
        $padding_left = 45 * $scale;
        $padding_right = 15 * $scale;
        $padding_top  = 12 * $scale;
        $padding_bottom = 40 * $scale;

        $chart_width  = $width - $padding_left - $padding_right;
        $chart_height = $height - $padding_top - $padding_bottom;

        $labels    = $data->chart_labels;
        $pageviews = $data->chart_pageviews;
        $visits    = $data->chart_visits;

        if ( empty( $labels ) || count( $labels ) < 2 ) {
            return null;
        }

        $count = count( $labels );

        // Find max value for scaling
        $max_value = max( max( $pageviews ), max( $visits ) );
        $max_value = $max_value > 0 ? $max_value : 1;

        // Round up to nice number for y-axis with more granular steps
        $magnitude = pow( 10, floor( log10( $max_value ) ) );
        $normalized = $max_value / $magnitude;
        if ( $normalized <= 2 ) {
            $max_value = ceil( $normalized * 5 ) / 5 * $magnitude; // Round to nearest 0.2
        } elseif ( $normalized <= 5 ) {
            $max_value = ceil( $normalized * 2 ) / 2 * $magnitude; // Round to nearest 0.5
        } else {
            $max_value = ceil( $normalized ) * $magnitude;
        }

        // Create image
        $img = imagecreatetruecolor( $width, $height );

        // Enable anti-aliasing
        imageantialias( $img, true );

        // Colors - softer, more professional palette
        $white      = imagecolorallocate( $img, 255, 255, 255 );
        $gray_light = imagecolorallocate( $img, 240, 242, 245 ); // Very light grid
        $gray_text  = imagecolorallocate( $img, 155, 165, 180 ); // Soft gray text

        // Parse primary color for visitors (brand color)
        $primary_rgb  = self::hex_to_rgb_array( $primary_color );
        $primary      = imagecolorallocate( $img, $primary_rgb[0], $primary_rgb[1], $primary_rgb[2] );
        $primary_fill = imagecolorallocatealpha( $img, $primary_rgb[0], $primary_rgb[1], $primary_rgb[2], 75 );

        // Pageviews uses soft gray tones
        $secondary      = imagecolorallocate( $img, 160, 165, 175 ); // Soft gray stroke
        $secondary_fill = imagecolorallocatealpha( $img, 210, 215, 225, 50 ); // Very light gray fill

        // Fill background
        imagefill( $img, 0, 0, $white );

        // Find a TrueType font
        $font_file = self::find_font();
        $font_size = 9 * $scale; // Font size in points (smaller for cleaner look)

        // Draw more Y-axis gridlines (5 lines)
        $num_gridlines = 5;
        for ( $i = 0; $i <= $num_gridlines; $i++ ) {
            $val = ( $i / $num_gridlines ) * $max_value;
            $y = $padding_top + $chart_height - ( $i / $num_gridlines ) * $chart_height;

            // Grid line
            imagesetthickness( $img, 1 );
            imageline( $img, $padding_left, (int) $y, $width - $padding_right, (int) $y, $gray_light );

            // Y-axis label (only show a few to avoid clutter)
            if ( $i % 2 == 0 || $i == $num_gridlines ) {
                $formatted = self::format_number_short( (int) $val );

                if ( $font_file ) {
                    // Use TrueType font
                    $bbox = imagettfbbox( $font_size, 0, $font_file, $formatted );
                    $text_width = $bbox[2] - $bbox[0];
                    $x_pos = $padding_left - 15 * $scale - $text_width;
                    imagettftext( $img, $font_size, 0, (int) $x_pos, (int) $y + $font_size / 3, $gray_text, $font_file, $formatted );
                } else {
                    // Fallback to built-in font
                    imagestring( $img, 5, $padding_left - 50 * $scale, (int) $y - 7, $formatted, $gray_text );
                }
            }
        }

        // Calculate points
        $pageview_points = [];
        $visit_points = [];

        for ( $i = 0; $i < $count; $i++ ) {
            $x = $padding_left + ( $i / ( $count - 1 ) ) * $chart_width;
            $pv_y = $padding_top + $chart_height - ( ( $pageviews[ $i ] / $max_value ) * $chart_height );
            $v_y  = $padding_top + $chart_height - ( ( $visits[ $i ] / $max_value ) * $chart_height );

            $pageview_points[] = [ (int) $x, (int) $pv_y ];
            $visit_points[]    = [ (int) $x, (int) $v_y ];
        }

        // Draw filled areas
        $baseline = $padding_top + $chart_height;

        // Pageviews area (gray - drawn first, behind)
        $pv_polygon = [];
        $pv_polygon[] = $padding_left;
        $pv_polygon[] = $baseline;
        foreach ( $pageview_points as $pt ) {
            $pv_polygon[] = $pt[0];
            $pv_polygon[] = $pt[1];
        }
        $pv_polygon[] = $padding_left + $chart_width;
        $pv_polygon[] = $baseline;
        imagefilledpolygon( $img, $pv_polygon, $secondary_fill );

        // Visits area (primary color - drawn second, in front)
        $v_polygon = [];
        $v_polygon[] = $padding_left;
        $v_polygon[] = $baseline;
        foreach ( $visit_points as $pt ) {
            $v_polygon[] = $pt[0];
            $v_polygon[] = $pt[1];
        }
        $v_polygon[] = $padding_left + $chart_width;
        $v_polygon[] = $baseline;
        imagefilledpolygon( $img, $v_polygon, $primary_fill );

        // Draw lines (thicker for visibility)
        imagesetthickness( $img, 2 * $scale );

        // Pageviews line (gray)
        for ( $i = 0; $i < count( $pageview_points ) - 1; $i++ ) {
            imageline( $img, $pageview_points[$i][0], $pageview_points[$i][1],
                           $pageview_points[$i+1][0], $pageview_points[$i+1][1], $secondary );
        }

        // Visits line (primary color)
        for ( $i = 0; $i < count( $visit_points ) - 1; $i++ ) {
            imageline( $img, $visit_points[$i][0], $visit_points[$i][1],
                           $visit_points[$i+1][0], $visit_points[$i+1][1], $primary );
        }

        imagesetthickness( $img, 1 );

        // X-axis labels - show fewer labels to avoid crowding
        $x_label_y = $padding_top + $chart_height + 18 * $scale;

        // Determine label interval - show max 6-7 labels
        $max_labels = 6;
        $label_interval = max( 1, ceil( $count / $max_labels ) );

        for ( $i = 0; $i < $count; $i += $label_interval ) {
            $x = $padding_left + ( $i / ( $count - 1 ) ) * $chart_width;
            $label = date( 'M j', strtotime( $labels[ $i ] ) );

            if ( $font_file ) {
                // Use TrueType font
                $bbox = imagettfbbox( $font_size, 0, $font_file, $label );
                $label_width = $bbox[2] - $bbox[0];
                imagettftext( $img, $font_size, 0, (int) ( $x - $label_width / 2 ), (int) $x_label_y, $gray_text, $font_file, $label );
            } else {
                // Fallback to built-in font
                $label_width = strlen( $label ) * 9;
                imagestring( $img, 5, (int) ( $x - $label_width / 2 ), (int) $x_label_y - 15, $label, $gray_text );
            }
        }

        // Output to WebP (much smaller file size than PNG)
        ob_start();
        imagewebp( $img, null, 85 ); // Quality 85 is a good balance
        $webp_data = ob_get_clean();

        imagedestroy( $img );

        return $webp_data;
    }

    /**
     * Convert hex color to RGB array
     *
     * @param string $hex Hex color
     * @return array [r, g, b]
     */
    private static function hex_to_rgb_array( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) == 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            hexdec( substr( $hex, 0, 2 ) ),
            hexdec( substr( $hex, 2, 2 ) ),
            hexdec( substr( $hex, 4, 2 ) ),
        ];
    }

    /**
     * Generate icon images for email embedding (checkmark, plus, minus)
     *
     * @param string $brand_color Primary brand color for checkmark icon
     * @return array Array of icon data with 'cid' and 'image' keys
     */
    public static function generate_icon_images( $brand_color = '#0D47A1' ) {
        $icons = [
            'checkmark' => [
                'cid'   => 'icon-checkmark-' . md5( $brand_color ),
                'image' => null,
            ],
            'plus' => [
                'cid'   => 'icon-plus',
                'image' => null,
            ],
            'minus' => [
                'cid'   => 'icon-minus',
                'image' => null,
            ],
        ];

        if ( ! extension_loaded( 'gd' ) ) {
            return $icons;
        }

        $size = 56; // 28px * 2 for retina

        // Generate checkmark icon (brand color)
        $icons['checkmark']['image'] = self::generate_circle_icon( $size, $brand_color, 'checkmark' );

        // Generate plus icon (green)
        $icons['plus']['image'] = self::generate_circle_icon( $size, '#48bb78', 'plus' );

        // Generate minus icon (red)
        $icons['minus']['image'] = self::generate_circle_icon( $size, '#e53e3e', 'minus' );

        return $icons;
    }

    /**
     * Generate a circle icon with symbol
     *
     * @param int    $size   Image size in pixels
     * @param string $color  Hex color for stroke
     * @param string $symbol Symbol type: 'checkmark', 'plus', or 'minus'
     * @return string|null PNG binary data or null on failure
     */
    private static function generate_circle_icon( $size, $color, $symbol ) {
        $img = imagecreatetruecolor( $size, $size );

        // Enable anti-aliasing
        imageantialias( $img, true );

        // Transparent background
        imagesavealpha( $img, true );
        $transparent = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
        imagefill( $img, 0, 0, $transparent );

        // Parse color
        $rgb = self::hex_to_rgb_array( $color );
        $stroke = imagecolorallocate( $img, $rgb[0], $rgb[1], $rgb[2] );

        // Draw circle
        $cx = $size / 2;
        $cy = $size / 2;
        $radius = ( $size / 2 ) - 4;
        $thickness = 3;

        // Draw thick circle using multiple arcs
        for ( $i = 0; $i < $thickness; $i++ ) {
            imagearc( $img, (int) $cx, (int) $cy, (int) ( $radius * 2 - $i ), (int) ( $radius * 2 - $i ), 0, 360, $stroke );
        }

        // Draw symbol
        imagesetthickness( $img, 4 );

        if ( $symbol === 'checkmark' ) {
            // Checkmark: two lines forming a check
            $x1 = $cx - $radius * 0.35;
            $y1 = $cy;
            $x2 = $cx - $radius * 0.05;
            $y2 = $cy + $radius * 0.3;
            $x3 = $cx + $radius * 0.4;
            $y3 = $cy - $radius * 0.25;

            imageline( $img, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $stroke );
            imageline( $img, (int) $x2, (int) $y2, (int) $x3, (int) $y3, $stroke );
        } elseif ( $symbol === 'plus' ) {
            // Plus: vertical and horizontal lines
            $line_len = $radius * 0.5;
            imageline( $img, (int) $cx, (int) ( $cy - $line_len ), (int) $cx, (int) ( $cy + $line_len ), $stroke );
            imageline( $img, (int) ( $cx - $line_len ), (int) $cy, (int) ( $cx + $line_len ), (int) $cy, $stroke );
        } elseif ( $symbol === 'minus' ) {
            // Minus: horizontal line
            $line_len = $radius * 0.5;
            imageline( $img, (int) ( $cx - $line_len ), (int) $cy, (int) ( $cx + $line_len ), (int) $cy, $stroke );
        }

        // Output to PNG
        ob_start();
        imagepng( $img, null, 9 );
        $png_data = ob_get_clean();

        imagedestroy( $img );

        return $png_data;
    }

    /**
     * Find a TrueType font file on the system
     *
     * @return string|false Font file path or false if not found
     */
    private static function find_font() {
        // Common font paths to check
        $font_paths = [
            // macOS
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/SFNSText.ttf',
            '/System/Library/Fonts/SFNS.ttf',
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            // Linux
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
            '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            // Windows
            'C:/Windows/Fonts/arial.ttf',
            'C:/Windows/Fonts/calibri.ttf',
        ];

        foreach ( $font_paths as $path ) {
            if ( file_exists( $path ) && is_readable( $path ) ) {
                return $path;
            }
        }

        return false;
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
        $padding_bottom = 70;

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

        // Create area fill paths
        $baseline = $padding_top + $chart_height;

        $pv_area_points = $pageview_points;
        array_unshift( $pv_area_points, $padding_left . ',' . $baseline );
        $pv_area_points[] = ( $padding_left + $chart_width ) . ',' . $baseline;

        $v_area_points = $visit_points;
        array_unshift( $v_area_points, $padding_left . ',' . $baseline );
        $v_area_points[] = ( $padding_left + $chart_width ) . ',' . $baseline;

        // Format labels for display
        $label_interval = max( 1, floor( $count / 5 ) );
        $x_labels_svg   = '';
        $x_label_y      = $padding_top + $chart_height + 20;
        $last_label_i   = 0;

        for ( $i = 0; $i < $count; $i += $label_interval ) {
            $x = $padding_left + ( $i / ( $count - 1 ) ) * $chart_width;
            $label = date( 'M j', strtotime( $labels[ $i ] ) );
            $x_labels_svg .= "<text x='{$x}' y='{$x_label_y}' text-anchor='middle' fill='#718096' font-size='11' font-family='Arial, sans-serif'>{$label}</text>";
            $last_label_i = $i;
        }

        $gap_to_end = $count - 1 - $last_label_i;
        if ( $gap_to_end >= $label_interval * 0.6 ) {
            $x = $padding_left + $chart_width;
            $label = date( 'M j', strtotime( $labels[ $count - 1 ] ) );
            $x_labels_svg .= "<text x='{$x}' y='{$x_label_y}' text-anchor='middle' fill='#718096' font-size='11' font-family='Arial, sans-serif'>{$label}</text>";
        }

        // Y-axis labels
        $y_labels_svg = '';
        $y_values = [ 0, $max_value / 2, $max_value ];
        foreach ( $y_values as $val ) {
            $y = $padding_top + $chart_height - ( ( $val / $max_value ) * $chart_height );
            $formatted = self::format_number_short( $val );
            $y_labels_svg .= "<text x='" . ( $padding_left - 10 ) . "' y='" . ( $y + 4 ) . "' text-anchor='end' fill='#718096' font-size='11' font-family='Arial, sans-serif'>{$formatted}</text>";
            $y_labels_svg .= "<line x1='{$padding_left}' y1='{$y}' x2='" . ( $width - $padding_right ) . "' y2='{$y}' stroke='#e2e8f0' stroke-width='1'/>";
        }

        // Convert hex to rgb for fill opacity
        $primary_rgb   = self::hex_to_rgb( $primary_color );
        $secondary_rgb = self::hex_to_rgb( $secondary_color );

        $svg = "<?xml version='1.0' encoding='UTF-8'?>
<svg xmlns='http://www.w3.org/2000/svg' width='{$width}' height='{$height}' viewBox='0 0 {$width} {$height}'>
    <rect width='{$width}' height='{$height}' fill='white'/>
    {$y_labels_svg}
    <polygon points='" . implode( ' ', $pv_area_points ) . "' fill='rgba({$secondary_rgb}, 0.3)'/>
    <polyline points='" . implode( ' ', $pageview_points ) . "' fill='none' stroke='{$secondary_color}' stroke-width='2.5'/>
    <polygon points='" . implode( ' ', $v_area_points ) . "' fill='rgba({$primary_rgb}, 0.3)'/>
    <polyline points='" . implode( ' ', $visit_points ) . "' fill='none' stroke='{$primary_color}' stroke-width='2.5'/>
    {$x_labels_svg}
    <rect x='" . ( $width / 2 - 80 ) . "' y='" . ( $height - 25 ) . "' width='12' height='12' fill='{$secondary_color}' rx='2'/>
    <text x='" . ( $width / 2 - 63 ) . "' y='" . ( $height - 15 ) . "' fill='#4a5568' font-size='11' font-family='Arial, sans-serif'>Pageviews</text>
    <rect x='" . ( $width / 2 + 20 ) . "' y='" . ( $height - 25 ) . "' width='12' height='12' fill='{$primary_color}' rx='2'/>
    <text x='" . ( $width / 2 + 37 ) . "' y='" . ( $height - 15 ) . "' fill='#4a5568' font-size='11' font-family='Arial, sans-serif'>Visitors</text>
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
     * Convert hex color to RGBA string
     *
     * @param string $hex Hex color
     * @param float $alpha Alpha value (0-1)
     * @return string RGBA value as "rgba(r, g, b, a)"
     */
    private static function hex_to_rgba( $hex, $alpha = 1 ) {
        $rgb = self::hex_to_rgb( $hex );
        return "rgba({$rgb}, {$alpha})";
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

        $config          = Configurations::get();
        $brand_color     = $config->colors->primary ?? '#0D47A1';
        $secondary_color = $config->colors->secondary ?? '#90CAF9';
        $logo_url        = $config->logo ?? '';
        $site_name       = get_bloginfo( 'name' );

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

        // Store chart image data for embedding
        $chart_image = null;

        if ( $has_stats ) {
            // Generate chart image if we have enough data points
            if ( ! empty( $data->chart_labels ) && count( $data->chart_labels ) > 1 ) {
                $chart_image = self::generate_chart_image( $data );

                if ( ! empty( $chart_image ) && is_array( $chart_image ) ) {
                    // Use CID reference for embedded image
                    $chart_cid = $chart_image['cid'];
                    $chart_html = "
                                    <!-- Stats Chart -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-bottom: 20px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; text-align: center;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; margin-bottom: 12px;'>Traffic Overview</div>
                                                    <img src='cid:{$chart_cid}' alt='Traffic Chart' style='width: 100%; max-width: 520px; height: auto;' />
                                                    <div style='margin-top: 15px; text-align: center;'>
                                                        <span style='display: inline-block; margin-right: 25px;'>
                                                            <span style='display: inline-block; width: 14px; height: 14px; background-color: #c8cdd7; border: 2px solid #787d87; border-radius: 2px; vertical-align: middle; margin-right: 8px;'></span>
                                                            <span style='font-size: 13px; color: #5a6070; vertical-align: middle;'>Pageviews</span>
                                                        </span>
                                                        <span style='display: inline-block;'>
                                                            <span style='display: inline-block; width: 14px; height: 14px; background-color: {$brand_color}; opacity: 0.7; border: 2px solid {$brand_color}; border-radius: 2px; vertical-align: middle; margin-right: 8px;'></span>
                                                            <span style='font-size: 13px; color: #5a6070; vertical-align: middle;'>Visitors</span>
                                                        </span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                    <!-- End Stats Chart -->
                    ";
                }
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

        // Generate icon images for email embedding (SVGs don't work in Gmail)
        $icons = self::generate_icon_images( $brand_color );

        // Icon HTML using CID references
        $checkmark_icon = "<img src='cid:{$icons['checkmark']['cid']}' alt='✓' width='28' height='28' style='vertical-align: middle; margin-right: 8px;' />";
        $plus_icon = "<img src='cid:{$icons['plus']['cid']}' alt='+' width='28' height='28' style='vertical-align: middle; margin-right: 8px;' />";
        $minus_icon = "<img src='cid:{$icons['minus']['cid']}' alt='-' width='28' height='28' style='vertical-align: middle; margin-right: 8px;' />";

        // Build plugin updates HTML
        $plugin_updates_html = '';
        if ( ! empty( $data->plugin_updates ) ) {
            $plugin_count = count( $data->plugin_updates );
            $plugin_word = $plugin_count === 1 ? 'plugin was' : 'plugins were';
            $plugin_rows = '';

            foreach ( $data->plugin_updates as $plugin ) {
                $title       = htmlspecialchars( $plugin['title'] ?: $plugin['name'] );
                $old_version = htmlspecialchars( $plugin['old_version'] );
                $new_version = htmlspecialchars( $plugin['new_version'] );
                $plugin_rows .= "
                    <tr>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 500; color: #2d3748; text-align: left;'>{$title}</td>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>
                            <span style='background: #edf2f7; padding: 4px 8px; border-radius: 4px; font-family: monospace;'>{$old_version}</span>
                            <span style='color: #a0aec0; margin: 0 6px;'>&rarr;</span>
                            <span style='background: " . self::hex_to_rgba( $brand_color, 0.1 ) . "; color: {$brand_color}; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: 600;'>{$new_version}</span>
                        </td>
                    </tr>";
            }

            $plugin_updates_html = "
                                    <!-- Plugin Updates -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 20px 10px 10px; text-align: center;'>
                                                {$checkmark_icon}<span style='font-size: 18px; font-weight: 600; color: #2d3748; vertical-align: middle;'>{$plugin_count} {$plugin_word} updated.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        {$plugin_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Build theme updates HTML
        $theme_updates_html = '';
        if ( ! empty( $data->theme_updates ) ) {
            $theme_count = count( $data->theme_updates );
            $theme_word = $theme_count === 1 ? 'theme was' : 'themes were';
            $theme_rows = '';

            foreach ( $data->theme_updates as $theme ) {
                $title       = htmlspecialchars( $theme['title'] ?: $theme['name'] );
                $old_version = htmlspecialchars( $theme['old_version'] );
                $new_version = htmlspecialchars( $theme['new_version'] );
                $theme_rows .= "
                    <tr>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 500; color: #2d3748; text-align: left;'>{$title}</td>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>
                            <span style='background: #edf2f7; padding: 4px 8px; border-radius: 4px; font-family: monospace;'>{$old_version}</span>
                            <span style='color: #a0aec0; margin: 0 6px;'>&rarr;</span>
                            <span style='background: " . self::hex_to_rgba( $brand_color, 0.1 ) . "; color: {$brand_color}; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: 600;'>{$new_version}</span>
                        </td>
                    </tr>";
            }

            $theme_updates_html = "
                                    <!-- Theme Updates -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 20px 10px 10px; text-align: center;'>
                                                {$checkmark_icon}<span style='font-size: 18px; font-weight: 600; color: #2d3748; vertical-align: middle;'>{$theme_count} {$theme_word} updated.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        {$theme_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Combine updates HTML
        $updates_html = $plugin_updates_html . $theme_updates_html;

        // Build plugins added HTML
        $plugins_added_html = '';
        if ( ! empty( $data->plugins_added ) ) {
            $plugin_count = count( $data->plugins_added );
            $plugin_word = $plugin_count === 1 ? 'plugin was' : 'plugins were';
            $plugin_rows = '';

            foreach ( $data->plugins_added as $plugin ) {
                $title   = htmlspecialchars( $plugin['title'] ?: $plugin['name'] );
                $version = htmlspecialchars( $plugin['version'] );
                $plugin_rows .= "
                    <tr>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 500; color: #2d3748; text-align: left;'>{$title}</td>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>
                            <span style='background: rgba(72, 187, 120, 0.1); color: #48bb78; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: 600;'>{$version}</span>
                        </td>
                    </tr>";
            }

            $plugins_added_html = "
                                    <!-- Plugins Added -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 20px 10px 10px; text-align: center;'>
                                                {$plus_icon}<span style='font-size: 18px; font-weight: 600; color: #2d3748; vertical-align: middle;'>{$plugin_count} {$plugin_word} added.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        {$plugin_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Build themes added HTML
        $themes_added_html = '';
        if ( ! empty( $data->themes_added ) ) {
            $theme_count = count( $data->themes_added );
            $theme_word = $theme_count === 1 ? 'theme was' : 'themes were';
            $theme_rows = '';

            foreach ( $data->themes_added as $theme ) {
                $title   = htmlspecialchars( $theme['title'] ?: $theme['name'] );
                $version = htmlspecialchars( $theme['version'] );
                $theme_rows .= "
                    <tr>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 500; color: #2d3748; text-align: left;'>{$title}</td>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>
                            <span style='background: rgba(72, 187, 120, 0.1); color: #48bb78; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: 600;'>{$version}</span>
                        </td>
                    </tr>";
            }

            $themes_added_html = "
                                    <!-- Themes Added -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 20px 10px 10px; text-align: center;'>
                                                {$plus_icon}<span style='font-size: 18px; font-weight: 600; color: #2d3748; vertical-align: middle;'>{$theme_count} {$theme_word} added.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        {$theme_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Combine added HTML
        $added_html = $plugins_added_html . $themes_added_html;

        // Build plugins removed HTML
        $plugins_removed_html = '';
        if ( ! empty( $data->plugins_removed ) ) {
            $plugin_count = count( $data->plugins_removed );
            $plugin_word = $plugin_count === 1 ? 'plugin was' : 'plugins were';
            $plugin_rows = '';

            foreach ( $data->plugins_removed as $plugin ) {
                $title   = htmlspecialchars( $plugin['title'] ?: $plugin['name'] );
                $version = htmlspecialchars( $plugin['version'] );
                $plugin_rows .= "
                    <tr>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 500; color: #2d3748; text-align: left;'>{$title}</td>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>
                            <span style='background: rgba(229, 62, 62, 0.1); color: #e53e3e; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: 600;'>{$version}</span>
                        </td>
                    </tr>";
            }

            $plugins_removed_html = "
                                    <!-- Plugins Removed -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 20px 10px 10px; text-align: center;'>
                                                {$minus_icon}<span style='font-size: 18px; font-weight: 600; color: #2d3748; vertical-align: middle;'>{$plugin_count} {$plugin_word} removed.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        {$plugin_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Build themes removed HTML
        $themes_removed_html = '';
        if ( ! empty( $data->themes_removed ) ) {
            $theme_count = count( $data->themes_removed );
            $theme_word = $theme_count === 1 ? 'theme was' : 'themes were';
            $theme_rows = '';

            foreach ( $data->themes_removed as $theme ) {
                $title   = htmlspecialchars( $theme['title'] ?: $theme['name'] );
                $version = htmlspecialchars( $theme['version'] );
                $theme_rows .= "
                    <tr>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 14px; font-weight: 500; color: #2d3748; text-align: left;'>{$title}</td>
                        <td style='padding: 14px 12px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #718096; text-align: right; white-space: nowrap;'>
                            <span style='background: rgba(229, 62, 62, 0.1); color: #e53e3e; padding: 4px 8px; border-radius: 4px; font-family: monospace; font-weight: 600;'>{$version}</span>
                        </td>
                    </tr>";
            }

            $themes_removed_html = "
                                    <!-- Themes Removed -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 20px 10px 10px; text-align: center;'>
                                                {$minus_icon}<span style='font-size: 18px; font-weight: 600; color: #2d3748; vertical-align: middle;'>{$theme_count} {$theme_word} removed.</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                                                        {$theme_rows}
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>";
        }

        // Combine removed HTML
        $removed_html = $plugins_removed_html . $themes_removed_html;

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

        // Build visual captures HTML (calendar-style week view)
        $visual_captures_html = '';
        if ( ! empty( $data->visual_captures ) ) {
            $total_captures = count( $data->visual_captures );

            // Group captures by date (Y-m-d) for lookup
            $captures_by_date = [];
            foreach ( $data->visual_captures as $capture ) {
                $date_key = date( 'Y-m-d', $capture['date'] );
                if ( ! isset( $captures_by_date[ $date_key ] ) ) {
                    $captures_by_date[ $date_key ] = $capture;
                }
            }

            // Find date range from the report
            $start_ts = strtotime( $data->start_date );
            $end_ts   = strtotime( $data->end_date );

            // Adjust to start on Sunday of the first week
            $start_dow = date( 'w', $start_ts );
            $calendar_start = strtotime( "-{$start_dow} days", $start_ts );

            // Adjust to end on Saturday of the last week
            $end_dow = date( 'w', $end_ts );
            $days_to_saturday = ( 6 - $end_dow );
            $calendar_end = strtotime( "+{$days_to_saturday} days", $end_ts );

            // Build calendar weeks
            $calendar_html = '';
            $current_date = $calendar_start;
            $current_month = '';
            $start_month_year = date( 'F Y', $start_ts );

            while ( $current_date <= $calendar_end ) {
                // Find the dominant month for this week (the month that has more days in it)
                $week_dates = [];
                $temp_date = $current_date;
                for ( $d = 0; $d < 7; $d++ ) {
                    $week_dates[] = $temp_date;
                    $temp_date = strtotime( '+1 day', $temp_date );
                }

                // Count days per month in this week
                $month_counts = [];
                foreach ( $week_dates as $wd ) {
                    $m = date( 'F Y', $wd );
                    $month_counts[ $m ] = ( $month_counts[ $m ] ?? 0 ) + 1;
                }
                arsort( $month_counts );
                $dominant_month = key( $month_counts );

                // For the first week, use the report's start month if dominant month is earlier
                if ( $current_month === '' && strtotime( $dominant_month ) < $start_ts ) {
                    $dominant_month = $start_month_year;
                }

                // Add month header if entering a new month
                if ( $dominant_month !== $current_month ) {
                    $current_month = $dominant_month;
                    $calendar_html .= "
                        <tr>
                            <td colspan='7' style='padding: 15px 12px 8px; text-align: left; font-size: 14px; font-weight: 600; color: #2d3748; border-bottom: 2px solid #e2e8f0;'>{$current_month}</td>
                        </tr>";
                }

                $week_html = '<tr>';

                for ( $day = 0; $day < 7; $day++ ) {
                    $date_key    = date( 'Y-m-d', $current_date );
                    $day_num     = date( 'j', $current_date );
                    $is_in_range = ( $current_date >= $start_ts && $current_date <= $end_ts );

                    if ( isset( $captures_by_date[ $date_key ] ) && $is_in_range ) {
                        // Has capture - make it a link
                        $url = htmlspecialchars( $captures_by_date[ $date_key ]['url'] );
                        $week_html .= "
                            <td style='padding: 6px 4px; text-align: center; border: 1px solid #edf2f7;'>
                                <a href='{$url}' target='_blank' style='display: block; padding: 8px 4px; background-color: " . self::hex_to_rgba( $brand_color, 0.1 ) . "; border-radius: 4px; color: {$brand_color}; text-decoration: none; font-size: 13px; font-weight: 600;'>{$day_num}</a>
                            </td>";
                    } elseif ( $is_in_range ) {
                        // In range but no capture
                        $week_html .= "
                            <td style='padding: 6px 4px; text-align: center; border: 1px solid #edf2f7;'>
                                <span style='display: block; padding: 8px 4px; color: #cbd5e0; font-size: 13px;'>{$day_num}</span>
                            </td>";
                    } else {
                        // Outside report range
                        $week_html .= "
                            <td style='padding: 6px 4px; text-align: center; border: 1px solid #edf2f7; background-color: #f9fafb;'>
                                <span style='display: block; padding: 8px 4px; color: #e2e8f0; font-size: 13px;'>{$day_num}</span>
                            </td>";
                    }

                    $current_date = strtotime( '+1 day', $current_date );
                }

                $week_html .= '</tr>';
                $calendar_html .= $week_html;
            }

            $visual_captures_html = "
                                    <!-- Visual Captures -->
                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='margin-top: 30px;'>
                                        <tr>
                                            <td style='padding: 10px;'>
                                                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;'>
                                                    <div style='font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; padding: 15px 15px 10px; text-align: center;'>Visual Captures ({$total_captures})</div>
                                                    <p style='font-size: 13px; color: #718096; margin: 0; padding: 0 15px 15px; text-align: center;'>Whenever changes are detected, we take a full-sized screenshot of the homepage. Highlighted days are clickable.</p>
                                                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='table-layout: fixed;'>
                                                        <tr style='background-color: #f7fafc;'>
                                                            <td style='padding: 8px 4px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 14.28%;'>Sun</td>
                                                            <td style='padding: 8px 4px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 14.28%;'>Mon</td>
                                                            <td style='padding: 8px 4px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 14.28%;'>Tue</td>
                                                            <td style='padding: 8px 4px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 14.28%;'>Wed</td>
                                                            <td style='padding: 8px 4px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 14.28%;'>Thu</td>
                                                            <td style='padding: 8px 4px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 14.28%;'>Fri</td>
                                                            <td style='padding: 8px 4px; text-align: center; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; width: 14.28%;'>Sat</td>
                                                        </tr>
                                                        {$calendar_html}
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
                                                    <div style='font-size: 28px; font-weight: 700; color: #2d3748;'>{$backups_formatted}</div>" . ( ! empty( $data->backups_since ) ? "<div style='font-size: 11px; color: #718096; margin-top: 6px;'>since {$data->backups_since}</div>" : "" ) . "<div style='font-size: 10px; color: #a0aec0; margin-top: 4px;'>Scheduled Daily</div>
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

                                    {$visual_captures_html}

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

        return (object) [
            'html'        => $message,
            'chart_image' => $chart_image,
            'icons'       => $icons,
        ];
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
        $result  = self::render( $data );
        $html    = $result->html;

        // Build subject with shorter date format and site name for uniqueness
        $before_ts = ! empty( $start_date ) ? strtotime( $start_date ) : strtotime( "-30 days" );
        $after_ts  = ! empty( $end_date ) ? strtotime( $end_date ) : time();
        $date_range = date( 'M j', $before_ts ) . ' - ' . date( 'M j, Y', $after_ts );

        // Include site name(s) for uniqueness (prevents email grouping)
        $site_label = count( $data->sites ) === 1
            ? $data->sites[0]
            : count( $data->sites ) . ' sites';

        $subject = "Maintenance Report: {$site_label} ({$date_range})";

        // If we have a chart image, embed using CID (required for Gmail)
        $has_chart = ! empty( $result->chart_image ) && is_array( $result->chart_image ) && ! empty( $result->chart_image['image'] );

        if ( ! $has_chart ) {
            // No image available - remove the chart section from email
            $html = preg_replace(
                '/<!-- Stats Chart -->.*?<!-- End Stats Chart -->/s',
                '',
                $html
            );
        }

        // Collect all images to embed
        $images_to_embed = [];

        if ( $has_chart ) {
            $images_to_embed[] = [
                'data'     => $result->chart_image['image'],
                'cid'      => $result->chart_image['cid'],
                'filename' => 'traffic-chart.webp',
                'mimetype' => 'image/webp',
            ];
        }

        // Add icon images
        if ( ! empty( $result->icons ) ) {
            foreach ( $result->icons as $icon_name => $icon ) {
                if ( ! empty( $icon['image'] ) ) {
                    $images_to_embed[] = [
                        'data'     => $icon['image'],
                        'cid'      => $icon['cid'],
                        'filename' => "icon-{$icon_name}.png",
                        'mimetype' => 'image/png',
                    ];
                }
            }
        }

        // Use custom send with embedded image support
        self::send_with_embedded_images( $recipient, $subject, $html, $images_to_embed );

        return true;
    }

    /**
     * Send email with multiple CID-embedded images
     *
     * @param string $to        Recipient email
     * @param string $subject   Email subject
     * @param string $html      HTML content (should have src='cid:xxx' references)
     * @param array  $images    Array of image data arrays with 'data', 'cid', 'filename', 'mimetype' keys
     * @return bool Success
     */
    private static function send_with_embedded_images( $to, $subject, $html, $images = [] ) {
        // Prepare Mailer settings (for SMTP config)
        Mailer::prepare();

        $embed_callback = null;

        // If we have images, embed them via phpmailer_init hook
        if ( ! empty( $images ) ) {
            // Add hook to embed images when PHPMailer is initialized
            $embed_callback = function( $phpmailer ) use ( $images ) {
                foreach ( $images as $image ) {
                    if ( ! empty( $image['data'] ) && ! empty( $image['cid'] ) ) {
                        $phpmailer->addStringEmbeddedImage(
                            $image['data'],
                            $image['cid'],
                            $image['filename'] ?? 'image.png',
                            'base64',
                            $image['mimetype'] ?? 'image/png'
                        );
                    }
                }
            };

            add_action( 'phpmailer_init', $embed_callback, 999 );
        }

        // Set HTML content type
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        // Send using wp_mail (HTML already has src='cid:xxx' from render())
        $result = wp_mail( $to, $subject, $html, $headers );

        // Remove our hook after sending
        if ( $embed_callback !== null ) {
            remove_action( 'phpmailer_init', $embed_callback, 999 );
        }

        return $result;
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
     * For preview, we use base64 data URI which works in browsers
     *
     * @param array  $site_ids   Array of site IDs
     * @param string $start_date Start date for stats
     * @param string $end_date   End date for stats
     * @return string HTML content
     */
    public static function preview( $site_ids = [], $start_date = "", $end_date = "" ) {
        $data   = self::generate( $site_ids, $start_date, $end_date );
        $result = self::render( $data );
        $html   = $result->html;

        // For preview, convert CID references to data URIs (works in browsers)

        // Convert chart image CID to data URI
        if ( ! empty( $result->chart_image ) && is_array( $result->chart_image ) && ! empty( $result->chart_image['image'] ) ) {
            $base64 = base64_encode( $result->chart_image['image'] );
            $data_uri = 'data:image/webp;base64,' . $base64;

            $html = str_replace(
                "src='cid:" . $result->chart_image['cid'] . "'",
                "src='" . $data_uri . "'",
                $html
            );
        }

        // Convert icon CIDs to data URIs
        if ( ! empty( $result->icons ) ) {
            foreach ( $result->icons as $icon ) {
                if ( ! empty( $icon['image'] ) && ! empty( $icon['cid'] ) ) {
                    $base64 = base64_encode( $icon['image'] );
                    $data_uri = 'data:image/png;base64,' . $base64;

                    $html = str_replace(
                        "src='cid:" . $icon['cid'] . "'",
                        "src='" . $data_uri . "'",
                        $html
                    );
                }
            }
        }

        return $html;
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
