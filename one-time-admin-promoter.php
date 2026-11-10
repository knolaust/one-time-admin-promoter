<?php
/**
 * Plugin Name: One-Time Admin Promoter
 * Description: Promotes one existing user selected in wp-admin and sets the site administration email to their email once, then deactivates itself. Single-site only.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * License: GPL-2.0-or-later
 *
 * Usage: Activate, then open Tools > Admin Handover, enter an existing username,
 * and confirm. Activation alone makes no role or email changes.
 * No configuration edit is required; the legacy OTAP_TARGET_USERNAME constant
 * only prefills the form when defined as a string.
 * No password is needed, read, stored, or changed.
 * The attempt record otap_completed_v1 is retained after failures and deletion.
 */

defined( 'ABSPATH' ) || exit;

final class OTAP_One_Time_Admin_Promoter {
    const RECORD = 'otap_completed_v1';

    public static function stop( $message ) {
        wp_die( esc_html( $message ), 'One-Time Admin Promoter', array( 'back_link' => true ) );
    }

    public static function activate( $network_wide = false ) {
        if ( is_multisite() || $network_wide || ! self::allowed() ) {
            self::stop( 'Single-site WordPress and an administrator with plugin, user, and site-setting permissions are required.' );
        }
    }

    public static function allowed() {
        return is_user_logged_in() && current_user_can( 'activate_plugins' ) && current_user_can( 'promote_users' ) && current_user_can( 'manage_options' );
    }

    public static function menu() {
        if ( ! is_multisite() && self::allowed() ) {
            add_management_page( 'Admin Handover', 'Admin Handover', 'manage_options', 'otap-handover', array( __CLASS__, 'page' ) );
        }
    }

    public static function page() {
        if ( is_multisite() || ! self::allowed() ) {
            self::stop( 'You do not have permission to perform this handover.' );
        }
        $default = defined( 'OTAP_TARGET_USERNAME' ) && is_string( OTAP_TARGET_USERNAME ) ? OTAP_TARGET_USERNAME : '';
        echo '<div class="wrap"><h1>Admin Handover</h1>';
        echo '<p>Enter an existing user’s exact login. This will replace their roles with Administrator and immediately set the site Administration Email Address to their current email, without an email confirmation link. Other administrators keep their access.</p>';
        echo '<p>WordPress has no primary administrator role. Some plugins and user-specific notifications use separate email recipients. Confirm the target’s email under Users before continuing.</p>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="otap_handover">';
        wp_nonce_field( 'otap_handover' );
        echo '<p><label for="otap_username">Existing username</label><br><input id="otap_username" name="otap_username" type="text" class="regular-text" required maxlength="60" autocomplete="off" value="' . esc_attr( $default ) . '"></p>';
        echo '<p><label><input type="checkbox" name="otap_confirm" value="1" required> I confirm this user should be an Administrator and receive site administration notices at their current email.</label></p>';
        submit_button( 'Complete handover and deactivate plugin' );
        echo '</form></div>';
    }

    public static function submit() {
        if ( is_multisite() || ! self::allowed() ) {
            self::stop( 'You do not have permission to perform this handover.' );
        }
        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
            self::stop( 'Submit the handover form using POST.' );
        }
        check_admin_referer( 'otap_handover' );
        if ( ! isset( $_POST['otap_confirm'] ) || '1' !== $_POST['otap_confirm'] || ! isset( $_POST['otap_username'] ) || ! is_string( $_POST['otap_username'] ) ) {
            self::stop( 'Enter a username and confirm the handover.' );
        }
        self::run( wp_unslash( $_POST['otap_username'] ) );
        self::deactivate();
        wp_safe_redirect( admin_url( 'plugins.php?deactivate=true' ) );
        exit;
    }

    public static function run( $username ) {
        $network_wide = false;
        if ( is_multisite() || $network_wide ) {
            self::stop( 'This plugin supports single-site WordPress only.' );
        }
        // submit() checks the POST method, handover nonce, and confirmation.
        // Recheck operator permissions before applying the handover.
        if ( ! is_user_logged_in() || ! current_user_can( 'activate_plugins' ) || ! current_user_can( 'promote_users' ) ) {
            self::stop( 'You must be signed in with permission to activate plugins and promote users.' );
        }
        if ( false !== get_option( self::RECORD, false ) ) {
            // Never repeat an attempted promotion, including after an interrupted run.
            add_action( 'shutdown', array( __CLASS__, 'deactivate' ), PHP_INT_MAX );
            return;
        }
        // Reject malformed input rather than silently selecting a different login.
        if ( ! is_string( $username ) || '' === $username || strlen( $username ) > 60 || trim( $username ) !== $username || sanitize_user( $username ) !== $username ) {
            self::stop( 'Enter a valid existing username with no surrounding whitespace.' );
        }
        $user = get_user_by( 'login', $username );
        if ( ! $user || $user->user_login !== $username ) {
            self::stop( 'The username does not exactly match an existing user login. No account was created.' );
        }
        if ( ! current_user_can( 'edit_user', $user->ID ) || ! current_user_can( 'promote_user', $user->ID ) ) {
            self::stop( 'You do not have permission to edit and promote this user.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            self::stop( 'You must have permission to change site settings.' );
        }
        if ( ! is_email( $user->user_email ) ) {
            self::stop( 'The target user must have a valid email address. No change was made.' );
        }
        if ( ! get_role( 'administrator' ) ) {
            self::stop( 'The Administrator role is missing. No change was made.' );
        }
        // Atomic insert claims the one-time operation before modifying any role.
        // Keep this record on failures too: an interrupted operation must not retry.
        $record = array( 'status' => 'started', 'user_id' => $user->ID, 'actor_id' => get_current_user_id(), 'time_utc' => gmdate( 'c' ) );
        if ( ! add_option( self::RECORD, $record, '', false ) ) {
            self::stop( 'A previous or concurrent run has claimed this operation. No change was made by this request.' );
        }
        add_action( 'shutdown', array( __CLASS__, 'deactivate' ), PHP_INT_MAX );
        $user->set_role( 'administrator' );
        // Authorized one-time handover: apply immediately without a confirmation link.
        update_option( 'admin_email', $user->user_email );
        // Invalidate any older pending administration-email confirmation request.
        delete_option( 'new_admin_email' );
        delete_option( 'adminhash' );
        clean_user_cache( $user->ID );
        $verified = get_userdata( $user->ID );
        $record['status'] = $verified && in_array( 'administrator', $verified->roles, true ) && get_option( 'admin_email' ) === $user->user_email ? 'completed' : 'verification_failed';
        update_option( self::RECORD, $record, false );
        if ( 'completed' !== $record['status'] ) {
            self::stop( 'The role or administration email change could not be verified. Inspect the user and Settings > General manually; automatic retries are disabled.' );
        }
    }

    public static function deactivate() {
        if ( ! function_exists( 'deactivate_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        // Called after submission and again at shutdown as a fallback.
        deactivate_plugins( plugin_basename( __FILE__ ), true, false );
    }

    public static function cleanup() {
        // Deactivate on a later admin request if a claimed handover was interrupted.
        if ( ! is_multisite() && current_user_can( 'activate_plugins' ) && false !== get_option( self::RECORD, false ) ) {
            self::deactivate();
        }
    }
}

register_activation_hook( __FILE__, array( 'OTAP_One_Time_Admin_Promoter', 'activate' ) );
add_action( 'admin_init', array( 'OTAP_One_Time_Admin_Promoter', 'cleanup' ) );
add_action( 'admin_menu', array( 'OTAP_One_Time_Admin_Promoter', 'menu' ) );
add_action( 'admin_post_otap_handover', array( 'OTAP_One_Time_Admin_Promoter', 'submit' ) );
