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
                'filtered'        => true
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
        $account        = self::get( $account_id );
        $plan           = empty( $account->plan ) ? (object) [] : json_decode( $account->plan );
        $new_plan       = (object) $new_plan;
        $total          = is_array( $plan->price ) ? 0 : (float) $plan->price;
        $configurations = ( new Configurations )->get();

        if ( is_array( $plan->addons ) && count( $plan->addons ) > 0 ) {
            foreach( $plan->addons as $addon ) {
                $total = $total + (float) $addon->price;
            }
        }

        // Calculate credit or charge for paid plans when interval changes.
        if ( ! empty( $plan->status ) && $plan->status == "active" && $plan->interval != $new_plan->interval ) {
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
        
        // If the plan name is the same but the interval changed, recalculate the price.
        if ( $plan->name == $new_plan->name && $plan->interval != $new_plan->interval ) {
            // Find the original plan from configurations to get its base price and interval
            $original_plan = null;
            foreach ( $configurations->hosting_plans as $hosting_plan ) {
                if ( $hosting_plan->name == $new_plan->name ) {
                    $original_plan = $hosting_plan;
                    break;
                }
            }

            if ( $original_plan ) {
                // Calculate the price per month from the original plan's defaults
                $unit_price = $original_plan->price / $original_plan->interval;
                // Set the new price based on the new interval
                $plan->price = $unit_price * $new_plan->interval;
            } else {
                // Fallback for custom plans or if template not found
                $plan->price = ( $new_plan->price === "" ) ? "" : $new_plan->price;
            }
        } else {
            // This is the original logic, which works for changing plan types.
            $plan->price = ( $new_plan->price === "" ) ? "" : $new_plan->price;
        }

        $plan->name              = empty( $new_plan->name ) ? "" : $new_plan->name;
        $plan->addons            = empty( $new_plan->addons ) ? "" : $new_plan->addons;
        $plan->credits           = empty( $new_plan->credits ) ? "" : $new_plan->credits;
        $plan->charges           = empty( $new_plan->charges ) ? "" : $new_plan->charges;
        $plan->limits            = empty( $new_plan->limits ) ? "" : $new_plan->limits;
        $plan->auto_pay          = empty( $new_plan->auto_pay ) ? "" : $new_plan->auto_pay;
        $plan->auto_switch       = empty( $new_plan->auto_switch ) ? "" : $new_plan->auto_switch;
        $plan->interval          = empty( $new_plan->interval ) ? "" : $new_plan->interval;
        $plan->next_renewal      = empty( $new_plan->next_renewal ) ? "" : $new_plan->next_renewal;
        $plan->billing_user_id   = empty( $new_plan->billing_user_id ) ? "" : $new_plan->billing_user_id;
        $plan->additional_emails = empty( $new_plan->additional_emails ) ? "" : $new_plan->additional_emails;

        self::update( [ "plan" => json_encode( $plan ) ], [ "account_id" => $account_id ] );

        if ( $plan->auto_switch == "true" ) {
            ( new Account( $account_id, true ) )->auto_switch_plan();
        }
    }

    public static function auto_switch_plans() {
        $accounts = self::with_renewals();
        foreach ( $accounts as $account ) {
            $plan = json_decode( $account->plan );
            if ( empty( $plan->next_renewal ) ) {
                continue;
            }
            if ( ! empty( $plan->auto_switch ) && $plan->auto_switch == "true" ) {
                ( new Account( $account->account_id, true ) )->auto_switch_plan();
                $check_plan = json_decode( self::get( $account->account_id )->plan );
                if ( $plan->name != $check_plan->name ) {
                    echo "Auto switched plan for {$account->name} from {$plan->name} to {$check_plan->name}\n";
                }
            }
        }
    }

    public static function process_renewals() {
        $accounts = self::with_renewals();
        $now      = strtotime( "now" );
        foreach ( $accounts as $account ) {
            $plan         = json_decode( $account->plan );
            if ( empty( $plan->next_renewal ) ) {
                continue;
            }
            $next_renewal = strtotime ( $plan->next_renewal );
            if ( ! empty( $next_renewal ) && $next_renewal < $now ) {
                echo "Processing renewal for {$account->name} as it's past {$plan->next_renewal}\n";
                ( new Account( $account->account_id, true ) )->generate_order();
                $plan = json_decode( ( new Accounts )->get( $account->account_id )->plan );
                $plan->next_renewal = date("Y-m-d H:i:s", strtotime( "+{$plan->interval} month", $next_renewal ) );
                unset( $plan->charges );
                unset( $plan->credits );
                if ( ! empty( $plan->over_payment ) ) {
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

    public static function process_outstanding_notifications() {
        $accounts = self::with_renewals();
        foreach ( $accounts as $account ) {
            $orders = wc_get_orders( [
                'limit'        => -1,
                'meta_key'     => 'captaincore_account_id',
                'meta_value'   => $account->account_id,
                'orderby'      => 'date',
                'order'        => 'DESC',
                'status'       => [ 'wc-pending', 'failed' ],
                'date_created' => '<' . strtotime('-30 days')
            ] );
            if ( count( $orders ) == 0 ) {
                continue;
            }
            // Processing outstanding notifications for account
            ( new Account( $account->account_id, true ) )->outstanding_notify();
        }
    }

    public function account_ids() {
        return $this->accounts;
    }

}