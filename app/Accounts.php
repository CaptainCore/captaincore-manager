<?php 

namespace CaptainCore;

class Accounts extends DB {

	static $primary_key = 'account_id';

	protected $accounts = [];

    public function __construct( $accounts = [] ) {
        $user        = new User;
        $account_ids = $user->accounts();

        // Bail if not assigned a role
        if ( ! $user->role_check() ) {
            return 'Error: Please log in.';
        }

        // Administrators return all accounts
        if ( $user->is_admin() ) {
            $this->accounts = self::select( "account_id");
            return;
        }

        // Bail if no accounts set.
        if ( ! is_array( $account_ids ) ) {
            return;
        }

        $this->accounts = $account_ids;
        return;
        
    }

	public function list() {
		$accounts = [];
		foreach ( $this->accounts as $account_id ) {
			$account  = self::get( $account_id );
			$defaults = json_decode( $account->defaults );
			$result = (object) [
				'account_id' => $account->account_id,
				'name'       => html_entity_decode( $account->name ),
				'defaults'   => json_decode( $account->defaults ),
                'metrics'    => json_decode( $account->metrics ),
            ];
            if ( $result->defaults->users == "" ) {
                $result->defaults->users = [];
            }
            if ( $result->defaults->recipes == "" ) {
                $result->defaults->recipes = [];
            }
			$accounts[] = $result;
		}
		usort($accounts, function($a, $b) { return strcmp( strtolower($a->name), strtolower($b->name) ); });
		return $accounts;
	}

}