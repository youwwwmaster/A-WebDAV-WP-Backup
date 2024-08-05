<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Admin interface functions

function webdav_wp_backup_settings_page() {
    ?>
    <div class="wrap">
        <h1>WebDAV WP Backup</h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=webdav-wp-backup&tab=main" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'main' ? 'nav-tab-active' : ''; ?>">Main</a>
            <a href="?page=webdav-wp-backup&tab=settings" class="nav-tab <?php echo isset($_GET['tab']) && $_GET['tab'] == 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
        </h2>
        <div id="webdav-wp-backup-main-notifications"></div>
        <?php
        $tab = isset($_GET['tab']) ? $_GET['tab'] : 'main';
        if ($tab == 'main') {
            webdav_wp_backup_main_page();
        } else {
            webdav_wp_backup_settings_form();
        }
        ?>
    </div>
    <?php
}

function webdav_wp_backup_main_page() {
    ?>
    <div id="webdav-wp-backup-main">
        <h2>Backup Actions</h2>
        <div id="webdav-wp-backup-main-notifications"></div>
        <p><strong><?php echo webdav_wp_backup_get_next_scheduled_backup(); ?></strong></p>
        <button id="manual-backup" class="button button-primary">Create Manual Backup</button>
        <button id="schedule-backup" class="button">Run Scheduled Backup</button>
        <button id="cancel-scheduled-backup" class="button">Cancel Scheduled Backups</button>
        <h2>Local Backups</h2>
        <div id="webdav-wp-backup-list">
            <?php webdav_wp_backup_list_backups(); ?>
        </div>
        <h2>About WebDAV WP Backup</h2>
        <p>WebDAV WP Backup is a free plugin designed to help you back up your WordPress site using WebDAV. The plugin will always be free. If you like the plugin and want to support its development, you can buy the author a coffee!</p>
        <form action="https://buy.stripe.com/test_dR6aFw2TtbV53OcaEE" method="POST">
            <button type="submit" class="button button-primary">Buy me a coffee</button>
        </form>
        <form action="https://buy.stripe.com/test_8wM8y42Tt9dK0FGdQT" method="POST">
            <button type="submit" class="button">Subscribe for monthly coffee</button>
        </form>
    </div>
    <?php
}

function webdav_wp_backup_settings_form() {
    ?>
    <form id="webdav-wp-backup-settings-form" method="post" action="options.php" autocomplete="off">
        <?php settings_fields('webdav_wp_backup_settings_group'); ?>
        <?php wp_nonce_field('webdav_wp_backup_settings', 'webdav_wp_backup_settings_nonce'); ?>
        <?php do_settings_sections('webdav_wp_backup_settings_group'); ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Backup Frequency (in hours)</th>
                <td><input type="number" name="webdav_wp_backup_frequency" value="<?php echo esc_attr(get_option('webdav_wp_backup_frequency', 24)); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Delay Start (in hours)</th>
                <td><input type="number" name="webdav_wp_backup_delay_start" value="<?php echo esc_attr(get_option('webdav_wp_backup_delay_start', 0)); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Local Backups to Keep</th>
                <td><input type="number" name="webdav_wp_backup_local_keep" value="<?php echo esc_attr(get_option('webdav_wp_backup_local_keep', '')); ?>" min="0" max="999" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Remote Backups to Keep</th>
                <td><input type="number" name="webdav_wp_backup_remote_keep" value="<?php echo esc_attr(get_option('webdav_wp_backup_remote_keep', 2)); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Notification Email</th>
                <td><input type="email" name="webdav_wp_backup_email" value="<?php echo esc_attr(get_option('webdav_wp_backup_email', get_option('admin_email'))); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row">Email Subject Prefix</th>
                <td><input type="text" name="webdav_wp_backup_email_prefix" value="<?php echo esc_attr(get_option('webdav_wp_backup_email_prefix', get_bloginfo('name'))); ?>" /></td>
            </tr>
            <tr valign="top">
                <th colspan="2"><h3>WebDAV Settings</h3></th>
            </tr>
            <tr valign="top">
                <th scope="row">Remote Storage URL</th>
                <td><input type="text" name="webdav_wp_backup_remote_url" value="<?php echo esc_attr(get_option('webdav_wp_backup_remote_url')); ?>" /></td>
            </tr>
            <tr valign="top">
    <th scope="row">Username</th>
    <td><input type="text" name="webdav_wp_backup_username" value="<?php echo esc_attr(get_option('webdav_wp_backup_username')); ?>" autocomplete="off" /></td>
</tr>
<tr valign="top">
    <th scope="row">Password</th>
    <td><input type="password" name="webdav_wp_backup_password" value="<?php echo esc_attr(get_option('webdav_wp_backup_password')); ?>" autocomplete="new-password" /></td>
</tr>
        </table>
        <p>Please save your settings before testing the connection to the remote server.</p>
        <?php submit_button(); ?>
    </form>
    <div id="webdav-connection-notifications"></div>
    <button type="button" class="button" id="test-webdav-connection">Test WebDAV Connection</button>
    <?php
}

function webdav_wp_backup_register_settings() {
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_remote_url');
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_username');
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_password');
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_frequency', ['default' => 24]);
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_delay_start', ['default' => 0]);
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_local_keep', ['default' => 1]);
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_remote_keep', ['default' => 2]);
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_email', ['default' => get_option('admin_email')]);
    register_setting('webdav_wp_backup_settings_group', 'webdav_wp_backup_email_prefix', ['default' => get_bloginfo('name')]);
}

add_action('admin_init', 'webdav_wp_backup_register_settings');
