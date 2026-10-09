<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
        if ( ( empty( $audit->site_name ) || $audit->environment === 'Staging' ) && ! empty( $audit->home_url ) ) {
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
        if ( empty( $finding_id ) ) {
            return 0;
        }

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
        $updated  = ( new SiteAuditFindings )->update(
            [
                'status'      => 'resolved',
                'resolution'  => $resolution,
                'resolved_at' => $time_now,
                'updated_at'  => $time_now,
            ],
            [ 'site_audit_finding_id' => $finding_id ]
        );

        // A failed write leaves the finding open; report it rather than
        // marking the audit remediated around it.
        if ( $updated === false ) {
            return false;
        }

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

        return true;
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
        $type_slug   = sanitize_title( str_replace( '_', '-', $audit->report_type ?: 'security-audit' ) );
        // Every other part of this name is derivable from outside - the date, the
        // site's domain and one of a handful of report types - and the file is
        // served straight from the webroot with no authentication in front of
        // it. The token is what makes the URL a capability rather than a guess.
        $token       = bin2hex( random_bytes( 16 ) );
        $filename    = "{$date_prefix}_{$slug}-{$type_slug}-{$token}.html";
        $html        = $this->render_html();
        $reports_dir = ABSPATH . 'reports';
        $file_path   = $reports_dir . '/' . $filename;

        // Each publish mints a new name, so the previous file would otherwise
        // stay readable at its old URL after the report was re-published.
        if ( ! empty( $audit->report_path ) && $audit->report_path !== $filename ) {
            $previous = $reports_dir . '/' . basename( $audit->report_path );
            if ( file_exists( $previous ) ) {
                unlink( $previous );
            }
        }

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

    /**
     * Normalize a bar-chart bar's width into a valid CSS length string.
     *
     * Accepts:
     *   - already-formatted CSS strings ("100%", "240px") — passed through
     *   - bare numbers (100 or "100") — appended with "%"
     *   - null / empty — auto-computed from $value/$max_numeric, falling back to "0%"
     *
     * @param mixed $width            Raw width value from the bar payload.
     * @param mixed $value            The bar's numeric value (for auto-compute).
     * @param float $max_numeric      Largest numeric value across all bars in the chart.
     * @param bool  $any_explicit     Whether any bar in the chart had an explicit width.
     * @return string                 An esc_attr-safe CSS length string.
     */
    private function normalize_bar_width( $width, $value, $max_numeric, $any_explicit ) {
        // Auto-compute when no explicit width was supplied anywhere in the chart
        if ( ( $width === null || $width === '' ) && ! $any_explicit ) {
            if ( is_numeric( $value ) && $max_numeric > 0 ) {
                $pct = max( 0.0, min( 100.0, ( (float) $value / $max_numeric ) * 100 ) );
                return esc_attr( round( $pct, 1 ) . '%' );
            }
            return '0%';
        }

        if ( $width === null || $width === '' ) {
            return '0%';
        }

        // Bare number (int, float, or numeric string) → treat as percent
        if ( is_numeric( $width ) ) {
            return esc_attr( $width . '%' );
        }

        // String that ends in a digit (no unit) → append %
        $width_str = (string) $width;
        if ( preg_match( '/[0-9]$/', $width_str ) ) {
            return esc_attr( $width_str . '%' );
        }

        return esc_attr( $width_str );
    }

    /**
     * Normalize a bar-chart bar's color into a valid CSS color value.
     *
     * Accepts the same severity vocabulary used by stats / table cells
     * (info / clean / good / warn / critical / poor) and maps to the
     * report's CSS variables. Already-valid CSS values pass through.
     *
     * @param mixed $color  Raw color value from the bar payload.
     * @return string       An esc_attr-safe CSS color value.
     */
    private function normalize_bar_color( $color ) {
        if ( $color === null || $color === '' ) {
            return 'var(--navy)';
        }

        $tones = [
            'good' => 'var(--good)',
            'warn' => 'var(--warn)',
            'bad'  => 'var(--bad)',
            'info' => 'var(--navy)',
        ];
        $key  = strtolower( trim( (string) $color ) );
        $tone = $this->tone( $key );
        if ( $tone ) {
            return $tones[ $tone ];
        }

        // Convenience aliases for the old chart palette, still defined as tokens.
        if ( preg_match( '/^c[1-8]$/', $key ) ) {
            return "var(--{$key})";
        }

        return esc_attr( $color );
    }

    /**
     * Collapse the status vocabulary audits use (clean, warn-cell, poor,
     * success, ...) into the brand's four tones. Empty string when unknown.
     */
    private function tone( $value ) {
        $map = [
            'clean'      => 'good',
            'good'       => 'good',
            'pass'       => 'good',
            'success'    => 'good',
            'fix'        => 'good',
            'resolved'   => 'good',
            'remediated' => 'good',
            'warn'       => 'warn',
            'warning'    => 'warn',
            'warn-cell'  => 'warn',
            'medium'     => 'warn',
            'critical'   => 'bad',
            'poor'       => 'bad',
            'high'       => 'bad',
            'fail'       => 'bad',
            'bad'        => 'bad',
            'alert'      => 'bad',
            'issue'      => 'bad',
            'info'       => 'info',
            'low'        => 'info',
            'action'     => 'info',
            'metric'     => 'info',
        ];
        return $map[ strtolower( trim( (string) $value ) ) ] ?? '';
    }

    private function report_title( $audit ) {
        if ( ! empty( $audit->report_title ) ) {
            return $audit->report_title;
        }
        $type = str_replace( '-', '_', $audit->report_type ?: 'security_audit' );
        $map  = [
            'security_audit'      => 'Security Audit Report',
            'malware_incident'    => 'Malware Incident Report',
            'performance_review'  => 'Performance Review',
            'accessibility_audit' => 'Accessibility Audit Report',
            'debug_report'        => 'Debug Report',
            'incident_report'     => 'Incident Report',
        ];
        return $map[ $type ] ?? ucwords( str_replace( '_', ' ', $type ) );
    }

    /**
     * Title and description for the built-in timeline. Only security reports
     * reconstruct an attacker's activity; other report types get a neutral one.
     */
    private function timeline_heading( $audit ) {
        $type = str_replace( '-', '_', $audit->report_type ?: 'security_audit' );
        if ( in_array( $type, [ 'security_audit', 'malware_incident' ], true ) ) {
            return [ 'Attack timeline', 'Reconstructed attacker activity based on logs, file timestamps and user events.' ];
        }
        return [ 'Timeline', 'Key events in order, from the logs and the work done.' ];
    }

    private static function icon( $name, $class = '' ) {
        $paths = [
            'check'   => '<path d="M5 12l5 5 9-10"/>',
            'alert'   => '<path d="M12 7v6M12 17h.01"/>',
            'x'       => '<path d="M7 7l10 10M17 7L7 17"/>',
            'info'    => '<path d="M12 11v6M12 7h.01"/>',
            'chevron' => '<path d="M9 6l6 6-6 6"/>',
            'shield'  => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
            'printer' => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
            'moon'    => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
            'sun'     => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        ];
        $class = trim( "i {$class}" );
        return "<svg class=\"{$class}\" viewBox=\"0 0 24 24\" aria-hidden=\"true\">{$paths[ $name ]}</svg>";
    }

    public function render_html() {
        $audit = $this->get();
        if ( ! $audit ) {
            return '';
        }

        $report_title = $this->report_title( $audit );
        $site_name    = $audit->site_name ?: $report_title;
        $date         = date( 'F j, Y', strtotime( $audit->created_at ) );
        $findings     = $this->sort_findings( (array) $audit->findings );
        $open         = array_values( array_filter( $findings, fn( $f ) => ( $f->status ?? 'open' ) !== 'resolved' ) );
        $resolved     = count( $findings ) - count( $open );

        $e_title = esc_html( $report_title );
        $e_site  = esc_html( $site_name );
        $css     = self::report_css();

        // Sections keyed the way section_order names them.
        $sections = [];

        if ( ! empty( $audit->scan_checks ) ) {
            $sections['scan-results'] = [
                'title'  => 'Scan results',
                'render' => fn( $num, $id ) => $this->render_section( $num, $id, 'Scan results', 'Filesystem integrity, malware signatures, frontend analysis, database scan, logs and user accounts.', $this->render_scan_checks( (array) $audit->scan_checks ) ),
            ];
        }

        if ( ! empty( $findings ) ) {
            $sections['findings'] = [
                'title'  => 'Issues found',
                'count'  => count( $findings ),
                'render' => fn( $num, $id ) => $this->render_findings_section( $num, $id, $findings, count( $open ), $resolved ),
            ];
        }

        if ( ! empty( $audit->admin_accounts ) && is_array( $audit->admin_accounts ) ) {
            $sections['admin-accounts'] = [
                'title'  => 'Administrator accounts',
                'render' => function( $num, $id ) use ( $audit ) {
                    $rows = '';
                    foreach ( $audit->admin_accounts as $account ) {
                        $uname = (string) ( $account->username ?? '' );
                        $note  = '';
                        // Usernames often carry a note: "jane (super admin, no login on record)".
                        if ( preg_match( '/^(.*?)\s*\((.+)\)\s*$/', $uname, $m ) && $m[1] !== '' ) {
                            $uname = $m[1];
                            $note  = '<span class="sub">' . esc_html( $m[2] ) . '</span>';
                        }
                        $rows .= '<tr><td class="mono">' . esc_html( $account->user_id ?? '' ) . '</td>'
                            . '<td><strong>' . esc_html( $uname ) . "</strong>{$note}</td>"
                            . '<td class="break">' . esc_html( $account->email ?? '' ) . '</td>'
                            . '<td class="mono nowrap">' . esc_html( $account->registered ?? '' ) . '</td></tr>';
                    }
                    $table = "<div class=\"card card--flush\"><div class=\"table-wrap\"><table><thead><tr><th>ID</th><th>Account</th><th>Email</th><th>Registered</th></tr></thead><tbody>{$rows}</tbody></table></div></div>";
                    return $this->render_section( $num, $id, 'Administrator accounts', count( $audit->admin_accounts ) . ' administrator accounts.', $table );
                },
            ];
        }

        if ( ! empty( $audit->site_config ) ) {
            $sections['site-config'] = [
                'title'  => 'Site configuration',
                'render' => function( $num, $id ) use ( $audit ) {
                    $items = '';
                    foreach ( $audit->site_config as $config ) {
                        $tone   = $this->tone( $config->status ?? '' );
                        $class  = $tone ? " class=\"st st--{$tone}\"" : '';
                        $items .= '<div><dt>' . esc_html( $config->key ?? '' ) . "</dt><dd{$class}>" . esc_html( $config->value ?? '' ) . '</dd></div>';
                    }
                    return $this->render_section( $num, $id, 'Site configuration', 'Current WordPress configuration and security settings.', "<dl class=\"card config\">{$items}</dl>" );
                },
            ];
        }

        if ( ! empty( $audit->timeline_events ) ) {
            [ $timeline_title, $timeline_description ] = $this->timeline_heading( $audit );
            $sections['timeline'] = [
                'title'  => $timeline_title,
                'render' => fn( $num, $id ) => $this->render_section( $num, $id, $timeline_title, $timeline_description, '<div class="card">' . $this->render_timeline( (array) $audit->timeline_events, true ) . '</div>' ),
            ];
        }

        // Custom sections keyed by slugified title
        if ( ! empty( $audit->sections ) && is_array( $audit->sections ) ) {
            foreach ( $audit->sections as $i => $section ) {
                $title = $section->title ?? 'Additional Information';
                $key   = sanitize_title( $section->title ?? "section-{$i}" );
                $sections[ $key ] = [
                    'title'  => $title,
                    'render' => fn( $num, $id ) => $this->render_section( $num, $id, $title, captaincore_markdown( $section->description ?? '', true ), $this->render_section_content( $section->content ?? [] ), '', true ),
                ];
            }
        }

        // Render in section_order, then anything it does not name (e.g. a section
        // renamed after the order was saved), numbering both the same way.
        $order = [];
        foreach ( (array) $audit->section_order as $key ) {
            if ( is_string( $key ) && isset( $sections[ $key ] ) && ! in_array( $key, $order, true ) ) {
                $order[] = $key;
            }
        }
        foreach ( array_keys( $sections ) as $key ) {
            if ( ! in_array( $key, $order, true ) ) {
                $order[] = $key;
            }
        }

        $body = '';
        $toc  = '';
        foreach ( $order as $i => $key ) {
            $num    = str_pad( $i + 1, 2, '0', STR_PAD_LEFT );
            $id     = sanitize_title( $sections[ $key ]['title'] );
            $body  .= $sections[ $key ]['render']( $num, $id );
            $count  = isset( $sections[ $key ]['count'] ) ? '<span class="count">' . (int) $sections[ $key ]['count'] . '</span>' : '';
            $toc   .= "<li><a href=\"#{$id}\"><span class=\"n\">{$num}</span>" . esc_html( $sections[ $key ]['title'] ) . "{$count}</a></li>\n";
        }

        // Open items: what is left to do, ahead of the detail.
        $summary_card = '';
        if ( $open ) {
            $items = '';
            foreach ( $open as $finding ) {
                $rec    = trim( (string) ( $finding->recommendation ?? '' ) );
                $title  = (string) ( $finding->title ?? '' );
                $rec    = ( $rec !== '' && $rec !== $title ) ? '<div class="open-item__rec">' . esc_html( $rec ) . '</div>' : '';
                $items .= '<li><a class="open-item" href="#finding-' . (int) $finding->site_audit_finding_id . '">'
                    . $this->severity_chip( $finding->severity ?? 'low' )
                    . '<div class="open-item__body"><div class="open-item__title">' . esc_html( $title ) . "</div>{$rec}</div>"
                    . self::icon( 'chevron', 'open-item__go' ) . "</a></li>\n";
            }
            $summary_card = "<section id=\"open-items\" class=\"card open-items\"><div class=\"open-items__head\"><h2>Open items</h2><span class=\"eyebrow\">" . count( $open ) . ' of ' . count( $findings ) . " remain</span></div><ol>{$items}</ol></section>\n";
            $toc          = '<li><a href="#open-items"><span class="n">00</span>Open items<span class="count">' . count( $open ) . "</span></a></li>\n" . $toc;
        } elseif ( $findings ) {
            $summary_card = '<div class="card all-resolved"><span class="ico ico--pass">' . self::icon( 'check' ) . '</span>' . ( count( $findings ) === 1 ? 'The finding in this report is resolved.' : 'All ' . count( $findings ) . ' findings in this report are resolved.' ) . "</div>\n";
        }

        // Masthead meta
        $meta     = '';
        $fs       = strtolower( trim( (string) $audit->filesystem_status ) );
        $verdicts = [
            'clean'      => [ 'good', 'Filesystem clean' ],
            'remediated' => [ 'good', 'Remediated' ],
            'warn'       => [ 'warn', 'Filesystem warnings' ],
            'critical'   => [ 'bad', 'Filesystem compromised' ],
        ];
        if ( isset( $verdicts[ $fs ] ) ) {
            $meta .= "<span class=\"verdict verdict--{$verdicts[ $fs ][0]}\"><span class=\"dot\"></span>{$verdicts[ $fs ][1]}</span>";
        } elseif ( $fs !== '' ) {
            $meta .= '<span class="verdict"><span class="dot"></span>Filesystem ' . esc_html( $fs ) . '</span>';
        }
        if ( ( $audit->environment ?? '' ) === 'Staging' ) {
            $meta .= '<span>Staging environment</span>';
        }
        $meta .= '<span>Prepared by Anchor Hosting</span>';

        $lede = ! empty( $audit->summary ) ? '<p class="lede">' . esc_html( $audit->summary ) . '</p>' : '';

        // Dashboard metrics
        $metrics = [];
        if ( ! empty( $audit->dashboard_metrics ) && is_array( $audit->dashboard_metrics ) ) {
            foreach ( $audit->dashboard_metrics as $metric ) {
                $metrics[] = [ (string) ( $metric->value ?? '' ), (string) ( $metric->label ?? '' ), $this->tone( $metric->class ?? '' ), '', '' ];
            }
        } else {
            $fs_tone   = $this->tone( $fs );
            $issues    = (int) $audit->issues_count;
            $metrics[] = [ $fs !== '' ? ucfirst( $fs ) : 'N/A', 'Filesystem', $fs_tone, '', $fs_tone === 'good' ? self::icon( 'shield' ) : '' ];
            $metrics[] = [ $audit->wp_version ?: 'N/A', 'WordPress version', '', '', '' ];
            $metrics[] = [ (string) $issues, 'Issues found', $issues === 0 ? 'good' : ( $open ? 'warn' : '' ), $findings ? "{$resolved} resolved &middot; " . count( $open ) . ' open' : '', '' ];
            $metrics[] = [ (string) (int) $audit->plugins_count, 'Plugins', '', '', '' ];
        }
        $statband = '';
        foreach ( $metrics as [ $value, $label, $tone, $sub, $icon ] ) {
            $classes   = 'stat__value' . ( $tone ? " is-{$tone}" : '' ) . ( mb_strlen( $value ) > 10 ? ' stat__value--long' : '' );
            $statband .= "<div><div class=\"{$classes}\">{$icon}" . esc_html( $value ) . '</div><span class="stat__label">' . esc_html( $label ) . '</span>' . ( $sub ? "<div class=\"stat__sub\">{$sub}</div>" : '' ) . "</div>\n";
        }

        $lockup = self::lockup_svg();
        $mark   = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="4.5" r="2"/><path d="M12 6.5V21"/><path d="M7.5 10h9"/><path d="M4 14.5a8 8 0 0 0 16 0"/><path d="M4 14.5h2.6M20 14.5h-2.6"/></svg>';
        $i_print = self::icon( 'printer' );
        $i_moon  = self::icon( 'moon', 'i-moon' );
        $i_sun   = self::icon( 'sun', 'i-sun' );

        $html  = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n";
        $html .= "<meta charset=\"UTF-8\">\n";
        $html .= "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "<meta name=\"robots\" content=\"noindex, nofollow\">\n";
        $html .= "<title>{$e_title} &middot; {$e_site}</title>\n";
        $html .= "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>\n";
        $html .= "<link rel=\"stylesheet\" href=\"https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=JetBrains+Mono:wght@400;500;600&amp;display=swap\">\n";
        // Apply a saved theme before first paint so a dark preference never flashes light.
        $html .= "<script>try{var t=localStorage.getItem('anchor-report-theme');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>\n";
        $html .= "<style>\n{$css}\n</style>\n</head>\n<body>\n\n";

        $html .= <<<HTML
<header class="topbar">
	<div class="topbar__inner">
		<a href="https://anchor.host" aria-label="Anchor Hosting">{$lockup}</a>
		<span class="topbar__doc">{$e_title}</span>
		<div class="topbar__actions">
			<button class="btn-ghost btn-ghost--print" type="button" onclick="window.print()" aria-label="Print or save as PDF">{$i_print}<span>Save PDF</span></button>
			<button class="btn-ghost btn-ghost--icon" type="button" id="theme-toggle" aria-label="Toggle dark mode">{$i_moon}{$i_sun}</button>
		</div>
	</div>
</header>

<section class="masthead">
	<div class="wrap">
		<div class="eyebrow">{$e_title} &middot; {$date}</div>
		<h1>{$e_site}</h1>
		{$lede}
		<div class="masthead__meta">{$meta}</div>
	</div>
</section>

<div class="statband"><div class="statband__inner">
{$statband}</div></div>

<div class="wrap layout">
<nav class="toc" aria-label="On this page">
	<span class="eyebrow">On this page</span>
	<ol>
{$toc}	</ol>
</nav>
<main class="content">
{$summary_card}{$body}</main>
</div>

<footer class="footer">
	<div class="footer__inner">
		<div class="footer__brand">{$mark}<div><strong>Prepared by Anchor Hosting</strong><span>{$date} &middot; <a href="https://anchor.host">anchor.host</a></span></div></div>
	</div>
</footer>

HTML;

        $html .= "<script>\n" . self::report_js() . "\n</script>\n";
        $html .= "</body>\n</html>";

        return $html;
    }

    private function render_section( $num, $id, $title, $desc, $content, $head_extra = '', $desc_is_html = false ) {
        $title = esc_html( $title );
        $desc  = $desc_is_html ? $desc : esc_html( $desc );
        $desc  = $desc !== '' ? "<p>{$desc}</p>" : '';
        return "<section class=\"section\" id=\"{$id}\">\n<div class=\"section-head\"><div class=\"n\">{$num}</div>"
            . ( $head_extra !== '' ? "<div class=\"section-head__row\"><div><h2>{$title}</h2>{$desc}</div>{$head_extra}</div>" : "<h2>{$title}</h2>{$desc}" )
            . "</div>\n{$content}\n</section>\n\n";
    }

    private function render_findings_section( $num, $id, $findings, $open_count, $resolved_count ) {
        $pills = '';
        if ( $open_count && $resolved_count ) {
            $pills = '<div class="pill-group" role="group" aria-label="Filter findings">'
                . '<button class="pill is-active" type="button" data-filter="all">All <span class="c">' . count( $findings ) . '</span></button>'
                . "<button class=\"pill\" type=\"button\" data-filter=\"open\">Open <span class=\"c\">{$open_count}</span></button>"
                . "<button class=\"pill\" type=\"button\" data-filter=\"resolved\">Resolved <span class=\"c\">{$resolved_count}</span></button></div>";
        }

        $counts = [];
        foreach ( $findings as $finding ) {
            $sev            = strtolower( $finding->severity ?? 'low' );
            $counts[ $sev ] = ( $counts[ $sev ] ?? 0 ) + 1;
        }
        uksort( $counts, function( $a, $b ) {
            $rank = [ 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3, 'info' => 4 ];
            return ( $rank[ $a ] ?? 9 ) - ( $rank[ $b ] ?? 9 );
        } );
        $tally = '';
        foreach ( $counts as $sev => $n ) {
            $dot    = in_array( $sev, [ 'critical', 'high', 'medium', 'low', 'info' ], true ) ? $sev : 'other';
            $tally .= "<span><i class=\"d d--{$dot}\"></i><b>{$n}</b> " . esc_html( $sev ) . '</span>';
        }

        $cards = '';
        foreach ( $findings as $finding ) {
            $cards .= $this->render_finding_card( $finding );
        }

        return $this->render_section( $num, $id, 'Issues found', 'Individual findings with severity ratings and evidence.', "<div class=\"tally\">{$tally}</div>\n<div class=\"findings\">\n{$cards}</div>", $pills );
    }

    private function severity_chip( $severity ) {
        $severity = strtolower( (string) $severity );
        $class    = in_array( $severity, [ 'critical', 'high', 'medium', 'low', 'info' ], true ) ? $severity : 'other';
        return "<span class=\"sev sev--{$class}\">" . esc_html( ucfirst( $severity ) ) . '</span>';
    }

    private function render_finding_card( $finding ) {
        $title    = esc_html( $finding->title ?? '' );
        $desc     = $finding->description ?? '';
        $resolved = ( $finding->status ?? 'open' ) === 'resolved';
        $id       = (int) ( $finding->site_audit_finding_id ?? 0 );

        $status = $resolved
            ? '<span class="status status--resolved">' . self::icon( 'check' ) . 'Resolved</span>'
            : '<span class="status status--open">Open</span>';

        $html  = '<article class="card finding" data-status="' . ( $resolved ? 'resolved' : 'open' ) . "\" id=\"finding-{$id}\">\n";
        $html .= '<div class="finding__chips">' . $this->severity_chip( $finding->severity ?? 'low' ) . "{$status}</div>\n";
        $html .= "<h3 class=\"finding__title\">{$title}</h3>\n";

        // Description is stored through wp_kses_post, render raw
        if ( $desc ) {
            $html .= "<div class=\"prose\">{$desc}</div>\n";
        }

        $evidence = json_decode( $finding->evidence ?? '[]' );
        if ( is_array( $evidence ) ) {
            foreach ( $evidence as $ev ) {
                if ( is_object( $ev ) ) {
                    $html .= $this->render_block( $ev, 'finding' ) . "\n";
                }
            }
        }

        // Resolution — stored via wp_kses_post like description, render raw
        if ( $resolved && ! empty( $finding->resolution ) ) {
            $when  = ! empty( $finding->resolved_at ) ? ' &middot; ' . esc_html( date( 'M j, Y', strtotime( $finding->resolved_at ) ) ) : '';
            $html .= '<div class="resolution"><div class="resolution__label">' . self::icon( 'check' ) . "Resolution{$when}</div>" . $finding->resolution . "</div>\n";
        }

        // Inline recommendation: the call to action while open, a footnote once resolved
        $rec = trim( (string) ( $finding->recommendation ?? '' ) );
        if ( $rec !== '' && $rec !== ( $finding->title ?? '' ) ) {
            $html .= $resolved
                ? '<p class="rec rec--done"><span class="rec__label">Recommendation:</span>' . esc_html( $rec ) . "</p>\n"
                : '<div class="rec"><div class="rec__label">Recommendation</div>' . esc_html( $rec ) . "</div>\n";
        }

        $html .= "</article>\n\n";
        return $html;
    }

    private function render_scan_checks( $checks ) {
        $checks = $this->normalize_checks( $checks );
        $groups = [ 'fail' => [], 'warn' => [], 'info' => [], 'pass' => [] ];
        foreach ( $checks as $check ) {
            $groups[ $check->icon ][] = $check;
        }

        $summary = '<div class="checks__summary">'
            . '<span class="k"><b>' . count( $checks ) . '</b> checks run</span>'
            . '<span class="k"><span class="ico ico--pass ico--sm">' . self::icon( 'check' ) . '</span><b>' . count( $groups['pass'] ) . '</b> passed</span>'
            . '<span class="k"><span class="ico ico--warn ico--sm">' . self::icon( 'alert' ) . '</span><b>' . count( $groups['warn'] ) . '</b> need attention</span>'
            . '<span class="k"><span class="ico ico--fail ico--sm">' . self::icon( 'x' ) . '</span><b>' . count( $groups['fail'] ) . '</b> failed</span>'
            . '</div>';

        $attention = array_merge( $groups['fail'], $groups['warn'] );
        $passed    = array_merge( $groups['info'], $groups['pass'] );
        $html      = "<div class=\"card\">{$summary}";
        if ( $attention ) {
            $html .= '<div class="checks__group-label">Needs attention</div><ul class="checks">' . $this->render_check_rows( $attention ) . '</ul>';
        }
        if ( $passed ) {
            $html .= '<details class="checks-more" open><summary>' . self::icon( 'chevron' ) . 'Passed &middot; ' . count( $passed ) . '</summary><ul class="checks">' . $this->render_check_rows( $passed ) . '</ul></details>';
        }
        return $html . '</div>';
    }

    /**
     * Checks as { icon, label } objects, plain strings counting as passes,
     * sorted failures first so problems lead the list.
     */
    private function normalize_checks( $items ) {
        $icons = [ 'pass' => 'pass', 'good' => 'pass', 'warn' => 'warn', 'fail' => 'fail', 'info' => 'info' ];
        $rank  = [ 'fail' => 0, 'warn' => 1, 'info' => 2, 'pass' => 3 ];
        $out   = [];
        foreach ( array_values( (array) $items ) as $i => $item ) {
            $item  = is_string( $item ) ? (object) [ 'label' => $item ] : (object) $item;
            $out[] = (object) [
                'icon'  => $icons[ $item->icon ?? 'pass' ] ?? 'pass',
                'label' => (string) ( $item->label ?? '' ),
                'i'     => $i,
            ];
        }
        usort( $out, fn( $a, $b ) => ( $rank[ $a->icon ] - $rank[ $b->icon ] ) ?: ( $a->i - $b->i ) );
        return $out;
    }

    private function render_check_rows( $checks ) {
        $glyphs = [ 'pass' => 'check', 'warn' => 'alert', 'fail' => 'x', 'info' => 'info' ];
        $html   = '';
        foreach ( $checks as $check ) {
            $label = $check->label;
            $pos   = strpos( $label, ': ' );
            // "Core Checksums: WordPress verified" leads with its name in bold
            $text  = ( $pos !== false && $pos <= 60 )
                ? '<strong>' . esc_html( substr( $label, 0, $pos + 1 ) ) . '</strong> ' . esc_html( substr( $label, $pos + 2 ) )
                : esc_html( $label );
            $html .= "<li class=\"check\"><span class=\"ico ico--{$check->icon}\">" . self::icon( $glyphs[ $check->icon ] ) . "</span><span class=\"check__text\">{$text}</span></li>\n";
        }
        return $html;
    }

    private function render_timeline( $events, $markdown ) {
        $html = '<ol class="timeline">';
        foreach ( $events as $event ) {
            // Untyped events have always drawn as critical
            $tone = $this->tone( $event->type ?? '' ) ?: 'bad';
            // "Sep 24, 2026 — 01:58 UTC" reads better as date over time
            $when = str_replace( [ ' — ', ' &mdash; ' ], '<br>', esc_html( $event->timestamp ?? '' ) );
            $desc = $markdown ? captaincore_markdown( $event->description ?? '' ) : esc_html( $event->description ?? '' );
            $html .= "<li class=\"tl--{$tone}\"><div class=\"when\">{$when}</div><div class=\"what\">{$desc}</div></li>\n";
        }
        return $html . '</ol>';
    }

    private function render_table( $block ) {
        $html    = '<div class="table-wrap"><table>';
        $headers = (array) ( $block->headers ?? [] );
        if ( ! empty( $headers ) ) {
            $html .= '<thead><tr>';
            foreach ( $headers as $h ) {
                $html .= '<th>' . esc_html( $h ) . '</th>';
            }
            $html .= '</tr></thead>';
        }
        $html .= '<tbody>';
        foreach ( (array) ( $block->rows ?? [] ) as $row ) {
            $html .= '<tr>';
            foreach ( (array) $row as $cell ) {
                if ( is_object( $cell ) || is_array( $cell ) ) {
                    $cell  = (object) $cell;
                    $val   = esc_html( $cell->value ?? '' );
                    $class = (string) ( $cell->class ?? '' );
                    $tone  = $this->tone( $class );
                    if ( $tone ) {
                        $html .= "<td><span class=\"st st--{$tone}\">{$val}</span></td>";
                    } else {
                        $html .= '<td' . ( $class !== '' ? ' class="' . esc_attr( $class ) . '"' : '' ) . ">{$val}</td>";
                    }
                } else {
                    $html .= '<td>' . esc_html( $cell ) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        return $html . '</tbody></table></div>';
    }

    /**
     * One content block. Findings carry these as evidence, custom sections as
     * content; both read the same types. An unknown type inside a finding falls
     * back to a plain evidence box, inside a section it is skipped.
     */
    private function render_block( $block, $context = 'section' ) {
        $type    = $block->type ?? ( $context === 'finding' ? 'evidence' : 'prose' );
        $label   = esc_html( $block->label ?? '' );
        $caption = $label !== '' ? "<figcaption class=\"block__label\">{$label}</figcaption>" : '';

        switch ( $type ) {
            case 'prose':
                $content = $block->content ?? $block->html ?? '';
                return '<div class="block prose">' . wp_kses_post( is_string( $content ) ? $content : '' ) . '</div>';

            case 'code':
                return "<figure class=\"block block--code\">{$caption}<pre><code>" . esc_html( $block->content ?? '' ) . '</code></pre></figure>';

            case 'callout':
                $variants = [ 'blue' => 'info', 'green' => 'good', 'red' => 'bad', 'yellow' => 'warn' ];
                $variant  = $variants[ $block->variant ?? 'blue' ] ?? ( $this->tone( $block->variant ?? '' ) ?: 'info' );
                return "<div class=\"block callout callout--{$variant}\">" . captaincore_markdown( $block->content ?? '' ) . '</div>';

            case 'diff':
                $lines = '';
                foreach ( (array) ( $block->lines ?? [] ) as $line ) {
                    $line_type = in_array( $line->type ?? 'ctx', [ 'add', 'del', 'ctx' ], true ) ? $line->type : 'ctx';
                    $lines    .= "<div class=\"diff-line {$line_type}\">" . esc_html( $line->text ?? '' ) . '</div>';
                }
                return '<div class="block diff">' . ( $label !== '' ? "<div class=\"diff-header\">{$label}</div>" : '' ) . "<div class=\"diff-body\">{$lines}</div></div>";

            case 'file-tree':
                return "<figure class=\"block\">{$caption}<div class=\"file-tree\">" . wp_kses_post( $block->content ?? '' ) . '</div></figure>';

            case 'stats':
                $items = '';
                foreach ( (array) ( $block->items ?? [] ) as $item ) {
                    $tone   = $this->tone( $item->variant ?? '' );
                    $items .= '<div><div class="v' . ( $tone ? " is-{$tone}" : '' ) . '">' . esc_html( $item->value ?? '' ) . '</div><div class="l">' . esc_html( $item->label ?? '' ) . '</div></div>';
                }
                return "<div class=\"block stats\">{$items}</div>";

            case 'ioc-list':
                $columns = max( 1, min( 6, intval( $block->columns ?? 3 ) ) );
                $items   = '';
                foreach ( (array) ( $block->items ?? [] ) as $item ) {
                    $items .= '<span>' . esc_html( $item ) . '</span>';
                }
                return "<figure class=\"block\">{$caption}<div class=\"ioc-grid\" style=\"column-count:{$columns}\">{$items}</div></figure>";

            case 'table':
                return "<figure class=\"block\">{$caption}" . $this->render_table( $block ) . '</figure>';

            case 'check-list':
                return '<ul class="block checks">' . $this->render_check_rows( $this->normalize_checks( $block->items ?? [] ) ) . '</ul>';

            case 'timeline':
                return '<div class="block">' . $this->render_timeline( (array) ( $block->events ?? [] ), false ) . '</div>';

            case 'bar-chart':
                $bars = (array) ( $block->bars ?? [] );

                // If no explicit width on any bar, auto-compute from numeric value relative to max.
                $any_explicit_width = false;
                $max_numeric        = 0.0;
                foreach ( $bars as $b ) {
                    if ( isset( $b->width ) && $b->width !== '' ) {
                        $any_explicit_width = true;
                    }
                    if ( isset( $b->value ) && is_numeric( $b->value ) ) {
                        $max_numeric = max( $max_numeric, (float) $b->value );
                    }
                }

                $rows = '';
                foreach ( $bars as $bar ) {
                    $width = $this->normalize_bar_width( $bar->width ?? null, $bar->value ?? null, $max_numeric, $any_explicit_width );
                    $color = $this->normalize_bar_color( $bar->color ?? null );
                    $rows .= '<div class="bar-row"><div class="bar-label">' . esc_html( $bar->label ?? '' ) . "</div><div class=\"bar-track\"><div class=\"bar-fill\" style=\"width:{$width};background:{$color}\">" . esc_html( $bar->value ?? '' ) . '</div></div></div>';
                }
                $title = esc_html( $block->title ?? '' );
                return '<figure class="block chart">' . ( $title !== '' ? "<figcaption class=\"chart-title\">{$title}</figcaption>" : '' ) . "<div class=\"bar-chart\">{$rows}</div></figure>";

            case 'evidence':
                break;

            default:
                if ( $context !== 'finding' ) {
                    return '';
                }
        }

        $tone = $this->tone( $block->variant ?? '' );
        return '<figure class="block block--log' . ( $tone ? " is-{$tone}" : '' ) . "\">{$caption}<pre>" . esc_html( $block->content ?? '' ) . '</pre></figure>';
    }

    /**
     * Custom section content: consecutive blocks share a card, a bar chart
     * gets a card of its own, and a lone unlabelled table runs edge to edge.
     */
    private function render_section_content( $blocks ) {
        $html  = '';
        $group = [];
        $flush = function() use ( &$group, &$html ) {
            if ( ! $group ) {
                return;
            }
            if ( count( $group ) === 1 && ( $group[0]->type ?? '' ) === 'table' && empty( $group[0]->label ) ) {
                $html .= '<div class="card card--flush">' . $this->render_table( $group[0] ) . "</div>\n";
            } else {
                $inner = '';
                foreach ( $group as $block ) {
                    $inner .= $this->render_block( $block, 'section' ) . "\n";
                }
                if ( trim( $inner ) !== '' ) {
                    $html .= "<div class=\"card card-body\">\n{$inner}</div>\n";
                }
            }
            $group = [];
        };

        foreach ( (array) $blocks as $block ) {
            if ( ! is_object( $block ) ) {
                continue;
            }
            if ( ( $block->type ?? 'prose' ) === 'bar-chart' ) {
                $flush();
                $html .= '<div class="card card-body">' . $this->render_block( $block, 'section' ) . "</div>\n";
                continue;
            }
            $group[] = $block;
        }
        $flush();

        return $html;
    }

    /**
     * The Anchor Hosting lockup (anchor.host/brand), drawn with tokens so it
     * follows the light and dark schemes.
     */
    private static function lockup_svg() {
        return <<<'SVG'
<svg class="lockup" viewBox="0 0 2535.74 544" role="img" aria-label="Anchor Hosting"><g class="mk" fill="none" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" transform="translate(-57.03 40.00) scale(19.3333)"><circle cx="12" cy="4.5" r="2"/><path d="M12 6.5V21"/><path d="M7.5 10h9"/><path d="M4 14.5a8 8 0 0 0 16 0"/><path d="M4 14.5h2.6M20 14.5h-2.6"/></g><g class="wm"><path transform="translate(525.93 373.32) scale(0.272000 -0.272000)" d="M11 0 268 745H444L701 0H553L500 160H212L158 0ZM251 280H461L356 594Z"/><path transform="translate(716.88 373.32) scale(0.272000 -0.272000)" d="M61 0V544H184V477Q206 516 246.0 536.0Q286 556 338 556Q398 556 444.0 529.5Q490 503 516.5 457.0Q543 411 543 350V0H412V319Q412 373 381.5 404.5Q351 436 302 436Q253 436 222.5 404.0Q192 372 192 319V0Z"/><path transform="translate(875.45 373.32) scale(0.272000 -0.272000)" d="M323 -12Q241 -12 177.0 25.5Q113 63 76.0 127.5Q39 192 39 273Q39 354 76.0 417.5Q113 481 177.0 518.5Q241 556 323 556Q381 556 431.0 535.5Q481 515 517.0 479.0Q553 443 570 395L455 345Q440 386 404.5 411.0Q369 436 323 436Q280 436 246.5 414.5Q213 393 194.0 356.5Q175 320 175 272Q175 224 194.0 187.0Q213 150 246.5 129.0Q280 108 323 108Q370 108 404.5 132.5Q439 157 455 200L570 148Q554 101 518.0 65.0Q482 29 432.0 8.5Q382 -12 323 -12Z"/><path transform="translate(1038.11 373.32) scale(0.272000 -0.272000)" d="M61 0V757H192V489Q215 522 253.0 539.0Q291 556 338 556Q398 556 444.0 529.5Q490 503 516.5 457.0Q543 411 543 350V0H412V319Q412 373 381.0 404.5Q350 436 302 436Q254 436 223.0 404.0Q192 372 192 319V0Z"/><path transform="translate(1196.69 373.32) scale(0.272000 -0.272000)" d="M327 -12Q247 -12 181.5 25.0Q116 62 77.5 126.0Q39 190 39 272Q39 354 77.5 418.0Q116 482 181.0 519.0Q246 556 327 556Q406 556 471.0 519.0Q536 482 575.0 418.0Q614 354 614 272Q614 190 575.0 125.5Q536 61 471.0 24.5Q406 -12 327 -12ZM327 108Q370 108 404.0 129.0Q438 150 457.5 187.0Q477 224 477 272Q477 319 457.5 356.0Q438 393 404.0 414.5Q370 436 327 436Q282 436 248.0 414.5Q214 393 194.5 356.0Q175 319 175 272Q175 224 194.5 187.0Q214 150 248.0 129.0Q282 108 327 108Z"/><path transform="translate(1371.31 373.32) scale(0.272000 -0.272000)" d="M61 0V544H184V468Q205 513 243.0 531.5Q281 550 331 550H363V434H316Q261 434 226.5 399.5Q192 365 192 303V0Z"/><path transform="translate(1516.01 373.32) scale(0.272000 -0.272000)" d="M72 0V745H208V420H525V745H662V0H525V300H208V0Z"/><path transform="translate(1712.67 373.32) scale(0.272000 -0.272000)" d="M327 -12Q247 -12 181.5 25.0Q116 62 77.5 126.0Q39 190 39 272Q39 354 77.5 418.0Q116 482 181.0 519.0Q246 556 327 556Q406 556 471.0 519.0Q536 482 575.0 418.0Q614 354 614 272Q614 190 575.0 125.5Q536 61 471.0 24.5Q406 -12 327 -12ZM327 108Q370 108 404.0 129.0Q438 150 457.5 187.0Q477 224 477 272Q477 319 457.5 356.0Q438 393 404.0 414.5Q370 436 327 436Q282 436 248.0 414.5Q214 393 194.5 356.0Q175 319 175 272Q175 224 194.5 187.0Q214 150 248.0 129.0Q282 108 327 108Z"/><path transform="translate(1887.29 373.32) scale(0.272000 -0.272000)" d="M268 -12Q181 -12 116.5 29.5Q52 71 28 142L126 189Q147 143 184.5 117.0Q222 91 268 91Q303 91 324.5 106.5Q346 122 346 149Q346 173 327.5 186.0Q309 199 283 206L194 231Q125 250 89.5 291.5Q54 333 54 389Q54 439 80.0 476.5Q106 514 151.0 535.0Q196 556 255 556Q332 556 391.0 518.5Q450 481 475 415L375 368Q361 405 328.0 427.0Q295 449 254 449Q221 449 202.0 434.0Q183 419 183 395Q183 372 200.5 359.0Q218 346 247 338L334 312Q401 292 437.5 251.5Q474 211 474 154Q474 104 448.0 67.0Q422 30 375.5 9.0Q329 -12 268 -12Z"/><path transform="translate(2024.38 373.32) scale(0.272000 -0.272000)" d="M309 -6Q217 -6 166.5 44.5Q116 95 116 187V427H22V544H32Q72 544 94.0 565.0Q116 586 116 626V668H247V544H372V427H247V194Q247 153 269.0 131.0Q291 109 339 109Q354 109 374 112V0Q360 -2 342.0 -4.0Q324 -6 309 -6Z"/><path transform="translate(2134.00 373.32) scale(0.272000 -0.272000)" d="M61 605V745H192V605ZM61 0V544H192V0Z"/><path transform="translate(2199.82 373.32) scale(0.272000 -0.272000)" d="M61 0V544H184V477Q206 516 246.0 536.0Q286 556 338 556Q398 556 444.0 529.5Q490 503 516.5 457.0Q543 411 543 350V0H412V319Q412 373 381.5 404.5Q351 436 302 436Q253 436 222.5 404.0Q192 372 192 319V0Z"/><path transform="translate(2358.40 373.32) scale(0.272000 -0.272000)" d="M325 -220Q233 -220 163.5 -176.5Q94 -133 68 -61L190 -15Q202 -53 237.5 -76.5Q273 -100 325 -100Q384 -100 422.0 -66.5Q460 -33 460 27V86Q402 22 303 22Q226 22 166.5 57.0Q107 92 73.0 152.5Q39 213 39 290Q39 366 72.5 426.0Q106 486 165.5 521.0Q225 556 300 556Q406 556 467 480V544H591V27Q591 -45 556.5 -100.5Q522 -156 462.0 -188.0Q402 -220 325 -220ZM319 143Q381 143 420.5 184.0Q460 225 460 289Q460 352 420.0 394.0Q380 436 319 436Q277 436 244.5 416.5Q212 397 193.5 364.0Q175 331 175 289Q175 247 193.5 214.0Q212 181 244.5 162.0Q277 143 319 143Z"/></g></svg>
SVG;
    }

    public static function report_js() {
        return <<<'JS'
(function () {
	var root = document.documentElement;
	var media = window.matchMedia('(prefers-color-scheme: dark)');
	function effective() {
		return root.getAttribute('data-theme') || (media.matches ? 'dark' : 'light');
	}
	function sync() { root.setAttribute('data-effective', effective()); }
	sync();
	if (media.addEventListener) media.addEventListener('change', sync);
	var toggle = document.getElementById('theme-toggle');
	if (toggle) toggle.addEventListener('click', function () {
		var next = effective() === 'dark' ? 'light' : 'dark';
		root.setAttribute('data-theme', next);
		try { localStorage.setItem('anchor-report-theme', next); } catch (e) {}
		sync();
	});

	// Findings filter
	var pills = document.querySelectorAll('.pill[data-filter]');
	var findings = document.querySelectorAll('.finding');
	pills.forEach(function (pill) {
		pill.addEventListener('click', function () {
			var f = pill.getAttribute('data-filter');
			pills.forEach(function (p) { p.classList.toggle('is-active', p === pill); });
			findings.forEach(function (card) {
				card.hidden = f !== 'all' && card.getAttribute('data-status') !== f;
			});
		});
	});

	// Open items jump to a finding even when the filter hides it
	document.querySelectorAll('.open-item').forEach(function (a) {
		a.addEventListener('click', function () {
			var target = document.querySelector(a.getAttribute('href'));
			var all = document.querySelector('.pill[data-filter="all"]');
			if (target && target.hidden && all) all.click();
		});
	});

	// Scroll spy for the table of contents
	var links = Array.prototype.slice.call(document.querySelectorAll('.toc a'));
	var targets = links.map(function (l) { return document.getElementById(l.getAttribute('href').slice(1)); });
	function spy() {
		if (!links.length) return;
		var y = window.scrollY + 120, current = 0;
		targets.forEach(function (t, i) { if (t && t.getBoundingClientRect().top + window.scrollY <= y) current = i; });
		if (window.innerHeight + window.scrollY >= document.body.scrollHeight - 4) current = targets.length - 1;
		links.forEach(function (l, i) { l.classList.toggle('is-active', i === current); });
	}
	window.addEventListener('scroll', spy, { passive: true });
	spy();
})();
JS;
    }

    public static function report_css() {
        return <<<'CSS'
/* Tokens: anchor.host/brand, light and dark */
:root {
	--bg: #F5F7FA;
	--surface: #FFFFFF;
	--surface-2: #FAFBFD;
	--surface-3: #EDF0F5;
	--border: #E3E7EE;
	--border-strong: #CBD2DE;
	--text: #15181D;
	--text-2: #565C66;
	--text-3: #666D7A;
	--navy: #123E8C;
	--navy-hover: #0D2F6E;
	--on-navy: #FFFFFF;
	--navy-soft: #EAF0FB;
	--good: #1C8A55;
	--warn: #B0761B;
	--bad: #BF3B2E;
	--shadow: rgba(18, 25, 40, .07);
	--code-bg: #15181D;
	--code-text: #E3E7EE;
	--code-border: #15181D;

	--good-soft: color-mix(in srgb, var(--good) 10%, var(--surface));
	--warn-soft: color-mix(in srgb, var(--warn) 12%, var(--surface));
	--bad-soft: color-mix(in srgb, var(--bad) 10%, var(--surface));
	--good-line: color-mix(in srgb, var(--good) 28%, var(--surface));
	--warn-line: color-mix(in srgb, var(--warn) 32%, var(--surface));
	--bad-line: color-mix(in srgb, var(--bad) 28%, var(--surface));
	--navy-line: color-mix(in srgb, var(--navy) 22%, var(--surface));

	/* The old chart palette, kept as names for bars and inline styles that still use them */
	--c1: var(--navy);
	--c2: var(--good);
	--c3: var(--bad);
	--c4: var(--navy);
	--c5: var(--text-2);
	--c6: var(--navy);
	--c7: var(--good);
	--c8: var(--warn);

	--font-sans: 'Plus Jakarta Sans', system-ui, -apple-system, 'Segoe UI', sans-serif;
	--font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
	--wrap: 1180px;
	--gutter: 24px;
	--radius-sm: 10px;
	--radius: 14px;
	--radius-lg: 18px;
}
@media (prefers-color-scheme: dark) {
	:root:not([data-theme="light"]) {
		--bg: #0B0E13; --surface: #141922; --surface-2: #1A202B; --surface-3: #212936;
		--border: #242C39; --border-strong: #35404F;
		--text: #E9ECF1; --text-2: #A3ACB9; --text-3: #767F8C;
		--navy: #5C97F7; --navy-hover: #82B0FF; --on-navy: #08111F; --navy-soft: #16223A;
		--good: #3FBE7F; --warn: #E0A64A; --bad: #EE7264;
		--shadow: rgba(0, 0, 0, .5);
		--code-bg: #0B0E13; --code-text: #E9ECF1; --code-border: #242C39;
	}
}
:root[data-theme="dark"] {
	--bg: #0B0E13; --surface: #141922; --surface-2: #1A202B; --surface-3: #212936;
	--border: #242C39; --border-strong: #35404F;
	--text: #E9ECF1; --text-2: #A3ACB9; --text-3: #767F8C;
	--navy: #5C97F7; --navy-hover: #82B0FF; --on-navy: #08111F; --navy-soft: #16223A;
	--good: #3FBE7F; --warn: #E0A64A; --bad: #EE7264;
	--shadow: rgba(0, 0, 0, .5);
	--code-bg: #0B0E13; --code-text: #E9ECF1; --code-border: #242C39;
}

/* Base */
* { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; scroll-padding-top: 88px; }
body { font-family: var(--font-sans); font-size: 16px; line-height: 1.6; color: var(--text); background: var(--bg); -webkit-font-smoothing: antialiased; overflow-x: hidden; overflow-wrap: break-word; }
a { color: var(--navy); text-decoration: none; }
a:hover { color: var(--navy-hover); }
code { font-family: var(--font-mono); font-size: .84em; background: var(--surface-3); padding: 1px 6px; border-radius: 5px; overflow-wrap: anywhere; }
pre code { background: none; padding: 0; border-radius: 0; font-size: inherit; color: inherit; overflow-wrap: normal; }
.mono { font-family: var(--font-mono); }
.wrap { max-width: var(--wrap); margin: 0 auto; padding: 0 var(--gutter); }
.eyebrow { font-size: 12px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--text-3); }
svg.i { width: 16px; height: 16px; flex: none; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

/* Top bar */
.topbar { position: sticky; top: 0; z-index: 50; background: color-mix(in srgb, var(--surface) 88%, transparent); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); border-bottom: 1px solid var(--border); }
.topbar__inner { max-width: var(--wrap); margin: 0 auto; padding: 13px var(--gutter); display: flex; align-items: center; gap: 18px; }
.lockup { display: block; height: 26px; width: auto; }
.lockup .mk { stroke: var(--navy); }
.lockup .wm { fill: var(--text); }
.topbar__doc { padding-left: 18px; border-left: 1px solid var(--border); font-size: 14px; font-weight: 600; color: var(--text-2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.topbar__actions { margin-left: auto; display: flex; gap: 8px; }
.btn-ghost { display: inline-flex; align-items: center; gap: 7px; height: 36px; padding: 0 12px; font: 600 13.5px/1 var(--font-sans); color: var(--text-2); background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; transition: color .15s ease, border-color .15s ease; }
.btn-ghost:hover { color: var(--text); border-color: var(--border-strong); }
.btn-ghost--icon { width: 36px; padding: 0; justify-content: center; }
.i-sun { display: none; }
html[data-effective="dark"] .i-sun { display: block; }
html[data-effective="dark"] .i-moon { display: none; }

/* Masthead + stat band */
.masthead { background: var(--surface); padding: 60px 0 44px; }
.masthead h1 { margin-top: 14px; font-size: clamp(2.2rem, 5vw, 3.4rem); font-weight: 800; line-height: 1.05; letter-spacing: -.035em; overflow-wrap: anywhere; }
.masthead .lede { margin-top: 18px; max-width: 700px; font-size: 17.5px; line-height: 1.6; color: var(--text-2); }
.masthead__meta { margin-top: 26px; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 18px; font-size: 14px; color: var(--text-3); }
.verdict { display: inline-flex; align-items: center; gap: 8px; padding: 7px 13px 7px 11px; font-size: 14px; font-weight: 700; border-radius: 999px; color: var(--text-2); background: var(--surface-3); border: 1px solid var(--border); }
.verdict .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.verdict--good { color: var(--good); background: var(--good-soft); border-color: var(--good-line); }
.verdict--warn { color: var(--warn); background: var(--warn-soft); border-color: var(--warn-line); }
.verdict--bad { color: var(--bad); background: var(--bad-soft); border-color: var(--bad-line); }
.verdict--good .dot { box-shadow: 0 0 0 3px var(--good-line); }
.verdict--warn .dot { box-shadow: 0 0 0 3px var(--warn-line); }
.verdict--bad .dot { box-shadow: 0 0 0 3px var(--bad-line); }
.statband { background: var(--surface-2); border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.statband__inner { max-width: var(--wrap); margin: 0 auto; padding: 30px var(--gutter); display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 26px; }
.stat__value { display: flex; align-items: center; gap: 9px; font-family: var(--font-mono); font-size: 27px; font-weight: 600; letter-spacing: -.02em; line-height: 1.2; overflow-wrap: anywhere; }
.stat__value--long { font-size: 19px; }
.stat__value.is-good { color: var(--good); }
.stat__value.is-warn { color: var(--warn); }
.stat__value.is-bad { color: var(--bad); }
.stat__value.is-info { color: var(--navy); }
.stat__value svg.i { width: 22px; height: 22px; stroke-width: 2.2; }
.stat__label { display: block; margin-top: 5px; font-size: 13.5px; color: var(--text-3); }
.stat__sub { font-family: var(--font-mono); font-size: 12px; color: var(--text-3); margin-top: 2px; }

/* Layout: table of contents + content */
.layout { display: grid; grid-template-columns: 196px minmax(0, 1fr); gap: 56px; padding-top: 44px; padding-bottom: 72px; }
.toc { position: sticky; top: 92px; align-self: start; }
.toc .eyebrow { display: block; margin-bottom: 12px; }
.toc ol { list-style: none; border-left: 1px solid var(--border); }
.toc a { display: flex; gap: 10px; align-items: baseline; margin-left: -1px; padding: 6px 0 6px 14px; font-size: 14px; font-weight: 500; color: var(--text-2); border-left: 2px solid transparent; transition: color .15s ease, border-color .15s ease; }
.toc a:hover { color: var(--text); }
.toc a.is-active { color: var(--navy); border-left-color: var(--navy); font-weight: 600; }
.toc .n { font-family: var(--font-mono); font-size: 11.5px; color: var(--text-3); }
.toc a.is-active .n { color: var(--navy); }
.toc .count { margin-left: auto; font-family: var(--font-mono); font-size: 11.5px; color: var(--text-3); }
.content { min-width: 0; }

/* Cards, sections */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg); box-shadow: 0 1px 2px var(--shadow); }
.card-body { padding: 22px 26px 24px; }
.card-body > .block:first-child { margin-top: 0; }
.card--flush { overflow: hidden; }
.section > .card + .card { margin-top: 14px; }
.section { margin-top: 64px; }
.content > .section:first-child { margin-top: 0; }
.section-head { margin-bottom: 20px; }
.section-head .n { font-family: var(--font-mono); font-size: 13px; font-weight: 600; color: var(--navy); }
.section-head h2 { margin-top: 6px; font-size: clamp(1.5rem, 2.4vw, 1.9rem); font-weight: 800; line-height: 1.15; letter-spacing: -.028em; }
.section-head p { margin-top: 8px; font-size: 15.5px; color: var(--text-2); max-width: 640px; }
.section-head__row { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px 24px; }

/* Open items */
.open-items { padding: 6px 0; }
.open-items__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 22px 12px; }
.open-items__head h2 { font-size: 17px; font-weight: 700; letter-spacing: -.01em; }
.open-items ol { list-style: none; }
.open-items li + li { border-top: 1px solid var(--border); }
.open-item { display: grid; grid-template-columns: 82px minmax(0, 1fr) auto; gap: 14px; align-items: start; padding: 15px 22px; color: inherit; transition: background .15s ease; }
.open-item:hover { background: var(--surface-2); color: inherit; }
.open-item .sev { margin-top: 2px; justify-self: start; }
.open-item__title { font-size: 15px; font-weight: 600; line-height: 1.4; }
.open-item__rec { margin-top: 3px; font-size: 14px; color: var(--text-2); line-height: 1.5; }
.open-item__go { color: var(--text-3); margin-top: 3px; }
.open-item:hover .open-item__go { color: var(--navy); }
.all-resolved { display: flex; align-items: center; gap: 12px; padding: 16px 22px; font-size: 15px; font-weight: 600; }
.open-items + .section, .all-resolved + .section { margin-top: 56px; }

/* Chips */
.sev, .status, .badge { display: inline-flex; align-items: center; gap: 5px; height: 23px; padding: 0 9px; font-family: var(--font-mono); font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; border-radius: 7px; white-space: nowrap; vertical-align: middle; }
.sev--other, .badge { background: var(--surface-3); color: var(--text-2); }
.sev--critical, .badge-critical, .severity.critical { background: var(--bad); color: var(--surface); }
.sev--high, .badge-high, .severity.high { background: var(--bad-soft); color: var(--bad); box-shadow: inset 0 0 0 1px var(--bad-line); }
.sev--medium, .badge-medium, .severity.medium { background: var(--warn-soft); color: var(--warn); box-shadow: inset 0 0 0 1px var(--warn-line); }
.sev--low, .sev--info, .badge-low, .severity.low { background: var(--navy-soft); color: var(--navy); box-shadow: inset 0 0 0 1px var(--navy-line); }
.status--resolved, .badge-clean, .severity.clean { background: var(--good-soft); color: var(--good); box-shadow: inset 0 0 0 1px var(--good-line); }
.status--open { background: var(--surface); color: var(--text-2); box-shadow: inset 0 0 0 1px var(--border-strong); }
.status svg.i { width: 12px; height: 12px; stroke-width: 2.6; }
.severity { display: inline-block; font-family: var(--font-mono); font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; padding: 2px 8px; border-radius: 6px; vertical-align: middle; }

/* Scan results */
.checks__summary { display: flex; flex-wrap: wrap; gap: 8px 22px; padding: 16px 22px; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text-2); }
.checks__summary b { font-family: var(--font-mono); font-weight: 600; color: var(--text); }
.checks__summary .k { display: inline-flex; align-items: center; gap: 7px; }
.checks__group-label { padding: 14px 22px 6px; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-3); }
.checks { list-style: none; padding: 0 22px 8px; }
.card-body .checks { padding: 0; }
.check { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 12px; padding: 11px 0; font-size: 14.5px; line-height: 1.5; color: var(--text-2); }
.check + .check { border-top: 1px solid var(--border); }
.check__text strong { color: var(--text); font-weight: 650; }
.ico { display: grid; place-items: center; width: 22px; height: 22px; border-radius: 50%; flex: none; }
.ico svg.i { width: 12px; height: 12px; stroke-width: 3; }
.ico--sm { width: 18px; height: 18px; }
.ico--sm svg.i { width: 10px; height: 10px; }
.ico--pass { background: var(--good-soft); color: var(--good); }
.ico--warn { background: var(--warn-soft); color: var(--warn); }
.ico--fail { background: var(--bad-soft); color: var(--bad); }
.ico--info { background: var(--navy-soft); color: var(--navy); }
details.checks-more { border-top: 1px solid var(--border); }
.checks__summary + details.checks-more { border-top: 0; }
details.checks-more > summary { display: flex; align-items: center; gap: 8px; padding: 14px 22px; list-style: none; cursor: pointer; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: var(--text-3); user-select: none; }
details.checks-more > summary::-webkit-details-marker { display: none; }
details.checks-more > summary svg.i { transition: transform .15s ease; }
details.checks-more[open] > summary svg.i { transform: rotate(90deg); }
details.checks-more > summary:hover { color: var(--text); }

/* Findings */
.tally { display: flex; flex-wrap: wrap; gap: 6px 16px; margin: -6px 0 18px; font-size: 14px; color: var(--text-2); }
.tally span { display: inline-flex; align-items: center; gap: 7px; }
.tally b { font-family: var(--font-mono); font-weight: 600; color: var(--text); }
.tally .d { width: 8px; height: 8px; border-radius: 50%; }
.d--critical, .d--high { background: var(--bad); }
.d--critical { box-shadow: 0 0 0 2px var(--bad-line); }
.d--medium { background: var(--warn); }
.d--low, .d--info { background: var(--navy); }
.d--other { background: var(--text-3); }
.pill-group { display: inline-flex; gap: 4px; padding: 4px; background: var(--surface-3); border-radius: 12px; }
.pill { display: inline-flex; align-items: center; gap: 7px; font: 600 13.5px/1 var(--font-sans); padding: 8px 13px; border-radius: 9px; border: none; background: transparent; color: var(--text-2); cursor: pointer; transition: background .15s ease, color .15s ease; }
.pill:hover { color: var(--text); }
.pill.is-active { background: var(--surface); color: var(--text); box-shadow: 0 1px 3px var(--shadow); }
.pill .c { font-family: var(--font-mono); font-size: 11.5px; color: var(--text-3); }
.findings { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
.finding { padding: 24px 26px 26px; }
.finding[hidden] { display: none; }
.finding__chips { display: flex; flex-wrap: wrap; gap: 6px; }
.finding__title { margin-top: 12px; font-size: 19px; font-weight: 700; line-height: 1.35; letter-spacing: -.015em; }
.finding > .prose { margin-top: 12px; }

/* Prose (descriptions, resolutions and prose blocks carry author HTML) */
.prose { font-size: 15.5px; line-height: 1.65; color: var(--text-2); overflow-x: auto; }
.prose > * + * { margin-top: 10px; }
.prose ul, .prose ol { padding-left: 20px; }
.prose li + li { margin-top: 5px; }
.prose li::marker { color: var(--text-3); }
.prose strong, .prose b { color: var(--text); font-weight: 650; }
.prose h2, .prose h3, .prose h4 { color: var(--text); font-weight: 700; letter-spacing: -.01em; line-height: 1.35; }
.prose h2 { font-size: 18px; }
.prose h3 { font-size: 16.5px; }
.prose h4 { font-size: 15.5px; }
.prose pre { font-family: var(--font-mono); font-size: 13px; line-height: 1.7; padding: 15px 18px; border-radius: var(--radius); overflow-x: auto; background: var(--code-bg); color: var(--code-text); border: 1px solid var(--code-border); }
.prose table { font-size: 14px; }
.prose blockquote { padding-left: 14px; border-left: 3px solid var(--border-strong); }
.issue { padding-top: 14px; }
.issue + .issue { margin-top: 14px; border-top: 1px solid var(--border); }
.prose > .issue:first-child { padding-top: 0; }
.issue h3 { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.issue h3 + p { margin-top: 6px; }

/* Blocks */
.block { margin-top: 18px; }
.block__label { display: block; margin-bottom: 7px; font-family: var(--font-mono); font-size: 11.5px; font-weight: 500; color: var(--text-3); }
.block pre { font-family: var(--font-mono); font-size: 13px; line-height: 1.7; padding: 15px 18px; border-radius: var(--radius); overflow-x: auto; }
.block--code pre { background: var(--code-bg); color: var(--code-text); border: 1px solid var(--code-border); }
.block--log pre { background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border); white-space: pre-wrap; overflow-wrap: anywhere; }
.block--log.is-warn pre { background: var(--warn-soft); border-color: var(--warn-line); }
.block--log.is-bad pre { background: var(--bad-soft); border-color: var(--bad-line); }
.block--log.is-info pre { background: var(--navy-soft); border-color: var(--navy-line); }
.block--log.is-good pre { background: var(--good-soft); border-color: var(--good-line); }
.file-tree { font-family: var(--font-mono); font-size: 13px; line-height: 1.85; padding: 13px 18px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); color: var(--text-2); overflow-x: auto; white-space: nowrap; }
.file-tree .dir { color: var(--navy); font-weight: 600; }
.file-tree .mal { color: var(--bad); font-weight: 600; }
.file-tree .safe { color: var(--text-3); }
.file-tree .size { color: var(--text-3); font-size: 11.5px; margin: 0 8px; }
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
.stats > div { padding: 14px 16px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); }
.stats .v { font-family: var(--font-mono); font-size: 24px; font-weight: 600; letter-spacing: -.02em; line-height: 1.2; overflow-wrap: anywhere; }
.stats .v.is-good { color: var(--good); }
.stats .v.is-warn { color: var(--warn); }
.stats .v.is-bad { color: var(--bad); }
.stats .v.is-info { color: var(--navy); }
.stats .l { margin-top: 4px; font-size: 13px; color: var(--text-3); line-height: 1.35; }
.ioc-grid { column-gap: 18px; font-family: var(--font-mono); font-size: 12.5px; line-height: 1.75; color: var(--text-2); padding: 12px 16px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); }
.ioc-grid span { display: block; break-inside: avoid; overflow-wrap: anywhere; }
.diff { font-family: var(--font-mono); font-size: 13px; line-height: 1.7; border-radius: var(--radius); overflow: hidden; border: 1px solid var(--border); }
.diff-header { background: var(--surface-2); padding: 8px 16px; font-size: 11.5px; color: var(--text-3); border-bottom: 1px solid var(--border); }
.diff-body { padding: 8px 0; overflow-x: auto; }
.diff-line { padding: 0 16px; white-space: pre-wrap; overflow-wrap: anywhere; color: var(--text-2); }
.diff-line.add { background: var(--good-soft); color: var(--good); }
.diff-line.add::before { content: '+ '; font-weight: 700; }
.diff-line.del { background: var(--bad-soft); color: var(--bad); }
.diff-line.del::before { content: '- '; font-weight: 700; }
.diff-line.ctx { color: var(--text-3); }
.diff-line.ctx::before { content: '  '; }
.chart-title { margin-bottom: 14px; font-size: 15px; font-weight: 700; color: var(--text); }
.bar-chart { display: flex; flex-direction: column; gap: 9px; }
.bar-row { display: grid; grid-template-columns: 180px minmax(0, 1fr); gap: 14px; align-items: center; }
.bar-label { font-size: 13.5px; text-align: right; color: var(--text-2); line-height: 1.35; }
.bar-track { height: 26px; background: var(--surface-3); border-radius: 7px; overflow: hidden; }
.bar-fill { height: 100%; border-radius: 7px; display: flex; align-items: center; padding: 0 9px; font-family: var(--font-mono); font-size: 11.5px; font-weight: 600; color: var(--on-navy); min-width: fit-content; white-space: nowrap; }

/* Callouts, plus the class names older report content uses */
.callout { padding: 14px 18px; border-radius: var(--radius); font-size: 14.5px; line-height: 1.6; color: var(--text); background: var(--navy-soft); border: 1px solid var(--navy-line); }
.callout > * + * { margin-top: 8px; }
.callout strong:first-child { display: block; margin-bottom: 2px; }
.callout ul, .callout ol { padding-left: 20px; }
.callout--info strong:first-child, .callout.blue strong:first-child { color: var(--navy); }
.callout--good, .callout.green { background: var(--good-soft); border-color: var(--good-line); }
.callout--good strong:first-child, .callout.green strong:first-child { color: var(--good); }
.callout--warn, .callout.yellow, .callout-yellow { background: var(--warn-soft); border-color: var(--warn-line); }
.callout--warn strong:first-child, .callout.yellow strong:first-child, .callout-yellow strong:first-child { color: var(--warn); }
.callout--bad, .callout.red { background: var(--bad-soft); border-color: var(--bad-line); }
.callout--bad strong:first-child, .callout.red strong:first-child { color: var(--bad); }
.good { color: var(--good); font-weight: 600; }
.warn-cell { color: var(--warn); font-weight: 600; }
.poor { color: var(--bad); font-weight: 600; }
.evidence { font-family: var(--font-mono); font-size: 13px; line-height: 1.7; padding: 13px 16px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--radius); white-space: pre-wrap; overflow-wrap: anywhere; }
.evidence-label { display: block; font-family: var(--font-mono); font-size: 11.5px; color: var(--text-3); }
details.collapsible { border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; }
details.collapsible summary { display: flex; align-items: center; gap: 8px; padding: 12px 16px; font-weight: 600; color: var(--text); cursor: pointer; list-style: none; }
details.collapsible summary::-webkit-details-marker { display: none; }
details.collapsible .summary-count { margin-left: auto; font-family: var(--font-mono); font-size: 11.5px; color: var(--text-3); }
details.collapsible .collapsible-body { padding: 12px 16px 14px; border-top: 1px solid var(--border); }

/* Resolution + recommendation */
.resolution { margin-top: 20px; padding: 15px 18px; background: var(--good-soft); border: 1px solid var(--good-line); border-radius: var(--radius); font-size: 14.5px; line-height: 1.6; color: var(--text); }
.resolution > p + p, .resolution > p + ul, .resolution > ul + p { margin-top: 8px; }
.resolution ul { padding-left: 20px; }
.resolution code { background: color-mix(in srgb, var(--good) 14%, var(--surface)); }
.resolution__label, .rec__label { display: flex; align-items: center; gap: 7px; margin-bottom: 5px; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
.resolution__label { color: var(--good); }
.resolution__label svg.i { width: 14px; height: 14px; stroke-width: 2.6; }
.rec { margin-top: 14px; padding: 15px 18px; background: var(--navy-soft); border-radius: var(--radius); font-size: 14.5px; line-height: 1.6; }
.rec__label { color: var(--navy); }
.rec--done { background: transparent; padding: 10px 0 0; font-size: 13.5px; color: var(--text-3); }
.rec--done .rec__label { display: inline; font-size: inherit; letter-spacing: 0; text-transform: none; font-weight: 600; color: var(--text-2); margin: 0 4px 0 0; }

/* Tables */
.table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.block .table-wrap { border: 1px solid var(--border); border-radius: var(--radius); }
table { width: 100%; border-collapse: collapse; font-size: 14.5px; }
th, td { text-align: left; padding: 12px 18px; border-bottom: 1px solid var(--border); vertical-align: top; }
td { overflow-wrap: break-word; }
th { font-size: 12px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: var(--text-3); background: var(--surface-2); white-space: nowrap; }
tbody tr:last-child td { border-bottom: none; }
td.mono { font-size: 13px; }
td.break, td.mono { overflow-wrap: anywhere; }
td .sub { display: block; margin-top: 1px; font-size: 13px; color: var(--text-3); }
td.nowrap { white-space: nowrap; }
.st { display: inline-flex; align-items: baseline; gap: 7px; font-weight: 600; }
.st::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; flex: none; transform: translateY(-1px); }
.st--good { color: var(--good); }
.st--warn { color: var(--warn); }
.st--bad { color: var(--bad); }
.st--info { color: var(--navy); }

/* Site configuration */
.config { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.config > div { padding: 15px 22px; border-top: 1px solid var(--border); min-width: 0; }
.config > div:nth-child(-n+2) { border-top: none; }
.config > div:nth-child(even) { border-left: 1px solid var(--border); }
.config dt { font-size: 13px; color: var(--text-3); }
.config dd { margin-top: 3px; font-size: 15px; font-weight: 600; overflow-wrap: anywhere; }

/* Timeline */
.timeline { list-style: none; padding: 24px 26px 8px; }
.card-body .timeline, .block .timeline { padding: 4px 0 0; }
.timeline li { position: relative; display: grid; grid-template-columns: 168px minmax(0, 1fr); gap: 20px; padding-bottom: 22px; }
.timeline li::before { content: ''; position: absolute; left: 168px; top: 18px; bottom: -4px; width: 2px; background: var(--border); }
.timeline li:last-child::before { display: none; }
.timeline .when { position: relative; font-family: var(--font-mono); font-size: 12.5px; font-weight: 500; color: var(--text-3); text-align: right; padding-top: 2px; padding-right: 14px; line-height: 1.5; }
.timeline .when::after { content: ''; position: absolute; right: -7px; top: 5px; width: 12px; height: 12px; border-radius: 50%; background: var(--bad); box-shadow: 0 0 0 4px var(--surface); }
.timeline .tl--warn .when::after { background: var(--warn); }
.timeline .tl--info .when::after { background: var(--navy); }
.timeline .tl--good .when::after { background: var(--good); }
.timeline .what { font-size: 15px; line-height: 1.6; color: var(--text-2); padding-left: 16px; min-width: 0; }
.timeline .what > * + * { margin-top: 8px; }

/* Footer */
.footer { border-top: 1px solid var(--border); background: var(--surface); }
.footer__inner { max-width: var(--wrap); margin: 0 auto; padding: 34px var(--gutter) 40px; font-size: 14px; color: var(--text-3); }
.footer__brand { display: flex; gap: 12px; align-items: flex-start; }
.footer__brand svg { width: 30px; height: 30px; flex: none; stroke: var(--navy); }
.footer__brand strong { display: block; font-size: 15px; color: var(--text); font-weight: 700; letter-spacing: -.01em; }

/* Responsive */
@media (max-width: 980px) {
	.layout { grid-template-columns: minmax(0, 1fr); gap: 0; padding-top: 28px; }
	.toc { display: none; }
}
@media (max-width: 720px) {
	:root { --gutter: 16px; }
	.topbar__doc { display: none; }
	.btn-ghost--print span { display: none; }
	.btn-ghost--print { width: 36px; padding: 0; justify-content: center; }
	.masthead { padding: 38px 0 30px; }
	.masthead .lede { font-size: 16.5px; }
	.statband__inner { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 22px 16px; padding: 24px var(--gutter); }
	.stat__value { font-size: 23px; }
	.stat__value--long { font-size: 17px; }
	.section { margin-top: 48px; }
	.finding, .card-body { padding: 20px 18px 22px; }
	.finding__title { font-size: 17.5px; }
	.checks, .checks__summary, .checks__group-label, details.checks-more > summary { padding-left: 18px; padding-right: 18px; }
	.card-body .checks { padding: 0; }
	.open-item { grid-template-columns: minmax(0, 1fr) auto; padding: 14px 18px; gap: 6px 12px; }
	.open-item .sev, .open-item__body { grid-column: 1; }
	.open-item__go { grid-column: 2; grid-row: 1 / span 2; align-self: center; }
	.open-items__head, .all-resolved { padding-left: 18px; padding-right: 18px; }
	.config { grid-template-columns: minmax(0, 1fr); }
	.config > div:nth-child(even) { border-left: none; }
	.config > div:nth-child(2) { border-top: 1px solid var(--border); }
	.timeline { padding: 20px 18px 4px; }
	.timeline li { grid-template-columns: minmax(0, 1fr); gap: 2px; padding-left: 22px; }
	.timeline li::before { left: 5px; top: 18px; }
	.timeline .when { text-align: left; padding: 0; }
	.timeline .when::after { left: -22px; right: auto; top: 4px; }
	.timeline .what { padding-left: 0; }
	.bar-row { grid-template-columns: minmax(0, 1fr); gap: 4px; }
	.bar-label { text-align: left; }
	.ioc-grid { column-count: 1 !important; }
	th, td { padding: 11px 14px; }
	.table-wrap table { min-width: 620px; }
}

/* Print: always light, every finding visible, no chrome */
@media print {
	:root, :root[data-theme="dark"] {
		--bg: #FFFFFF; --surface: #FFFFFF; --surface-2: #FAFBFD; --surface-3: #EDF0F5;
		--border: #E3E7EE; --border-strong: #CBD2DE; --text: #15181D; --text-2: #565C66; --text-3: #666D7A;
		--navy: #123E8C; --navy-soft: #EAF0FB; --on-navy: #FFFFFF; --good: #1C8A55; --warn: #B0761B; --bad: #BF3B2E;
		--code-bg: #FAFBFD; --code-text: #15181D; --code-border: #E3E7EE;
	}
	.topbar { position: static; backdrop-filter: none; }
	.topbar__actions, .toc, .pill-group { display: none !important; }
	.layout { grid-template-columns: minmax(0, 1fr); padding-top: 20px; }
	.finding[hidden] { display: block !important; }
	.card { box-shadow: none; break-inside: avoid; }
	.finding, .card-body, .card--flush { break-inside: auto; }
	.block, .resolution, .rec, .check, .timeline li, tr { break-inside: avoid; }
	.block pre, .file-tree, .prose pre { white-space: pre-wrap; overflow-wrap: anywhere; }
	details > *:not(summary) { display: block !important; }
	a { color: inherit; }
}
CSS;
    }

}
