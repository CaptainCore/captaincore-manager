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
            $response = Run::CLI( $command );
            return $response;
        }
        $command  = "quicksave rollback {$this->site_id}-{$environment} $hash --version=$version --$type=$value";
        $response = Run::task( $command );
        return $response;
    }

}