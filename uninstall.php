<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

// Delete plugin options
delete_option('webdav_wp_backup_remote_url');
delete_option('webdav_wp_backup_username');
delete_option('webdav_wp_backup_password');
delete_option('webdav_wp_backup_self_signed');
delete_option('webdav_wp_backup_frequency');
delete_option('webdav_wp_backup_delay_start');
delete_option('webdav_wp_backup_local_keep');
delete_option('webdav_wp_backup_remote_keep');
delete_option('webdav_wp_backup_email');
delete_option('webdav_wp_backup_email_prefix');

// Define the new backups directory path
$backup_dir = WP_CONTENT_DIR . '/site-backups';
if (file_exists($backup_dir)) {
    array_map('unlink', glob("$backup_dir/*.*"));
    rmdir($backup_dir);
}

// Delete log file
$log_file = plugin_dir_path(__FILE__) . 'logs/log.txt';
if (file_exists($log_file)) {
    unlink($log_file);
}