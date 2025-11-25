jQuery(document).ready(function ($) {
    function showNotification(message, color, target) {
        console.log('Show notification:', message, color, target);  // Debug message
        $(target).html('<div class="notification" style="color: ' + color + ';">' + message + '</div>');
    }

    $('#test-webdav-connection').on('click', function () {
        console.log('Test WebDAV Connection clicked');  // Debug message
        showNotification('Please wait, processing...', 'black', '#webdav-connection-notifications');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'webdav_wp_backup_test_webdav'
            },
            success: function (response) {
                console.log('WebDAV response:', response);  // Debug message
                if (response.success) {
                    showNotification('WebDAV connection successful!', 'green', '#webdav-connection-notifications');
                } else {
                    showNotification('WebDAV connection failed: ' + response.data, 'red', '#webdav-connection-notifications');
                }
            },
            error: function (error) {
                console.error('WebDAV AJAX error:', error);  // Debug message
                showNotification('An error occurred while testing the WebDAV connection.', 'red', '#webdav-connection-notifications');
            }
        });
    });

    $('#manual-backup').on('click', function () {
        console.log('Manual Backup clicked');  // Debug message
        showNotification('Please wait, processing...', 'black', '#webdav-wp-backup-main-notifications');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'webdav_wp_backup_manual'
            },
            success: function (response) {
                console.log('Manual Backup response:', response);  // Debug message
                if (response.success) {
                    var message = 'Manual backup created successfully!<br>' +
                                  'Location: ' + response.data.location + '<br>' +
                                  'Size: ' + response.data.size + ' MB<br>' +
                                  'Time: ' + response.data.time;
                    showNotification(message, 'green', '#webdav-wp-backup-main-notifications');
                    setTimeout(function() {
                        location.reload();
                    }, 7000);  // Page reload in 7 seconds
                } else {
                    // Static message for WebDAV error
                    var errorMessage = 'Backup created locally, but failed to upload to WebDAV.<br>' +
                                       'Please check WebDAV settings on the settings page.';
                    showNotification(errorMessage, '#FD7A25', '#webdav-wp-backup-main-notifications');  // Dark orange color
                    setTimeout(function() {
                        location.reload();
                    }, 7000);  // Page reload in 7 seconds
                }
            },
            error: function (error) {
                console.error('Manual Backup AJAX error:', error);  // Debug message
                showNotification('An error occurred while creating the manual backup.', 'red', '#webdav-wp-backup-main-notifications');
            }
        });
    });

    $('#schedule-backup').on('click', function () {
        showNotification('Please wait, processing...', 'black', '#webdav-wp-backup-main-notifications');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'webdav_wp_backup_schedule'
            },
            success: function (response) {
                if (response.success) {
                    showNotification('Scheduled backup created successfully!', 'green', '#webdav-wp-backup-main-notifications');
                    setTimeout(function() {
                        location.reload();
                    }, 5000);  // Page reload in 5 seconds
                } else {
                    showNotification('Failed to create scheduled backup: ' + response.data, 'red', '#webdav-wp-backup-main-notifications');
                }
            },
            error: function (error) {
                console.error('Scheduled backup AJAX error:', error);
                showNotification('An error occurred while scheduling the backup.', 'red', '#webdav-wp-backup-main-notifications');
            }
        });
    });

    $('#cancel-scheduled-backup').on('click', function () {
        showNotification('Please wait, processing...', 'black', '#webdav-wp-backup-main-notifications');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'webdav_wp_backup_cancel_schedule'
            },
            success: function (response) {
                if (response.success) {
                    showNotification('Scheduled backups canceled.', 'green', '#webdav-wp-backup-main-notifications');
                    setTimeout(function() {
                        location.reload();
                    }, 5000);  // Page reload in 5 seconds
                } else {
                    showNotification('No scheduled backups to cancel.', 'red', '#webdav-wp-backup-main-notifications');
                }
            },
            error: function (error) {
                console.error('Cancel scheduled backup AJAX error:', error);
                showNotification('An error occurred while canceling scheduled backups.', 'red', '#webdav-wp-backup-main-notifications');
            }
        });
    });
});
