<?php 

namespace CaptainCore;

class Quicksave {
    
    protected $site_id = "";

    public function __construct( $site_id = "" ) {
        $this->site_id = $site_id;
    }

    public function get( $hash, $environment = "production" ) {
        $command  = "quicksave get {$this->site_id}-$environment $hash";
        $response = Run::CLI( $command );
        $json     = json_decode( $response );
        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }
        return $json;
    }

    public function search( $search, $environment = "production" ) {
        $command  = "quicksave search {$this->site_id}-$environment ". base64_encode($search);
        $response = Run::CLI( $command );
        $json     = json_decode( $response );
        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }
        return $json;
    }

    public function changed( $hash, $environment = "production", $match = "" ) {
        $command  = "quicksave show-changes {$this->site_id}-$environment $hash $match";
        $response = Run::CLI( $command );
        return $response;
    }

    public function filediff( $hash, $environment = "production", $file ) {
        $command  = "quicksave file-diff {$this->site_id}-{$environment} $hash $file --html";
        $response = Run::CLI( $command );
        return $response;
    }

    public function rollback( $hash, $environment = "production", $version, $type, $value = "" ) {
        if ( $type == "all") {
            $command  = "quicksave rollback {$this->site_id}-{$environment} $hash --version=$version --all";
            $response = Run::task( $command );
            return $response;
        }
        $command  = "quicksave rollback {$this->site_id}-{$environment} $hash --version=$version --$type=$value";
        $response = Run::task( $command );
        return $response;
    }

    public function blueprint( $hash, $environment = "production", $token = "" ) {
        $quicksave = $this->get( $hash, $environment );
        if ( empty( $quicksave ) ) {
            return [];
        }

        $base_url = home_url( "/wp-json/captaincore/v1/quicksaves/{$hash}/artifact" );
        $steps    = [];

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
        if ( ! empty( $quicksave->core ) ) {
            $core = is_string( $quicksave->core ) ? json_decode( $quicksave->core ) : $quicksave->core;
            if ( ! empty( $core->version ) ) {
                $blueprint['preferredVersions'] = [ 'wp' => $core->version ];
            }
        }

        return $blueprint;
    }

}