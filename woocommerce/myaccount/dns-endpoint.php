<?php

global $wp_query;

// Display single DNS page

if ( $wp_query->query_vars['dns'] ) {

	$domain_id = $wp_query->query_vars['dns'];

	if ( anchor_verify_permissions_domain( $domain_id ) ) {

		$domain   = constellix_api_get( "domains/$domain_id" );
		$response = constellix_api_get( "domains/$domain_id/records" );
		if ( ! $response->errors ) {
			array_multisort( array_column( $response, 'type' ), SORT_ASC, array_column( $response, 'name' ), SORT_ASC, $response );
		}
		$record_count = 0;
		foreach ( $response as $record ) {
			if ( is_array( $record->value ) ) {
				$record_count = $record_count + count( $record->value );
			} else {
				$record_count = $record_count + 1;
			}
		}; ?>
	 <script>
	 /*** Copyright 2013 Teun Duynstee Licensed under the Apache License, Version 2.0 ***/ ! function(n, t) {
			 "function" == typeof define && define.amd ? define([], t) : "object" == typeof exports ? module.exports = t() : n.firstBy = t()
	 }(this, function() {
			 var n = function() {
					 function n(n) {
							 return n
					 }

					 function t(n) {
							 return "string" == typeof n ? n.toLowerCase() : n
					 }

					 function e(e, r) {
							 if (r = "number" == typeof r ? {
											 direction: r
									 } : r || {}, "function" != typeof e) {
									 var i = e;
									 e = function(n) {
											 return n[i] ? n[i] : ""
									 }
							 }
							 if (1 === e.length) {
									 var o = e,
											 f = r.ignoreCase ? t : n;
									 e = function(n, t) {
											 return f(o(n)) < f(o(t)) ? -1 : f(o(n)) > f(o(t)) ? 1 : 0
									 }
							 }
							 return r.direction === -1 ? function(n, t) {
									 return -e(n, t)
							 } : e
					 }

					 function r(n, t) {
							 var i = "function" == typeof this && this,
									 o = e(n, t),
									 f = i ? function(n, t) {
											 return i(n, t) || o(n, t)
									 } : o;
							 return f.thenBy = r, f
					 }
					 return r
			 }();
			 return n
		 });
		 jQuery(document).ready( function () {

			 ajaxurl = "/wp-admin/admin-ajax.php";

			 new_dns_record = jQuery('.dns_record[data-status="new-record"]').clone();

			 // Changing record types via dropdown
			 jQuery('.dns_records').on("change", "tr select", function() {

				 record_type = jQuery(this).val().toLowerCase();
				 record_row = jQuery(this).parent().parent("tr");

				 jQuery(record_row).data( "type", record_type );
				 jQuery(record_row).attr( "data-type", record_type );

			 });

			 // Editing or Removing record
			 jQuery('.dns_records').on("click","tr td.actions a", function( event ) {
				 record_row = jQuery(this).parent("td.actions").parent("tr");
				 record_status = jQuery(record_row).data( "status" );
				 action_status = jQuery(this).attr('class');

				 if (record_status == action_status) {
					 jQuery(record_row).removeData( "status" );
					 jQuery(record_row).removeAttr( "data-status" );
				 } else {
					 jQuery(record_row).data( "status", jQuery(this).attr('class') );
					 jQuery(record_row).attr( "data-status", jQuery(this).attr('class') );
				 }

				 event.preventDefault();
			 });

			 jQuery('.dns_records').on("click",'.dns_record[data-status="new-record"] > td:last-child a.remove-record', function( event ) {
				jQuery(this).parent().parent("tr").remove();
				event.preventDefault();
			 });

			 jQuery('.dns_records .mx a.add-record').click(function( event ) {
				 jQuery(this).parent().parent("tr").before('<tr class="dns_record" data-status="new-record" data-type="a"><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
				 event.preventDefault();
			 });
			 jQuery('.dns_records .mx').on("click","a.remove-record", function( event ) {
				 jQuery(this).parent().parent("tr").remove();
				 event.preventDefault();
			 });
			 jQuery('.dns_records tr[data-type="txt"] a.add-record').click(function( event ) {
				 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
				 event.preventDefault();
			 });
			 jQuery('.dns_records tr[data-type="txt"] table').on("click","a.remove-record", function( event ) {
				 jQuery(this).parent().parent("tr").remove();
				 event.preventDefault();
			 });
			 jQuery('.dns_records').on("click",'tr[data-type="a"] a.add-record', function( event ) {
				 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
				 event.preventDefault();
			 });
			 jQuery('.dns_records tr[data-type="a"] table').on("click","a.remove-record", function( event ) {
				 jQuery(this).parent().parent("tr").remove();
				 event.preventDefault();
			 });
			 jQuery('.dns_records').on("click",'tr[data-type="srv"] a.add-record', function( event ) {
				 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
				 event.preventDefault();
			 });
			 jQuery('.dns_records tr[data-type="srv"] table').on("click","a.remove-record", function( event ) {
				 jQuery(this).parent().parent("tr").remove();
				 event.preventDefault();
			 });
			 jQuery('.dns_records tr[data-type="spf"] a.add-record').click(function( event ) {
				 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
				 event.preventDefault();
			 });
			 jQuery('.dns_records tr[data-type="srv"] table').on("click","a.remove-record", function( event ) {
				 jQuery(this).parent().parent("tr").remove();
				 event.preventDefault();
			 });

			 jQuery('.add-additional-record').click(function( event ) {

				 jQuery('.dns_records > tbody > tr:last-child').before( new_dns_record.clone() );
				 event.preventDefault();

			 });

			 jQuery('.save_records').click(function( event ) {

				 // Show loader and dim table
				 jQuery('.progress').css("opacity","1");
				 jQuery('.dns_records').css("opacity","0.3");

				 record_updates = [];
				 // Loop through all modified dns records
				 jQuery('.dns_records tr.dns_record[data-status]').each(function() {

						record_id = jQuery(this).data('id');
						record_type = jQuery(this).data('type');
						record_name = jQuery(this).find('.name input').val();
						record_value = jQuery(this).find('.value input').val();
						record_ttl = jQuery(this).find('.ttl input').val();
						record_status = jQuery(this).data('status');

						if (record_type == "mx") {

							record_values = [];

							jQuery(this).find('tr:has("td")').each(function() {
								priority = jQuery(this).find("input:first").val();
								value = jQuery(this).find("input:last").val();

								// Check for MX value ending in period. If not add one.
								if ( value && value.substr(value.length - 1) != "." ) {
									value = value + ".";
								}

								if (priority && value) {
									record_values.push({
										"priority": priority,
										"value": value
									});
								}
							});

							record_value = record_values;

						}
						if (record_type == "txt" || record_type == "a") {

							record_values = [];

							jQuery(this).find('tr:has("td")').each(function() {
								value = jQuery(this).find("input:last").val();
								if ( value ) {
									record_values.push({
										"value": value
									});
								}
							});
							if (record_values.length > 0) {
								record_value = record_values;
							}
						}

						new_record = {
							"record_id": record_id,
							"record_type": record_type,
							"record_name": record_name,
							"record_value": record_value,
							"record_ttl": record_ttl,
							"record_status": record_status
						};

					 // Prep new/modified items
					 if ( record_type ) {
						 if ( record_value || record_name ) {
							record_updates.push( new_record );
						}
					 }

				 });

				 // Submit DNS Updates
				 var data = {
					'action': 'anchor_dns',
					'domain_key': <?php echo $domain_id; ?>,
					'record_updates': record_updates
				 };

				jQuery.post(ajaxurl, data, function(response) {
					console.log(response);
					var response = jQuery.parseJSON(response);
					jQuery(response).each(function() {
						// Display success
						if ( this["success"] ) {
							if( this["success"] == "Record deleted successfully" ) {
								record_id = this["record_id"];
								jQuery('tr[data-id='+record_id+']').remove();
							}
							if( this["success"] == "Record updated successfully" ) {
								record_id = this["record_id"];
								record_type = this["record_type"];
								record_row = jQuery('tr[data-id='+record_id+']');
								record_name = record_row.find('.name .record-editable input').val();
								record_row.find('.name .record-view').html( record_name );
								if ( record_type == "mx" ) {
									record_values = [];
									record_row.find('.value .record-editable table tr').each(function() {
										record_priority = jQuery(this).find("td:nth-child(1) input").val();
										record_server = jQuery(this).find("td:nth-child(2) input").val();
										if (record_priority && record_server) {
											record_values.push ( '<p>'+record_priority+' '+record_server+'</p>' );
										}
									});
									record_row.find('.value .record-view').html( "" );
									jQuery(record_values).each(function() {
										record_row.find('.value .record-view').append( this );
									});
								} else if ( record_type == "txt" || record_type == "a" ) {
									record_values = [];
									record_row.find('.value .record-editable table input').each(function() {
										record_values.push ( '<p>'+jQuery(this).val()+'</p>' );
									});
									record_row.find('.value .record-view').html( "" );
									jQuery(record_values).each(function() {
										record_row.find('.value .record-view').append( this );
									});
								} else {
									record_value = record_row.find('.value .record-editable input').val();
									record_row.find('.value .record-view').html( record_value );
								}
								record_ttl = record_row.find('.ttl .record-editable input').val();
								record_row.find('.ttl .record-view').html( record_ttl );
								jQuery(record_row).removeData( "status" );
								jQuery(record_row).removeAttr( "data-status" );
							}

							Materialize.toast( this["success"] , 4000);
						}
						// Display errors
						if ( this["errors"] ) {
							Materialize.toast( this["errors"], 4000 );
						}
						// New record
						if ( this["id"] ) {
							record_id = this["id"];
							record_type = this["type"];
							record_recordtype = this["recordType"];
							if ( Array.isArray( this["value"]) ) {
								if ( typeof this["value"][0] === 'object') {
									record_value = this["value"][0]["value"];
								} else {
									record_value = this["value"][0];
								}
							} else {
								record_value = this["value"];
							}
							record_name = this["name"];
							record_zone = this["zone"];
							record_ttl = this["ttl"];
							new_dns_record_html = jQuery('tr.dns_record:first').clone();
							new_dns_record_html.find('.type div:first-child').html( record_type );
							new_dns_record_html.find('.name .record-view').html( record_name );
							new_dns_record_html.find('.name .record-editable input').val( record_name );
							new_dns_record_html.find('.value .record-view').html( record_value );
							new_dns_record_html.find('.value .record-editable input').val( record_value );
							new_dns_record_html.find('.ttl .record-view').html( record_ttl );
							new_dns_record_html.find('.ttl .record-editable input').val( record_ttl );
							new_dns_record_html.data("id", record_id);
							new_dns_record_html.attr("data-id", record_id);
							new_dns_record_html.data("type", record_recordtype);
							new_dns_record_html.attr("data-type", record_recordtype);
							jQuery('.dns_records > tbody > tr:first-child').before( new_dns_record_html.clone() );
							jQuery('tr.dns_record:first').find("select option").filter(function() {
									return (jQuery(this).text() == record_type );
							}).prop('selected', true);
							jQuery('.dns_record[data-status="new-record"]').remove();
							var dnsrecords = jQuery('.dns_records');
							var dnsrecordstr = jQuery(dnsrecords).children('tbody').children('tr').get();
							dnsrecordstr.sort(
									firstBy(function (v) { return jQuery(v).attr("data-type"); })
									.thenBy(function (v) { return jQuery(v).find(".name .record-view").text().trim(); })
							);
							jQuery.each(dnsrecordstr, function(idx, itm) { dnsrecords.append(itm); });
							Materialize.toast( "New record added" , 4000);
							jQuery('.add-additional-record').click();
						}

					});

					// Hide loader and reveal table
					jQuery('.progress').css("opacity","0");
					jQuery('.dns_records').css("opacity","1");

				});

				event.preventDefault();
			 });
		 });
		 </script>
		<h3>Domain: <?php echo $domain->name; ?> <small class="alignright"><?php echo $record_count; ?> records</small></h3>

		<hr>

		<table class="dns_records">
		<thead>
			<tr>
					<th>Type</th>
					<th>Name</th>
					<th>Value</th>
					<th>TTL</th>
					<th></th>
			</tr>
		</thead>
		<tbody>
		<?php
		foreach ( $response as $records ) {
			$record_id     = $records->id;
			$record_name   = $records->name;
			$record_type   = $records->type;
			$record_host   = $records->host;  // Used for CNAME records
			$record_ttl    = $records->ttl;
			$record_url    = $records->url;
			$record_values = $records->value;
			?>
			<tr data-id="<?php echo $record_id; ?>" data-type="<?php echo strtolower( $record_type ); ?>" class="dns_record">
				<td class="type">
					<div>
						<?php
						if ( $record_type == 'HTTPRedirection' ) {
							echo 'HTTP Redirect';
						} else {
							echo $record_type;
						}
						?>
					</div>
					<div class="record-non-editable">
						<select>
							<option<?php if( $record_type == "A" ) { echo " selected"; } ?>>A</option>
							<option<?php if( $record_type == "AAAA" ) { echo " selected"; } ?>>AAAA</option>
							<option<?php if( $record_type == "ANAME" ) { echo " selected"; } ?>>ANAME</option>
							<option<?php if( $record_type == "CNAME" ) { echo " selected"; } ?>>CNAME</option>
							<option<?php if( $record_type == "HTTPRedirection" ) { echo " selected"; } ?> value="HTTPRedirection">HTTP Redirect</option>
							<option<?php if( $record_type == "MX" ) { echo " selected"; } ?>>MX</option>
							<option<?php if( $record_type == "SPF" ) { echo " selected"; } ?>>SPF</option>
							<option<?php if( $record_type == "SRV" ) { echo " selected"; } ?>>SRV</option>
							<option<?php if( $record_type == "TXT" ) { echo " selected"; } ?>>TXT</option>
						</select>
					</div>
				</td>
				<td class="name">
					<div class="record-view">
						<?php echo $record_name; ?>
					</div>
					<div class="record-editable">
						<input type="text" value="<?php echo $record_name; ?>">
					</div>
				</td>
				<td class="value">
					<?php
					if ( $records->type == 'MX' ) {
						array_multisort( array_column( $record_values, 'level' ), SORT_ASC, array_column( $record_values, 'value' ), SORT_ASC, $record_values );
					?>
						<div class="mx">
							<div class="record-view">
						<?php
						foreach ( $record_values as $record ) {
							$record_value = $record->value;
							$record_level = $record->level;  // Used by MX records
							?>

							<p><?php echo $record_level; ?> <?php echo $record_value; ?></p>
						<?php } ?>
						</div>
						<div class="record-editable">
							<table>
								<tr><th>Priority</th><th>Mail Server</th><th></th></tr>
						<?php
						foreach ( $record_values as $record ) {
							$record_value = $record->value;
							$record_level = $record->level;  // Used by MX records
							?>
							<tr><td><input type="text" value="<?php echo $record_level; ?>"></td><td><div class="message">.<?php echo $domain->name; ?></div><input type="text" value="<?php echo $record_value; ?>"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
						<?php } ?>
								<tr><td colspan="3"><a href="#" class="add-record">Add Additional Record</a></td></tr>
							 </table>
						 </div>
					 </div>
	<?php
					}
					if ( $records->type == 'CNAME' or $records->type == 'HTTPRedirection' ) {
			?>
							<div class="record-view">
								<?php echo $record_values; ?>
							</div>
							<div class="record-editable">
								<div class="message">.<?php echo $domain->name; ?></div>
								<input type="text" value="<?php echo $record_values; ?>">
							</div>
		<?php
					}
					if ( $records->type == 'AAAA' or $records->type == 'ANAME' ) {

						foreach ( $record_values as $record ) {

							$record_value = $record->value;
							if ( ! isset( $record->value ) ) {
								$record_value = $record; }
					?>
							<div class="record-view">
								<?php echo $record_value; ?>
							</div>
							<div class="record-editable">
								<input type="text" value="<?php echo htmlspecialchars( $record_value ); ?>">
							</div>
		<?php
						}
					}
					if ( $records->type == 'A' ) {
			?>

						<div class="record-view">
					<?php foreach ( $record_values as $record ) { ?>
				<p><?php echo $record; ?></p>
			<?php } ?>
					</div>
					<div class="record-editable">
						<table>
							<tr><th>Value</th><th></th></tr>
					<?php foreach ( $record_values as $record ) { ?>
				<tr><td><input type="text" value='<?php echo $record; ?>'></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
				<?php } ?>
							<tr><td colspan="2"><a href="#" class="add-record">Add Additional Record</a></td></tr>
						 </table>
					 </div>
		<?php
					}
					if ( $records->type == 'SPF' or $records->type == 'TXT' ) {
			?>

						<div class="record-view">
					<?php
					foreach ( $record_values as $record ) {
						$record_value = $record->value;
							?>

						<p><?php echo $record_value; ?></p>
					<?php } ?>
					</div>
					<div class="record-editable">
						<table>
					<tr><th>Value</th><th></th></tr>
					<?php
					foreach ( $record_values as $record ) {
						$record_value = $record->value;
						?>
						<tr><td><input type="text" value='<?php echo $record_value; ?>'></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
				<?php } ?>
							<tr><td colspan="2"><a href="#" class="add-record">Add Additional Record</a></td></tr>
						 </table>
					 </div>
		<?php
					}
					if ( $records->type == 'SRV' ) {
			?>
						<div class="srv">
							<div class="record-view">
							<?php
							foreach ( $record_values as $record ) {

								$record_value    = $record->value;
								$record_priority = $record->priority;
								$record_weight   = $record->weight;
								$record_port     = $record->port;

							?>
								<p><?php echo $record_value; ?> <?php echo $record_priority; ?> <?php echo $record_weight; ?> <?php echo $record_port; ?></p>
					<?php } ?>
						</div>
						<div class="record-editable">
					<table>
						<tr><th>Priority</th><th>Weight</th><th>Port</th><th>Host</th><th></th></tr>
					<?php foreach ( $record_values as $record ) { ?>
									<tr>
										<td><input type="text" value="<?php echo $record_priority; ?>"></td><td><input type="text" value="<?php echo $record_weight; ?>"></td><td><input type="text" value="<?php echo $record_port; ?>"></td>
										<td><input type="text" value="<?php echo $record_value; ?>"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td>
									</tr>
					<?php } ?>
							<tr><td colspan="5"><a href="#" class="add-record">Add Additional Record</a></td></tr>
					 </table>
					</div>
						</div>
					<?php } ?>
			</td>
			<td class="ttl">
				<div class="record-view">
					<?php echo $record_ttl; ?>
				</div>
				<div class="record-editable">
					<input type="text" value="<?php echo $record_ttl; ?>">
				</div>
			</td>
			<td class="actions">
				<a class="edit-record" href=""><i class="fas fa-edit"></i></a>
				<a class="remove-record"  href=""><i class="fas fa-times"></i></a>
			</td>
		</tr>
			<?php

		}
		?>
		<tr class="dns_record" data-status="new-record" data-type="a">
			<td>
				<select>
					<option selected>A</option>
					<option>AAAA</option>
					<option>ANAME</option>
					<option>CNAME</option>
					<option value="httpredirection">HTTP Redirect</option>
					<option>MX</option>
					<option>SPF</option>
					<option>SRV</option>
					<option>TXT</option>

				</select>
			</td>
			<td class="name"><input type="text"></td>
			<td class="value">
				<div class="value">
				<div class="message">.<?php echo $domain->name; ?></div>
				<input type="text">
			</div>
			<div class="mx">
				<table>
					<tr><th>Priority</th><th>Mail Server</th><th></th></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td colspan="3"><a href="#" class="add-record">Add Additional Record</a></td></tr>
			 </table>
			</div>

			<div class="srv">
				<table>
					<tr><th>Priority</th><th>Weight</th><th>Port</th><th>Host</th><th></th></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
					<tr><td colspan="5"><a href="#" class="add-record">Add Additional Record</a></td></tr>
			 </table>
			</div>

			</td>
			<td class="ttl"><input type="text" value="1800"></td>
			<td>
				<a class="remove-record" href=""><i class="fas fa-times"></i></a>
			</td>
		</tr>
		<tr data-type="z">
			<td colspan="5"><p><a href="#" class="add-additional-record">Add Additional Record</a></p></td>
		</tr>
	</tbody>
	</table>
	<div class="progress" style="opacity:0;">
		<div class="indeterminate"></div>
</div>
	<a class="button save_records">Save Records</a>
	<a href="<?php echo get_site_url( null, '/my-account/dns/' ); ?>" class="alignright button">View All Domains</a>
<?php
	} else {
?>
	Domain not found
	<a href="<?php echo get_site_url( null, '/my-account/dns/' ); ?>" class="alignright button">View All Domains</a>
	<?php
	}

} // end if ( $wp_query->query_vars['dns'] ) {

 // Display DNS listing page
if ( !$wp_query->query_vars['dns'] ) { ?>
	<div class="row">
		<div class="col s12">
			<div class="card">
				<div class="card-content row">
				<div class="col s12 m7">
				 <p>Anchor DNS is available for all <a href="https://anchor.host/plans/">customers</a> and helps keep things running smooth.
	It allows you to manage your own zone records and use <a href="https://constellix.com/">Constellix</a>, an enterprise grade DNS service built by DNS Made Easy.
	Not comfortable with DNS? As always email <a href="mailto:support@anchor.host">support@anchor.host</a> and we'll take care of any DNS updates for you.</p>
				</div>
				<div class="col s12 m1"></div>
				<div class="col s12 m4">
					<span class="card-title">Nameservers</span>
						<ul>
							<li>ns11.constellix.com</li>
							<li>ns21.constellix.com</li>
							<li>ns31.constellix.com</li>
							<li>ns41.constellix.net</li>
							<li>ns51.constellix.net</li>
							<li>ns61.constellix.net</li>
						</ul>
				</div>
				</div>
			</div>
		</div>
	</div>

<?php

	$user_id = get_current_user_id();

	$partner = get_field( 'partner', 'user_' . get_current_user_id() );
	if ( $partner ) {
		foreach ( $partner as $partner_id ) {

		// Get all domains partner has access to
		$domains = get_domains_per_partner( $partner_id );

		if ( $domains ) :
			echo '<h3>Account: ' . get_the_title( $partner_id ) . ' <small class="alignright">' . count( $domains ) . ' domains</small></h3>';
			echo '<div class="row dns_records">';
			foreach ( $domains as $domain ) :
			?>

		 <div class="col s12 m6">
			 <div class="card">

				 <div class="card-content">
					 <p><span class="card-title"><?php echo get_the_title( $domain ); ?></span></p>
				 </div>
				 <div class="card-action">
					 <a href="<?php echo get_site_url( null, '/my-account/dns' ); ?>/<?php echo get_field( 'domain_id', $domain ); ?>/">Modify DNS</a>
				 </div>
			 </div>
		 </div>

			<?php
			endforeach;
			echo '</div>';
		endif;

		}
	} // end foreach ($partner as $partner_id)
}
