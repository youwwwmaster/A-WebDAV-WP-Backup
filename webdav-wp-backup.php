<?php
/*
Plugin Name: A+ WebDAV WP Backup
Description: A plugin to backup your WordPress site using WebDAV.
Version: 1.0
Author: Mike Art @trueresort
Text Domain: a-webdav-wp-backup
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'includes/backup-functions.php';
include_once plugin_dir_path(__FILE__) . 'admin/admin-interface.php';
include_once plugin_dir_path(__FILE__) . 'includes/connection-tests.php';


// Activation and deactivation hooks
register_activation_hook(__FILE__, 'webdav_wp_backup_activate');
register_deactivation_hook(__FILE__, 'webdav_wp_backup_deactivate');

// Plugin activation callback
function webdav_wp_backup_activate() {
    if (get_option('webdav_wp_backup_local_keep') === false) {
        update_option('webdav_wp_backup_local_keep', '');
    }

    // Ensure the backup directory exists and create .htaccess
    $backup_dir = WP_CONTENT_DIR . '/site-backups';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $htaccess_content = "Order allow,deny\nDeny from all\n";
    file_put_contents($backup_dir . '/.htaccess', $htaccess_content);
}

// Plugin deactivation callback
function webdav_wp_backup_deactivate() {
    // Deactivation code here
}

// Admin menu setup
add_action('admin_menu', 'webdav_wp_backup_admin_menu');
function webdav_wp_backup_admin_menu() {
    add_menu_page(
        'WebDAV WP Backup',
        'WebDAV WP Backup',
        'manage_options',
        'webdav-wp-backup',
        'webdav_wp_backup_admin_page'
    );
}

// Admin page content
function webdav_wp_backup_admin_page() {
    webdav_wp_backup_settings_page();
}

// Enqueue scripts and styles
function webdav_wp_backup_enqueue_scripts() {
    wp_enqueue_style('webdav-wp-backup-styles', plugin_dir_url(__FILE__) . 'assets/css/styles.css');
    wp_enqueue_script('webdav-wp-backup-scripts', plugin_dir_url(__FILE__) . 'assets/js/scripts.js', array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'webdav_wp_backup_enqueue_scripts');

// AJAX action for creating manual backup
add_action('wp_ajax_webdav_wp_backup_manual', 'webdav_wp_backup_manual_callback');

function webdav_wp_backup_manual_callback() {
    $result = webdav_wp_backup_create_backup('manual');
    if ($result['success']) {
        wp_send_json_success(['location' => $result['data']['location'], 'size' => $result['data']['size'], 'time' => $result['data']['time']]);
    } else {
        wp_send_json_error($result['error']);
    }
}

// Add a download handler for backups
add_action('init', 'webdav_wp_backup_download_init');
function webdav_wp_backup_download_init() {
    if (isset($_GET['webdav_wp_backup_download']) && current_user_can('manage_options')) {
        $file = sanitize_text_field($_GET['webdav_wp_backup_download']);
        webdav_wp_backup_download_file($file);
    }
}

function webdav_wp_backup_download_file($file) {
    $backup_dir = WP_CONTENT_DIR . '/site-backups';
    $file_path = $backup_dir . '/' . $file;

    if (!file_exists($file_path)) {
        wp_die('File not found.');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path);
    exit;
}

// Add custom cron intervals
function webdav_wp_backup_custom_cron_schedules($schedules) {
    $frequency_in_hours = get_option('webdav_wp_backup_frequency', 24); // Default to 24 hours if not set
    $schedules['custom_backup_interval'] = array(
        'interval' => $frequency_in_hours * HOUR_IN_SECONDS,
        'display'  => __('Custom Backup Interval')
    );
    return $schedules;
}
add_filter('cron_schedules', 'webdav_wp_backup_custom_cron_schedules');

// Обработчик AJAX-запроса для планирования резервного копирования
function webdav_wp_backup_schedule() {
    // Проверяем права пользователя
    if (!current_user_can('manage_options')) {
        wp_send_json_error('You do not have permission to perform this action.');
    }

    // Отменяем текущие запланированные задания
    $timestamp = wp_next_scheduled('automatic_backup_event');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'automatic_backup_event');
    }

    // Получаем настройки частоты и задержки
    $delay = get_option('webdav_wp_backup_delay_start', 0); // по умолчанию нет задержки

    // Вычисляем время следующего запуска
    $next_scheduled_time = time() + ($delay * HOUR_IN_SECONDS);

    // Планируем событие резервного копирования с кастомным интервалом
    wp_schedule_event($next_scheduled_time, 'custom_backup_interval', 'automatic_backup_event');
    wp_send_json_success();
}
add_action('wp_ajax_webdav_wp_backup_schedule', 'webdav_wp_backup_schedule');

// Функция для выполнения резервного копирования
function automatic_backup_event_handler() {
    // Здесь вызовите вашу существующую функцию создания резервной копии
    webdav_wp_backup_create_backup('automatic');
}
add_action('automatic_backup_event', 'automatic_backup_event_handler');

?>
