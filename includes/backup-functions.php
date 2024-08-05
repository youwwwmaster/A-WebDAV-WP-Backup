<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Function to log messages
/*
function webdav_wp_backup_log($message) {
    $log_file = plugin_dir_path(__FILE__) . '../logs/log.txt';
    $time = current_time('Y-m-d H:i:s');
    $formatted_message = "[" . $time . "] " . $message . PHP_EOL;
    file_put_contents($log_file, $formatted_message, FILE_APPEND);
}
*/

// Function to create a backup
function webdav_wp_backup_create_backup($type = 'manual') {
    // webdav_wp_backup_log('Starting backup process.');

    // Set the backup directory to a non-public location
    $backup_dir = WP_CONTENT_DIR . '/site-backups';
    $local_keep = get_option('webdav_wp_backup_local_keep', '');
    $remote_keep = get_option('webdav_wp_backup_remote_keep', '');

    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
        // webdav_wp_backup_log('Created backup directory: ' . $backup_dir);

        // Create .htaccess file to restrict access
        $htaccess_content = "Order allow,deny\nDeny from all\n";
        file_put_contents($backup_dir . '/.htaccess', $htaccess_content);
        // webdav_wp_backup_log('Created .htaccess file to restrict access in: ' . $backup_dir);
    }

    // Pre-count existing backups
    $existing_backups_count = webdav_wp_backup_count_existing_backups($backup_dir);

    // Create new backup
    $timestamp = current_time('Ymd_Hi');
    $files_backup_name = $type . '_files_' . $timestamp . '.zip';
    $db_backup_name = $type . '_db_' . $timestamp . '.sql.gz';

    $files_backup_path = $backup_dir . '/' . $files_backup_name;
    $db_backup_path = $backup_dir . '/' . $db_backup_name;

    // Create the files backup zip file
    $zip = new ZipArchive();
    if ($zip->open($files_backup_path, ZipArchive::CREATE) !== TRUE) {
        // webdav_wp_backup_log('Failed to create files zip file: ' . $files_backup_path);
        return ['success' => false, 'error' => 'Failed to create files zip file'];
    }

    // Add files to the zip archive, excluding the backup directory
    if (!webdav_wp_backup_add_files_to_zip($zip, ABSPATH, 'SITE', $backup_dir)) {
        $zip->close();
        unlink($files_backup_path);
        // webdav_wp_backup_log('Failed to add files to zip.');
        return ['success' => false, 'error' => 'Failed to add files to zip'];
    }

    $zip->close();

    // Add database to a file
    if (!webdav_wp_backup_add_db_to_file($db_backup_path)) {
        unlink($db_backup_path);
        return ['success' => false, 'error' => 'Failed to create database backup'];
    }

    // webdav_wp_backup_log('Backup completed successfully.');
    $backup_size = filesize($files_backup_path) + filesize($db_backup_path);

    // Upload backups to WebDAV
    $webdav_result_files = webdav_wp_backup_upload_to_webdav($files_backup_path);
    $webdav_result_db = webdav_wp_backup_upload_to_webdav($db_backup_path);

    $error_messages = [];
    $webdav_success = true;
    if (!$webdav_result_files['success']) {
        $error_messages[] = $webdav_result_files['error'];
        $webdav_success = false;
    }
    if (!$webdav_result_db['success']) {
        $error_messages[] = $webdav_result_db['error'];
        $webdav_success = false;
    }

    // Cleanup old backups if needed after creating the new backup
    if ($local_keep !== '') {
        webdav_wp_backup_cleanup_old_backups($backup_dir, $local_keep);
    }

    // Cleanup old WebDAV backups if needed after uploading the new backup
    if ($remote_keep !== '') {
        webdav_wp_backup_cleanup_old_webdav_backups($remote_keep);
    }

    if (!empty($error_messages)) {
        $error_message = 'Failed to upload backups to WebDAV: ' . implode('; ', array_unique($error_messages));
        // webdav_wp_backup_log($error_message);
        return [
            'success' => false,
            'error' => $error_message,
            'data' => [
                'location' => $backup_dir,
                'size' => round($backup_size / 1048576, 2), // Размер в MB
                'time' => current_time('Y-m-d H:i:s'),
                'webdav_success' => $webdav_success
            ]
        ];
    }

    // Отправка email уведомления о завершении резервного копирования
    sleep(2);
    webdav_wp_backup_send_email_notification([
        'location' => $backup_dir,
        'size' => round($backup_size / 1048576, 2), // Размер в MB
        'time' => current_time('Y-m-d H:i:s')
    ]);

    return ['success' => true, 'data' => [
        'location' => $backup_dir,
        'size' => round($backup_size / 1048576, 2), // Размер в MB
        'time' => current_time('Y-m-d H:i:s'),
        'webdav_success' => $webdav_success
    ]];
}

// Function to send email notifications after backup
function webdav_wp_backup_send_email_notification($backup_info) {
    $to = get_option('webdav_wp_backup_email');
    if (!$to) {
        return; // Если email не задан, выходим из функции
    }

    $subject_prefix = get_option('webdav_wp_backup_email_prefix', get_bloginfo('name'));
    $subject = $subject_prefix . ' - Backup Completed';

    $message = "A backup was successfully created.\n\n";
    $message .= "Location: " . $backup_info['location'] . "\n";
    $message .= "Size: " . $backup_info['size'] . " MB\n";
    $message .= "Time: " . $backup_info['time'] . "\n";

    wp_mail($to, $subject, $message);
}

// Function to count existing backups
function webdav_wp_backup_count_existing_backups($backup_dir) {
    $files = array_diff(scandir($backup_dir), array('.', '..'));
    $backups = [];

    foreach ($files as $file) {
        $parts = explode('_', $file);
        if (count($parts) >= 4) {
            $timestamp = $parts[2] . '_' . $parts[3];
            $backups[$timestamp][] = $file;
        }
    }

    return count($backups);
}

// Function to add files to the zip archive
function webdav_wp_backup_add_files_to_zip($zip, $folder, $parent_folder, $exclude_folder) {
    // Логируем начальные данные
    // webdav_wp_backup_log('Starting to add files to zip. Folder: ' . $folder . ', Parent Folder: ' . $parent_folder);

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder), RecursiveIteratorIterator::LEAVES_ONLY);

    foreach ($files as $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            // webdav_wp_backup_log('Processing file: ' . $filePath);

            if (strpos($filePath, $exclude_folder) === false) {
                $relativePath = substr($filePath, strlen($folder));
                $relativePath = ltrim($relativePath, '/'); // Удаляем начальный слеш, если он есть
                $finalPath = $parent_folder . '/' . $relativePath;

                // Логируем относительный и конечный пути
                // webdav_wp_backup_log('Relative path: ' . $relativePath);
                // webdav_wp_backup_log('Final path in zip: ' . $finalPath);

                $zip->addFile($filePath, $finalPath);
                // webdav_wp_backup_log('Added file to zip: ' . $filePath . ' as ' . $finalPath);
            } else {
                // webdav_wp_backup_log('Excluded file from zip: ' . $filePath);
            }
        }
    }

    return true;
}

// Function to add database to a file
function webdav_wp_backup_add_db_to_file($db_backup_path) {
    global $wpdb;
    // webdav_wp_backup_log('Creating database backup: ' . $db_backup_path);

    $command = sprintf('mysqldump --host=%s --user=%s --password=%s %s | gzip > %s',
        DB_HOST,
        DB_USER,
        DB_PASSWORD,
        DB_NAME,
        $db_backup_path
    );

    exec($command, $output, $return_var);
    if ($return_var !== 0) {
        // webdav_wp_backup_log('Failed to dump database. Command output: ' . implode("\n", $output));
        return false;
    }

    // webdav_wp_backup_log('Database dumped successfully.');
    return true;
}

// Function to upload backup to WebDAV
function webdav_wp_backup_upload_to_webdav($file_path) {
    $remote_url = get_option('webdav_wp_backup_remote_url');
    $username = get_option('webdav_wp_backup_username');
    $password = get_option('webdav_wp_backup_password');

    if (!$remote_url || !$username || !$password) {
        // webdav_wp_backup_log('WebDAV settings are not configured.');
        return ['success' => false, 'error' => 'WebDAV settings are not configured'];
    }

    $remote_url = rtrim($remote_url, '/') . '/' . basename($file_path);
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $remote_url);
    curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($curl, CURLOPT_PUT, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_INFILE, fopen($file_path, 'r'));
    curl_setopt($curl, CURLOPT_INFILESIZE, filesize($file_path));
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($http_code == 201 || $http_code == 204) {
        // webdav_wp_backup_log('File uploaded to WebDAV successfully: ' . $file_path);
        return ['success' => true];
    } else {
        $error_message = 'Failed to upload file to WebDAV: ' . $http_code . ' - ' . $curl_error;
        // webdav_wp_backup_log($error_message);
        return ['success' => false, 'error' => $error_message];
    }
}

// Function to cleanup old backups
function webdav_wp_backup_cleanup_old_backups($backup_dir, $local_keep) {
    // Check if local_keep is a valid number
    if (!is_numeric($local_keep) || $local_keep < 0) {
        return; // Do not perform cleanup if the setting is invalid
    }

    $files = array_diff(scandir($backup_dir), array('.', '..'));
    $backups = [];

    foreach ($files as $file) {
        $parts = explode('_', $file);
        if (count($parts) >= 4) {
            $timestamp = $parts[2] . '_' . $parts[3];
            $formatted_timestamp = substr($timestamp, 0, 8) . ' ' . substr($timestamp, 9, 2) . ':' . substr($timestamp, 11, 2);
            $backups[$formatted_timestamp][] = $file;
        }
    }

    ksort($backups);
    $backup_count = count($backups);

    while ($backup_count > $local_keep) {
        $oldest_backup = array_shift($backups);
        foreach ($oldest_backup as $file) {
            unlink($backup_dir . '/' . $file);
            // webdav_wp_backup_log('Deleted oldest backup file: ' . $backup_dir . '/' . $file);
        }
        $backup_count--;
    }
}

// Function to cleanup old WebDAV backups
function webdav_wp_backup_cleanup_old_webdav_backups($remote_keep) {
    $remote_url = get_option('webdav_wp_backup_remote_url');
    $username = get_option('webdav_wp_backup_username');
    $password = get_option('webdav_wp_backup_password');

    if (!is_numeric($remote_keep) || $remote_keep < 0) {
        return; // Не выполнять очистку, если настройка некорректна
    }

    if (!$remote_url || !$username || !$password) {
        // webdav_wp_backup_log('WebDAV настройки не сконфигурированы.');
        return;
    }

    // Убедиться, что базовый URL корректно заканчивается '/'
    $remote_url = rtrim($remote_url, '/') . '/';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $remote_url);
    curl_setopt($curl, CURLOPT_USERPWD, "$username:$password");
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PROPFIND');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Depth: 1'));
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($curl);
    curl_close($curl);

    if ($http_code != 207) {
        // webdav_wp_backup_log('Не удалось получить список файлов WebDAV: ' . $http_code . ' - ' . $curl_error);
        // webdav_wp_backup_log('PROPFIND response: ' . $response);
        return;
    }

    // webdav_wp_backup_log('PROPFIND response: ' . $response);

    // Load XML response
    $xml = simplexml_load_string($response);
    if ($xml === false) {
        // webdav_wp_backup_log('Не удалось разобрать ответ PROPFIND.');
        foreach(libxml_get_errors() as $error) {
            // webdav_wp_backup_log('XML Error: ' . $error->message);
        }
        return;
    }

    $namespaces = $xml->getNamespaces(true);

    $backups = [];
    foreach ($xml->children($namespaces['D'])->response as $response) {
        $file_url = (string) $response->children($namespaces['D'])->href;
        // webdav_wp_backup_log('Обработка URL файла: ' . $file_url);

        if (preg_match('/\.(zip|sql\.gz)$/', $file_url)) {
            $file_name = basename($file_url);
            // Извлечение временной метки из имени файла
            $parts = explode('_', $file_name);
            if (count($parts) >= 4) {
                $timestamp = $parts[2] . '_' . $parts[3];
                $formatted_timestamp = substr($timestamp, 0, 8) . ' ' . substr($timestamp, 9, 2) . ':' . substr($timestamp, 11, 2);
                $backups[$formatted_timestamp][] = $file_name;
            }
        }
    }

    ksort($backups);
    $backup_count = count($backups);

    while ($backup_count > $remote_keep) {
        $oldest_backup = array_shift($backups);
        foreach ($oldest_backup as $file_name) {
            $full_url = $remote_url . $file_name;
            $delete_curl = curl_init();
            curl_setopt($delete_curl, CURLOPT_URL, $full_url);
            curl_setopt($delete_curl, CURLOPT_USERPWD, "$username:$password");
            curl_setopt($delete_curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($delete_curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($delete_curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($delete_curl, CURLOPT_SSL_VERIFYHOST, false);

            $delete_response = curl_exec($delete_curl);
            $delete_http_code = curl_getinfo($delete_curl, CURLINFO_HTTP_CODE);
            $delete_curl_error = curl_error($delete_curl);
            curl_close($delete_curl);

            if ($delete_http_code == 200 || $delete_http_code == 204) {
                // webdav_wp_backup_log('Удален старый файл резервной копии WebDAV: ' . $full_url);
            } else {
                $delete_error_message = 'Не удалось удалить старый файл резервной копии WebDAV: ' . $full_url . ' - ' . $delete_http_code . ' - ' . $delete_curl_error;
                // webdav_wp_backup_log($delete_error_message);
            }
        }
        $backup_count--;
    }
}

// Function to list backups
function webdav_wp_backup_list_backups() {
    $backup_dir = WP_CONTENT_DIR . '/site-backups';

    if (!file_exists($backup_dir)) {
        echo 'No backups found.';
        return;
    }

    $files = array_diff(scandir($backup_dir), array('.', '..'));
    if (empty($files)) {
        echo 'No backups found.';
        return;
    }

    // Сортировка файлов по дате и времени
    usort($files, function($a, $b) use ($backup_dir) {
        return filemtime($backup_dir . '/' . $b) - filemtime($backup_dir . '/' . $a);
    });

    $backups = [];
    foreach ($files as $file) {
        $parts = explode('_', $file);
        if (count($parts) >= 4) {
            $timestamp = $parts[2] . '_' . $parts[3];
            $formatted_timestamp = substr($timestamp, 0, 8) . ' ' . substr($timestamp, 9, 2) . ':' . substr($timestamp, 11, 2);
            $backups[$formatted_timestamp][] = $file;
        }
    }

    if (empty($backups)) {
        echo 'No backups found.';
        return;
    }

    $index = 1;
    foreach ($backups as $timestamp => $backup_files) {
        $date = date('Y-m-d H:i:s', strtotime($timestamp));
        
        echo '<div class="backup-set">';
        echo '<h3>' . $index . '. Backup Set (' . $date . ')</h3>';
        echo '<ul>';
        foreach ($backup_files as $file) {
            $file_path = $backup_dir . '/' . $file;
            $file_size = round(filesize($file_path) / 1048576, 2); // Размер в MB
            $download_url = add_query_arg('webdav_wp_backup_download', urlencode($file), home_url());
            echo '<li>' . $file . ' (' . $file_size . ' MB) - <a href="' . esc_url($download_url) . '">Download</a></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '<hr>';
        $index++;
    }
}

// Function to get the next scheduled backup
function webdav_wp_backup_get_next_scheduled_backup() {
    $timestamp = wp_next_scheduled('automatic_backup_event');
    if ($timestamp) {
        // Используем дату WordPress вместо серверной даты
        return 'Next scheduled backup: ' . wp_date('Y-m-d H:i:s', $timestamp);
    } else {
        return 'No scheduled backups.';
    }
}

// Function to clean up data on plugin deactivation
function webdav_wp_backup_cleanup() {
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

    // Удаление запланированных заданий
    $timestamp = wp_next_scheduled('webdav_wp_backup_daily_event');
    while ($timestamp) {
        wp_unschedule_event($timestamp, 'webdav_wp_backup_daily_event');
        $timestamp = wp_next_scheduled('webdav_wp_backup_daily_event');
    }
}

// Register the cleanup function
register_deactivation_hook(__FILE__, 'webdav_wp_backup_cleanup');

// Function to cancel scheduled backups
function webdav_wp_backup_cancel_schedule() {
    $timestamp = wp_next_scheduled('automatic_backup_event');
    if ($timestamp) {
        while ($timestamp) {
            wp_unschedule_event($timestamp, 'automatic_backup_event');
            $timestamp = wp_next_scheduled('automatic_backup_event');
        }
        wp_send_json_success('Scheduled backups canceled.');
    } else {
        wp_send_json_error('No scheduled backups to cancel.');
    }
}

// AJAX action for canceling scheduled backups
add_action('wp_ajax_webdav_wp_backup_cancel_schedule', 'webdav_wp_backup_cancel_schedule');

?>
