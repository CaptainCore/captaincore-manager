jQuery(document).ready(function() {

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

});
