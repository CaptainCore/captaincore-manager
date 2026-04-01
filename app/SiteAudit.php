<?php

namespace CaptainCore;

class SiteAudit {

    protected $site_audit_id = "";

    public function __construct( $site_audit_id = "" ) {
        $this->site_audit_id = $site_audit_id;
    }

    public function get() {
        $audit = ( new SiteAudits )->get( $this->site_audit_id );
        if ( ! $audit ) {
            return null;
        }

        $site        = ( new Sites )->get( $audit->site_id );
        $environment = ( new Environments )->get( $audit->environment_id );

        $audit->home_url    = $environment ? $environment->home_url : '';
        $audit->environment = $environment ? $environment->environment : '';
        $audit->site_name   = $site ? $site->name : '';
        if ( empty( $audit->site_name ) && ! empty( $audit->home_url ) ) {
            $audit->site_name = preg_replace( '/^www\./', '', parse_url( $audit->home_url, PHP_URL_HOST ) ?: '' );
        }
        $audit->findings    = $this->findings();

        // Decode JSON fields
        $audit->scan_checks       = json_decode( $audit->scan_checks ) ?: [];
        $audit->site_config       = json_decode( $audit->site_config ) ?: [];
        $audit->admin_accounts    = json_decode( $audit->admin_accounts ) ?: [];
        $audit->timeline_events   = json_decode( $audit->timeline_events ) ?: [];
        $audit->dashboard_metrics = json_decode( $audit->dashboard_metrics ?? 'null' ) ?: null;
        $audit->sections          = json_decode( $audit->sections ?? 'null' ) ?: [];
        $audit->section_order     = json_decode( $audit->section_order ?? 'null' ) ?: [];

        return $audit;
    }

    public function findings() {
        return ( new SiteAuditFindings )->where( [ 'site_audit_id' => $this->site_audit_id ] );
    }

    public function add_finding( $data = [] ) {
        $time_now = date( 'Y-m-d H:i:s' );
        $data     = array_merge( $data, [
            'site_audit_id' => $this->site_audit_id,
            'created_at'        => $time_now,
            'updated_at'        => $time_now,
        ] );
        $finding_id = ( new SiteAuditFindings )->insert( $data );

        // Update issues count on audit
        $findings = $this->findings();
        ( new SiteAudits )->update(
            [ 'issues_count' => count( $findings ), 'updated_at' => $time_now ],
            [ 'site_audit_id' => $this->site_audit_id ]
        );

        return $finding_id;
    }

    public function resolve_finding( $finding_id, $resolution = '' ) {
        $time_now = date( 'Y-m-d H:i:s' );
        ( new SiteAuditFindings )->update(
            [
                'status'      => 'resolved',
                'resolution'  => $resolution,
                'resolved_at' => $time_now,
                'updated_at'  => $time_now,
            ],
            [ 'site_audit_finding_id' => $finding_id ]
        );

        // Check if all findings are resolved, update audit status
        $open_findings = ( new SiteAuditFindings )->where( [
            'site_audit_id' => $this->site_audit_id,
            'status'            => 'open',
        ] );
        if ( count( $open_findings ) === 0 ) {
            ( new SiteAudits )->update(
                [ 'status' => 'remediated', 'updated_at' => $time_now ],
                [ 'site_audit_id' => $this->site_audit_id ]
            );
        }
    }

    public function complete( $status = 'clean' ) {
        $time_now = date( 'Y-m-d H:i:s' );
        ( new SiteAudits )->update(
            [
                'status'       => $status,
                'updated_at'   => $time_now,
                'completed_at' => $time_now,
            ],
            [ 'site_audit_id' => $this->site_audit_id ]
        );
    }

    public function publish() {
        $audit = $this->get();
        if ( ! $audit ) {
            return null;
        }

        $date_prefix = date( 'Y-m-d', strtotime( $audit->created_at ) );
        $slug        = sanitize_title( $audit->site_name );
        $filename    = "{$date_prefix}_{$slug}-security-audit.html";
        $html        = $this->render_html();
        $reports_dir = ABSPATH . 'reports';
        $file_path   = $reports_dir . '/' . $filename;

        file_put_contents( $file_path, $html );

        $time_now = date( 'Y-m-d H:i:s' );
        ( new SiteAudits )->update(
            [ 'report_path' => $filename, 'updated_at' => $time_now ],
            [ 'site_audit_id' => $this->site_audit_id ]
        );

        return $filename;
    }

    public function unpublish() {
        $audit = ( new SiteAudits )->get( $this->site_audit_id );
        if ( ! $audit || ! $audit->report_path ) {
            return false;
        }

        $file_path = ABSPATH . 'reports/' . $audit->report_path;
        if ( file_exists( $file_path ) ) {
            unlink( $file_path );
        }

        $time_now = date( 'Y-m-d H:i:s' );
        ( new SiteAudits )->update(
            [ 'report_path' => null, 'updated_at' => $time_now ],
            [ 'site_audit_id' => $this->site_audit_id ]
        );

        return true;
    }

    private function sort_findings( $findings ) {
        $severity_order = [ 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3 ];
        usort( $findings, function( $a, $b ) use ( $severity_order ) {
            $a_resolved = ( $a->status ?? '' ) === 'resolved' ? 0 : 1;
            $b_resolved = ( $b->status ?? '' ) === 'resolved' ? 0 : 1;
            if ( $a_resolved !== $b_resolved ) {
                return $a_resolved - $b_resolved;
            }
            $a_sev = $severity_order[ $a->severity ?? 'low' ] ?? 99;
            $b_sev = $severity_order[ $b->severity ?? 'low' ] ?? 99;
            return $a_sev - $b_sev;
        } );
        return $findings;
    }

    public function render_html() {
        $audit = $this->get();
        if ( ! $audit ) {
            return '';
        }

        $site_name = esc_html( $audit->site_name );
        $date      = date( 'F j, Y', strtotime( $audit->created_at ) );
        $css       = self::report_css();

        // Report type title
        $report_type = $audit->report_type ?? 'security_audit';
        $title_map   = [
            'security_audit'  => 'Security Audit Report',
            'malware_incident' => 'Malware Incident Report',
            'performance_review' => 'Performance Review',
            'debug_report'    => 'Debug Report',
            'incident_report' => 'Incident Report',
        ];
        $report_title = ! empty( $audit->report_title ) ? esc_html( $audit->report_title ) : ( $title_map[ $report_type ] ?? ucwords( str_replace( '_', ' ', $report_type ) ) );

        $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        $html .= "<meta charset=\"UTF-8\">\n";
        $html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "<title>{$report_title} &mdash; {$site_name}</title>\n";
        $html .= "<style>\n{$css}\n</style>\n</head>\n<body>\n\n";

        // Hero
        $html .= "<div class=\"hero\">\n";
        $html .= "  <h1>{$report_title}</h1>\n";
        $html .= "  <p>{$site_name} &mdash; {$date}</p>\n";
        $html .= "</div>\n\n";

        $html .= "<div class=\"container\">\n\n";

        // Dashboard
        if ( ! empty( $audit->dashboard_metrics ) && is_array( $audit->dashboard_metrics ) ) {
            // Custom dashboard metrics
            $html .= "<div class=\"dashboard\">\n  <div class=\"dashboard-grid\">\n";
            foreach ( $audit->dashboard_metrics as $metric ) {
                $val   = esc_html( $metric->value ?? '' );
                $label = esc_html( $metric->label ?? '' );
                $class = esc_attr( $metric->class ?? '' );
                $cls   = $class ? " {$class}" : '';
                $html .= "    <div class=\"dash-card\">\n      <div class=\"dash-value{$cls}\">{$val}</div>\n      <div class=\"dash-label\">{$label}</div>\n    </div>\n";
            }
            $html .= "  </div>\n</div>\n\n";
        } else {
            // Default dashboard
            $fs_class  = $audit->filesystem_status === 'clean' ? 'clean' : ( $audit->filesystem_status === 'critical' ? 'critical' : 'warn' );
            $fs_label  = strtoupper( esc_html( $audit->filesystem_status ?: 'N/A' ) );
            $wp_ver    = esc_html( $audit->wp_version ?: 'N/A' );
            $issues    = (int) $audit->issues_count;
            $issues_cl = $issues === 0 ? 'clean' : 'warn';
            $plugins   = (int) $audit->plugins_count;

            $html .= "<div class=\"dashboard\">\n  <div class=\"dashboard-grid\">\n";
            $html .= "    <div class=\"dash-card\">\n      <div class=\"dash-value {$fs_class}\">{$fs_label}</div>\n      <div class=\"dash-label\">Filesystem</div>\n    </div>\n";
            $html .= "    <div class=\"dash-card\">\n      <div class=\"dash-value\">{$wp_ver}</div>\n      <div class=\"dash-label\">WP Version</div>\n    </div>\n";
            $html .= "    <div class=\"dash-card\">\n      <div class=\"dash-value {$issues_cl}\">{$issues}</div>\n      <div class=\"dash-label\">Issues Found</div>\n    </div>\n";
            $html .= "    <div class=\"dash-card\">\n      <div class=\"dash-value\">{$plugins}</div>\n      <div class=\"dash-label\">Plugins</div>\n    </div>\n";
            $html .= "  </div>\n</div>\n\n";
        }

        // Summary callout
        if ( ! empty( $audit->summary ) ) {
            $html .= "<div class=\"callout green\">" . esc_html( $audit->summary ) . "</div>\n\n";
        }

        $section_num = 0;
        $colors      = [ 'c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c7', 'c8' ];

        // Build renderable section map
        $section_renderers = [];

        if ( ! empty( $audit->scan_checks ) ) {
            $section_renderers['scan-results'] = function() use ( $audit, &$section_num ) {
                $out = $this->render_section( ++$section_num, 'Scan Results', 'Filesystem integrity, malware signatures, frontend analysis, database scan, logs, and user accounts.', 'c1' );
                $out .= "  <div class=\"card\">\n    <ul class=\"check-list\">\n";
                $icon_order  = [ 'pass' => 0, 'warn' => 1, 'fail' => 2 ];
                $sorted_checks = (array) $audit->scan_checks;
                usort( $sorted_checks, function( $a, $b ) use ( $icon_order ) {
                    return ( $icon_order[ $a->icon ?? 'pass' ] ?? 3 ) - ( $icon_order[ $b->icon ?? 'pass' ] ?? 3 );
                } );
                foreach ( $sorted_checks as $check ) {
                    $icon_class = esc_attr( $check->icon ?? 'pass' );
                    $icon_char  = $icon_class === 'pass' ? '&#10003;' : ( $icon_class === 'fail' ? '&#10007;' : '&#9888;' );
                    $label      = $check->label ?? '';
                    $out .= "      <li><span class=\"icon {$icon_class}\">{$icon_char}</span> {$label}</li>\n";
                }
                $out .= "    </ul>\n  </div>\n</div>\n\n";
                return $out;
            };
        }

        if ( ! empty( $audit->findings ) ) {
            $section_renderers['findings'] = function() use ( $audit, &$section_num ) {
                $sorted = $this->sort_findings( (array) $audit->findings );
                $out = $this->render_section( ++$section_num, 'Issues Found', 'Individual findings with severity ratings and evidence.', 'c3' );
                foreach ( $sorted as $finding ) {
                    $out .= $this->render_finding_card( $finding );
                }
                $out .= "</div>\n\n";
                return $out;
            };
        }

        if ( ! empty( $audit->admin_accounts ) && is_array( $audit->admin_accounts ) ) {
            $section_renderers['admin-accounts'] = function() use ( $audit, &$section_num ) {
                $out = $this->render_section( ++$section_num, 'Administrator Accounts', count( $audit->admin_accounts ) . ' administrator accounts.', 'c4' );
                $out .= "  <div class=\"card\">\n    <table>\n";
                $out .= "      <tr><th>ID</th><th>Username</th><th>Email</th><th>Registered</th></tr>\n";
                foreach ( $audit->admin_accounts as $account ) {
                    $uid   = esc_html( $account->user_id ?? '' );
                    $uname = esc_html( $account->username ?? '' );
                    $email = esc_html( $account->email ?? '' );
                    $reg   = esc_html( $account->registered ?? '' );
                    $out .= "      <tr><td>{$uid}</td><td>{$uname}</td><td>{$email}</td><td>{$reg}</td></tr>\n";
                }
                $out .= "    </table>\n  </div>\n</div>\n\n";
                return $out;
            };
        }

        if ( ! empty( $audit->site_config ) ) {
            $section_renderers['site-config'] = function() use ( $audit, &$section_num ) {
                $out = $this->render_section( ++$section_num, 'Site Configuration', 'Current WordPress configuration and security settings.', 'c5' );
                $out .= "  <div class=\"card\">\n    <table>\n";
                foreach ( $audit->site_config as $config ) {
                    $key    = esc_html( $config->key ?? '' );
                    $value  = esc_html( $config->value ?? '' );
                    $status = $config->status ?? null;
                    $class  = $status ? " class=\"{$status}\"" : '';
                    $out .= "      <tr><td><strong>{$key}</strong></td><td{$class}>{$value}</td></tr>\n";
                }
                $out .= "    </table>\n  </div>\n</div>\n\n";
                return $out;
            };
        }

        if ( ! empty( $audit->timeline_events ) ) {
            $section_renderers['timeline'] = function() use ( $audit, &$section_num ) {
                $out = $this->render_section( ++$section_num, 'Attack Timeline', 'Reconstructed attacker activity based on logs, file timestamps, and user events.', 'c8' );
                $out .= "  <div class=\"card\">\n    <ul class=\"timeline\">\n";
                foreach ( $audit->timeline_events as $event ) {
                    $type_class = esc_attr( $event->type ?? '' );
                    $timestamp  = esc_html( $event->timestamp ?? '' );
                    $desc       = esc_html( $event->description ?? '' );
                    $out .= "      <li class=\"{$type_class}\">\n";
                    $out .= "        <div class=\"time\">{$timestamp}</div>\n";
                    $out .= "        <div class=\"desc\">{$desc}</div>\n";
                    $out .= "      </li>\n";
                }
                $out .= "    </ul>\n  </div>\n</div>\n\n";
                return $out;
            };
        }

        // Custom sections keyed by slugified title
        if ( ! empty( $audit->sections ) && is_array( $audit->sections ) ) {
            foreach ( $audit->sections as $i => $section ) {
                $key = sanitize_title( $section->title ?? "section-{$i}" );
                $section_renderers[ $key ] = function() use ( $section, $i, $colors, &$section_num ) {
                    $title = esc_html( $section->title ?? 'Additional Information' );
                    $desc  = esc_html( $section->description ?? '' );
                    $color = $colors[ $i % 8 ];
                    $out   = $this->render_section( ++$section_num, $title, $desc, $color );
                    $out  .= $this->render_section_content( $section->content ?? [] );
                    return $out;
                };
            }
        }

        // Render sections in specified order, or default order
        $order = ! empty( $audit->section_order ) && is_array( $audit->section_order )
            ? $audit->section_order
            : array_keys( $section_renderers );

        foreach ( $order as $key ) {
            if ( isset( $section_renderers[ $key ] ) ) {
                $html .= $section_renderers[ $key ]();
            }
        }

        // Footer
        $html .= "<div class=\"footer\">\n";
        $html .= "  Generated by <a href=\"https://anchor.host\">Anchor Hosting</a> &mdash; {$date}\n";
        $html .= "</div>\n\n";
        $html .= "</div>\n</body>\n</html>";

        return $html;
    }

    private function render_section( $num, $title, $desc, $color ) {
        $title = esc_html( $title );
        $html  = "<div class=\"section\">\n";
        $html .= "  <div class=\"section-header\">\n";
        $html .= "    <div class=\"section-num\" style=\"background:var(--{$color})\">{$num}</div>\n";
        $html .= "    <div class=\"section-title\">{$title}</div>\n";
        $html .= "  </div>\n";
        $html .= "  <p class=\"section-desc\">{$desc}</p>\n\n";
        return $html;
    }

    private function render_finding_card( $finding ) {
        $severity = esc_attr( $finding->severity ?? 'low' );
        $title    = esc_html( $finding->title ?? '' );
        $desc     = $finding->description ?? '';
        $status   = $finding->status ?? 'open';

        $html = "  <div class=\"card\">\n    <div class=\"card-header\">\n";
        $html .= "      <span class=\"card-tag {$severity}\">" . ucfirst( $severity ) . "</span>\n";
        if ( $status === 'resolved' ) {
            $html .= "      <span class=\"card-tag resolved\">Resolved</span>\n";
        }
        $html .= "      <div class=\"card-title\">{$title}</div>\n";
        $html .= "    </div>\n";

        if ( $desc ) {
            $html .= "    {$desc}\n";
        }

        // Render evidence blocks
        $evidence = json_decode( $finding->evidence ?? '[]' );
        if ( is_array( $evidence ) ) {
            foreach ( $evidence as $ev ) {
                $type = $ev->type ?? 'evidence';
                switch ( $type ) {
                    case 'code':
                        $label = esc_html( $ev->label ?? '' );
                        if ( $label ) {
                            $html .= "    <div class=\"evidence-label\">{$label}</div>\n";
                        }
                        $html .= "    <pre><code>" . esc_html( $ev->content ?? '' ) . "</code></pre>\n";
                        break;

                    case 'callout':
                        $variant = esc_attr( $ev->variant ?? 'blue' );
                        $html .= "    <div class=\"callout {$variant}\">" . esc_html( $ev->content ?? '' ) . "</div>\n";
                        break;

                    case 'diff':
                        $label = esc_html( $ev->label ?? '' );
                        $html .= "    <div class=\"diff\">\n";
                        if ( $label ) {
                            $html .= "      <div class=\"diff-header\">{$label}</div>\n";
                        }
                        $html .= "      <div class=\"diff-body\">\n";
                        $lines = $ev->lines ?? [];
                        foreach ( $lines as $line ) {
                            $line_type  = esc_attr( $line->type ?? 'ctx' );
                            $line_text  = esc_html( $line->text ?? '' );
                            $html .= "        <div class=\"diff-line {$line_type}\">{$line_text}</div>\n";
                        }
                        $html .= "      </div>\n    </div>\n";
                        break;

                    case 'file-tree':
                        $label = esc_html( $ev->label ?? '' );
                        if ( $label ) {
                            $html .= "    <div class=\"evidence-label\">{$label}</div>\n";
                        }
                        $html .= "    <div class=\"file-tree\">" . ( $ev->content ?? '' ) . "</div>\n";
                        break;

                    case 'stats':
                        $html .= "    <div class=\"stat-row\">\n";
                        $items = $ev->items ?? [];
                        foreach ( $items as $item ) {
                            $val     = esc_html( $item->value ?? '' );
                            $lbl     = esc_html( $item->label ?? '' );
                            $variant = esc_attr( $item->variant ?? '' );
                            $var_cls = $variant ? " {$variant}" : '';
                            $html .= "      <div class=\"stat-card\">\n";
                            $html .= "        <div class=\"stat-value{$var_cls}\">{$val}</div>\n";
                            $html .= "        <div class=\"stat-label\">{$lbl}</div>\n";
                            $html .= "      </div>\n";
                        }
                        $html .= "    </div>\n";
                        break;

                    case 'ioc-list':
                        $label   = esc_html( $ev->label ?? '' );
                        $columns = intval( $ev->columns ?? 3 );
                        if ( $label ) {
                            $html .= "    <div class=\"evidence-label\">{$label}</div>\n";
                        }
                        $html .= "    <div class=\"ioc-grid\" style=\"column-count:{$columns}\">\n";
                        $items = $ev->items ?? [];
                        foreach ( $items as $item ) {
                            $html .= "      <span>" . esc_html( $item ) . "</span>\n";
                        }
                        $html .= "    </div>\n";
                        break;

                    case 'table':
                        $label   = esc_html( $ev->label ?? '' );
                        if ( $label ) {
                            $html .= "    <div class=\"evidence-label\">{$label}</div>\n";
                        }
                        $html .= "    <table>\n";
                        $headers = $ev->headers ?? [];
                        if ( ! empty( $headers ) ) {
                            $html .= "      <tr>";
                            foreach ( $headers as $h ) {
                                $html .= "<th>" . esc_html( $h ) . "</th>";
                            }
                            $html .= "</tr>\n";
                        }
                        $rows = $ev->rows ?? [];
                        foreach ( $rows as $row ) {
                            $html .= "      <tr>";
                            foreach ( $row as $cell ) {
                                if ( is_object( $cell ) || is_array( $cell ) ) {
                                    $cell = (object) $cell;
                                    $val  = esc_html( $cell->value ?? '' );
                                    $cls  = esc_attr( $cell->class ?? '' );
                                    $html .= "<td" . ( $cls ? " class=\"{$cls}\"" : '' ) . ">{$val}</td>";
                                } else {
                                    $html .= "<td>" . esc_html( $cell ) . "</td>";
                                }
                            }
                            $html .= "</tr>\n";
                        }
                        $html .= "    </table>\n";
                        break;

                    default: // 'evidence'
                        $label   = esc_html( $ev->label ?? '' );
                        $content = esc_html( $ev->content ?? '' );
                        $variant = esc_attr( $ev->variant ?? '' );
                        $variant_class = $variant ? " {$variant}" : '';
                        if ( $label ) {
                            $html .= "    <div class=\"evidence-label\">{$label}</div>\n";
                        }
                        $html .= "    <div class=\"evidence{$variant_class}\">{$content}</div>\n";
                        break;
                }
            }
        }

        // Resolution
        if ( $status === 'resolved' && ! empty( $finding->resolution ) ) {
            $html .= "    <div class=\"resolution\">" . esc_html( $finding->resolution ) . "</div>\n";
        }

        // Inline recommendation
        if ( ! empty( $finding->recommendation ) && $finding->recommendation !== ( $finding->title ?? '' ) ) {
            $html .= "    <div class=\"callout blue\"><strong>Recommendation</strong> " . esc_html( $finding->recommendation ) . "</div>\n";
        }

        $html .= "  </div>\n\n";
        return $html;
    }

    private function render_section_content( $blocks ) {
        $html    = '';
        $in_card = false;
        foreach ( $blocks as $block ) {
            $type = $block->type ?? 'prose';

            // Bar charts render outside the card wrapper
            if ( $type === 'bar-chart' ) {
                if ( $in_card ) { $html .= "  </div>\n"; $in_card = false; }
                $chart_title = esc_html( $block->title ?? '' );
                $html .= "  <div class=\"chart-container\">\n";
                if ( $chart_title ) {
                    $html .= "    <div class=\"chart-title\">{$chart_title}</div>\n";
                }
                $html .= "    <div class=\"bar-chart\">\n";
                foreach ( ( $block->bars ?? [] ) as $bar ) {
                    $label = esc_html( $bar->label ?? '' );
                    $value = esc_html( $bar->value ?? '' );
                    $width = esc_attr( $bar->width ?? '0%' );
                    $color = esc_attr( $bar->color ?? 'var(--c1)' );
                    $html .= "      <div class=\"bar-row\">\n";
                    $html .= "        <div class=\"bar-label\">{$label}</div>\n";
                    $html .= "        <div class=\"bar-track\"><div class=\"bar-fill\" style=\"width:{$width};background:{$color}\">{$value}</div></div>\n";
                    $html .= "      </div>\n";
                }
                $html .= "    </div>\n  </div>\n";
                continue;
            }

            // All other blocks render inside a card
            if ( ! $in_card ) { $html .= "  <div class=\"card\">\n"; $in_card = true; }

            switch ( $type ) {
                case 'prose':
                    $html .= "    " . ( $block->html ?? '' ) . "\n";
                    break;

                case 'table':
                    $label = esc_html( $block->label ?? '' );
                    if ( $label ) {
                        $html .= "    <div class=\"evidence-label\">{$label}</div>\n";
                    }
                    $html .= "    <table>\n";
                    $headers = $block->headers ?? [];
                    if ( ! empty( $headers ) ) {
                        $html .= "      <tr>";
                        foreach ( $headers as $h ) {
                            $html .= "<th>" . esc_html( $h ) . "</th>";
                        }
                        $html .= "</tr>\n";
                    }
                    $rows = $block->rows ?? [];
                    foreach ( $rows as $row ) {
                        $html .= "      <tr>";
                        foreach ( $row as $cell ) {
                            if ( is_object( $cell ) || is_array( $cell ) ) {
                                $cell = (object) $cell;
                                $val  = esc_html( $cell->value ?? '' );
                                $cls  = esc_attr( $cell->class ?? '' );
                                $html .= "<td" . ( $cls ? " class=\"{$cls}\"" : '' ) . ">{$val}</td>";
                            } else {
                                $html .= "<td>" . esc_html( $cell ) . "</td>";
                            }
                        }
                        $html .= "</tr>\n";
                    }
                    $html .= "    </table>\n";
                    break;

                case 'callout':
                    $variant = esc_attr( $block->variant ?? 'blue' );
                    $html .= "    <div class=\"callout {$variant}\">" . ( $block->content ?? '' ) . "</div>\n";
                    break;

                case 'code':
                    $label = esc_html( $block->label ?? '' );
                    if ( $label ) {
                        $html .= "    <div class=\"evidence-label\">{$label}</div>\n";
                    }
                    $html .= "    <pre><code>" . esc_html( $block->content ?? '' ) . "</code></pre>\n";
                    break;

                case 'check-list':
                    $html .= "    <ul class=\"check-list\">\n";
                    $items      = (array) ( $block->items ?? [] );
                    $icon_order = [ 'pass' => 0, 'warn' => 1, 'fail' => 2 ];
                    usort( $items, function( $a, $b ) use ( $icon_order ) {
                        return ( $icon_order[ $a->icon ?? 'pass' ] ?? 3 ) - ( $icon_order[ $b->icon ?? 'pass' ] ?? 3 );
                    } );
                    foreach ( $items as $item ) {
                        $icon_class = esc_attr( $item->icon ?? 'pass' );
                        $icon_char  = $icon_class === 'pass' ? '&#10003;' : ( $icon_class === 'fail' ? '&#10007;' : '&#9888;' );
                        $label      = $item->label ?? '';
                        $html .= "      <li><span class=\"icon {$icon_class}\">{$icon_char}</span> {$label}</li>\n";
                    }
                    $html .= "    </ul>\n";
                    break;

                case 'timeline':
                    $html .= "    <ul class=\"timeline\">\n";
                    $events = $block->events ?? [];
                    foreach ( $events as $event ) {
                        $type_class = esc_attr( $event->type ?? '' );
                        $timestamp  = esc_html( $event->timestamp ?? '' );
                        $desc       = esc_html( $event->description ?? '' );
                        $html .= "      <li class=\"{$type_class}\">\n";
                        $html .= "        <div class=\"time\">{$timestamp}</div>\n";
                        $html .= "        <div class=\"desc\">{$desc}</div>\n";
                        $html .= "      </li>\n";
                    }
                    $html .= "    </ul>\n";
                    break;

                case 'stats':
                    $html .= "    <div class=\"stat-row\">\n";
                    $items = $block->items ?? [];
                    foreach ( $items as $item ) {
                        $val     = esc_html( $item->value ?? '' );
                        $lbl     = esc_html( $item->label ?? '' );
                        $variant = esc_attr( $item->variant ?? '' );
                        $var_cls = $variant ? " {$variant}" : '';
                        $html .= "      <div class=\"stat-card\">\n";
                        $html .= "        <div class=\"stat-value{$var_cls}\">{$val}</div>\n";
                        $html .= "        <div class=\"stat-label\">{$lbl}</div>\n";
                        $html .= "      </div>\n";
                    }
                    $html .= "    </div>\n";
                    break;
            }
        }
        if ( $in_card ) { $html .= "  </div>\n"; }
        $html .= "</div>\n\n";
        return $html;
    }

    public static function report_css() {
        return ':root {
    --bg: #f5f7fa;
    --card: #fff;
    --border: #e4e9f0;
    --text: #1a2744;
    --muted: #6b7f94;
    --mono: \'SF Mono\', \'Fira Code\', \'Cascadia Code\', \'JetBrains Mono\', monospace;
    --anchor: #55c1e7;
    --anchor-dark: #1a2744;
    --radius: 14px;
    --shadow: 0 1px 3px rgba(26,39,68,0.06), 0 1px 2px rgba(26,39,68,0.04);
    --shadow-lg: 0 4px 12px rgba(26,39,68,0.08), 0 1px 3px rgba(26,39,68,0.06);
    --c1: #2b8fc7;
    --c2: #0e8a8a;
    --c3: #d14343;
    --c4: #3574a5;
    --c5: #1a5276;
    --c6: #2c5282;
    --c7: #1a8a6a;
    --c8: #7c3aed;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html { font-size: 18px; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', system-ui, sans-serif;
    color: var(--text);
    background: var(--bg);
    line-height: 1.65;
    -webkit-font-smoothing: antialiased;
  }
  .hero {
    background: var(--anchor);
    background-image: url("https://anchor.host/wp-content/uploads/2022/08/pattern-2.webp");
    background-size: 1200px;
    color: #fff;
    padding: 4.5rem 1.5rem 4rem;
    text-align: center;
  }
  .hero h1 { font-size: 2.4rem; font-weight: 800; letter-spacing: -0.03em; margin-bottom: 0.75rem; }
  .hero p { color: rgba(255,255,255,0.9); font-size: 1.1rem; max-width: 640px; margin: 0 auto; line-height: 1.7; font-weight: 400; }
  .container { max-width: 1100px; margin: 0 auto; padding: 0 2rem 3rem; }
  .dashboard { margin-top: -2.5rem; padding: 0 0 1.5rem; position: relative; z-index: 1; }
  .dashboard-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.85rem; }
  @media (max-width: 640px) { .dashboard-grid { grid-template-columns: repeat(2, 1fr); } }
  .dash-card { background: var(--card); border-radius: var(--radius); padding: 1.5rem 1.15rem 1.25rem; display: flex; flex-direction: column; align-items: center; text-align: center; box-shadow: var(--shadow-lg); transition: transform 0.15s ease, box-shadow 0.15s ease; }
  .dash-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,39,68,0.1), 0 2px 6px rgba(26,39,68,0.06); }
  .dash-value { font-size: 1.25rem; font-weight: 800; margin-bottom: 0.2rem; }
  .dash-value.clean { color: #16a34a; }
  .dash-value.warn { color: #ea580c; }
  .dash-value.critical { color: #dc2626; }
  .dash-label { font-size: 0.68rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); }
  .section { margin-top: 2.75rem; }
  .section-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.4rem; }
  .section-num { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800; color: #fff; flex-shrink: 0; }
  .section-title { font-size: 1.15rem; font-weight: 700; letter-spacing: -0.01em; }
  .section-desc { color: var(--muted); font-size: 0.9rem; margin-bottom: 1rem; padding-left: calc(34px + 0.75rem); }
  .card { background: var(--card); border-radius: var(--radius); padding: 1.25rem 1.35rem; margin-bottom: 0.65rem; box-shadow: var(--shadow); transition: box-shadow 0.15s ease; }
  .card:hover { box-shadow: var(--shadow-lg); }
  .card-header { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; flex-wrap: wrap; }
  .card-tag { font-family: var(--mono); font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 3px 8px; border-radius: 6px; color: #fff; white-space: nowrap; }
  .card-tag.critical { background: #dc2626; }
  .card-tag.high { background: #ea580c; }
  .card-tag.medium { background: #d97706; }
  .card-tag.low { background: #2563eb; }
  .card-tag.resolved { background: #16a34a; }
  .card-tag.t1 { background: var(--c1); }
  .card-tag.t2 { background: var(--c2); }
  .card-tag.t3 { background: var(--c3); }
  .card-tag.t4 { background: var(--c4); }
  .card-tag.t5 { background: var(--c5); }
  .card-tag.t6 { background: var(--c6); }
  .card-tag.t7 { background: var(--c7); }
  .card-tag.t8 { background: var(--c8); }
  .card-title { font-size: 0.95rem; font-weight: 650; }
  .card p { font-size: 0.88rem; margin-bottom: 0.5rem; color: #3d5166; }
  .card ul { padding-left: 1.25rem; margin-top: 0.25rem; }
  .card li { font-size: 0.86rem; margin-bottom: 0.3rem; color: #3d5166; }
  .card li strong { font-weight: 600; color: var(--text); }
  .card code { font-family: var(--mono); font-size: 0.8em; background: var(--bg); padding: 2px 6px; border-radius: 4px; }
  .severity { display: inline-block; font-family: var(--mono); font-size: 0.66rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; padding: 2px 7px; border-radius: 5px; vertical-align: middle; }
  .severity.critical { background: #fef2f2; color: #dc2626; }
  .severity.high { background: #fff7ed; color: #ea580c; }
  .severity.medium { background: #fffbeb; color: #d97706; }
  .severity.low { background: #eff6ff; color: #2563eb; }
  .severity.clean { background: #f0fdf4; color: #16a34a; }
  .resolution { margin-top: 0.5rem; font-size: 0.84rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem; color: #166534; }
  .resolution::before { content: \'\\2713\'; display: inline-flex; align-items: center; justify-content: center; width: 1.15rem; height: 1.15rem; background: #16a34a; color: #fff; border-radius: 50%; font-size: 0.65rem; font-weight: 700; flex-shrink: 0; }
  table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
  th, td { text-align: left; padding: 0.6rem 0.85rem; border-bottom: 1px solid var(--border); }
  th { font-weight: 600; color: var(--muted); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; background: var(--bg); }
  tr:last-child td { border-bottom: none; }
  .good { color: #16a34a; font-weight: 600; }
  .warn-cell { color: #ea580c; font-weight: 600; }
  .poor { color: #dc2626; font-weight: 600; }
  .check-list { list-style: none; }
  .check-list li { padding: 0.35rem 0; display: flex; align-items: center; gap: 0.5rem; font-size: 0.86rem; color: #3d5166; }
  .check-list li .icon { font-size: 1rem; width: 1.25rem; text-align: center; flex-shrink: 0; }
  .check-list li .icon.pass { color: #16a34a; }
  .check-list li .icon.fail { color: #dc2626; }
  .check-list li .icon.warn { color: #d97706; }
  .evidence { border-left: 3px solid var(--border); background: #fafbfd; border-radius: 0 0.5rem 0.5rem 0; padding: 0.85rem 1rem; margin: 0.75rem 0; font-family: var(--mono); font-size: 0.78rem; line-height: 1.7; color: #3d5166; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
  .evidence.critical { border-left-color: #dc2626; background: #fef8f8; }
  .evidence.warn { border-left-color: #d97706; background: #fffdf7; }
  .evidence-label { display: inline-block; font-family: var(--mono); font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin-bottom: 0.35rem; }
  pre { background: var(--anchor-dark); color: #e2e8f0; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; font-size: 0.78rem; line-height: 1.7; margin: 0.75rem 0; font-family: var(--mono); }
  pre code, .card pre code { background: none; padding: 0; color: inherit; font-size: inherit; border-radius: 0; }
  details.collapsible { background: var(--card); border-radius: var(--radius); margin-bottom: 0.65rem; box-shadow: var(--shadow); overflow: hidden; }
  details.collapsible summary { padding: 1rem 1.35rem; font-size: 0.9rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; list-style: none; color: var(--text); user-select: none; }
  details.collapsible summary::-webkit-details-marker { display: none; }
  details.collapsible summary::before { content: \'\\25B6\'; font-size: 0.6rem; color: var(--muted); transition: transform 0.15s ease; flex-shrink: 0; }
  details.collapsible[open] summary::before { transform: rotate(90deg); }
  details.collapsible summary .summary-count { margin-left: auto; font-family: var(--mono); font-size: 0.7rem; font-weight: 600; color: var(--muted); background: var(--bg); padding: 2px 8px; border-radius: 6px; }
  details.collapsible .collapsible-body { padding: 0 1.35rem 1.25rem; border-top: 1px solid var(--border); margin-top: -1px; padding-top: 1rem; }
  .timeline { list-style: none; position: relative; padding-left: calc(1.25rem - 0.5px); }
  .timeline::before { content: \'\'; position: absolute; left: calc(0.35rem - 0.5px); top: 0.5rem; bottom: 0.5rem; width: 2px; background: var(--border); }
  .timeline li { position: relative; margin-bottom: 1rem; }
  .timeline li::before { content: \'\'; position: absolute; left: calc(-0.9rem + 0.5px); top: 0.5rem; width: 10px; height: 10px; border-radius: 50%; background: #dc2626; border: 2px solid #fff; transform: translateX(-50%); }
  .timeline li.success::before { background: #16a34a; }
  .timeline li.info::before { background: #2563eb; }
  .timeline li.warning::before { background: #ea580c; }
  .timeline .time { font-weight: 600; font-size: 0.82rem; color: var(--muted); }
  .timeline .desc { font-size: 0.86rem; margin-top: 0.15rem; color: #3d5166; }
  .callout { border-radius: 0.5rem; padding: 1rem; margin: 0.75rem 0; font-size: 0.86rem; }
  .callout strong { display: block; margin-bottom: 0.25rem; }
  .callout.red { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
  .callout.green { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
  .callout.yellow { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
  .callout.blue { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
  .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 0.65rem; margin: 0.75rem 0; }
  .stat-card { background: var(--bg); border-radius: 0.5rem; padding: 1rem; text-align: center; }
  .stat-card .stat-value { font-size: 1.8rem; font-weight: 800; line-height: 1.2; }
  .stat-card .stat-value.critical { color: #dc2626; }
  .stat-card .stat-value.warn { color: #ea580c; }
  .stat-card .stat-value.clean { color: #16a34a; }
  .stat-card .stat-value.info { color: var(--c1); }
  .stat-card .stat-label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--muted); margin-top: 0.15rem; }
  .file-tree { font-family: var(--mono); font-size: 0.8rem; line-height: 1.8; color: #3d5166; padding: 0.5rem 0; }
  .file-tree .dir { color: var(--c1); font-weight: 600; }
  .file-tree .mal { color: #dc2626; font-weight: 600; }
  .file-tree .safe { color: var(--muted); }
  .file-tree .size { color: var(--muted); font-size: 0.72rem; margin-left: 0.5rem; }
  .diff { font-family: var(--mono); font-size: 0.78rem; line-height: 1.7; border-radius: 0.5rem; overflow: hidden; margin: 0.75rem 0; border: 1px solid var(--border); }
  .diff-header { background: var(--bg); padding: 0.5rem 1rem; font-weight: 600; font-size: 0.76rem; color: var(--muted); border-bottom: 1px solid var(--border); }
  .diff-body { padding: 0.5rem 0; }
  .diff-line { padding: 0 1rem; white-space: pre-wrap; word-break: break-all; }
  .diff-line.add { background: #f0fdf4; color: #166534; }
  .diff-line.add::before { content: \'+ \'; font-weight: 700; }
  .diff-line.del { background: #fef2f2; color: #991b1b; }
  .diff-line.del::before { content: \'- \'; font-weight: 700; }
  .diff-line.ctx { color: var(--muted); }
  .diff-line.ctx::before { content: \'  \'; }
  .chart-container { background: var(--card); border-radius: var(--radius); padding: 1.5rem; margin-bottom: 1rem; box-shadow: var(--shadow); }
  .chart-title { font-size: 0.85rem; font-weight: 650; margin-bottom: 1rem; }
  .bar-chart { display: flex; flex-direction: column; gap: 0.5rem; }
  .bar-row { display: flex; align-items: center; gap: 0.75rem; }
  .bar-label { font-size: 0.78rem; min-width: 160px; text-align: right; color: var(--muted); }
  .bar-track { flex: 1; height: 24px; background: var(--bg); border-radius: 6px; overflow: hidden; }
  .bar-fill { height: 100%; border-radius: 6px; display: flex; align-items: center; padding-left: 8px; font-family: var(--mono); font-size: 0.68rem; font-weight: 700; color: #fff; min-width: fit-content; }
  .donut-wrap { display: flex; align-items: center; gap: 2rem; justify-content: center; flex-wrap: wrap; }
  .donut-legend { display: flex; flex-direction: column; gap: 0.4rem; }
  .legend-item { display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; }
  .legend-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .legend-count { font-family: var(--mono); font-size: 0.75rem; color: var(--muted); margin-left: auto; padding-left: 1rem; }
  .highlight-row { background: #f0fdf4; }
  .ioc-grid { column-gap: 1rem; font-family: var(--mono); font-size: 0.68rem; line-height: 1.7; }
  .ioc-grid span { display: block; padding: 0.1rem 0; color: #3d5166; }
  .footer { margin-top: 3rem; text-align: center; color: var(--muted); font-size: 0.82rem; padding-top: 1.5rem; border-top: 1px solid var(--border); }
  .footer a { color: var(--c1); text-decoration: none; }
  .footer a:hover { text-decoration: underline; }
  @media (max-width: 640px) {
    .stat-row { grid-template-columns: 1fr 1fr; }
  }
  @media print {
    body { background: #fff; }
    .hero { padding: 2rem 1rem; }
    .card { break-inside: avoid; }
    pre { white-space: pre-wrap; word-wrap: break-word; }
    details.collapsible { break-inside: avoid; }
    details.collapsible[open] .collapsible-body { break-inside: avoid; }
    .diff { break-inside: avoid; }
    .chart-container { break-inside: avoid; }
  }';
    }

}
