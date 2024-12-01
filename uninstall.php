<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
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
    ];
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }

    // Delete plugin-related options from wp_options
    $options_to_delete = [
        'fqi3_access_roles',
        'fqi3_badges',
        'fqi3_current_version',
        'fqi3_items_per_page',
        'fqi3_options',
        'fqi3_quiz_levels',
        'fqi3_import_export_logs'
    ];
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }

    // Delete transients fqi3_stats_{user_id}
    $transient_prefix = '_transient_fqi3_stats_';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like($transient_prefix) . '%'
        )
    );
}