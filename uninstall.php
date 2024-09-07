<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Pulse
 */

namespace Pulse;

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('pulse_options');

// Delete any custom database tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pulse_affiliates");

// Delete any custom user meta
$wpdb->query("DELETE FROM {$wpdb->prefix}usermeta WHERE meta_key LIKE 'pulse_%'");

// Clear any scheduled cron jobs
wp_clear_scheduled_hook('pulse_daily_payout_check');

// Any additional cleanup code goes here