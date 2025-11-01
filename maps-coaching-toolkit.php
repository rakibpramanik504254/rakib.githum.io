<?php
/**
 * Plugin Name: MAPS Coaching Toolkit
 * Description: Standalone management toolkit for MAPS Coaching to manage subjects, enrollments, routines, attendance, and study materials.
 * Version: 1.0.0
 * Author: Rakib & GPT-5 Codex
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'MAPS_Coaching_Toolkit', false ) ) {

    final class MAPS_Coaching_Toolkit {

        /**
         * Singleton instance.
         *
         * @var MAPS_Coaching_Toolkit|null
         */
        private static $instance = null;

        /**
         * Flag to prevent duplicate frontend assets.
         *
         * @var bool
         */
        private $frontend_assets_hooked = false;

        /**
         * Get singleton instance.
         *
         * @return MAPS_Coaching_Toolkit
         */
        public static function instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * Constructor.
         */
        private function __construct() {
            add_action( 'admin_menu', array( $this, 'register_admin_menus' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
            add_action( 'init', array( $this, 'register_shortcodes' ) );
            add_action( 'init', array( $this, 'handle_teacher_attendance_form' ) );
            add_filter( 'login_redirect', array( $this, 'handle_login_redirect' ), 10, 3 );
            add_action( 'admin_init', array( $this, 'restrict_dashboard_access' ) );
            add_action( 'after_setup_theme', array( $this, 'maybe_hide_admin_bar' ) );
        }

        /**
         * Register activation hook (static context).
         */
        public static function activate() {
            global $wpdb;

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            $charset_collate = $wpdb->get_charset_collate();

            $tables = array(
                'maps_subjects'            => "CREATE TABLE {$wpdb->prefix}maps_subjects (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    subject_name varchar(255) NOT NULL,
                    class_name varchar(255) NOT NULL,
                    PRIMARY KEY (id)
                ) $charset_collate;",
                'maps_student_enrollments' => "CREATE TABLE {$wpdb->prefix}maps_student_enrollments (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    student_wp_id bigint(20) NOT NULL,
                    subject_id mediumint(9) NOT NULL,
                    PRIMARY KEY (id),
                    KEY student_wp_id (student_wp_id),
                    KEY subject_id (subject_id)
                ) $charset_collate;",
                'maps_teacher_subjects'    => "CREATE TABLE {$wpdb->prefix}maps_teacher_subjects (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    teacher_wp_id bigint(20) NOT NULL,
                    subject_id mediumint(9) NOT NULL,
                    PRIMARY KEY (id),
                    KEY teacher_wp_id (teacher_wp_id),
                    KEY subject_id (subject_id)
                ) $charset_collate;",
                'maps_teacher_attendance'  => "CREATE TABLE {$wpdb->prefix}maps_teacher_attendance (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    teacher_wp_id bigint(20) NOT NULL,
                    subject_id mediumint(9) NOT NULL,
                    class_date date NOT NULL,
                    attendance_status varchar(20) NOT NULL,
                    submission_time datetime NOT NULL,
                    approval_status varchar(20) NOT NULL DEFAULT 'pending',
                    PRIMARY KEY (id),
                    KEY teacher_wp_id (teacher_wp_id),
                    KEY subject_id (subject_id),
                    KEY approval_status (approval_status)
                ) $charset_collate;",
                'maps_routine_images'      => "CREATE TABLE {$wpdb->prefix}maps_routine_images (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    class_name varchar(255) NOT NULL,
                    image_url varchar(255) NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY class_name (class_name)
                ) $charset_collate;",
                'maps_study_materials'     => "CREATE TABLE {$wpdb->prefix}maps_study_materials (
                    id mediumint(9) NOT NULL AUTO_INCREMENT,
                    title text NOT NULL,
                    subject_id mediumint(9) NOT NULL,
                    file_url varchar(255) NOT NULL,
                    upload_date datetime NOT NULL,
                    PRIMARY KEY (id),
                    KEY subject_id (subject_id)
                ) $charset_collate;",
            );

            foreach ( $tables as $sql ) {
                dbDelta( $sql );
            }
        }

        /* ===================================================== */
        /* ===            ADMIN AREA RENDERING               === */
        /* ===================================================== */

        /**
         * Register main menu and submenus.
         */
        public function register_admin_menus() {
            add_menu_page(
                __( 'MAPS Toolkit', 'maps-coaching-toolkit' ),
                __( 'MAPS Toolkit', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-subjects',
                array( $this, 'render_subjects_page' ),
                'dashicons-welcome-learn-more',
                30
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Manage Subjects', 'maps-coaching-toolkit' ),
                __( 'Manage Subjects', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-subjects',
                array( $this, 'render_subjects_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Assign to Student', 'maps-coaching-toolkit' ),
                __( 'Assign to Student', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-assign-student',
                array( $this, 'render_assign_student_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Assign to Teacher', 'maps-coaching-toolkit' ),
                __( 'Assign to Teacher', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-assign-teacher',
                array( $this, 'render_assign_teacher_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Upload Routine', 'maps-coaching-toolkit' ),
                __( 'Upload Routine', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-routines',
                array( $this, 'render_routines_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Upload Materials', 'maps-coaching-toolkit' ),
                __( 'Upload Materials', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-materials',
                array( $this, 'render_materials_page' )
            );

            add_submenu_page(
                'maps-toolkit-subjects',
                __( 'Approve Attendance', 'maps-coaching-toolkit' ),
                __( 'Approve Attendance', 'maps-coaching-toolkit' ),
                'manage_options',
                'maps-toolkit-approve-attendance',
                array( $this, 'render_attendance_approval_page' )
            );
        }

        /**
         * Load media library and inline JS for admin pages that need it.
         *
         * @param string $hook_suffix Current admin page hook.
         */
        public function enqueue_admin_assets( $hook_suffix ) {
            $screen = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            $needs_media = array( 'maps-toolkit-routines', 'maps-toolkit-materials' );

            if ( in_array( $screen, $needs_media, true ) ) {
                wp_enqueue_media();

                wp_register_script( 'maps-toolkit-admin', '', array( 'jquery' ), '1.0.0', true );
                wp_enqueue_script( 'maps-toolkit-admin' );

                $inline_js = "jQuery(function($){\n\tfunction initMediaFrame(buttonSelector, inputSelector, previewSelector, title){\n\t\tvar frame;\n\t\t$(document).on('click', buttonSelector, function(event){\n\t\t\tevent.preventDefault();\n\t\t\tif(frame){\n\t\t\t\tframe.open();\n\t\t\t\treturn;\n\t\t\t}\n\t\t\tframe = wp.media({\n\t\t\t\ttitle: title,\n\t\t\t\tbutton: { text: title },\n\t\t\t\tmultiple: false\n\t\t\t});\n\t\t\tframe.on('select', function(){\n\t\t\t\tvar attachment = frame.state().get('selection').first().toJSON();\n\t\t\t\t$(inputSelector).val(attachment.url);\n\t\t\t\tif(previewSelector){\n\t\t\t\t\tvar previewHtml = (attachment.type === 'image')\n\t\t\t\t\t\t? '<img src="' + attachment.url + '" style="max-width:200px;height:auto;" />'\n\t\t\t\t\t\t: '<a href="' + attachment.url + '" target="_blank" rel="noopener">' + attachment.filename + '</a>';\n\t\t\t\t\t$(previewSelector).html(previewHtml);\n\t\t\t\t}\n\t\t\t});\n\t\t\tframe.open();\n\t\t});\n\t}\n\n\tinitMediaFrame('#maps_routine_upload_button', '#maps_routine_image', '#maps_routine_preview', '" . esc_js( __( 'Select Routine', 'maps-coaching-toolkit' ) ) . "');\n\tinitMediaFrame('#maps_material_upload_button', '#maps_material_file', '#maps_material_preview', '" . esc_js( __( 'Select File', 'maps-coaching-toolkit' ) ) . "');\n});";

                wp_add_inline_script( 'maps-toolkit-admin', $inline_js );
            }
        }

        /**
         * Render Manage Subjects page.
         */
        public function render_subjects_page() {
            $this->guard_admin_capability();

            if ( isset( $_POST['maps_subject_nonce'] ) ) {
                $this->handle_subject_submission();
            }

            global $wpdb;
            $table    = $wpdb->prefix . 'maps_subjects';
            $subjects = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY class_name, subject_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Manage Subjects', 'maps-coaching-toolkit' ) . '</h1>';

            if ( ! empty( $_GET['maps_status'] ) && 'subject_added' === $_GET['maps_status'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Subject saved successfully.', 'maps-coaching-toolkit' ) . '</p></div>';
            }

            echo '<form method="post" class="maps-form">';
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
                echo '<table class="widefat striped">';
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
                echo '<p>' . esc_html__( 'No subjects found yet.', 'maps-coaching-toolkit' ) . '</p>';
            }

            echo '</div>';
        }

        /**
         * Save a subject.
         */
        private function handle_subject_submission() {
            $this->guard_admin_capability();

            check_admin_referer( 'maps_add_subject', 'maps_subject_nonce' );

            $subject_name = isset( $_POST['maps_subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_subject_name'] ) ) : '';
            $class_name   = isset( $_POST['maps_class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_class_name'] ) ) : '';

            if ( empty( $subject_name ) || empty( $class_name ) ) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'maps_subjects';
            $wpdb->insert( $table, array( 'subject_name' => $subject_name, 'class_name' => $class_name ), array( '%s', '%s' ) );

            wp_safe_redirect( add_query_arg( 'maps_status', 'subject_added', admin_url( 'admin.php?page=maps-toolkit-subjects' ) ) );
            exit;
        }

        /**
         * Render assign-student page.
         */
        public function render_assign_student_page() {
            $this->render_assignment_page( 'student' );
        }

        /**
         * Render assign-teacher page.
         */
        public function render_assign_teacher_page() {
            $this->render_assignment_page( 'teacher' );
        }

        /**
         * Shared assignment page renderer.
         *
         * @param string $role Role slug.
         */
        private function render_assignment_page( $role ) {
            $this->guard_admin_capability();

            $role_label = ( 'teacher' === $role ) ? __( 'Teacher', 'maps-coaching-toolkit' ) : __( 'Student', 'maps-coaching-toolkit' );

            if ( isset( $_POST['maps_assign_nonce'] ) ) {
                $this->handle_assignment_submission( $role );
            }

            $selected_user = isset( $_REQUEST['maps_user_id'] ) ? absint( wp_unslash( $_REQUEST['maps_user_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

            $users = get_users(
                array(
                    'role'    => $role,
                    'orderby' => 'display_name',
                    'order'   => 'ASC',
                )
            );

            global $wpdb;
            $subjects = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}maps_subjects ORDER BY class_name, subject_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            echo '<div class="wrap">';
            echo '<h1>' . sprintf( esc_html__( 'Assign Subjects to %s', 'maps-coaching-toolkit' ), esc_html( $role_label ) ) . '</h1>';

            if ( empty( $users ) ) {
                echo '<p>' . esc_html__( 'No users available for this role.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            if ( ! empty( $_GET['maps_status'] ) && 'assign_saved' === $_GET['maps_status'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Assignments saved.', 'maps-coaching-toolkit' ) . '</p></div>';
            }

            echo '<form method="post" class="maps-form">';
            wp_nonce_field( 'maps_assign_subjects_' . $role, 'maps_assign_nonce' );

            echo '<table class="form-table">';
            echo '<tr><th><label for="maps_user_id">' . esc_html( $role_label ) . '</label></th><td>';
            echo '<select id="maps_user_id" name="maps_user_id" onchange="this.form.submit();">';
            echo '<option value="">' . esc_html__( 'Select a user', 'maps-coaching-toolkit' ) . '</option>';
            foreach ( $users as $user ) {
                printf(
                    '<option value="%1$d" %2$s>%3$s</option>',
                    absint( $user->ID ),
                    selected( $selected_user, $user->ID, false ),
                    esc_html( $user->display_name )
                );
            }
            echo '</select>';
            echo '</td></tr>';
            echo '</table>';

            if ( $selected_user && ! empty( $subjects ) ) {
                $assigned_ids = $this->get_user_subject_ids( $selected_user, $role );
                echo '<input type="hidden" name="maps_selected_user" value="' . esc_attr( $selected_user ) . '">';
                echo '<h2>' . esc_html__( 'Subjects', 'maps-coaching-toolkit' ) . '</h2>';
                echo '<table class="widefat striped">';
                echo '<thead><tr><th>' . esc_html__( 'Assign', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
                foreach ( $subjects as $subject ) {
                    $checked = in_array( (int) $subject->id, $assigned_ids, true ) ? 'checked' : '';
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
         * Save assignment updates.
         *
         * @param string $role Role slug.
         */
        private function handle_assignment_submission( $role ) {
            $this->guard_admin_capability();

            check_admin_referer( 'maps_assign_subjects_' . $role, 'maps_assign_nonce' );

            $user_id = isset( $_POST['maps_selected_user'] ) ? absint( $_POST['maps_selected_user'] ) : 0;
            if ( ! $user_id ) {
                return;
            }

            $subject_ids = isset( $_POST['maps_subject_ids'] ) ? array_map( 'absint', (array) $_POST['maps_subject_ids'] ) : array();

            global $wpdb;

            if ( 'student' === $role ) {
                $table = $wpdb->prefix . 'maps_student_enrollments';
                $wpdb->delete( $table, array( 'student_wp_id' => $user_id ), array( '%d' ) );
                foreach ( $subject_ids as $subject_id ) {
                    $wpdb->insert( $table, array( 'student_wp_id' => $user_id, 'subject_id' => $subject_id ), array( '%d', '%d' ) );
                }
            } else {
                $table = $wpdb->prefix . 'maps_teacher_subjects';
                $wpdb->delete( $table, array( 'teacher_wp_id' => $user_id ), array( '%d' ) );
                foreach ( $subject_ids as $subject_id ) {
                    $wpdb->insert( $table, array( 'teacher_wp_id' => $user_id, 'subject_id' => $subject_id ), array( '%d', '%d' ) );
                }
            }

            $redirect = add_query_arg(
                array(
                    'page'        => ( 'student' === $role ) ? 'maps-toolkit-assign-student' : 'maps-toolkit-assign-teacher',
                    'maps_user_id'=> $user_id,
                    'maps_status' => 'assign_saved',
                ),
                admin_url( 'admin.php' )
            );

            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Render routines page.
         */
        public function render_routines_page() {
            $this->guard_admin_capability();

            if ( isset( $_POST['maps_routine_nonce'] ) ) {
                $this->handle_routine_submission();
            }

            global $wpdb;
            $subjects_table = $wpdb->prefix . 'maps_subjects';
            $routines_table = $wpdb->prefix . 'maps_routine_images';

            $classes  = $wpdb->get_col( "SELECT DISTINCT class_name FROM {$subjects_table} ORDER BY class_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $routines = $wpdb->get_results( "SELECT class_name, image_url FROM {$routines_table} ORDER BY class_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $routine_map = array();
            foreach ( $routines as $routine ) {
                $routine_map[ $routine->class_name ] = $routine->image_url;
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Upload Class Routine', 'maps-coaching-toolkit' ) . '</h1>';

            if ( ! empty( $_GET['maps_status'] ) && 'routine_saved' === $_GET['maps_status'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Routine saved successfully.', 'maps-coaching-toolkit' ) . '</p></div>';
            }

            if ( empty( $classes ) ) {
                echo '<p>' . esc_html__( 'No classes found. Please add subjects first.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="post" class="maps-form">';
            wp_nonce_field( 'maps_upload_routine', 'maps_routine_nonce' );
            echo '<table class="form-table">';
            echo '<tr><th><label for="maps_routine_class">' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</label></th><td>';
            echo '<select id="maps_routine_class" name="maps_routine_class" required>';
            echo '<option value="">' . esc_html__( 'Select Class', 'maps-coaching-toolkit' ) . '</option>';
            foreach ( $classes as $class_name ) {
                echo '<option value="' . esc_attr( $class_name ) . '">' . esc_html( $class_name ) . '</option>';
            }
            echo '</select></td></tr>';

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
                echo '<table class="widefat striped">';
                echo '<thead><tr><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Routine', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
                foreach ( $routine_map as $class_name => $image_url ) {
                    echo '<tr><td>' . esc_html( $class_name ) . '</td><td><a href="' . esc_url( $image_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View Routine', 'maps-coaching-toolkit' ) . '</a></td></tr>';
                }
                echo '</tbody></table>';
            }

            echo '</div>';
        }

        /**
         * Save routine entry.
         */
        private function handle_routine_submission() {
            $this->guard_admin_capability();

            check_admin_referer( 'maps_upload_routine', 'maps_routine_nonce' );

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

            wp_safe_redirect( add_query_arg( 'maps_status', 'routine_saved', admin_url( 'admin.php?page=maps-toolkit-routines' ) ) );
            exit;
        }

        /**
         * Render materials page.
         */
        public function render_materials_page() {
            $this->guard_admin_capability();

            if ( isset( $_POST['maps_material_nonce'] ) ) {
                $this->handle_material_submission();
            }

            global $wpdb;
            $subjects_table  = $wpdb->prefix . 'maps_subjects';
            $materials_table = $wpdb->prefix . 'maps_study_materials';

            $subjects = $wpdb->get_results( "SELECT * FROM {$subjects_table} ORDER BY class_name, subject_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Upload Study Materials', 'maps-coaching-toolkit' ) . '</h1>';

            if ( ! empty( $_GET['maps_status'] ) && 'material_saved' === $_GET['maps_status'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Study material saved.', 'maps-coaching-toolkit' ) . '</p></div>';
            }

            if ( empty( $subjects ) ) {
                echo '<p>' . esc_html__( 'No subjects available. Please add subjects first.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="post" class="maps-form">';
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

            $materials = $wpdb->get_results(
                "SELECT m.*, s.subject_name, s.class_name
                FROM {$materials_table} m
                JOIN {$subjects_table} s ON s.id = m.subject_id
                ORDER BY m.upload_date DESC"
            ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

            echo '<h2>' . esc_html__( 'Existing Materials', 'maps-coaching-toolkit' ) . '</h2>';
            if ( ! empty( $materials ) ) {
                echo '<table class="widefat striped">';
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
            } else {
                echo '<p>' . esc_html__( 'No study materials uploaded yet.', 'maps-coaching-toolkit' ) . '</p>';
            }

            echo '</div>';
        }

        /**
         * Save study material.
         */
        private function handle_material_submission() {
            $this->guard_admin_capability();

            check_admin_referer( 'maps_upload_material', 'maps_material_nonce' );

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

            wp_safe_redirect( add_query_arg( 'maps_status', 'material_saved', admin_url( 'admin.php?page=maps-toolkit-materials' ) ) );
            exit;
        }

        /**
         * Render attendance approval page.
         */
        public function render_attendance_approval_page() {
            $this->guard_admin_capability();

            if ( isset( $_POST['maps_approve_attendance_nonce'] ) ) {
                $this->handle_attendance_approval();
            }

            global $wpdb;

            $attendance_table = $wpdb->prefix . 'maps_teacher_attendance';
            $subjects_table   = $wpdb->prefix . 'maps_subjects';

            $records = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.id, a.class_date, a.submission_time, u.display_name AS teacher_name, s.subject_name, s.class_name
                FROM {$attendance_table} a
                INNER JOIN {$wpdb->users} u ON u.ID = a.teacher_wp_id
                INNER JOIN {$subjects_table} s ON s.id = a.subject_id
                WHERE a.approval_status = %s
                ORDER BY a.submission_time DESC",
                'pending'
            ) );

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__( 'Approve Attendance', 'maps-coaching-toolkit' ) . '</h1>';

            if ( ! empty( $_GET['maps_status'] ) && 'attendance_approved' === $_GET['maps_status'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                echo '<div class="notice notice-success"><p>' . esc_html__( 'Attendance approved.', 'maps-coaching-toolkit' ) . '</p></div>';
            }

            if ( empty( $records ) ) {
                echo '<p>' . esc_html__( 'No pending attendance found.', 'maps-coaching-toolkit' ) . '</p>';
                echo '</div>';
                return;
            }

            echo '<form method="post">';
            wp_nonce_field( 'maps_approve_attendance', 'maps_approve_attendance_nonce' );
            echo '<table class="widefat striped">';
            echo '<thead><tr><th>' . esc_html__( 'Teacher', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Class Date', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Submitted', 'maps-coaching-toolkit' ) . '</th><th>' . esc_html__( 'Action', 'maps-coaching-toolkit' ) . '</th></tr></thead><tbody>';
            foreach ( $records as $record ) {
                echo '<tr>';
                echo '<td>' . esc_html( $record->teacher_name ) . '</td>';
                echo '<td>' . esc_html( $record->subject_name ) . '</td>';
                echo '<td>' . esc_html( $record->class_name ) . '</td>';
                echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $record->class_date ) ) ) . '</td>';
                echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $record->submission_time ) ) ) . '</td>';
                echo '<td><button class="button button-primary" name="maps_approve_id" value="' . esc_attr( $record->id ) . '">' . esc_html__( 'Approve', 'maps-coaching-toolkit' ) . '</button></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</form>';
            echo '</div>';
        }

        /**
         * Process attendance approval.
         */
        private function handle_attendance_approval() {
            $this->guard_admin_capability();

            check_admin_referer( 'maps_approve_attendance', 'maps_approve_attendance_nonce' );

            $record_id = isset( $_POST['maps_approve_id'] ) ? absint( $_POST['maps_approve_id'] ) : 0;
            if ( ! $record_id ) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'maps_teacher_attendance';
            $wpdb->update( $table, array( 'approval_status' => 'approved' ), array( 'id' => $record_id ), array( '%s' ), array( '%d' ) );

            wp_safe_redirect( add_query_arg( 'maps_status', 'attendance_approved', admin_url( 'admin.php?page=maps-toolkit-approve-attendance' ) ) );
            exit;
        }

        /* ===================================================== */
        /* ===                SHORTCODES                      === */
        /* ===================================================== */

        /**
         * Register shortcodes.
         */
        public function register_shortcodes() {
            add_shortcode( 'maps_teacher_dashboard', array( $this, 'render_teacher_dashboard' ) );
            add_shortcode( 'maps_student_dashboard', array( $this, 'render_student_dashboard' ) );
        }

        /**
         * Render teacher dashboard.
         *
         * @return string
         */
        public function render_teacher_dashboard() {
            if ( ! is_user_logged_in() ) {
                return '<p>' . esc_html__( 'You must be logged in to view this dashboard.', 'maps-coaching-toolkit' ) . '</p>';
            }

            $user = wp_get_current_user();
            if ( ! in_array( 'teacher', (array) $user->roles, true ) ) {
                return '<p>' . esc_html__( 'This dashboard is only available to teachers.', 'maps-coaching-toolkit' ) . '</p>';
            }

            global $wpdb;

            $subject_ids = $this->get_user_subject_ids( $user->ID, 'teacher' );
            $subjects    = array();
            if ( ! empty( $subject_ids ) ) {
                $placeholders = implode( ', ', array_fill( 0, count( $subject_ids ), '%d' ) );
                $sql          = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}maps_subjects WHERE id IN ($placeholders)", $subject_ids );
                $subjects     = $wpdb->get_results( $sql );
            }

            $pending_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}maps_teacher_attendance WHERE teacher_wp_id = %d AND approval_status = %s", $user->ID, 'pending' ) );

            $month_start = date( 'Y-m-01', current_time( 'timestamp' ) );
            $month_end   = date( 'Y-m-t', current_time( 'timestamp' ) );

            $monthly_records = $wpdb->get_results( $wpdb->prepare(
                "SELECT a.class_date, a.attendance_status, s.subject_name, s.class_name
                FROM {$wpdb->prefix}maps_teacher_attendance a
                INNER JOIN {$wpdb->prefix}maps_subjects s ON s.id = a.subject_id
                WHERE a.teacher_wp_id = %d
                  AND a.approval_status = %s
                  AND a.class_date BETWEEN %s AND %s
                ORDER BY a.class_date ASC",
                $user->ID,
                'approved',
                $month_start,
                $month_end
            ) );

            $this->hook_frontend_assets();

            ob_start();

            echo '<div class="maps-dashboard maps-dashboard--teacher">';
            echo '<h2>' . sprintf( esc_html__( 'Welcome, %s', 'maps-coaching-toolkit' ), esc_html( $user->display_name ) ) . '</h2>';

            if ( $pending_count > 0 ) {
                echo '<div class="maps-notice maps-notice--warning">' . sprintf( esc_html__( 'You have %d classes pending for approval.', 'maps-coaching-toolkit' ), $pending_count ) . '</div>';
            }

            if ( isset( $_GET['maps_status'] ) && 'attendance_submitted' === $_GET['maps_status'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                echo '<div class="maps-notice maps-notice--success">' . esc_html__( 'Attendance submitted successfully and awaiting approval.', 'maps-coaching-toolkit' ) . '</div>';
            }

            echo '<div class="maps-card">';
            echo '<h3>' . esc_html__( 'Mark Attendance', 'maps-coaching-toolkit' ) . '</h3>';
            if ( empty( $subjects ) ) {
                echo '<p>' . esc_html__( 'No subjects assigned to you yet.', 'maps-coaching-toolkit' ) . '</p>';
            } else {
                echo '<form method="post" class="maps-form maps-form--attendance">';
                wp_nonce_field( 'maps_teacher_attendance', 'maps_teacher_attendance_nonce' );
                echo '<p><label for="maps_attendance_subject">' . esc_html__( 'Subject', 'maps-coaching-toolkit' ) . '</label><br />';
                echo '<select id="maps_attendance_subject" name="maps_attendance_subject" required>';
                echo '<option value="">' . esc_html__( 'Select Subject', 'maps-coaching-toolkit' ) . '</option>';
                foreach ( $subjects as $subject ) {
                    echo '<option value="' . esc_attr( $subject->id ) . '">' . esc_html( $subject->subject_name . ' (' . $subject->class_name . ')' ) . '</option>';
                }
                echo '</select></p>';

                echo '<p><label for="maps_attendance_date">' . esc_html__( 'Class Date', 'maps-coaching-toolkit' ) . '</label><br />';
                echo '<input type="date" id="maps_attendance_date" name="maps_attendance_date" required></p>';

                echo '<p><label for="maps_attendance_status">' . esc_html__( 'Status', 'maps-coaching-toolkit' ) . '</label><br />';
                echo '<select id="maps_attendance_status" name="maps_attendance_status" required>';
                echo '<option value="present">' . esc_html__( 'Present', 'maps-coaching-toolkit' ) . '</option>';
                echo '<option value="absent">' . esc_html__( 'Absent', 'maps-coaching-toolkit' ) . '</option>';
                echo '</select></p>';

                echo '<p><button type="submit" class="maps-button">' . esc_html__( 'Mark Complete', 'maps-coaching-toolkit' ) . '</button></p>';
                echo '</form>';
            }
            echo '</div>';

            echo '<div class="maps-card">';
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
                echo '<p class="maps-total">' . sprintf( esc_html__( 'Total classes completed this month: %d', 'maps-coaching-toolkit' ), count( $monthly_records ) ) . '</p>';
            }
            echo '</div>';

            echo '</div>';

            return ob_get_clean();
        }

        /**
         * Render student dashboard.
         *
         * @return string
         */
        public function render_student_dashboard() {
            if ( ! is_user_logged_in() ) {
                return '<p>' . esc_html__( 'You must be logged in to view this dashboard.', 'maps-coaching-toolkit' ) . '</p>';
            }

            $user = wp_get_current_user();
            if ( ! in_array( 'student', (array) $user->roles, true ) ) {
                return '<p>' . esc_html__( 'This dashboard is only available to students.', 'maps-coaching-toolkit' ) . '</p>';
            }

            global $wpdb;

            $subject_ids = $this->get_user_subject_ids( $user->ID, 'student' );
            $subjects    = array();

            if ( ! empty( $subject_ids ) ) {
                $placeholders = implode( ', ', array_fill( 0, count( $subject_ids ), '%d' ) );
                $sql          = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}maps_subjects WHERE id IN ($placeholders)", $subject_ids );
                $subjects     = $wpdb->get_results( $sql );
            }

            $class_routines = array();
            if ( ! empty( $subjects ) ) {
                $class_names      = array_unique( wp_list_pluck( $subjects, 'class_name' ) );
                $placeholders_str = implode( ', ', array_fill( 0, count( $class_names ), '%s' ) );
                $sql_routines     = $wpdb->prepare( "SELECT class_name, image_url FROM {$wpdb->prefix}maps_routine_images WHERE class_name IN ($placeholders_str)", $class_names );
                $routine_rows     = $wpdb->get_results( $sql_routines );
                foreach ( $routine_rows as $routine_row ) {
                    $class_routines[ $routine_row->class_name ] = $routine_row->image_url;
                }
            }

            $materials_map = array();
            if ( ! empty( $subject_ids ) ) {
                $placeholders = implode( ', ', array_fill( 0, count( $subject_ids ), '%d' ) );
                $sql          = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}maps_study_materials WHERE subject_id IN ($placeholders) ORDER BY upload_date DESC", $subject_ids );
                $materials    = $wpdb->get_results( $sql );
                foreach ( $materials as $material ) {
                    $materials_map[ (int) $material->subject_id ][] = $material;
                }
            }

            $subject_count = count( $subject_ids );
            $monthly_bill  = $subject_count * 800;

            $this->hook_frontend_assets();

            ob_start();

            echo '<div class="maps-dashboard maps-dashboard--student">';
            echo '<h2>' . sprintf( esc_html__( 'Welcome, %s', 'maps-coaching-toolkit' ), esc_html( $user->display_name ) ) . '</h2>';

            echo '<div class="maps-card">';
            echo '<h3>' . esc_html__( 'Class Routine', 'maps-coaching-toolkit' ) . '</h3>';
            if ( empty( $class_routines ) ) {
                echo '<p>' . esc_html__( 'Routine for your class has not been uploaded yet.', 'maps-coaching-toolkit' ) . '</p>';
            } else {
                foreach ( $class_routines as $class_name => $image_url ) {
                    echo '<div class="maps-routine">';
                    if ( count( $class_routines ) > 1 ) {
                        echo '<h4>' . esc_html( $class_name ) . '</h4>';
                    }
                    echo '<div class="maps-routine-image"><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr__( 'Class Routine', 'maps-coaching-toolkit' ) . '" style="max-width:100%;height:auto;" /></div>';
                    echo '</div>';
                }
            }
            echo '</div>';

            echo '<div class="maps-card">';
            echo '<h3>' . esc_html__( 'Subjects & Materials', 'maps-coaching-toolkit' ) . '</h3>';
            if ( empty( $subjects ) ) {
                echo '<p>' . esc_html__( 'You are not enrolled in any subjects yet.', 'maps-coaching-toolkit' ) . '</p>';
            } else {
                echo '<div class="maps-accordion">';
                foreach ( $subjects as $index => $subject ) {
                    $subject_id = (int) $subject->id;
                    echo '<div class="maps-accordion-item">';
                    echo '<button type="button" class="maps-accordion-trigger" data-target="maps-accordion-' . esc_attr( $index ) . '">' . esc_html( $subject->subject_name ) . '</button>';
                    echo '<div id="maps-accordion-' . esc_attr( $index ) . '" class="maps-accordion-content" style="display:none;">';
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

            echo '<div class="maps-card">';
            echo '<h3>' . esc_html__( 'Monthly Bill', 'maps-coaching-toolkit' ) . '</h3>';
            echo '<p>' . sprintf( esc_html__( 'Total Subjects: %d', 'maps-coaching-toolkit' ), $subject_count ) . '</p>';
            echo '<p>' . sprintf( esc_html__( 'Your bill for this month: %s Taka', 'maps-coaching-toolkit' ), esc_html( number_format_i18n( $monthly_bill ) ) ) . '</p>';
            echo '<p><a class="maps-button" href="#">' . esc_html__( 'Pay Bill', 'maps-coaching-toolkit' ) . '</a></p>';
            echo '</div>';

            echo '</div>';

            return ob_get_clean();
        }

        /* ===================================================== */
        /* ===             FRONTEND HELPERS                  === */
        /* ===================================================== */

        /**
         * Intercept teacher attendance submissions.
         */
        public function handle_teacher_attendance_form() {
            if ( empty( $_POST['maps_teacher_attendance_nonce'] ) ) {
                return;
            }

            if ( ! wp_verify_nonce( wp_unslash( $_POST['maps_teacher_attendance_nonce'] ), 'maps_teacher_attendance' ) ) {
                return;
            }

            if ( ! is_user_logged_in() ) {
                return;
            }

            $user = wp_get_current_user();
            if ( ! in_array( 'teacher', (array) $user->roles, true ) ) {
                return;
            }

            $subject_id = isset( $_POST['maps_attendance_subject'] ) ? absint( $_POST['maps_attendance_subject'] ) : 0;
            $date_raw   = isset( $_POST['maps_attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['maps_attendance_date'] ) ) : '';
            $status     = isset( $_POST['maps_attendance_status'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['maps_attendance_status'] ) ) ) : '';

            if ( ! $subject_id || empty( $date_raw ) || empty( $status ) ) {
                return;
            }

            $valid_statuses = array( 'present', 'absent' );
            if ( ! in_array( $status, $valid_statuses, true ) ) {
                return;
            }

            $assigned_subjects = $this->get_user_subject_ids( $user->ID, 'teacher' );
            if ( ! in_array( $subject_id, $assigned_subjects, true ) ) {
                return;
            }

            $date_obj = date_create( $date_raw );
            if ( ! $date_obj ) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'maps_teacher_attendance';

            $wpdb->insert(
                $table,
                array(
                    'teacher_wp_id'    => $user->ID,
                    'subject_id'       => $subject_id,
                    'class_date'       => $date_obj->format( 'Y-m-d' ),
                    'attendance_status'=> $status,
                    'submission_time'  => current_time( 'mysql' ),
                    'approval_status'  => 'pending',
                ),
                array( '%d', '%d', '%s', '%s', '%s', '%s' )
            );

            $redirect = add_query_arg( 'maps_status', 'attendance_submitted', home_url( '/teacher-dashboard/' ) );
            wp_safe_redirect( $redirect );
            exit;
        }

        /**
         * Ensure frontend assets only added once.
         */
        private function hook_frontend_assets() {
            if ( $this->frontend_assets_hooked ) {
                return;
            }

            add_action( 'wp_footer', array( $this, 'output_frontend_assets' ) );
            $this->frontend_assets_hooked = true;
        }

        /**
         * Output inline CSS/JS.
         */
        public function output_frontend_assets() {
            echo '<style>
                .maps-dashboard { max-width: 960px; margin: 0 auto; font-family: inherit; }
                .maps-dashboard h2 { margin-bottom: 1.25em; }
                .maps-card { background: #fff; border: 1px solid #dcdcdc; padding: 20px; margin-bottom: 24px; border-radius: 4px; }
                .maps-card h3 { margin-top: 0; }
                .maps-notice { padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; }
                .maps-notice--warning { background: #fff3cd; border: 1px solid #ffeeba; }
                .maps-notice--success { background: #d4edda; border: 1px solid #c3e6cb; }
                .maps-form input[type="text"], .maps-form select, .maps-form input[type="date"] { width: 100%; max-width: 320px; }
                .maps-table { width: 100%; border-collapse: collapse; }
                .maps-table th, .maps-table td { border: 1px solid #dcdcdc; padding: 8px; text-align: left; }
                .maps-total { font-weight: 600; margin-top: 10px; }
                .maps-button { background: #0073aa; color: #fff; padding: 8px 18px; border-radius: 4px; text-decoration: none; display: inline-block; border: none; cursor: pointer; }
                .maps-button:hover { background: #005177; color: #fff; }
                .maps-accordion-item { border: 1px solid #dcdcdc; border-radius: 4px; margin-bottom: 10px; overflow: hidden; }
                .maps-accordion-trigger { background: #f6f7f7; border: none; width: 100%; text-align: left; padding: 12px 16px; font-size: 16px; cursor: pointer; }
                .maps-accordion-content { padding: 16px; background: #fff; }
                .maps-materials { list-style: disc; padding-left: 20px; margin: 0; }
                .maps-materials li { margin-bottom: 6px; }
                .maps-routine + .maps-routine { margin-top: 16px; }
            </style>';

            echo '<script>
                (function(){
                    document.addEventListener("click", function(event){
                        if(event.target.classList.contains("maps-accordion-trigger")){
                            var targetId = event.target.getAttribute("data-target");
                            var content = document.getElementById(targetId);
                            if(content){
                                var isOpen = content.style.display === "block";
                                content.style.display = isOpen ? "none" : "block";
                            }
                        }
                    });
                })();
            </script>';
        }

        /* ===================================================== */
        /* ===              ACCESS CONTROL                    === */
        /* ===================================================== */

        /**
         * Redirect after login based on role.
         *
         * @param string           $redirect_to Redirect destination.
         * @param string           $requested   Original URL requested.
         * @param WP_User|WP_Error $user        Logged-in user.
         *
         * @return string
         */
        public function handle_login_redirect( $redirect_to, $requested, $user ) {
            if ( ! $user || is_wp_error( $user ) ) {
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
         * Restrict wp-admin for students and teachers.
         */
        public function restrict_dashboard_access() {
            if ( ! is_user_logged_in() ) {
                return;
            }

            if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
                return;
            }

            $user = wp_get_current_user();
            if ( array_intersect( array( 'student', 'teacher' ), (array) $user->roles ) ) {
                if ( is_admin() ) {
                    wp_safe_redirect( home_url() );
                    exit;
                }
            }
        }

        /**
         * Hide admin bar for restricted roles.
         */
        public function maybe_hide_admin_bar() {
            if ( ! is_user_logged_in() ) {
                return;
            }

            $user = wp_get_current_user();
            if ( array_intersect( array( 'student', 'teacher' ), (array) $user->roles ) ) {
                show_admin_bar( false );
            }
        }

        /* ===================================================== */
        /* ===                 HELPERS                        === */
        /* ===================================================== */

        /**
         * Ensure current user has manage_options capability.
         */
        private function guard_admin_capability() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'maps-coaching-toolkit' ) );
            }
        }

        /**
         * Retrieve subject IDs assigned to a user for a given role.
         *
         * @param int    $user_id User ID.
         * @param string $role    Role slug (student|teacher).
         *
         * @return array
         */
        private function get_user_subject_ids( $user_id, $role ) {
            global $wpdb;

            if ( 'student' === $role ) {
                $table = $wpdb->prefix . 'maps_student_enrollments';
                $column = 'student_wp_id';
            } else {
                $table = $wpdb->prefix . 'maps_teacher_subjects';
                $column = 'teacher_wp_id';
            }

            $ids = $wpdb->get_col( $wpdb->prepare( "SELECT subject_id FROM {$table} WHERE {$column} = %d", $user_id ) );
            return array_map( 'intval', $ids );
        }
    }
}

MAPS_Coaching_Toolkit::instance();
register_activation_hook( __FILE__, array( 'MAPS_Coaching_Toolkit', 'activate' ) );

