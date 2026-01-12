<?php

class Matric_Search_Form {

	public function __construct() {
		add_shortcode( 'matric_search_form', array( $this, 'render_search_form' ) );
		add_shortcode( 'matric_results', array( $this, 'render_results' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_check_matric_exam', array( $this, 'ajax_check_exam' ) );
		add_action( 'wp_ajax_nopriv_check_matric_exam', array( $this, 'ajax_check_exam' ) );
        // Removed register_block_template to prevent accidental assignment to standard pages
        
        // Virtual Page Handling
        add_filter( 'generate_rewrite_rules', array( $this, 'add_rewrite_rules' ) );
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        add_filter( 'template_include', array( $this, 'load_virtual_template' ) );
        
        // Activation flush (Hooked here, but really should be in main file activation hook, but this works for development syncs)
        add_action( 'init', array( $this, 'flush_rules_if_needed' ) );
	}

    public function add_rewrite_rules( $wp_rewrite ) {
        $rules = array(
            'matric-results-view/?$' => 'index.php?matric_results_page=1',
        );
        $wp_rewrite->rules = $rules + $wp_rewrite->rules;
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'matric_results_page';
        return $vars;
    }

    public function flush_rules_if_needed() {
        if ( get_option( 'matric_rules_flushed_v2' ) !== 'yes' ) {
            flush_rewrite_rules();
            update_option( 'matric_rules_flushed_v2', 'yes' );
        }
    }

    public function load_virtual_template( $template ) {
        if ( get_query_var( 'matric_results_page' ) == 1 ) {
            
            $block_template_file = NOVA_MATRIC_PLUGIN_DIR . 'templates/single-matric-results.html';
            
            if ( file_exists( $block_template_file ) ) {
                $virtual_template = NOVA_MATRIC_PLUGIN_DIR . 'templates/virtual-page-renderer.php';
                
                // Ensure the renderer file exists
                if ( ! file_exists( $virtual_template ) ) {
                    // Create it with necessary WP header/footer context IF the block template doesn't include them.
                    // However, the provided template includes WP Header/Footer template parts.
                    // The issue is likely that do_blocks() alone isn't enough to bootstrap the theme styles/scripts if get_header/footer aren't called in standard way.
                    // BUT, <!-- wp:template-part --> only works if the context is right (FSE).
                    // If we are in a Classic Theme or Hybrid that doesn't support FSE fully, these template parts fail to render.
                    
                    // Let's make it robust:
                    // 1. We include wp_head() and wp_footer() manually if we are taking over.
                    // 2. We output the block content.
                    
                    $renderer_code = '<?php
                    // Retrieve block content
                    $content = file_get_contents( "' . $block_template_file . '" );
                    
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
</html>';
                   file_put_contents( $virtual_template, $renderer_code );
                }
                
                return $virtual_template;
            }
        }
        return $template;
    }


    /**
     * Deprecated: Template registration removed to prevent accidental selection on Search pages.
     * The template is loaded virtually via load_virtual_template().
     */
    /*
    public function register_block_template( $query_result, $query, $template_type ) {
        // ... (Code removed to clean up scope) ...
        return $query_result;
    }
    */

	public function enqueue_assets() {
		wp_enqueue_style( 'nova-matric-style', NOVA_MATRIC_PLUGIN_URL . 'assets/css/style.css', array(), '1.0.0' );
		wp_enqueue_script( 'nova-matric-js', NOVA_MATRIC_PLUGIN_URL . 'assets/js/matric-search.js', array( 'jquery' ), '1.0.0', true );
		wp_localize_script( 'nova-matric-js', 'matric_search_vars', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'matric_search_verify' )
		));
	}

	public function ajax_check_exam() {
		check_ajax_referer( 'matric_search_verify', 'matric_nonce' );

		if ( empty( $_POST['exam_number'] ) ) {
			wp_send_json_error( 'Please enter a valid examination number' );
		}

		// Rate Limiting
		$ip_address = $_SERVER['REMOTE_ADDR'];
		$rate_limit_key = 'matric_search_ajax_' . md5( $ip_address );
		$limit_count = get_transient( $rate_limit_key );

		if ( false === $limit_count ) {
			set_transient( $rate_limit_key, 1, 5 * MINUTE_IN_SECONDS );
		} elseif ( $limit_count >= 20 ) { // Slightly higher limit for AJAX checks vs full page attempts
			wp_send_json_error( 'Too many attempts. Please try again later.' );
		} else {
			set_transient( $rate_limit_key, $limit_count + 1, 5 * MINUTE_IN_SECONDS );
		}

		$exam_number = preg_replace( '/[^0-9]/', '', $_POST['exam_number'] );
		
		if ( empty( $exam_number ) ) {
			wp_send_json_error( 'Invalid format' );
		}

		$result = Matric_Search_DB::get_result( $exam_number );

		if ( $result ) {
			wp_send_json_success();
		} else {
			wp_send_json_error( 'Please enter a valid examination number' );
		}
	}

	public function render_search_form( $atts = array() ) {
        $atts = shortcode_atts( array(
            'action_url' => home_url( '/matric-results-view/' ),
        ), $atts, 'matric_search_form' );

		ob_start();
		?>
		<div class="nova-matric-search-widget">
			<form action="<?php echo esc_url( $atts['action_url'] ); ?>" method="post" class="matric-search-form">
				<?php wp_nonce_field( 'matric_search_verify', 'matric_nonce' ); ?>
				<div class="form-group">
					<label for="exam_number">Examination Number</label>
					<input type="text" name="exam_number" id="exam_number" placeholder="Enter Exam Number" required pattern="[0-9]+" title="Numbers only, no spaces or special characters">
					<div class="matric-search-error" style="display:none; color: red; margin-top: 5px; font-size: 0.9em;"></div>
				</div>
                <input type="hidden" name="matric_search_submitted" value="1">
				<button type="submit" class="matric-search-btn">Search</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_results() {
		// Only run if form submitted via POST
		if ( empty( $_POST['exam_number'] ) || empty( $_POST['matric_nonce'] ) ) {
			return '';
		}

		// Verify Nonce
		if ( ! wp_verify_nonce( $_POST['matric_nonce'], 'matric_search_verify' ) ) {
			return '<div class="matric-error">Security check failed. Please return to the search form and try again.</div>';
		}

		// Rate Limiting
		$ip_address = $_SERVER['REMOTE_ADDR'];
		$rate_limit_key = 'matric_search_limit_' . md5( $ip_address );
		$limit_count = get_transient( $rate_limit_key );

		if ( false === $limit_count ) {
			set_transient( $rate_limit_key, 1, 5 * MINUTE_IN_SECONDS ); // 5 Minute window
		} elseif ( $limit_count >= 10 ) { // Limit to 10 searches per 5 minutes
			return '<div class="matric-error">Too many search attempts. Please try again in a few minutes.</div>';
		} else {
			set_transient( $rate_limit_key, $limit_count + 1, 5 * MINUTE_IN_SECONDS );
		}

		$exam_number = preg_replace( '/[^0-9]/', '', $_POST['exam_number'] );
        if( empty($exam_number) ) {
            return '<div class="matric-error">Invalid examination number format.</div>';
        }

		// Query DB
		$result = Matric_Search_DB::get_result( $exam_number );

		ob_start();
		if ( $result ) {
			$subjects = json_decode( $result->subjects );
			?>
			<div class="matric-result-card">
				
				<h2 class="matric-congrats">Congratulations!</h2>

				<div class="matric-main-content">
					
					<div class="matric-section qualification-section">
						<h3>You have achieved a result that makes you eligible for a:</h3>
						<p class="matric-achievement highlight"><?php echo esc_html( $result->achievement_type ); ?></p>
					</div>

					<?php if ( ! empty( $subjects ) ) : ?>
						<div class="matric-section outstanding-subjects">
							<h3>You have also achieved outstanding results (80% - 100%) in the following subjects:</h3>
							<ul>
								<?php foreach ( $subjects as $subject ) : ?>
									<li><?php echo esc_html( $subject->code . ' - ' . $subject->name ); ?></li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>

					<div class="matric-details">
						<div class="detail-row">
							<span class="label">Result for:</span>
							<span class="value"><?php echo esc_html( $result->exam_number ); ?></span>
						</div>
						
						<div class="detail-row">
							<span class="label">Province:</span>
							<span class="value"><?php echo esc_html( ucwords( strtolower( $result->province ) ) ); ?></span>
						</div>
					</div>

				</div>

			</div>
			<?php
		} else {
			?>
			<div class="matric-no-result">
				<p>No results found for examination number <strong><?php echo esc_html( $exam_number ); ?></strong>.</p>
				<p>Please check the number and try again.</p>
			</div>
			<?php
		}
		return ob_get_clean();
	}
}
