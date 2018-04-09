<?php
if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>
<div id="form-wrapper">
	<div class="loading_overlay"></div>
	<div id="form-identity">
		<img src="<?php print get_header_image(); ?>" alt="Login form header" />
	</div>
	<form id="sttv_login_form" action="/" method="post">
		<p class="message"><?php do_action( 'print_test' ); ?></p>
		<div class="row">
			<div class="input-field s12">
				<input type="text" name="sttv_user" id="sttv_user" placeholder="Username" minlength="4" />
			</div>
			<div class="input-field s12">
				<input type="password" name="sttv_pass" id="sttv_pass" placeholder="Password" />
			</div>
		</div>
		<button type="submit" class="z-depth-1" id="login-btn">Login</button>
    </form>
    <div id="forgot-block"><a class="lostpwd" href="<?php print wp_lostpassword_url(); ?>">Forgot your password?</a></div>
</div>
<?php

get_footer();