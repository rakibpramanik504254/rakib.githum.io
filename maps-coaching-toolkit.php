<?php
/**
 * Plugin Name: MAPS Coaching Toolkit
 * Description: Custom toolkit for managing MAPS Coaching center subjects, students, teachers, routines, attendance, and study materials.
 * Version: 1.0.0
 * Author: Rakib & GPT-5 Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'MAPS_Coaching_Toolkit' ) ) {

    class MAPS_Coaching_Toolkit {

        /**
         * Plugin initialization hook.
         */
        public static function init() {
            register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );

            add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ) );
            add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
            add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
            add_action( 'init', array( __CLASS__, 'handle_frontend_forms' ) );
            add_filter( 'login_redirect', array( __CLASS__, 'redirect_after_login' ), 10, 3 );
            add_action( 'admin_init', array( __CLASS__, 'restrict_admin_access' ) );
            add_action( 'after_setup_theme', array( __CLASS__, 'maybe_hide_admin_bar' ) );
        }

        /**
         * Plugin activation tasks: create required tables.
         */
        public static function activate() {
            global $wpdb;

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $charset_collate = $wpdb->get_charset_collate();

            $tables = array();

            $tables['maps_subjects'] = "CREATE TABLE {$wpdb->prefix}maps_subjects (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                subject_name varchar(255) NOT NULL,
                class_name varchar(255) NOT NULL,
                PRIMARY KEY  (id)
            ) $charset_collate;";

            $tables['maps_student_enrollments'] = "CREATE TABLE {$wpdb->prefix}maps_student_enrollments (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                student_wp_id bigint(20) NOT NULL,
                subject_id mediumint(9) NOT NULL,
                PRIMARY KEY  (id),
                KEY student_wp_id (student_wp_id),
                KEY subject_id (subject_id)
            ) $charset_collate;";

            $tables['maps_teacher_subjects'] = "CREATE TABLE {$wpdb->prefix}maps_teacher_subjects (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                teacher_wp_id bigint(20) NOT NULL,
                subject_id mediumint(9) NOT NULL,
                PRIMARY KEY  (id),
                KEY teacher_wp_id (teacher_wp_id),
                KEY subject_id (subject_id)
            ) $charset_collate;";

            $tables['maps_teacher_attendance'] = "CREATE TABLE {$wpdb->prefix}maps_teacher_attendance (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                teacher_wp_id bigint(20) NOT NULL,
                subject_id mediumint(9) NOT NULL,
                class_date date NOT NULL,
                attendance_status varchar(20) NOT NULL,
                submission_time datetime NOT NULL,
                approval_status varchar(20) NOT NULL DEFAULT 'pending',
                PRIMARY KEY  (id),
                KEY teacher_wp_id (teacher_wp_id),
                KEY subject_id (subject_id),
                KEY approval_status (approval_status)
            ) $charset_collate;";

            $tables['maps_routine_images'] = "CREATE TABLE {$wpdb->prefix}maps_routine_images (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                class_name varchar(255) NOT NULL,
                image_url varchar(255) NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY class_name (class_name)
            ) $charset_collate;";

            $tables['maps_study_materials'] = "CREATE TABLE {$wpdb->prefix}maps_study_materials (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                title text NOT NULL,
                subject_id mediumint(9) NOT NULL,
                file_url varchar(255) NOT NULL,
                upload_date datetime NOT NULL,
                PRIMARY KEY  (id),
                KEY subject_id (subject_id)
            ) $charset_collate;";

            foreach ( $tables as $sql ) {
                dbDelta( $sql );
            }
        }

        /**
         * Register admin menu pages.
         */
        public static function register_admin_menu() {
            add_menu_page(
                __( 'MAPS Toolkit', 'maps-coaching-toolkit' ),
                __( 'MAPS Toolkit', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-subjects',
                array( __CLASS__, 'render_subjects_page' ),
                'dashicons-welcome-learn-more',
                26
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Manage Subjects', 'maps-coaching-toolkit' ),
                __( 'Manage Subjects', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-subjects',
                array( __CLASS__, 'render_subjects_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Assign to Student', 'maps-coaching-toolkit' ),
                __( 'Assign to Student', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-assign-student',
                array( __CLASS__, 'render_assign_student_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Assign to Teacher', 'maps-coaching-toolkit' ),
                __( 'Assign to Teacher', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-assign-teacher',
                array( __CLASS__, 'render_assign_teacher_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Upload Routine', 'maps-coaching-toolkit' ),
                __( 'Upload Routine', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-routine',
                array( __CLASS__, 'render_routine_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Upload Materials', 'maps-coaching-toolkit' ),
                __( 'Upload Materials', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-materials',
                array( __CLASS__, 'render_materials_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Approve Attendance', 'maps-coaching-toolkit' ),
                __( 'Approve Attendance', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-approve-attendance',
                array( __CLASS__, 'render_approve_attendance_page' )
            );
        }

        /**
         * Load admin scripts.
         */
        public static function enqueue_admin_assets( $hook_suffix ) {
            $pages = array(
                'maps-toolkit-routine',
                'maps-toolkit-materials',
            );

            $current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if ( in_array( $current_page, $pages, true ) ) {
                wp_enqueue_media();

                wp_register_script( 'maps-toolkit-admin', '', array( 'jquery' ), '1.0.0', true );
                wp_enqueue_script( 'maps-toolkit-admin' );

                $inline_js = "jQuery(function($){\n\tfunction initMediaFrame(buttonSelector, inputSelector, previewSelector, title){\n\t\tvar frame;\n\t\t$(document).on('click', buttonSelector, function(e){\n\t\t\te.preventDefault();\n\t\t\tif(frame){\n\t\t\t\tframe.open();\n\t\t\t\treturn;\n\t\t\t}\n\t\t\tframe = wp.media({\n\t\t\t\ttitle: title,\n\t\t\t\tbutton: { text: title },\n\t\t\t\tmultiple: false\n\t\t\t});\n\t\t\tframe.on('select', function(){\n\t\t\t\tvar attachment = frame.state().get('selection').first().toJSON();\n\t\t\t\t$(inputSelector).val(attachment.url);\n\t\t\t\tif(previewSelector){\n\t\t\t\t\tvar previewHtml = attachment.type === 'image' ? '<img src="' + attachment.url + '" style="max-width:200px;height:auto;" />' : '<a href="' + attachment.url + '" target="_blank" rel="noopener">' + attachment.filename + '</a>';\n\t\t\t\t\t$(previewSelector).html(previewHtml);\n\t\t\t\t}\n\t\t\t});\n\t\t\tframe.open();\n\t\t});\n\t}\n\n\tinitMediaFrame('#maps_routine_upload_button', '#maps_routine_image', '#maps_routine_preview', '" . esc_js( __( 'Select Routine', 'maps-coaching-toolkit' ) ) . "');\n\tinitMediaFrame('#maps_material_upload_button', '#maps_material_file', '#maps_material_preview', '" . esc_js( __( 'Select File', 'maps-coaching-toolkit' ) ) . "');\n});";

                wp_add_inline_script( 'maps-toolkit-admin', $inline_js );
            }
        }

        /**
         * Register shortcodes.
         */
        public static function register_shortcodes() {
            add_shortcode( 'maps_teacher_dashboard', array( __CLASS__, 'render_teacher_dashboard' ) );
            add_shortcode( 'maps_student_dashboard', array( __CLASS__, 'render_student_dashboard' ) );
        }

        /**
         * Handle frontend form submissions from teacher dashboard.
         */
        public static function handle_frontend_forms() {
            if ( isset( $_POST['maps_teacher_attendance_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maps_teacher_attendance_nonce'] ) ), 'maps_teacher_attendance' ) ) {
                self::process_teacher_attendance_submission();
            }
        }

        /**
         * Process teacher attendance submission.
         */
        private static function process_teacher_attendance_submission() {
            if ( ! is_user_logged_in() ) {
                return;
            }

            $user = wp_get_current_user();
            if ( ! in_array( 'teacher', (array) $user->roles, true ) ) {
                return;
            }

            global $wpdb;

            $subject_id       = isset( $_POST['maps_attendance_subject'] ) ? absint( $_POST['maps_attendance_subject'] ) : 0;
            $class_date_raw   = isset( $_POST['maps_attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_attendance_date'] ) ) : '';
            $attendance_status = isset( $_POST['maps_attendance_status'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_attendance_status'] ) ) : '';

            if ( empty( $subject_id ) || empty( $class_date_raw ) || empty( $attendance_status ) ) {
                return;
            }

            $assigned_subjects = self::get_user_assigned_subjects( $user->ID, 'teacher' );
            if ( ! in_array( $subject_id, $assigned_subjects, true ) ) {
                return;
            }

            $valid_statuses = array( 'present', 'absent' );
            if ( ! in_array( strtolower( $attendance_status ), $valid_statuses, true ) ) {
                return;
            }

            $date_obj = date_create( $class_date_raw );
            if ( ! $date_obj ) {
                return;
            }

            $class_date = $date_obj->format( 'Y-m-d' );

            $table = $wpdb->prefix . 'maps_teacher_attendance';

            $wpdb->insert(
                $table,
                array(
                    'teacher_wp_id'    => $user->ID,
                    'subject_id'       => $subject_id,
                    'class_date'       => $class_date,
                    'attendance_status'=> $attendance_status,
                    'submission_time'  => current_time( 'mysql' ),
                    'approval_status'  => 'pending',
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s' )
            );

            wp_safe_redirect( add_query_arg( 'maps_attendance_submitted', '1', wp_get_referer() ? wp_get_referer() : home_url( '/teacher-dashboard/' ) ) );
            exit;
        }

        /**
         * Redirect users after login based on role.
         */
        public static function redirect_after_login( $redirect_to, $request, $user ) {
            if ( ! $user || is_wp_error( $user ) || empty( $user->roles ) ) {
                return $redirect_to;
            }

            if ( in_array( 'teacher', (array) $user->roles, true ) ) {
                return home_url( '/teacher-dashboard/' );
            }

            if ( in_array( 'student', (array) $user->roles, true ) ) {
                return home_url( '/student-dashboard/' );
            }

            return $redirect_to;
        }

        /**
         * Restrict admin access for non-admin roles.
         */
        public static function restrict_admin_access() {
            if ( ! is_user_logged_in() ) {
                return;
            }

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                return;
            }

            if ( self::current_user_is_limited() && is_admin() ) {
                wp_safe_redirect( home_url() );
                exit;
            }
        }

        /**
         * Hide admin bar for restricted roles.
         */
        public static function maybe_hide_admin_bar() {
            if ( self::current_user_is_limited() ) {
                show_admin_bar( false );
            }
        }

        /**
         * Determine if current user is a student or teacher.
         */
        private static function current_user_is_limited() {
            if ( ! is_user_logged_in() ) {
                return false;
            }

            $user = wp_get_current_user();
            $roles = (array) $user->roles;
            return in_array( 'student', $roles, true ) || in_array( 'teacher', $roles, true );
        }

        /**
         * Render Manage Subjects page.
         */
        public static function render_subjects_page() {
            if ( isset( $_POST['maps_subject_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maps_subject_nonce'] ) ), 'maps_add_subject' ) ) {
                self::handle_add_subject();
            }

            global $wpdb;
            $subjects_table = $wpdb->prefix . 'maps_subjects';
            $subjects = $wpdb->get_results( "SELECT * FROM {$subjects_table} ORDER BY class_name, subject_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Manage Subjects', 'maps-coaching-toolkit' ) . '</h1>';

            echo '<form method="post">';
            wp_nonce_field( 'maps_add_subject', 'maps_subject_nonce' );
            echo '<table class="form-table">';
            echo '<tr><th><label for="maps_subject_name">' . esc_html__( 'Subject Name', 'maps-coaching-toolkit' ) . '</label></th>';
            echo '<td><input type="text" id="maps_subject_name" name="maps_subject_name" class="regular-text" required></td></tr>';
            echo '<tr><th><label for="maps_class_name">' . esc_html__( 'Class Name', 'maps-coaching-toolkit' ) . '</label></th>';
            echo '<td><input type="text" id="maps_class_name" name="maps_class_name" class="regular-text" required></td></tr>';
            echo '</table>';
            submit_button( __( 'Add Subject', 'maps-coaching-toolkit' ) );
            echo '</form>';

            echo '<h2>' . esc_html__( 'Existing Subjects', 'maps-coaching-toolkit' ) . '</h2>';
            if ( ! empty( $subjects ) ) {
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__( 'ID', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
                foreach ( $subjects as $subject ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( $subject->id ) . '</td>';
                    echo '<td>' . esc_html( $subject->subject_name ) . '</td>';
                    echo '<td>' . esc_html( $subject->class_name ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>' . esc_html__( 'No subjects found.', 'maps-coaching-toolkit' ) . '</p>';
            }

            echo '</div>';
        }

        /**
         * Handle new subject creation.
         */
        private static function handle_add_subject() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $subject_name = isset( $_POST['maps_subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_subject_name'] ) ) : '';
            $class_name   = isset( $_POST['maps_class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_class_name'] ) ) : '';

            if ( empty( $subject_name ) || empty( $class_name ) ) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'maps_subjects';
            $wpdb->insert(
                $table,
                array(
                    'subject_name' => $subject_name,
                    'class_name'   => $class_name,
                ),
                array( '%s', '%s' )
            );
        }

        /**
         * Render Assign Student page.
         */
        public static function render_assign_student_page() {
            self::handle_assign_subjects( 'student' );
        }

        /**
         * Render Assign Teacher page.
         */
        public static function render_assign_teacher_page() {
            self::handle_assign_subjects( 'teacher' );
        }

        /**
         * Shared handler for assigning subjects to users.
         */
        private static function handle_assign_subjects( $role ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $role_label = $role === 'teacher' ? __( 'Teacher', 'maps-coaching-toolkit' ) : __( 'Student', 'maps-coaching-toolkit' );

            if ( isset( $_POST['maps_assign_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maps_assign_nonce'] ) ), 'maps_assign_subjects_' . $role ) ) {
                self::process_subject_assignment( $role );
            }

            $users = get_users( array( 'role' => $role, 'orderby' => 'display_name', 'order' => 'ASC' ) );

            global $wpdb;
            $subjects_table = $wpdb->prefix . 'maps_subjects';
            $subjects = $wpdb->get_results( "SELECT * FROM {$subjects_table} ORDER BY class_name, subject_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            $selected_user_id = isset( $_GET['maps_user_id'] ) ? absint( $_GET['maps_user_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            if ( isset( $_POST['maps_user_id'] ) ) {
                $selected_user_id = absint( $_POST['maps_user_id'] );
            }

            echo '<div class="wrap">';
            echo '<h1>' . sprintf( esc_html__( 'Assign Subjects to %s', 'maps-coaching-toolkit' ), esc_html( $role_label ) ) . '</h1>';

            if ( empty( $users ) ) {
                echo '<p>' . esc_html__( 'No users found for this role.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="post">';
            wp_nonce_field( 'maps_assign_subjects_' . $role, 'maps_assign_nonce' );
            echo '<table class="form-table">';
            echo '<tr><th><label for="maps_user_id">' . esc_html( $role_label ) . '</label></th><td>';
            echo '<select name="maps_user_id" id="maps_user_id" onchange="this.form.submit();">';
            echo '<option value="">' . esc_html__( 'Select a user', 'maps-coaching-toolkit' ) . '</option>';
            foreach ( $users as $user ) {
                echo '<option value="' . esc_attr( $user->ID ) . '" ' . selected( $selected_user_id, $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
            }
            echo '</select>';
            echo '</td></tr>';
            echo '</table>';

            if ( $selected_user_id && ! empty( $subjects ) ) {
                $assigned_subjects = self::get_user_assigned_subjects( $selected_user_id, $role );

                echo '<h2>' . esc_html__( 'Subjects', 'maps-coaching-toolkit' ) . '</h2>';
                echo '<input type="hidden" name="maps_selected_user" value="' . esc_attr( $selected_user_id ) . '">';
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__( 'Assign', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
                foreach ( $subjects as $subject ) {
                    $checked = in_array( (int) $subject->id, $assigned_subjects, true ) ? 'checked' : '';
                    echo '<tr>';
                    echo '<td><input type="checkbox" name="maps_subject_ids[]" value="' . esc_attr( $subject->id ) . '" ' . esc_attr( $checked ) . '></td>';
                    echo '<td>' . esc_html( $subject->subject_name ) . '</td>';
                    echo '<td>' . esc_html( $subject->class_name ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                submit_button( __( 'Save Assignments', 'maps-coaching-toolkit' ) );
            }

            echo '</form>';
            echo '</div>';
        }

        /**
         * Save subject assignments.
         */
        private static function process_subject_assignment( $role ) {
            $user_id = isset( $_POST['maps_selected_user'] ) ? absint( $_POST['maps_selected_user'] ) : 0;

            if ( ! $user_id ) {
                return;
            }

            global $wpdb;

            $selected_subjects = isset( $_POST['maps_subject_ids'] ) ? array_map( 'absint', (array) $_POST['maps_subject_ids'] ) : array();

            if ( 'student' === $role ) {
                $table = $wpdb->prefix . 'maps_student_enrollments';
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE student_wp_id = %d", $user_id ) );
                foreach ( $selected_subjects as $subject_id ) {
                    $wpdb->insert( $table, array( 'student_wp_id' => $user_id, 'subject_id' => $subject_id ), array( '%d', '%d' ) );
                }
            } else {
                $table = $wpdb->prefix . 'maps_teacher_subjects';
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE teacher_wp_id = %d", $user_id ) );
                foreach ( $selected_subjects as $subject_id ) {
                    $wpdb->insert( $table, array( 'teacher_wp_id' => $user_id, 'subject_id' => $subject_id ), array( '%d', '%d' ) );
                }
            }
        }

        /**
         * Get subjects assigned to user by role.
         */
        private static function get_user_assigned_subjects( $user_id, $role ) {
            global $wpdb;
            if ( 'student' === $role ) {
                $table = $wpdb->prefix . 'maps_student_enrollments';
                $column = 'student_wp_id';
            } else {
                $table = $wpdb->prefix . 'maps_teacher_subjects';
                $column = 'teacher_wp_id';
            }

            $results = $wpdb->get_col( $wpdb->prepare( "SELECT subject_id FROM {$table} WHERE {$column} = %d", $user_id ) );
            return array_map( 'intval', $results );
        }

        /**
         * Render routine upload page.
         */
        public static function render_routine_page() {
            if ( isset( $_POST['maps_routine_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maps_routine_nonce'] ) ), 'maps_upload_routine' ) ) {
                self::handle_routine_upload();
            }

            global $wpdb;
            $subjects_table = $wpdb->prefix . 'maps_subjects';
            $routines_table = $wpdb->prefix . 'maps_routine_images';

            $classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM {$subjects_table} ORDER BY class_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $existing_routines = $wpdb->get_results( "SELECT class_name, image_url FROM {$routines_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $routine_map = array();
            foreach ( $existing_routines as $routine ) {
                $routine_map[ $routine->class_name ] = $routine->image_url;
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Upload Class Routine', 'maps-coaching-toolkit' ) . '</h1>';

            if ( empty( $classes ) ) {
                echo '<p>' . esc_html__( 'No classes found. Please add subjects first.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="post">';
            wp_nonce_field( 'maps_upload_routine', 'maps_routine_nonce' );

            echo '<table class="form-table">';
            echo '<tr><th><label for="maps_routine_class">' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</label></th><td>';
            echo '<select id="maps_routine_class" name="maps_routine_class" required>';
            echo '<option value="">' . esc_html__( 'Select Class', 'maps-coaching-toolkit' ) . '</option>';
            foreach ( $classes as $class ) {
                echo '<option value="' . esc_attr( $class->class_name ) . '">' . esc_html( $class->class_name ) . '</option>';
            }
            echo '</select>';
            echo '</td></tr>';

            echo '<tr><th>' . esc_html__( 'Routine Image', 'maps-coaching-toolkit' ) . '</th><td>';
            echo '<input type="hidden" id="maps_routine_image" name="maps_routine_image" value="">';
            echo '<button type="button" class="button" id="maps_routine_upload_button">' . esc_html__( 'Select Image', 'maps-coaching-toolkit' ) . '</button>';
            echo '<div id="maps_routine_preview" style="margin-top:15px;"></div>';
            echo '</td></tr>';
            echo '</table>';

            submit_button( __( 'Save Routine', 'maps-coaching-toolkit' ) );
            echo '</form>';

            if ( ! empty( $routine_map ) ) {
                echo '<h2>' . esc_html__( 'Existing Routines', 'maps-coaching-toolkit' ) . '</h2>';
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Routine URL', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
                foreach ( $routine_map as $class_name => $image_url ) {
                    echo '<tr><td>' . esc_html( $class_name ) . '</td><td><a href="' . esc_url( $image_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Routine', 'maps-coaching-toolkit' ) . '</a></td></tr>';
                }
                echo '</tbody></table>';
            }

            echo '</div>';
        }

        /**
         * Handle routine saving.
         */
        private static function handle_routine_upload() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $class_name = isset( $_POST['maps_routine_class'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_routine_class'] ) ) : '';
            $image_url  = isset( $_POST['maps_routine_image'] ) ? esc_url_raw( wp_unslash( $_POST['maps_routine_image'] ) ) : '';

            if ( empty( $class_name ) || empty( $image_url ) ) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'maps_routine_images';

            $existing_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE class_name = %s", $class_name ) );

            if ( $existing_id ) {
                $wpdb->update( $table, array( 'image_url' => $image_url ), array( 'id' => $existing_id ), array( '%s' ), array( '%d' ) );
            } else {
                $wpdb->insert( $table, array( 'class_name' => $class_name, 'image_url' => $image_url ), array( '%s', '%s' ) );
            }
        }

        /**
         * Render materials upload page.
         */
        public static function render_materials_page() {
            if ( isset( $_POST['maps_material_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maps_material_nonce'] ) ), 'maps_upload_material' ) ) {
                self::handle_material_upload();
            }

            global $wpdb;
            $subjects_table = $wpdb->prefix . 'maps_subjects';
            $materials_table = $wpdb->prefix . 'maps_study_materials';

            $subjects = $wpdb->get_results( "SELECT * FROM {$subjects_table} ORDER BY class_name, subject_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $materials = $wpdb->get_results( "SELECT m.*, s.subject_name, s.class_name FROM {$materials_table} m JOIN {$subjects_table} s ON m.subject_id = s.id ORDER BY m.upload_date DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Upload Study Materials', 'maps-coaching-toolkit' ) . '</h1>';

            if ( empty( $subjects ) ) {
                echo '<p>' . esc_html__( 'No subjects available. Please add subjects first.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="post">';
            wp_nonce_field( 'maps_upload_material', 'maps_material_nonce' );
            echo '<table class="form-table">';
            echo '<tr><th><label for="maps_material_title">' . esc_html__( 'Title', 'maps-coaching-toolkit' ) . '</label></th><td><input type="text" id="maps_material_title" name="maps_material_title" class="regular-text" required></td></tr>';
            echo '<tr><th><label for="maps_material_subject">' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</label></th><td>';
            echo '<select id="maps_material_subject" name="maps_material_subject" required>';
            echo '<option value="">' . esc_html__( 'Select Subject', 'maps-coaching-toolkit' ) . '</option>';
            foreach ( $subjects as $subject ) {
                echo '<option value="' . esc_attr( $subject->id ) . '">' . esc_html( $subject->subject_name . ' (' . $subject->class_name . ')' ) . '</option>';
            }
            echo '</select></td></tr>';
            echo '<tr><th>' . esc_html__( 'File', 'maps-coaching-toolkit' ) . '</th><td>';
            echo '<input type="hidden" id="maps_material_file" name="maps_material_file" value="">';
            echo '<button type="button" class="button" id="maps_material_upload_button">' . esc_html__( 'Select File', 'maps-coaching-toolkit' ) . '</button>';
            echo '<div id="maps_material_preview" style="margin-top:15px;"></div>';
            echo '</td></tr>';
            echo '</table>';
            submit_button( __( 'Save Material', 'maps-coaching-toolkit' ) );
            echo '</form>';

            if ( ! empty( $materials ) ) {
                echo '<h2>' . esc_html__( 'Existing Materials', 'maps-coaching-toolkit' ) . '</h2>';
                echo '<table class="widefat fixed striped">';
                echo '<thead><tr><th>' . esc_html__( 'Title', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'File', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Uploaded', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
                foreach ( $materials as $material ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( $material->title ) . '</td>';
                    echo '<td>' . esc_html( $material->subject_name ) . '</td>';
                    echo '<td>' . esc_html( $material->class_name ) . '</td>';
                    echo '<td><a href="' . esc_url( $material->file_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Download', 'maps-coaching-toolkit' ) . '</a></td>';
                    echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $material->upload_date ) ) ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }

            echo '</div>';
        }

        /**
         * Handle study material saving.
         */
        private static function handle_material_upload() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $title      = isset( $_POST['maps_material_title'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_material_title'] ) ) : '';
            $subject_id = isset( $_POST['maps_material_subject'] ) ? absint( $_POST['maps_material_subject'] ) : 0;
            $file_url   = isset( $_POST['maps_material_file'] ) ? esc_url_raw( wp_unslash( $_POST['maps_material_file'] ) ) : '';

            if ( empty( $title ) || empty( $subject_id ) || empty( $file_url ) ) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'maps_study_materials';

            $wpdb->insert(
                $table,
                array(
                    'title'       => $title,
                    'subject_id'  => $subject_id,
                    'file_url'    => $file_url,
                    'upload_date' => current_time( 'mysql' ),
                ),
                array( '%s', '%d', '%s', '%s' )
            );
        }

        /**
         * Render attendance approval page.
         */
        public static function render_approve_attendance_page() {
            if ( isset( $_POST['maps_approve_attendance_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maps_approve_attendance_nonce'] ) ), 'maps_approve_attendance' ) ) {
                self::handle_approve_attendance();
            }

            global $wpdb;
            $attendance_table = $wpdb->prefix . 'maps_teacher_attendance';
            $teacher_subjects_table = $wpdb->prefix . 'maps_teacher_subjects';
            $subjects_table = $wpdb->prefix . 'maps_subjects';

            $query = $wpdb->prepare(
                "SELECT a.*, u.display_name AS teacher_name, s.subject_name, s.class_name
                FROM {$attendance_table} a
                JOIN {$subjects_table} s ON a.subject_id = s.id
                JOIN {$wpdb->users} u ON a.teacher_wp_id = u.ID
                WHERE a.approval_status = %s
                ORDER BY a.submission_time DESC",
                'pending'
            );

            $records = $wpdb->get_results( $query );

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Approve Attendance', 'maps-coaching-toolkit' ) . '</h1>';

            if ( empty( $records ) ) {
                echo '<p>' . esc_html__( 'No pending attendance records.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="post">';
            wp_nonce_field( 'maps_approve_attendance', 'maps_approve_attendance_nonce' );
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr><th>' . esc_html__( 'Teacher', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class Date', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Submitted', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Action', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
            foreach ( $records as $record ) {
                echo '<tr>';
                echo '<td>' . esc_html( $record->teacher_name ) . '</td>';
                echo '<td>' . esc_html( $record->subject_name ) . '</td>';
                echo '<td>' . esc_html( $record->class_name ) . '</td>';
                echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $record->class_date ) ) ) . '</td>';
                echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record->submission_time ) ) ) . '</td>';
                echo '<td>';
                echo '<button class="button button-primary" name="maps_approve_id" value="' . esc_attr( $record->id ) . '">' . esc_html__( 'Approve', 'maps-coaching-toolkit' ) . '</button>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</form>';
            echo '</div>';
        }

        /**
         * Handle attendance approval submissions.
         */
        private static function handle_approve_attendance() {
            if ( ! current_user_can( 'manage_options' ) ) {
                return;
            }

            $record_id = isset( $_POST['maps_approve_id'] ) ? absint( $_POST['maps_approve_id'] ) : 0;

            if ( ! $record_id ) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'maps_teacher_attendance';

            $wpdb->update(
                $table,
                array( 'approval_status' => 'approved' ),
                array( 'id' => $record_id ),
                array( '%s' ),
                array( '%d' )
            );
        }

        /**
         * Render the teacher dashboard shortcode.
         */
        public static function render_teacher_dashboard() {
            if ( ! is_user_logged_in() ) {
                return '<p>' . esc_html__( 'You must be logged in to view this dashboard.', 'maps-coaching-toolkit' ) . '</p>';
            }

            $user = wp_get_current_user();
            if ( ! in_array( 'teacher', (array) $user->roles, true ) ) {
                return '<p>' . esc_html__( 'This dashboard is only available to teachers.', 'maps-coaching-toolkit' ) . '</p>';
            }

            global $wpdb;

            $subject_ids = self::get_user_assigned_subjects( $user->ID, 'teacher' );
            if ( empty( $subject_ids ) ) {
                $subjects = array();
            } else {
                $placeholders = implode( ',', array_fill( 0, count( $subject_ids ), '%d' ) );
                $subjects = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}maps_subjects WHERE id IN ($placeholders)", $subject_ids ) );
            }

            $pending_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}maps_teacher_attendance WHERE teacher_wp_id = %d AND approval_status = %s", $user->ID, 'pending' ) );

            $current_month_start = date( 'Y-m-01', current_time( 'timestamp' ) );
            $current_month_end   = date( 'Y-m-t', current_time( 'timestamp' ) );

            $monthly_records = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.*, s.subject_name, s.class_name
                FROM {$wpdb->prefix}maps_teacher_attendance a
                JOIN {$wpdb->prefix}maps_subjects s ON a.subject_id = s.id
                WHERE a.teacher_wp_id = %d
                AND a.approval_status = %s
                AND a.class_date BETWEEN %s AND %s
                ORDER BY a.class_date ASC",
                $user->ID,
                'approved',
                $current_month_start,
                $current_month_end
            ) );

            $total_classes = count( $monthly_records );

            ob_start();

            echo '<div class="maps-dashboard maps-dashboard--teacher">';
            echo '<h2>' . sprintf( esc_html__( 'Welcome, %s', 'maps-coaching-toolkit' ), esc_html( $user->display_name ) ) . '</h2>';

            if ( (int) $pending_count > 0 ) {
                echo '<div class="maps-notice maps-notice--pending">' . sprintf( esc_html__( 'You have %d classes pending for approval', 'maps-coaching-toolkit' ), (int) $pending_count ) . '</div>';
            }

            if ( isset( $_GET['maps_attendance_submitted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                echo '<div class="maps-notice maps-notice--success">' . esc_html__( 'Attendance submitted successfully and awaiting approval.', 'maps-coaching-toolkit' ) . '</div>';
            }

            echo '<div class="maps-card maps-card--form">';
            echo '<h3>' . esc_html__( 'Mark Attendance', 'maps-coaching-toolkit' ) . '</h3>';
            if ( empty( $subjects ) ) {
                echo '<p>' . esc_html__( 'No subjects assigned to you yet.', 'maps-coaching-toolkit' ) . '</p>';
            } else {
                echo '<form method="post">';
                wp_nonce_field( 'maps_teacher_attendance', 'maps_teacher_attendance_nonce' );
                echo '<p><label for="maps_attendance_subject">' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</label><br/>';
                echo '<select id="maps_attendance_subject" name="maps_attendance_subject" required>';
                echo '<option value="">' . esc_html__( 'Select Subject', 'maps-coaching-toolkit' ) . '</option>';
                foreach ( $subjects as $subject ) {
                    echo '<option value="' . esc_attr( $subject->id ) . '">' . esc_html( $subject->subject_name . ' (' . $subject->class_name . ')' ) . '</option>';
                }
                echo '</select></p>';

                echo '<p><label for="maps_attendance_date">' . esc_html__( 'Class Date', 'maps-coaching-toolkit' ) . '</label><br/>';
                echo '<input type="date" id="maps_attendance_date" name="maps_attendance_date" required></p>';

                echo '<p><label for="maps_attendance_status">' . esc_html__( 'Status', 'maps-coaching-toolkit' ) . '</label><br/>';
                echo '<select id="maps_attendance_status" name="maps_attendance_status" required>';
                echo '<option value="present">' . esc_html__( 'Present', 'maps-coaching-toolkit' ) . '</option>';
                echo '<option value="absent">' . esc_html__( 'Absent', 'maps-coaching-toolkit' ) . '</option>';
                echo '</select></p>';

                echo '<p><button type="submit" class="maps-button">' . esc_html__( 'Mark Complete', 'maps-coaching-toolkit' ) . '</button></p>';
                echo '</form>';
            }
            echo '</div>';

            echo '<div class="maps-card maps-card--report">';
            echo '<h3>' . esc_html__( 'Monthly Report', 'maps-coaching-toolkit' ) . '</h3>';
            if ( empty( $monthly_records ) ) {
                echo '<p>' . esc_html__( 'No approved attendance records for this month yet.', 'maps-coaching-toolkit' ) . '</p>';
            } else {
                echo '<table class="maps-table">';
                echo '<thead><tr><th>' . esc_html__( 'Date', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Status', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
                foreach ( $monthly_records as $record ) {
                    echo '<tr>';
                    echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $record->class_date ) ) ) . '</td>';
                    echo '<td>' . esc_html( $record->subject_name ) . '</td>';
                    echo '<td>' . esc_html( $record->class_name ) . '</td>';
                    echo '<td>' . esc_html( ucfirst( $record->attendance_status ) ) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '<p class="maps-total">' . sprintf( esc_html__( 'Total classes completed this month: %d', 'maps-coaching-toolkit' ), (int) $total_classes ) . '</p>';
            }
            echo '</div>';

            echo '</div>';

            self::enqueue_frontend_assets();

            return ob_get_clean();
        }

        /**
         * Render the student dashboard shortcode.
         */
        public static function render_student_dashboard() {
            if ( ! is_user_logged_in() ) {
                return '<p>' . esc_html__( 'You must be logged in to view this dashboard.', 'maps-coaching-toolkit' ) . '</p>';
            }

            $user = wp_get_current_user();
            if ( ! in_array( 'student', (array) $user->roles, true ) ) {
                return '<p>' . esc_html__( 'This dashboard is only available to students.', 'maps-coaching-toolkit' ) . '</p>';
            }

            global $wpdb;

            $subject_ids = self::get_user_assigned_subjects( $user->ID, 'student' );

            if ( empty( $subject_ids ) ) {
                $subjects = array();
            } else {
                $placeholders = implode( ',', array_fill( 0, count( $subject_ids ), '%d' ) );
                $subjects = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}maps_subjects WHERE id IN ($placeholders)", $subject_ids ) );
            }

            $class_routines = array();
            if ( ! empty( $subjects ) ) {
                $class_names = array_unique( wp_list_pluck( $subjects, 'class_name' ) );
                if ( ! empty( $class_names ) ) {
                    $routine_table = $wpdb->prefix . 'maps_routine_images';
                    $class_placeholders = implode( ',', array_fill( 0, count( $class_names ), '%s' ) );
                    $routine_query = $wpdb->prepare( "SELECT class_name, image_url FROM {$routine_table} WHERE class_name IN ($class_placeholders)", $class_names );
                    $routine_results = $wpdb->get_results( $routine_query );
                    foreach ( $routine_results as $routine_result ) {
                        $class_routines[ $routine_result->class_name ] = $routine_result->image_url;
                    }
                }
            }

            $materials_map = array();
            if ( ! empty( $subject_ids ) ) {
                $materials_table = $wpdb->prefix . 'maps_study_materials';
                $placeholders = implode( ',', array_fill( 0, count( $subject_ids ), '%d' ) );
                $materials = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$materials_table} WHERE subject_id IN ($placeholders) ORDER BY upload_date DESC", $subject_ids ) );
                foreach ( $materials as $material ) {
                    $materials_map[ $material->subject_id ][] = $material;
                }
            }

            $subject_count = count( $subject_ids );
            $monthly_bill = $subject_count * 800;

            ob_start();

            echo '<div class="maps-dashboard maps-dashboard--student">';
            echo '<h2>' . sprintf( esc_html__( 'Welcome, %s', 'maps-coaching-toolkit' ), esc_html( $user->display_name ) ) . '</h2>';

            echo '<div class="maps-card maps-card--routine">';
            echo '<h3>' . esc_html__( 'Class Routine', 'maps-coaching-toolkit' ) . '</h3>';
            if ( ! empty( $class_routines ) ) {
                foreach ( $class_routines as $class_name => $routine_url ) {
                    echo '<div class="maps-routine-entry">';
                    if ( count( $class_routines ) > 1 ) {
                        echo '<h4>' . esc_html( $class_name ) . '</h4>';
                    }
                    echo '<div class="maps-routine-image"><img src="' . esc_url( $routine_url ) . '" alt="' . esc_attr__( 'Class Routine', 'maps-coaching-toolkit' ) . '" style="max-width:100%;height:auto;" /></div>';
                    echo '</div>';
                }
            } else {
                echo '<p>' . esc_html__( 'Routine for your class has not been uploaded yet.', 'maps-coaching-toolkit' ) . '</p>';
            }
            echo '</div>';

            echo '<div class="maps-card maps-card--subjects">';
            echo '<h3>' . esc_html__( 'Subjects & Materials', 'maps-coaching-toolkit' ) . '</h3>';
            if ( empty( $subjects ) ) {
                echo '<p>' . esc_html__( 'You are not enrolled in any subjects yet.', 'maps-coaching-toolkit' ) . '</p>';
            } else {
                echo '<div class="maps-accordion">';
                foreach ( $subjects as $index => $subject ) {
                    $subject_id = (int) $subject->id;
                    echo '<div class="maps-accordion-item">';
                    echo '<button class="maps-accordion-header" type="button" data-target="maps-accordion-content-' . esc_attr( $index ) . '">' . esc_html( $subject->subject_name ) . '</button>';
                    echo '<div class="maps-accordion-content" id="maps-accordion-content-' . esc_attr( $index ) . '" style="display:none;">';
                    if ( ! empty( $materials_map[ $subject_id ] ) ) {
                        echo '<ul class="maps-materials">';
                        foreach ( $materials_map[ $subject_id ] as $material ) {
                            echo '<li><a href="' . esc_url( $material->file_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $material->title ) . '</a></li>';
                        }
                        echo '</ul>';
                    } else {
                        echo '<p>' . esc_html__( 'No study materials uploaded yet.', 'maps-coaching-toolkit' ) . '</p>';
                    }
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            }
            echo '</div>';

            echo '<div class="maps-card maps-card--billing">';
            echo '<h3>' . esc_html__( 'Monthly Bill', 'maps-coaching-toolkit' ) . '</h3>';
            echo '<p>' . sprintf( esc_html__( 'Total Subjects: %d', 'maps-coaching-toolkit' ), (int) $subject_count ) . '</p>';
            echo '<p>' . sprintf( esc_html__( 'Your bill for this month: %s Taka', 'maps-coaching-toolkit' ), esc_html( number_format_i18n( $monthly_bill ) ) ) . '</p>';
            echo '<p><a class="maps-button" href="#">' . esc_html__( 'Pay Bill', 'maps-coaching-toolkit' ) . '</a></p>';
            echo '</div>';

            echo '</div>';

            self::enqueue_frontend_assets();

            return ob_get_clean();
        }

        /**
         * Enqueue simple frontend styles/scripts via inline output.
         */
        private static function enqueue_frontend_assets() {
            static $enqueued = false;
            if ( $enqueued ) {
                return;
            }
            $enqueued = true;

            add_action( 'wp_footer', array( __CLASS__, 'output_frontend_assets' ) );
        }

        /**
         * Output inline styles/scripts for dashboard accordions.
         */
        public static function output_frontend_assets() {
            echo '<style>
                .maps-dashboard { max-width: 900px; margin: 0 auto; font-family: inherit; }
                .maps-dashboard h2 { margin-bottom: 1.5em; }
                .maps-card { background: #fff; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px; border-radius: 4px; }
                .maps-notice { padding: 12px 15px; border-radius: 4px; margin-bottom: 20px; }
                .maps-notice--pending { background: #fff3cd; border: 1px solid #ffeeba; }
                .maps-notice--success { background: #d4edda; border: 1px solid #c3e6cb; }
                .maps-table { width: 100%; border-collapse: collapse; }
                .maps-table th, .maps-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .maps-total { font-weight: bold; margin-top: 10px; }
                .maps-button { display: inline-block; background: #0073aa; color: #fff; padding: 8px 16px; border-radius: 4px; text-decoration: none; }
                .maps-button:hover { background: #005177; color: #fff; }
                .maps-accordion-item { border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; overflow: hidden; }
                .maps-accordion-header { width: 100%; background: #f1f1f1; border: none; text-align: left; padding: 12px 16px; font-size: 16px; cursor: pointer; }
                .maps-accordion-content { padding: 15px 16px; background: #fff; }
                .maps-materials { list-style: disc; padding-left: 20px; }
            </style>';

            echo '<script>
                (function(){
                    document.addEventListener("click", function(event){
                        if(event.target.classList.contains("maps-accordion-header")){
                            var targetId = event.target.getAttribute("data-target");
                            var content = document.getElementById(targetId);
                            if(content){
                                var isVisible = content.style.display === "block";
                                content.style.display = isVisible ? "none" : "block";
                            }
                        }
                    });
                })();
            </script>';
        }

    }

    MAPS_Coaching_Toolkit::init();
}

