<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// AJAX action for testing WebDAV connection
add_action('wp_ajax_webdav_wp_backup_test_webdav', 'webdav_wp_backup_test_webdav');
function webdav_wp_backup_test_webdav() {
    $remote_url = get_option('webdav_wp_backup_remote_url');
    $username = get_option('webdav_wp_backup_username');
    $password = get_option('webdav_wp_backup_password');

    // Проверка и исправление URL
    if (substr($remote_url, -1) !== '/') {
        $remote_url .= '/';
    }

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $remote_url);
    curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true); // Следовать за перенаправлениями
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // Игнорировать проверку SSL сертификата
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // Игнорировать проверку SSL хоста
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PROPFIND'); // Метод PROPFIND для WebDAV
    curl_setopt($curl, CURLOPT_HEADER, true); // Включить заголовки в вывод

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl); // Получение текста ошибки от cURL
    curl_close($curl);

    if ($http_code == 200 || $http_code == 207) { // 207 Multi-Status для WebDAV
        wp_send_json_success('WebDAV connection successful!');
    } else {
        $message =  $http_code . ' - ' . $curl_error;
        $message .= ' Please check the entered data and make sure to save it.';
        wp_send_json_error($message);
    }
}
