<?php
/**
 * Plugin Name: Secrets
 * Plugin URI: https://runthings.dev
 * Description: Share secrets securely
 * Version: 0.5.0
 * Author: Matthew Harris, runthings.dev
 * Author URI: https://runthings.dev/
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Requires at least: 5.8
 * Requires PHP: 7.0
 */
/*
Copyright 2023 Matthew Harris

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 3, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if (!defined('WPINC')) {
    die;
}

define('RUNTHINGS_SECRETS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RUNTHINGS_SECRETS_PLUGIN_DIR_INCLUDES', plugin_dir_path(__FILE__) . "includes/");

class runthings_secrets_Plugin
{
    const VERSION = '0.5.0';

    protected static $single_instance = null;

    protected function __construct()
    {
        include RUNTHINGS_SECRETS_PLUGIN_DIR_INCLUDES . 'options/runthings-secrets-options-page.php';
        include RUNTHINGS_SECRETS_PLUGIN_DIR_INCLUDES . 'integration/runthings-secrets-integration.php';
        include RUNTHINGS_SECRETS_PLUGIN_DIR_INCLUDES . 'render/runthings-secrets-template-loader.php';
    }

    public static function get_instance()
    {
        if (self::$single_instance === null) {
            self::$single_instance = new self();
        }

        return self::$single_instance;
    }

    public function hooks()
    {
        add_action('init', [$this, 'init'], ~PHP_INT_MAX);
    }

    public function init()
    {
        $this->load_textdomain();

        add_filter('plugin_action_links_runthings-secrets/runthings-secrets.php', [$this, 'add_settings_link']);

        add_action('init', [$this, 'schedule_clear_expired_secrets']);
        add_action('runthings_secrets_clear_expired_secrets', array($this, 'clear_expired_secrets'));
    }

    public function activate()
    {
        $this->activate_database();
        $this->activate_options();
    }

    public function deactivate()
    {
        $this->deactivate_scheduled_tasks();
    }
    
    public function load_textdomain()
    {
        load_plugin_textdomain('runthings-secrets', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_settings_link($links)
    {
        $settings_link = '<a href="options-general.php?page=runthings-secrets">' . __('Settings', 'runthings-secrets') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function clear_expired_secrets()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'runthings_secrets';

        $current_time = current_time('timestamp');

        // calculate the expiration time (24 hours ago)
        $expiration_time = $current_time - (24 * 60 * 60);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE expiration_time <= %d",
                $expiration_time
            )
        );
    }

    public function schedule_clear_expired_secrets()
    {
        if (!wp_next_scheduled('runthings_secrets_clear_expired_secrets')) {
            wp_schedule_event(time(), 'daily', 'runthings_secrets_clear_expired_secrets');
        }
    }

    private function activate_database()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'runthings_secrets';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
          id int(11) NOT NULL AUTO_INCREMENT,
          uuid varchar(255) NOT NULL,
          secret text NOT NULL,
          max_views int(11) NOT NULL,
          views int(11) NOT NULL,
          expiration datetime NOT NULL,
          created_at datetime NOT NULL,
          PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('runthings_secrets_db_version', self::VERSION);
    }

    private function activate_options()
    {
        add_option('runthings_secrets_enqueue_form_styles', 1, '', 'no');
        add_option('runthings_secrets_stats_total_secrets', 0, '', 'no');
        add_option('runthings_secrets_stats_total_views', 0, '', 'no');
        add_option('runthings_secrets_recaptcha_score', 0.5, '', 'no');
    }

    private function deactivate_scheduled_tasks()
    {
        $tasks = array('runthings_secrets_clear_expired_secrets');
        foreach ($tasks as $task) {
            wp_clear_scheduled_hook($task);
        }
    }
}

if (!function_exists('runthings_secrets_uninstall')) {
    function runthings_secrets_uninstall()
    {
        // delete plugin options
        $options = array(
            'runthings_secrets_db_version',
            'runthings_secrets_first_run_completed',
            'runthings_secrets_add_page',
            'runthings_secrets_created_page',
            'runthings_secrets_view_page',
            'runthings_secrets_recaptcha_enabled',
            'runthings_secrets_recaptcha_public_key',
            'runthings_secrets_recaptcha_private_key',
            'runthings_secrets_recaptcha_score',
            'runthings_secrets_enqueue_form_styles',
            'runthings_secrets_stats_total_secrets',
            'runthings_secrets_stats_total_views',
            'runthings_secrets_encryption_key',
        );
        foreach ($options as $option) {
            delete_option($option);
        }

        // drop all plugin tables
        global $wpdb;
        $tables = array(
            'runthings_secrets'
        );
        foreach ($tables as $table) {
            $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . $table);
        }
    }
}

if (!function_exists('runthings_secrets')) {
	function runthings_secrets() {
		return runthings_secrets_Plugin::get_instance();
	}
}

// start
add_action('plugins_loaded', array(runthings_secrets(), 'hooks'));

// activation and deactivation hooks
register_activation_hook(__FILE__, array(runthings_secrets(), 'activate'));
register_deactivation_hook(__FILE__, array(runthings_secrets(), 'deactivate'));
register_uninstall_hook(__FILE__, 'runthings_secrets_uninstall');