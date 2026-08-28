<?php
/*
Plugin Name: WP Calendar Notes
Description: A simple calendar with note-taking for each date.
Version: 1.0
Author: Elias Katsaniotis + DeepAI
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class WPCalendarNotes {
    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'create_table' ) );
        add_shortcode( 'calendar_notes', array( $this, 'render_calendar' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_save_note', array( $this, 'save_note' ) );
        add_action( 'wp_ajax_get_note', array( $this, 'get_note' ) );
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
    }

    public function create_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'calendar_notes';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            note text NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY date (date)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function enqueue_scripts() {
        wp_enqueue_style( 'calendar-notes-style', plugins_url( 'style.css', __FILE__ ) );
        wp_enqueue_script( 'calendar-notes-script', plugins_url( 'script.js', __FILE__ ), array( 'jquery' ), null, true );
        wp_localize_script( 'calendar-notes-script', 'cn_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'cn_nonce' )
        ) );
    }

    public function render_calendar() {
        $current_month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : date( 'm' );
        $current_year = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : date( 'Y' );

        // First day of the month
        $first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
        $total_days = cal_days_in_month( CAL_GREGORIAN, $current_month, $current_year );

        $start_day = date( 'N', $first_day ); // 1 (Mon) to 7 (Sun)

        $prev_month = $current_month == 1 ? 12 : $current_month - 1;
        $prev_year = $current_month == 1 ? $current_year - 1 : $current_year;

        $next_month = $current_month == 12 ? 1 : $current_month + 1;
        $next_year = $current_month == 12 ? $current_year + 1 : $current_year;

        ob_start();
        ?>
        <div class="cn-calendar-container">
            <div class="cn-navigation">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>">&laquo; Prev</a>
                <span><?php echo date( 'F Y', $first_day ); ?></span>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>">Next &raquo;</a>
            </div>
            <table class="cn-calendar">
                <thead>
                    <tr>
                        <th>Mon</th>
                        <th>Tue</th>
                        <th>Wed</th>
                        <th>Thu</th>
                        <th>Fri</th>
                        <th>Sat</th>
                        <th>Sun</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $day_counter = 1;
                    $cell = 1;
                    $total_cells = ceil( ( $total_days + $start_day - 1 ) / 7 ) * 7;

                    for ( $i = 1; $i <= $total_cells; $i++ ) {
                        if ( $i % 7 == 1 ) echo '<tr>';

                        if ( $i >= $start_day && $day_counter <= $total_days ) {
                            $date_str = sprintf( '%04d-%02d-%02d', $current_year, $current_month, $day_counter );
                            echo '<td data-date="' . esc_attr( $date_str ) . '">';
                            echo '<div class="date-number">' . $day_counter . '</div>';
                            echo '<div class="note-preview" id="note-' . esc_attr( $date_str ) . '">Loading...</div>';
                            echo '</td>';

                            $day_counter++;
                        } else {
                            echo '<td></td>';
                        }

                        if ( $i % 7 == 0 ) echo '</tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Modal for editing notes -->
        <div id="cn-note-modal" style="display:none;">
            <div class="cn-modal-content">
                <h3>Note for <span id="cn-modal-date"></span></h3>
                <textarea id="cn-note-text" rows="5" style="width:100%;"></textarea>
                <button id="cn-save-note">Save</button>
                <button id="cn-close-modal">Close</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function save_note() {
        check_ajax_referer( 'cn_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';
        $note = isset( $_POST['note'] ) ? sanitize_textarea_field( $_POST['note'] ) : '';

        if ( empty( $date ) ) {
            wp_send_json_error( 'Invalid date' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'calendar_notes';

        $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE date = %s", $date ) );

        if ( $existing ) {
            $wpdb->update(
                $table_name,
                array( 'note' => $note ),
                array( 'date' => $date ),
                array( '%s' ),
                array( '%s' )
            );
        } else {
            $wpdb->insert(
                $table_name,
                array(
                    'date' => $date,
                    'note' => $note
                ),
                array( '%s', '%s' )
            );
        }

        wp_send_json_success( 'Note saved' );
    }

    public function get_note() {
        check_ajax_referer( 'cn_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : '';

        if ( empty( $date ) ) {
            wp_send_json_error( 'Invalid date' );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'calendar_notes';

        $note = $wpdb->get_var( $wpdb->prepare( "SELECT note FROM $table_name WHERE date = %s", $date ) );

        wp_send_json_success( array( 'note' => $note ? $note : '' ) );
    }

    public function add_admin_menu() {
        add_menu_page( 'Calendar Notes', 'Calendar Notes', 'manage_options', 'calendar-notes', '', 'dashicons-calendar', 6 );
    }
}

new WPCalendarNotes();

// Include CSS
add_action( 'admin_enqueue_scripts', function() {
    wp_enqueue_style( 'calendar-notes-style', plugins_url( 'style.css', __FILE__ ) );
} );
