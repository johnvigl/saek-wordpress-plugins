<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TPM_DB_Setup {

    public static function create_custom_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Teachers Table
        $table_teachers = $wpdb->prefix . 'tpm_teachers';
        $sql_teachers = "CREATE TABLE $table_teachers (
            email varchar(100) NOT NULL,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            phone varchar(20),
            PRIMARY KEY  (email)
        ) $charset_collate;";
        dbDelta( $sql_teachers );

        // 2. Classes Table
        $table_classes = $wpdb->prefix . 'tpm_classes';
        $sql_classes = "CREATE TABLE $table_classes (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            specialty_name varchar(100) NOT NULL,
            semester varchar(50) NOT NULL,
            department varchar(100) NOT NULL,
            team_number varchar(50) NOT NULL,
            lesson_name varchar(150) NOT NULL,
            classroom varchar(50) NOT NULL,
            type_indicator varchar(20) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_class_definition (specialty_name, department, lesson_name, type_indicator, team_number, semester)
        ) $charset_collate;";
        dbDelta( $sql_classes );

        // 3. Relationships Table
        $table_relationships = $wpdb->prefix . 'tpm_teacher_classes';
        $sql_relationships = "CREATE TABLE $table_relationships (
            teacher_email varchar(100) NOT NULL,
            class_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY  (teacher_email, class_id)
        ) $charset_collate;";
        dbDelta( $sql_relationships );

		// 4. Applications Table
		$table_applications = $wpdb->prefix . 'tpm_applications';
		$sql_applications = "CREATE TABLE $table_applications (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			teacher_email varchar(100) NOT NULL,
			class_id bigint(20) unsigned NOT NULL,
			mod_type varchar(20) NOT NULL,
			target_date date NOT NULL,
			co_teacher_status tinyint(1) NOT NULL DEFAULT 0,
			modification_details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			valuation_status varchar(20) NOT NULL DEFAULT 'n/a',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";
        dbDelta( $sql_applications );
    }
}
