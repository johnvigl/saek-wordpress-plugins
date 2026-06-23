<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TPM_DB - Database Logic Layer
 * Responsible for all CRUD operations on TPM tables.
 */
class TPM_DB {

    /**
     * Get table names with WordPress prefix
     */
    private static function table_teachers() { global $wpdb; return $wpdb->prefix . 'tpm_teachers'; }
    private static function table_classes()  { global $wpdb; return $wpdb->prefix . 'tpm_classes'; }
    private static function table_mapping()  { global $wpdb; return $wpdb->prefix . 'tpm_teacher_classes'; }

    /**
     * Teachers logic
     */
    public static function insert_or_update_teacher( $data ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare(
            "INSERT INTO " . self::table_teachers() . " (email, first_name, last_name, phone) 
            VALUES (%s, %s, %s, %s) 
            ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), phone=VALUES(phone)",
            $data['email'], $data['first_name'], $data['last_name'], $data['phone']
        ) );
    }

    public static function get_all_teachers() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM " . self::table_teachers() . " ORDER BY last_name ASC, first_name ASC" );
    }

    public static function delete_teacher( $email ) {
        global $wpdb;
        $wpdb->delete( self::table_mapping(), array( 'teacher_email' => $email ), array( '%s' ) );
        return $wpdb->delete( self::table_teachers(), array( 'email' => $email ), array( '%s' ) );
    }

    /**
     * Classes & Mapping logic
     */
    public static function get_teacher_by_name( $first, $last ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT email FROM " . self::table_teachers() . " WHERE last_name = %s AND first_name = %s LIMIT 1",
            $last, $first
        ) );
    }

    public static function get_class_id( $params ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM " . self::table_classes() . " WHERE lesson_name = %s AND department = %s AND semester = %s AND team_number = %s AND type_indicator = %s AND specialty_name = %s",
            $params['lesson_name'], $params['department'], $params['semester'], $params['team_number'], $params['type_indicator'], $params['specialty_name']
        ) );
    }

	public static function upsert_class( $id, $data ) {
		global $wpdb;
		$table = self::table_classes();

		// 1. Αν δεν έχουμε ID από τον Processor, δοκιμάζουμε να το βρούμε στη βάση 
		// βάσει των μοναδικών στοιχείων του μαθήματος (Unique Key)
		if ( ! $id ) {
			$id = self::get_class_id( $data );
		}

		if ( $id ) {
			// Περίπτωση UPDATE: Το μάθημα υπάρχει.
			// Ενημερώνουμε τα στοιχεία (π.χ. αίθουσα) και επιστρέφουμε ΟΠΩΣΔΗΠΟΤΕ το ID.
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id; 
		} else {
			// Περίπτωση INSERT: Το μάθημα είναι πραγματικά νέο.
			$inserted = $wpdb->insert( $table, $data );
			if ( $inserted ) {
            	return $wpdb->insert_id;
        	}
    	}
    
    	return 0; // Επιστρέφουμε 0 μόνο αν αποτύχουν όλα
	}

	
    public static function map_teacher_to_class( $email, $class_id ) {
        global $wpdb;
        return $wpdb->query( $wpdb->prepare(
            "INSERT IGNORE INTO " . self::table_mapping() . " (teacher_email, class_id) VALUES (%s, %d)",
            $email, $class_id
        ) );
    }

    public static function get_teacher_class_ids( $email ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare( 
            "SELECT class_id FROM " . self::table_mapping() . " WHERE teacher_email = %s", 
            $email 
        ) );
    }

    public static function truncate_all() {
        global $wpdb;
        $wpdb->query( "TRUNCATE TABLE " . self::table_mapping() );
        $wpdb->query( "TRUNCATE TABLE " . self::table_classes() );
        $wpdb->query( "TRUNCATE TABLE " . self::table_teachers() );
    }
	
	public static function get_applications() {
		global $wpdb;
		$table_apps     = $wpdb->prefix . 'tpm_applications';
		$table_teachers = $wpdb->prefix . 'tpm_teachers';
		$table_classes  = $wpdb->prefix . 'tpm_classes';

		return $wpdb->get_results( "
			SELECT 
				app.*, 
				t.first_name, t.last_name, 
				c.lesson_name, c.department, c.semester, c.specialty_name, c.team_number
			FROM $table_apps AS app
			LEFT JOIN $table_teachers AS t ON app.teacher_email = t.email
			LEFT JOIN $table_classes AS c ON app.class_id = c.id
			ORDER BY app.created_at DESC
		" );
	}

	public static function update_application_status( $id, $status ) {
		global $wpdb;
		return $wpdb->update( 
			$wpdb->prefix . 'tpm_applications', 
			array( 'status' => $status ), 
			array( 'id' => $id ) 
		);
	}
}
