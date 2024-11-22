<?php 

namespace CaptainCore;

class Scripts extends DB {

	static $primary_key = 'script_id';
	
	public function list() {
		$user        = new User;
		$user_id     = get_current_user_id();
		$recipes     = [];
		$all_scripts = self::fetch_scripts();

        // Bail if not assigned a role
        if ( ! $user->role_check() ) {
            return 'Error: Please log in.';
        }

        foreach( $fetch_scripts as $script ) {
			// Remove details if not and admin and record not owned by them
			if ( ! $user->is_admin() && $script->user_id != $user_id ) {
				$script->content = "";
				$script->user_id = "system";
			}
            
            unset( $script->updated_at );
            unset( $script->created_at );
            $scripts[] = $script;
        }
        //usort($scripts, function($a, $b) { return strcmp($a->title, $b->title); });
        return $scripts;
    }

    public function verify( $recipe_id = "" ) {
        // Check multiple site ids
        if ( is_array( $recipe_id ) ) {
            $valid = true;
            foreach ($site_id as $id) {
                if ( in_array( $id, $this->sites_all ) ) {
                    continue;
                }
                $valid = false;
            }
            return $valid;
        }
        // Check individual site id
        if ( in_array( $site_id, $this->sites_all ) ) {
            return true;
        }
        return false;
    }

    public static function run_scheduled() {
        $scripts = self::where( [ "status" => "scheduled" ] );
        $now     = time();
        $count   = 0;
        foreach ( $scripts as $script ) {
            $details = json_decode( $script->details );
            if ( $now < $details->run_at ) {
                continue;
            }
            $environment = Environments::get( $script->environment_id );
            $env         = strtolower( $environment->environment );
            $site        = Sites::get( $environment->site_id );
            $site        = "{$site->site}-{$env}"; 
            $code        = base64_encode( stripslashes_deep( $script->code ) );
            $command     = "run $site --code=$code";
            \CaptainCore\Run::CLI( $command, true );
            self::update( [ "status" => "done" ], [ "script_id" => $script->script_id ] );
            $count++;
        }
        return "executed $count scripts";
    }

}