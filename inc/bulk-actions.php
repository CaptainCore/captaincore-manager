<?php 
if (!class_exists('FRS_Custom_Bulk_Action')) {
 
	class FRS_Custom_Bulk_Action {
		
		public function __construct() {
			
			if(is_admin()) {
				// admin actions/filters
				add_action('admin_footer-edit.php', array(&$this, 'custom_bulk_admin_footer'));
				add_action('load-edit.php',         array(&$this, 'custom_bulk_action'));
				add_action('admin_notices',         array(&$this, 'custom_bulk_admin_notices'));
			}
		}
		
		
		/**
		 * Step 1: add the custom Bulk Action to the select menus
		 */
		function custom_bulk_admin_footer() {
			global $post_type;
			
			if($post_type == 'website') {
				?>
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery('<option>').val('generate').text('<?php _e('Generate Customer')?>').appendTo("select[name='action']");
							jQuery('<option>').val('generate').text('<?php _e('Generate Customer')?>').appendTo("select[name='action2']");
							jQuery('<option>').val('partner').text('<?php _e('Add Partner')?>').appendTo("select[name='action']");
							jQuery('<option>').val('partner').text('<?php _e('Add Partner')?>').appendTo("select[name='action2']");
							jQuery('<input type="text" name="new_partner" id="new_partner">').insertBefore('#doaction');
						});
					</script>
				<?php
	    	}
		}
		
		
		/**
		 * Step 2: handle the custom Bulk Action
		 * 
		 * Based on the post http://wordpress.stackexchange.com/questions/29822/custom-bulk-action
		 */
		function custom_bulk_action() {
			global $typenow;
			$post_type = $typenow;
			
			if($post_type == 'website') {
				
				// get the action
				$wp_list_table = _get_list_table('WP_Posts_List_Table');  // depending on your resource type this could be WP_Users_List_Table, WP_Comments_List_Table, etc
				$action = $wp_list_table->current_action();
				
				$allowed_actions = array("generate",'partner');
				if(!in_array($action, $allowed_actions)) return;
				
				// security check
				check_admin_referer('bulk-posts');
				
				// make sure ids are submitted.  depending on the resource type, this may be 'media' or 'ids'
				if(isset($_REQUEST['post'])) {
					$post_ids = array_map('intval', $_REQUEST['post']);
				}
				
				$partner_id = $_REQUEST['new_partner'];

				if(empty($post_ids)) return;
				
				// this is based on wp-admin/edit.php
				$sendback = remove_query_arg( array('exported', 'untrashed', 'deleted', 'ids'), wp_get_referer() );
				if ( ! $sendback )
					$sendback = admin_url( "edit.php?post_type=$post_type" );
				
				$pagenum = $wp_list_table->get_pagenum();
				$sendback = add_query_arg( 'paged', $pagenum, $sendback );
				
				switch($action) {
					case 'generate':
						
						// if we set up user permissions/capabilities, the code might look like:
						//if ( !current_user_can($post_type_object->cap->export_post, $post_id) )
						//	wp_die( __('You are not allowed to export this post.') );
						
						$exported = 0;
						foreach( $post_ids as $post_id ) {

							if ( !$this->perform_export($post_id) )
								wp_die( __('Error exporting post.') );
			
							$exported++;
						}
						
						$sendback = add_query_arg( array('exported' => $exported, 'ids' => join(',', $post_ids) ), $sendback );
					break;
					case 'partner':
						
						// if we set up user permissions/capabilities, the code might look like:
						//if ( !current_user_can($post_type_object->cap->export_post, $post_id) )
						//	wp_die( __('You are not allowed to export this post.') );
						
						$partner_added = 0;
						foreach( $post_ids as $post_id ) {

							if ( !$this->assign_partner($post_id, $partner_id) )
								wp_die( __('Error exporting post.') );
					
							$partner_added++;
						}
						
						$sendback = add_query_arg( array('partner_added' => $partner_added, 'ids' => join(',', $post_ids) ), $sendback );
					break;
					
					default: return;
				}
				
				$sendback = remove_query_arg( array('action', 'action2', 'tags_input', 'post_author', 'comment_status', 'ping_status', '_status',  'post', 'bulk_edit', 'post_view'), $sendback );
				
				wp_redirect($sendback);
				exit();
			}
		}
		
		
		/**
		 * Step 3: display an admin notice on the Posts page after exporting
		 */
		function custom_bulk_admin_notices() {
			global $post_type, $pagenow;
			
			if($pagenow == 'edit.php' && $post_type == 'website' && isset($_REQUEST['exported']) && (int) $_REQUEST['exported']) {
				$message = sprintf( _n( 'Customers generated.', '%s customers generated.', $_REQUEST['exported'] ), number_format_i18n( $_REQUEST['exported'] ) );
				echo "<div class=\"updated\"><p>{$message}</p></div>";
			}
			if($pagenow == 'edit.php' && $post_type == 'website' && isset($_REQUEST['partner_added']) && (int) $_REQUEST['partner_added']) {
				$message = sprintf( _n( 'Partner assigned.', '%s partners assigned.', $_REQUEST['partner_added'] ), number_format_i18n( $_REQUEST['partner_added'] ) );
				echo "<div class=\"updated\"><p>{$message}</p></div>";
			}
		}
		
		function perform_export($post_id) {
			// do whatever work needs to be done

			// Update the post into the database
			anchor_acf_save_post_after($post_id);
			return true;
		}
		function assign_partner($post_id, $partner_id) {
			// do whatever work needs to be done

			// Assign the partners from the bulk edit
			update_field( "field_56181a38cf6e3", $partner_id, $post_id);

			return true;
		}
	}
}

new FRS_Custom_Bulk_Action();