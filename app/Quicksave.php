<?php 

namespace CaptainCore;

class Quicksave {
    
    protected $site_id = "";

    public function __construct( $site_id = "" ) {
        $this->site_id = $site_id;
    }

    // Constrain the environment segment to the known values; site_id is an int.
    private function safe_environment( $environment ) {
        return strtolower( (string) $environment ) === "staging" ? "staging" : "production";
    }

    public function get( $hash, $environment = "production" ) {
        $environment = $this->safe_environment( $environment );
        $response = Run::CLI( [ 'quicksave', 'get', "{$this->site_id}-{$environment}", $hash ] );
        $json     = json_decode( $response );
        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }
        return $json;
    }

    public function search( $search, $environment = "production" ) {
        $environment = $this->safe_environment( $environment );
        $response = Run::CLI( [ 'quicksave', 'search', "{$this->site_id}-{$environment}", base64_encode( $search ) ] );
        $json     = json_decode( $response );
        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }
        return $json;
    }

    public function changed( $hash, $environment = "production", $match = "" ) {
        $environment = $this->safe_environment( $environment );
        return Run::CLI( [ 'quicksave', 'show-changes', "{$this->site_id}-{$environment}", $hash, $match ] );
    }

    public function filediff( $hash, $environment = "production", $file ) {
        $environment = $this->safe_environment( $environment );
        return Run::CLI( [ 'quicksave', 'file-diff', "{$this->site_id}-{$environment}", $hash, $file, '--html' ] );
    }

    public function rollback( $hash, $environment = "production", $version, $type, $value = "" ) {
        $environment = $this->safe_environment( $environment );
        if ( $type == "all" ) {
            return Run::task( [ 'quicksave', 'rollback', "{$this->site_id}-{$environment}", $hash, '--version=' . $version, '--all' ] );
        }
        // $type is a flag name (plugin/theme) — restrict to a safe charset.
        $type_flag = preg_replace( '/[^a-z0-9_-]/i', '', (string) $type );
        return Run::task( [ 'quicksave', 'rollback', "{$this->site_id}-{$environment}", $hash, '--version=' . $version, '--' . $type_flag . '=' . $value ] );
    }

    public function blueprint( $hash, $environment = "production", $token = "", $include_database = false ) {
        $quicksave = $this->get( $hash, $environment );
        if ( empty( $quicksave ) ) {
            return [];
        }

        $base_url = home_url( "/wp-json/captaincore/v1/quicksaves/{$hash}/artifact" );
        $steps    = [];

        // Import database SQL before login when opted in
        if ( $include_database ) {
            $database_url = "{$base_url}?token={$token}&type=database&name=database";
            $steps[] = [
                'step' => 'runSql',
                'sql'  => [ 'resource' => 'url', 'url' => $database_url ],
            ];
        }

        // Login step
        $steps[] = [ 'step' => 'login', 'username' => 'admin', 'password' => 'password' ];

        // Install themes
        if ( ! empty( $quicksave->themes ) ) {
            foreach ( $quicksave->themes as $theme ) {
                $artifact_url = "{$base_url}?token={$token}&type=theme&name={$theme->name}";
                $steps[] = [
                    'step'      => 'installTheme',
                    'themeData' => [ 'resource' => 'url', 'url' => $artifact_url ],
                    'options'   => [ 'activate' => ( $theme->status === 'active' ) ],
                ];
            }
        }

        // Install and activate plugins via single runPHP step for resilience
        // Individual installPlugin steps crash the entire blueprint if any plugin is incompatible
        $plugin_list = [];
        if ( ! empty( $quicksave->plugins ) ) {
            foreach ( $quicksave->plugins as $plugin ) {
                if ( in_array( $plugin->status, [ 'must-use', 'dropin' ], true ) ) {
                    continue;
                }
                $artifact_url = "{$base_url}?token={$token}&type=plugin&name={$plugin->name}";
                $plugin_list[] = [
                    'url'      => $artifact_url,
                    'slug'     => $plugin->name,
                    'activate' => ( $plugin->status === 'active' ),
                ];
            }
        }

        if ( ! empty( $plugin_list ) ) {
            $plugins_escaped = str_replace( "'", "\\'", json_encode( $plugin_list ) );
            $steps[] = [
                'step' => 'runPHP',
                'code' => "<?php\nrequire '/wordpress/wp-load.php';\n\$plugins = json_decode( '{$plugins_escaped}', true );\nforeach ( \$plugins as \$p ) {\n    try {\n        \$zip_data = @file_get_contents( \$p['url'] );\n        if ( empty( \$zip_data ) ) continue;\n        \$tmp = '/tmp/' . \$p['slug'] . '.zip';\n        file_put_contents( \$tmp, \$zip_data );\n        \$z = new ZipArchive;\n        if ( \$z->open( \$tmp ) === true ) {\n            \$z->extractTo( '/wordpress/wp-content/plugins/' );\n            \$z->close();\n        }\n        @unlink( \$tmp );\n        if ( \$p['activate'] ) {\n            \$files = glob( '/wordpress/wp-content/plugins/' . \$p['slug'] . '/*.php' );\n            foreach ( \$files as \$f ) {\n                \$data = get_file_data( \$f, [ 'Name' => 'Plugin Name' ] );\n                if ( ! empty( \$data['Name'] ) ) {\n                    @activate_plugin( \$p['slug'] . '/' . basename( \$f ) );\n                    break;\n                }\n            }\n        }\n    } catch ( \\Throwable \$e ) {}\n}",
            ];
        }

        $blueprint = [
            'landingPage' => '/wp-admin/',
            'steps'       => $steps,
        ];

        // Set preferred WP version from core data
        // Use zip URL to load the exact version via Playground's proxy
        if ( ! empty( $quicksave->core ) ) {
            $core = $quicksave->core;
            if ( is_string( $core ) ) {
                $decoded = json_decode( $core );
                $version = is_object( $decoded ) && ! empty( $decoded->version ) ? $decoded->version : $core;
            } else {
                $version = ! empty( $core->version ) ? $core->version : '';
            }
            if ( ! empty( $version ) ) {
                $wp_zip = "https://playground.wordpress.net/plugin-proxy.php?url=https://wordpress.org/wordpress-{$version}.zip";
                $blueprint['preferredVersions'] = [ 'wp' => $wp_zip ];
            }
        }

        return $blueprint;
    }

}