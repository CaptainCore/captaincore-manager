<?php

namespace CaptainCore;

class AccountPortal {

    protected $account_id = "";

    public function __construct( $account_id = "", $admin = false ) {
        if ( ( new User )->verify_accounts( [ $account_id ] ) ) {
            $this->account_id = $account_id;
        }
        if ( $admin ) {
            $this->account_id = $account_id;
        }
    }

    public function update( $portal ) {

        if ( empty( $this->account_id ) ) {
            return;
        }

        $portal = (object) $portal;

        $current_portals = ( new AccountPortals )->where( [ "account_id" => $this->account_id ] );
        foreach( $current_portals as $current_portal ) {
            ( new AccountPortals )->delete( $current_portal->account_portal_id );
        }

        $configurations = [
            "name"             => $portal->name,
            "url"              => $portal->url,
            "logo"             => $portal->logo,
            "logo_width"       => $portal->logo_width,
            "dns_introduction" => $portal->dns_introduction,
            "dns_nameservers"  => $portal->dns_nameservers,
            "colors"           => $portal->colors
        ];

        ( new AccountPortals )->insert( [
            "account_id"     => $this->account_id,
            "domain"         => $portal->domain,
            "configurations" => json_encode( $configurations )
        ]);
    }

}