<?php
                    // Retrieve block content
                    $content = file_get_contents( "/wordpress/wp-content/plugins/nova-matric-results/templates/single-matric-results.html" );
                    
                    // Simple check: if we are in a block theme, we might get away with just outputting.
                    // But to be safe across all themes, we need to setup the page structure.
                    
                    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( "charset" ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php echo do_shortcode( do_blocks( $content ) ); ?>
<?php wp_footer(); ?>
</body>
</html>