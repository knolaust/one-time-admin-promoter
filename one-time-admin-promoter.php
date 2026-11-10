<?php
/**
 * Plugin Name: One-Time Admin Promoter
 * Description: Promotes one existing configured user and sets the site administration email to their email once, then deactivates itself. Single-site only.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.2
 * License: GPL-2.0-or-later
 *
 * Usage: Configure OTAP_TARGET_USERNAME with an exact existing user login,
 * then activate through Plugins as an authorized administrator. Activation
 * replaces the target's roles with Administrator and immediately copies their
 * email to the site administration email setting. See README.md for setup.
 * No password is needed, read, stored, or changed.
 * The one-time attempt record otap_completed_v1 is intentionally retained,
 * including after interrupted or failed attempts and plugin deletion.
 */

defined( 'ABSPATH' ) || exit;

final class OTAP_One_Time_Admin_Promoter {
    const RECORD = 'otap_completed_v1';

    public static function stop( $message ) {
        wp_die( esc_html( $message ), 'One-Time Admin Promoter', array( 'back_link' => true ) );
    }

    public static function activate( $network_wide = false ) {
        if ( is_multisite() || $network_wide ) {
            self::stop( 'This plugin supports single-site WordPress only.' );
        }
        // Core's Plugins activation flow supplies the activation nonce check.
        // These checks also protect calls made outside that normal UI flow.
        if ( ! is_user_logged_in() || ! current_user_can( 'activate_plugins' ) || ! current_user_can( 'promote_users' ) ) {
            self::stop( 'You must be signed in with permission to activate plugins and promote users.' );
        }
        if ( false !== get_option( self::RECORD, false ) ) {
            // Never repeat an attempted promotion, including after an interrupted run.
            add_action( 'shutdown', array( __CLASS__, 'deactivate' ), PHP_INT_MAX );
            return;
        }
        if ( ! defined( 'OTAP_TARGET_USERNAME' ) || ! is_string( OTAP_TARGET_USERNAME ) || '' === OTAP_TARGET_USERNAME || trim( OTAP_TARGET_USERNAME ) !== OTAP_TARGET_USERNAME ) {
            self::stop( 'Define OTAP_TARGET_USERNAME in wp-config.php with an existing username and no surrounding whitespace.' );
        }
        $user = get_user_by( 'login', OTAP_TARGET_USERNAME );
        if ( ! $user || $user->user_login !== OTAP_TARGET_USERNAME ) {
            self::stop( 'The configured username does not exactly match an existing user login. No account was created.' );
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
        // Shutdown runs after core saves the newly active plugin list.
        deactivate_plugins( plugin_basename( __FILE__ ), true, false );
    }

    public static function cleanup() {
        // Fallback if activation was interrupted before its shutdown callback.
        if ( ! is_multisite() && current_user_can( 'activate_plugins' ) && false !== get_option( self::RECORD, false ) ) {
            self::deactivate();
        }
    }
}

register_activation_hook( __FILE__, array( 'OTAP_One_Time_Admin_Promoter', 'activate' ) );
add_action( 'admin_init', array( 'OTAP_One_Time_Admin_Promoter', 'cleanup' ) );
