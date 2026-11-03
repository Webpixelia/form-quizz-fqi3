<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Scheduled events must never outlive the plugin, whatever the "delete data" setting
foreach (['fqi3_weekly_statistics_event', 'fqi3_monthly_statistics_event', 'fqi3_purge_attempts_event', 'fqi3_send_daily_email'] as $fqi3_hook) {
    wp_clear_scheduled_hook($fqi3_hook);
}

// Check if the option to delete data is enabled
$options = get_option('fqi3_options', []);
if (isset($options['fqi3_delete_data']) && $options['fqi3_delete_data']) {
    
    // Delete plugin-related tables from database
    global $wpdb;
    $tables = [
        $wpdb->prefix . 'fqi3_quizzes',
        $wpdb->prefix . 'fqi3_performance',
        $wpdb->prefix . 'fqi3_awards',
        $wpdb->prefix . 'fqi3_periodic_statistics',
        $wpdb->prefix . 'fqi3_quiz_attempts',
    ];
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    // Delete plugin-related options from wp_options
    $options_to_delete = [
        'fqi3_access_roles',
        'fqi3_badges',
        'fqi3_current_version',
        'fqi3_import_export_logs',
        'fqi3_items_per_page',
        'fqi3_options',
        'fqi3_quiz_levels',   
        'fqi3_encryption_key',
        'fqi3_attempts_table_version',
    ];
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // Remove the per-user daily quiz counters
    delete_metadata('user', 0, 'fqi3_daily_quiz_count', '', true);

    // Remove the premium role (kept on deactivation so members don't lose it)
    remove_role('premium_member');

    // Delete transients fqi3_stats_{user_id}
    $transient_prefix = '_transient_fqi3_stats_';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like($transient_prefix) . '%'
        )
    );
}