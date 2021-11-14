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
                'account_id'      => $account->account_id,
                'billing_user_id' => (int) $account->billing_user_id,
				'name'            => html_entity_decode( $account->name ),
				'defaults'        => json_decode( $account->defaults ),
                'metrics'         => json_decode( $account->metrics ),
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

    public function update_plan( $new_plan, $account_id ) {
        $account = self::get( $account_id );
        $plan    = json_decode( $account->plan );
        $total   = is_array( $plan->price ) ? 0 : $plan->price;
        if ( is_array( $plan->addons ) && count( $plan->addons ) > 0 ) {
            foreach( $plan->addons as $addon ) {
                $total = $total + $addon->price;
            }
        }

        // Calculate credit or charge for paid plans when interval changes.
        if ( $plan->status == "active" && $plan->interval != $new_plan["interval"] ) {
            $now              = new \DateTime();
            $next_renewal     = new \DateTime( $plan->next_renewal );
            $remaining_time   = $now->diff( $next_renewal );
            $remaining_days   = $remaining_time->format('%a');
            $per_month_total  = $total / $plan->interval;
            $remaining_credit = ( $remaining_days / 30 ) * $per_month_total;
            if ( $remaining_credit > 0 ) {
                $plan->credit = $plan->credit + $remaining_credit;
            }
            if ( $remaining_credit < 0 ) {
                $plan->charge = $plan->charge + $remaining_credit;
            }
        }

        if ( $plan->status == "" ) {
            $plan->status == "pending";
        }

		$plan->name              = $new_plan["name"];
        $plan->price             = $new_plan["price"];
        $plan->addons            = $new_plan["addons"];
        $plan->credits           = $new_plan["credits"];
        $plan->charges           = $new_plan["charges"];
        $plan->limits            = $new_plan["limits"];
        $plan->auto_pay          = $new_plan["auto_pay"];
        $plan->auto_switch       = $new_plan["auto_switch"];
        $plan->interval          = $new_plan["interval"];
        $plan->next_renewal      = $new_plan["next_renewal"];
        $plan->billing_user_id   = $new_plan["billing_user_id"];
        $plan->additional_emails = $new_plan["additional_emails"];

        self::update( [ "plan" => json_encode( $plan ) ], [ "account_id" => $account_id ] );

        if ( $plan->auto_switch == "true" ) {
            ( new Account( $account_id, true ) )->auto_switch_plan();
        }
    }

    public function process_renewals() {

        $accounts = self::with_renewals();
        $now      = strtotime( "now" );
        foreach ( $accounts as $account ) {
            $plan         = json_decode( $account->plan );
            $next_renewal = strtotime ( $plan->next_renewal );
            if ( ! empty( $next_renewal ) && $next_renewal < $now ) {
                echo "Processing renewal for {$account->name} as it's past {$plan->next_renewal}\n";
                ( new Account( $account->account_id, true ) )->generate_order();
                $plan = json_decode( ( new Accounts )->get( $account->account_id )->plan );
                $plan->next_renewal = date("Y-m-d H:i:s", strtotime( "+{$plan->interval} month", $next_renewal ) );
                unset( $plan->charges );
                unset( $plan->credits );
                if ( $plan->over_payment ) {
                    $plan->credits = [ 
                        (object) [
                            "name"     => "Previous credits",
                            "quantity" => "1",
                            "price"    => $plan->over_payment
                        ]
                    ];
                    unset( $plan->over_payment );
                }
                echo "Next renewal in {$plan->interval} months will be {$plan->next_renewal}\n";
                self::update( [ "plan" => json_encode( $plan ) ], [ "account_id" => $account->account_id ] );
            }
        }
    }

    public function account_ids() {
        return $this->accounts;
    }

}