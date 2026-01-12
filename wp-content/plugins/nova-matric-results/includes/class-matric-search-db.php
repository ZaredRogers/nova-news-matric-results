<?php

class Matric_Search_DB {

	public static function install() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'nova_matric_results';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			exam_number varchar(50) NOT NULL,
			province varchar(100) DEFAULT '',
			emis varchar(50) DEFAULT '',
			centre_name varchar(255) DEFAULT '',
			achievement_type varchar(255) DEFAULT '',
			subjects text,
			PRIMARY KEY  (id),
			KEY exam_number (exam_number)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public static function get_result( $exam_number ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nova_matric_results';
		// Sanitize
		$exam_number = preg_replace( '/[^0-9]/', '', $exam_number );
		
		$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE exam_number = %s LIMIT 1", $exam_number );
		return $wpdb->get_row( $query );
	}

	public static function clear_data() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nova_matric_results';
		$wpdb->query( "TRUNCATE TABLE $table_name" );
	}

	public static function import_csv( $file_path ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'nova_matric_results';

		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', 'CSV file not found.' );
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return new WP_Error( 'file_open_error', 'Could not open CSV file.' );
		}

		// Read header
		$header = fgetcsv( $handle );
		
		// Expected indices based on requirements roughly:
		// 0: Province
		// 1: EMIS
		// 2: Centre Name
		// 3: Exam Number
		// 4: Type of Achievement
		// 5: Subj Code 1
		// 6: Subj Name 1
		// ...

		$row_count = 0;
		$batch_size = 500;
		$values = [];
		$placeholders = [];

		$wpdb->query( 'START TRANSACTION' );

		while ( ( $data = fgetcsv( $handle ) ) !== false ) {
			// Skip empty rows
			if ( empty( $data[3] ) ) { // Exam Number is crucial
				continue;
			}

			$province = isset($data[0]) ? trim($data[0]) : '';
			$emis = isset($data[1]) ? trim($data[1]) : '';
			$centre_name = isset($data[2]) ? trim($data[2]) : '';
			$exam_number = preg_replace( '/[^0-9]/', '', isset($data[3]) ? trim($data[3]) : '' );
			$achievement_type = isset($data[4]) ? trim($data[4]) : '';

			$subjects = [];
			// Loop through up to 8 subjects.
			// Starting index 5. Each subject takes 2 columns (Code, Name).
			for ( $i = 0; $i < 8; $i++ ) {
				$code_idx = 5 + ( $i * 2 );
				$name_idx = 6 + ( $i * 2 );

				if ( ! empty( $data[$code_idx] ) ) {
					$subjects[] = [
						'code' => trim( $data[$code_idx] ),
						'name' => isset( $data[$name_idx] ) ? trim( $data[$name_idx] ) : ''
					];
				}
			}

			// Add to values
			array_push( $values, $exam_number, $province, $emis, $centre_name, $achievement_type, json_encode( $subjects ) );
			$placeholders[] = "(%s, %s, %s, %s, %s, %s)";
			$row_count++;

			if ( count( $placeholders ) >= $batch_size ) {
				$sql = "INSERT INTO $table_name (exam_number, province, emis, centre_name, achievement_type, subjects) VALUES " . implode( ', ', $placeholders );
				$wpdb->query( $wpdb->prepare( $sql, $values ) );
				$values = [];
				$placeholders = [];
			}
		}

		// Insert remaining
		if ( ! empty( $placeholders ) ) {
			$sql = "INSERT INTO $table_name (exam_number, province, emis, centre_name, achievement_type, subjects) VALUES " . implode( ', ', $placeholders );
			$wpdb->query( $wpdb->prepare( $sql, $values ) );
		}

		$wpdb->query( 'COMMIT' );
		fclose( $handle );

		return $row_count;
	}
}
