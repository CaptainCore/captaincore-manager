jQuery(document).ready(function() {


  jQuery('.woocommerce-MyAccount-navigation-link a').click(function(e) {

      // Only proceed if we are not already on the /sites/ page.
      if ( ! window.location.href.includes("/my-account/sites") ) {
        return
      }

      // If link click going to a /sites/ page then prevent click and change hash.
      if ( jQuery(this).attr("href").includes("/my-account/sites") ) {
        e.preventDefault();
        if ( jQuery(this).attr("href").includes("/my-account/sites/") ) {
          window.location.hash = "#sites"
          return
        }
        if ( jQuery(this).attr("href").includes("/my-account/sites") ) {
          href = jQuery(this).attr("href");
          hash = href.split("#")[1];
          window.location.hash = "#" + hash
        }
      }
    });

	jQuery('.toggle_woocommerce_my_account a').click(function(e) {
		e.preventDefault();
		toggle = jQuery(this).parent().parent();
		if ( toggle.hasClass("open") ) {
			jQuery(toggle).removeClass("open");
			jQuery(toggle).addClass("close");
			jQuery('.woocommerce-account nav.woocommerce-MyAccount-navigation').css("display", "none");
			jQuery('body.woocommerce-account .woocommerce-MyAccount-content').css("width", "100%");
		} else {
			jQuery(toggle).removeClass("close");
			jQuery(toggle).addClass("open");
			jQuery('.woocommerce-account nav.woocommerce-MyAccount-navigation').css("display", "block");
			jQuery('body.woocommerce-account .woocommerce-MyAccount-content').css("width", "80%");
		}
	});

  jQuery(".started-processes a.process-log-completed").click(function(e) {
    e.preventDefault();
    var parent_object = jQuery(this).parents(".process-star");
    var post_id = jQuery(this).parents(".process-star").attr("data-post-id");
    var data = {
      'action': 'log_process_completed',
      'post_id': post_id
    };

    console.log(data);

    // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
    jQuery.post(ajaxurl, data, function(response) {
      console.log(response);
      if (response == 1) {
        jQuery(parent_object).fadeOut('slow');
      } else {
        console.log(response);
      }
    });
  });

  if (jQuery('.woocommerce-MyAccount-navigation-link--subscriptions.is-active').length > 0 ||
    jQuery('.woocommerce-MyAccount-navigation-link--payment-methods.is-active').length > 0 ||
    jQuery('.woocommerce-MyAccount-navigation-link--edit-address.is-active').length > 0 ||
    jQuery('.woocommerce-MyAccount-navigation-link--orders.is-active').length > 0 ||
    window.location.pathname.includes("/my-account/view-subscription/") ||
    window.location.pathname.includes("/my-account/payment-methods/") ||
    window.location.pathname.includes("/my-account/view-order/")) {
    jQuery('.woocommerce-MyAccount-content').prepend("<div class='woocommerce-MyAccount-secondary'><ul></ul></div>");
    $menu_item = jQuery('.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--subscriptions').clone();
    $menu_item.appendTo('.woocommerce-MyAccount-secondary ul');
    jQuery('.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--payment-methods').appendTo('.woocommerce-MyAccount-secondary ul');
    jQuery('.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--edit-address').appendTo('.woocommerce-MyAccount-secondary ul');
    jQuery('.woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--orders').appendTo('.woocommerce-MyAccount-secondary ul');
  }

});
