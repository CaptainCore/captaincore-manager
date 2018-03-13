<script>
jQuery(document).ready(function() {
	jQuery('.license input').click(function() {
		 jQuery(this).focus().select();
	});
});
</script>
<h3>WordPress Licenses</h3>
<?php if( have_rows('licenses') ): ?>

	<div class="col s12">

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

		<div class="card">

		<div class="card-content">


			<?php if ($account_link) { ?>
				<span class="card-title activator grey-text text-darken-4"><?php echo $name; ?><i class="material-icons right">more_vert</i></span>
			<?php } else { ?>
			<span class="card-title"><?php echo $name; ?></span>
			<?php } ?>
			<p><?php echo $key; ?></p>
			<span class="chip"><?php echo $type; ?></span>
		</div>

		<?php if ($account_link) { ?>
			<div class="card-reveal">
				<span class="card-title grey-text text-darken-4"><i class="material-icons right">close</i></span>
				<?php echo $username; ?><br />
				<?php echo $password; ?><br />
				<a href="<?php echo $account_link; ?>" target="_blank">Account Login</a>
			</div>
		<?php } ?>

		</div>

	<?php endwhile; ?>

	</ul>

<?php endif; ?>

<?php
