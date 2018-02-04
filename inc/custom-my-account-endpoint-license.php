<?php
class Anchor_My_Account_Licenses_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'licenses';

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		$user = wp_get_current_user();
		$role_check = in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles );

		if ($role_check) {
		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the My Accout page title.
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );

		// Insering your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );
		}
	}

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add new query var.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::$endpoint;

		return $vars;
	}

	/**
	 * Set endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ self::$endpoint ] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			// New page title.
			$title = __( 'Licenses', 'woocommerce' );

			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}

		return $title;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function new_menu_items( $items ) {
		// Remove the logout menu item.
		$logout = $items['customer-logout'];
		unset( $items['customer-logout'] );

		// Insert your custom endpoint.
		$items[ self::$endpoint ] = __( 'Licenses', 'woocommerce' );

		// Insert back the logout item.
		$items['customer-logout'] = $logout;

		return $items;
	}

	/**
	 * Endpoint HTML content.
	 */
	public function endpoint_content() {
		?>
		<script>
		jQuery(document).ready(function() {
			jQuery('.license input').click(function() {
			   jQuery(this).focus().select();
			});
		});
		</script>
		<h3>WordPress Licenses</h3>
		<?php if( have_rows('licenses') ): ?>

			<div class="license">

			<?php while( have_rows('licenses') ): the_row(); 

				// vars
				$name = get_sub_field('name');
				$type = get_sub_field('type');
				$link = get_sub_field('link');
				$key = get_sub_field('key');
				$username = get_sub_field('username');
				$password = get_sub_field('password');
				$account_link = get_sub_field('account_link');

				?>

				<div class="link" href="http://cmdshiftdesign.com/">

				<?php if ($account_link) { ?>
					<div class="login">
						<span class="username"><?php echo $username; ?></span>
						<span class="password"><?php echo $password; ?></span>
						<a href="<?php echo $account_link; ?>" target="_blank">Account Login</a>
					</div>
				<?php } ?>
					<span class="name"><?php echo $name; ?></span>
					<span class="website"><input type="input" value="<?php echo $key; ?>"></span>
					<span class="tag"><?php echo $type; ?></span>

				</div>

			<?php endwhile; ?>

			</ul>

		<?php endif; ?>
				
	<?php }

	/**
	 * Plugin install action.
	 * Flush rewrite rules to make our custom endpoint available.
	 */
	public static function install() {
		flush_rewrite_rules();
	}
}

new Anchor_My_Account_Licenses_Endpoint();

// Flush rewrite rules on plugin activation.
register_activation_hook( __FILE__, array( 'Anchor_My_Account_Licenses_Endpoint', 'install' ) );