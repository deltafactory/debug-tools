<?php

global $title;

?>
<h2><?php echo $title ?></h2>
<?php
if ( isset( $_REQUEST['settings-updated'] ) ) {
	echo '<div class="updated"><p>Settings updated.</p></div>';
}
?>
<div class="wrap">

	<form method="post" action="options.php" >
		<?php settings_fields( 'dfdebug-admin') ?>
		<?php do_settings_sections( 'dfdebug-admin' ) ?>
		<p><input type="submit" value="Save" class="button" /></p>
	</form>
</div>