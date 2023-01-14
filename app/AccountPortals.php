<?php

namespace CaptainCore;

class AccountPortals extends DB {

	static $primary_key = 'account_portal_id';

	public function refresh_domains() {
		$domains = array_column( self::all(), "domain" );
		set_transient( "captaincore_authorized_domains", $domains );
	}

}