<?php
/**
 * The template for displaying any error messages.
 *
 * This template can be overridden by copying it to yourtheme/runthings-secrets/error.php.
 *
 * HOWEVER, on occasion we will need to update template files and you (the 
 * theme developer) will need to copy the new files to your theme to maintain 
 * compatibility. We try to do this as little as possible, but it does happen. 
 * When this occurs the version of the template file will be bumped and the 
 * readme will list any important changes.
 *
 * @version 1.0.0
 */
?>
<p><strong><?php _e('ERROR:', 'runthings-secrets'); ?></strong> <?php echo $context->error_message; ?></p>