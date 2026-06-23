<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TPM_Processor - Business Logic Layer
 * Handles CSV parsing and data coordination.
 */
class TPM_Processor {

    public static function process_teachers_csv( $file ) {
        if ( ( $handle = fopen( $file['tmp_name'], "r" ) ) !== FALSE ) {
            $headers = fgetcsv( $handle, 1000, "," );
            $count = 0;
            
            while ( ( $row_data = fgetcsv( $handle, 1000, "," ) ) !== FALSE ) {
                if ( count( $headers ) !== count( $row_data ) ) continue;
                $row = array_combine( $headers, $row_data );
                
                $data = [
                    'email'      => sanitize_email( $row['mail'] ),
                    'first_name' => sanitize_text_field( $row['name'] ),
                    'last_name'  => sanitize_text_field( $row['surname'] ),
                    'phone'      => sanitize_text_field( $row['phone'] )
                ];

                if ( ! empty( $data['email'] ) ) {
                    TPM_DB::insert_or_update_teacher( $data );
                    $count++;
                }
            }
            fclose( $handle );
            return $count;
        }
        return 0;
    }

    public static function process_classes_csv( $file ) {
        if ( ( $handle = fopen( $file['tmp_name'], "r" ) ) !== FALSE ) {
            $headers = fgetcsv( $handle, 1000, "," );
            $count = 0;
            
            while ( ( $row_data = fgetcsv( $handle, 1000, "," ) ) !== FALSE ) {
                if ( count( $headers ) !== count( $row_data ) ) continue;
                $row = array_combine( $headers, $row_data );
                
                // Get Teacher
                $teacher_email = TPM_DB::get_teacher_by_name( 
                    sanitize_text_field( $row['name'] ), 
                    sanitize_text_field( $row['surname'] ) 
                );

                // Prepare Class Data
                $class_params = [
                    'lesson_name'    => sanitize_text_field( $row['lesson_name'] ),
                    'department'     => sanitize_text_field( $row['department'] ),
                    'semester'       => sanitize_text_field( $row['semester'] ),
                    'team_number'    => sanitize_text_field( $row['team_number'] ),
                    'type_indicator' => sanitize_text_field( $row['type_indicator'] ),
                    'specialty_name' => sanitize_text_field( $row['specialty_name'] )
                ];

                $class_id = TPM_DB::get_class_id( $class_params );
                
                $class_data = array_merge( $class_params, [
                    'classroom' => sanitize_text_field( $row['classroom'] )
                ]);

                $class_id = TPM_DB::upsert_class( $class_id, $class_data );

                if ( ! empty( $teacher_email ) && ! empty( $class_id ) ) {
                    TPM_DB::map_teacher_to_class( $teacher_email, $class_id );
                }
                $count++;
            }
            fclose( $handle );
            return $count;
        }
        return 0;
    }
}
