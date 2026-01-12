<?php

class Matric_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			'Matric Results',
			'Matric Results',
			'manage_options',
			'nova-matric-results',
			array( $this, 'render_admin_page' ),
			'dashicons-welcome-learn-more',
			50
		);
	}

	private function get_csv_dir() {
		$upload = wp_upload_dir();
		$dir = $upload['basedir'] . '/matric-imports';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		
		// Security: Prevent directory browsing
		if ( ! file_exists( $dir . '/index.php' ) ) {
			file_put_contents( $dir . '/index.php', '<?php // Silence is golden' );
		}
		
		// Security: Prevent direct file access (Apache)
		if ( ! file_exists( $dir . '/.htaccess' ) ) {
			file_put_contents( $dir . '/.htaccess', 'Deny from all' );
		}

		return trailingslashit( $dir );
	}

	public function render_admin_page() {
		$message = '';
		$error = '';
		$csv_dir = $this->get_csv_dir();

		if ( isset( $_POST['matric_action'] ) && check_admin_referer( 'matric_process_data', 'matric_nonce' ) ) {
			if ( 'upload' === $_POST['matric_action'] ) {
				$upload_result = $this->handle_uploads( $csv_dir );
				if ( is_wp_error( $upload_result ) ) {
					$error = $upload_result->get_error_message();
				} else {
					$message = "Uploaded {$upload_result} files successfully.";
				}
			} elseif ( 'delete_files' === $_POST['matric_action'] ) {
				$this->delete_uploaded_files( $csv_dir );
				$message = 'Uploaded files deleted.';
			} elseif ( 'import' === $_POST['matric_action'] ) {
				$results = $this->process_imports( $csv_dir );
				if ( is_wp_error( $results ) ) {
					$error = $results->get_error_message();
				} else {
					$message = "Successfully imported {$results['rows']} records from {$results['files']} files.";
				}
			} elseif ( 'clear' === $_POST['matric_action'] ) {
				Matric_Search_DB::clear_data();
				$message = 'All matric data has been cleared.';
			}
		}

		$existing_files = glob( $csv_dir . '*.csv' );
		?>
		<div class="wrap">
			<h1>Matric Results Management</h1>

			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<div class="card">
				<h2>1. Upload Data</h2>
				<p>Upload your Province CSV files here. They will be stored in <code><?php echo esc_html( $csv_dir ); ?></code></p>
				<p><strong>Expected Format:</strong> Province, EMIS, Centre Name, Exam Number, Type of Achievement, Subj Code 1, Subj Name 1, ...</p>
				
				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'matric_process_data', 'matric_nonce' ); ?>
					<div style="margin-bottom: 15px;">
						<input type="file" name="csv_files[]" multiple accept=".csv" />
					</div>
					<button type="submit" name="matric_action" value="upload" class="button button-secondary">Upload CSV Files</button>
				</form>

				<?php if ( ! empty( $existing_files ) ) : ?>
					<hr>
					<h3>Uploaded Files Ready to Import:</h3>
					<ul>
						<?php foreach ( $existing_files as $file ) : ?>
							<li><?php echo esc_html( basename( $file ) ); ?></li>
						<?php endforeach; ?>
					</ul>
					<form method="post" action="" style="display:inline-block;">
						<?php wp_nonce_field( 'matric_process_data', 'matric_nonce' ); ?>
						<button type="submit" name="matric_action" value="delete_files" class="button link-delete" onclick="return confirm('Delete all uploaded CSV files?');">Delete all files</button>
					</form>
				<?php endif; ?>
			</div>

			<div class="card">
				<h2>2. Import Data</h2>
				<p>Import data from the uploaded files into the database table.</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'matric_process_data', 'matric_nonce' ); ?>
					<button type="submit" name="matric_action" value="import" class="button button-primary" <?php disabled( empty( $existing_files ) ); ?>>Import Data from Uploaded Files</button>
				</form>
			</div>

			<div class="card">
				<h2>Clear Database</h2>
				<p class="description">Empty the database table. Use this when the 6 weeks grace period has passed to delete all data (This is an irreversible action).</p>
				<form method="post" action="">
					<?php wp_nonce_field( 'matric_process_data', 'matric_nonce' ); ?>
					<button type="submit" name="matric_action" value="clear" class="button button-secondary" onclick="return confirm('Are you sure you want to delete all matric data from the database?');">Clear Database Table</button>
				</form>
			</div>
			
			<div class="card">
				<h2>Usage</h2>
				<p>Shortcodes:</p>
				<ul>
					<li><code>[matric_search_form]</code> - Search input.</li>
					<li><code>[matric_results]</code> - Results display.</li>
				</ul>
			</div>
		</div>
		<?php
	}

	private function handle_uploads( $target_dir ) {
		if ( empty( $_FILES['csv_files'] ) ) {
			return new WP_Error( 'no_files', 'No files uploaded.' );
		}

		$files = $_FILES['csv_files'];
		$count = 0;

		// Handle multiple file structure
		if ( is_array( $files['name'] ) ) {
			foreach ( $files['name'] as $key => $name ) {
				if ( $files['error'][ $key ] === UPLOAD_ERR_OK ) {
					$tmp_name = $files['tmp_name'][ $key ];
					$name = sanitize_file_name( $name );
					
					// Ensure simple csv check
					$ext = pathinfo( $name, PATHINFO_EXTENSION );
					if ( strtolower( $ext ) !== 'csv' ) {
						continue;
					}

					if ( move_uploaded_file( $tmp_name, $target_dir . $name ) ) {
						$count++;
					}
				}
			}
		}

		if ( 0 === $count ) {
			return new WP_Error( 'upload_failed', 'Failed to upload files. Please check file permissions and types.' );
		}

		return $count;
	}

	private function delete_uploaded_files( $dir ) {
		$files = glob( $dir . '*.csv' );
		foreach ( $files as $file ) {
			@unlink( $file );
		}
	}

	private function process_imports( $csv_dir ) {
		$files = glob( $csv_dir . '*.csv' );

		if ( empty( $files ) ) {
			return new WP_Error( 'no_files', 'No CSV files found in ' . $csv_dir );
		}

		$total_rows = 0;
		$processed_files = 0;

		@set_time_limit( 0 ); // Unlimited for large imports

		foreach ( $files as $file ) {
			$result = Matric_Search_DB::import_csv( $file );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$total_rows += $result;
			$processed_files++;
		}

		return [ 'rows' => $total_rows, 'files' => $processed_files ];
	}
}
