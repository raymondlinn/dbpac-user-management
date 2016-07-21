<div id="register-form" class="widecolumn">
    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php _e( 'Register', 'dbpac-login' ); ?></h3>
    <?php endif; ?>
 
    <form id="signupform" action="<?php echo wp_registration_url(); ?>" method="post">
        <p class="form-row">
            <label for="email"><?php _e( 'Email', 'dbpac-login' ); ?> <strong>*</strong></label>
            <input type="text" name="email" id="email">
        </p>

        <p class="form-row">
            <label for="password"><?php _e( 'Password', 'dbpac-login' ); ?> <strong>*</strong></label>
            <input type="text" name="email" id="email">
        </p>
 
        <p class="form-row">
            <label for="first_name"><?php _e( 'First name', 'dbpac-login' ); ?></label>
            <input type="text" name="first_name" id="first-name">
        </p>
 
        <p class="form-row">
            <label for="last_name"><?php _e( 'Last name', 'dbpac-login' ); ?></label>
            <input type="text" name="last_name" id="last-name">
        </p>
 
        <p class="form-row">
            <?php _e( 'Note: Your password will be generated automatically and sent to your email address.', 'personalize-login' ); ?>
        </p>
 
        <p class="signup-submit">
            <input type="submit" name="submit" class="register-button"
                   value="<?php _e( 'Register', 'dbpac-login' ); ?>"/>
        </p>
    </form>
</div>