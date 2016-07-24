<div id="register-form" class="widecolumn">
    <?php if ( $attributes['show_title'] ) : ?>
        <h3><?php _e( 'Register', 'dbpac-login' ); ?></h3>
    <?php endif; ?>

    <?php if ( count( $attributes['errors'] ) > 0 ) : ?>
        <?php foreach ( $attributes['errors'] as $error ) : ?>
            <p>
                <?php echo $error; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
 
    <form id="signupform" action="<?php echo wp_registration_url(); ?>" method="post">
         
        <p class="form-row">
            <label for="first_name"><?php _e( 'First name', 'dbpac-login' ); ?><strong>*</strong></label>
            <input type="text" name="first_name" id="first-name">
        </p>
 
        <p class="form-row">
            <label for="last_name"><?php _e( 'Last name', 'dbpac-login' ); ?><strong>*</strong></label>
            <input type="text" name="last_name" id="last-name">
        </p>

        <p class="form-row">
            <label for="email"><?php _e( 'Email', 'dbpac-login' ); ?> <strong>*</strong></label>
            <input type="text" name="email" id="email">
        </p>

        <p class="form-row">
            <label for="password"><?php _e( 'Password', 'dbpac-login' ); ?> <strong>*</strong></label>
            <input type="password" name="password" id="password">
        </p>

        <p class="form-row">
            <label for="address"><?php _e( 'Address', 'dbpac-login' ); ?><strong>*</strong></label>
            <input type="text" name="address" id="address">
        </p>

         <p class="form-row">
            <label for="phone"><?php _e( 'Phone', 'dbpac-login' ); ?><strong>*</strong></label>
            <input type="text" name="phone" id="phone">
        </p>
        
        <?php if ( $attributes['recaptcha_site_key'] ) : ?>
            <div class="recaptcha-container">
                <div class="g-recaptcha" data-sitekey="<?php echo $attributes['recaptcha_site_key']; ?>"></div>
            </div>
        <?php endif; ?>

        <p class="signup-submit">
            <input type="submit" name="submit" class="register-button"
                   value="<?php _e( 'Register', 'dbpac-login' ); ?>"/>
        </p>
    </form>
</div>