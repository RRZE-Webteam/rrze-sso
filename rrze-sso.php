<?php

/*
Plugin Name:        RRZE SSO
Plugin URI:         https://github.com/RRZE-Webteam/rrze-sso
Version:            1.7.2
Description:        Single-Sign-On (SSO) SAML-Integrations-Plugin für WordPress.
Author:             RRZE-Webteam
Author URI:         https://blogs.fau.de/webworking/
License:            GNU General Public License Version 3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:        rrze-sso
Domain Path:        languages
Requires at least:  6.8
Requires PHP:       8.2
*/

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * SPL Autoloader (PSR-4)
 * 
 * @param string $class The fully-qualified class name
 * @return void
 */
spl_autoload_register(function ($class) {
    $prefix = __NAMESPACE__;
    $baseDir = __DIR__ . '/includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Register activation hook for the plugin
register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');

// Register deactivation hook for the plugin
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');

/**
 * Add an action hook for the 'plugins_loaded' hook.
 *
 * This hook is triggered after all active plugins have been loaded, allowing the plugin to perform
 * initialization tasks.
 */
add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

/**
 * Activation callback function.
 */
function activation()
{
    // No special actions needed on activation currently.
}

/**
 * Deactivation callback function
 * 
 * @return void
 */
function deactivation()
{
    $optionGroup = Options::getOptionGroup();
    $optionName = Options::getOptionName();
    $options = Options::getOptions();
    unregister_setting($optionGroup, $optionName);
    if ($options->force_sso) {
        $options->force_sso = 0;
        $options = (array) $options;
        update_site_option($optionName, $options);
    }
}

/**
 * Instantiate Plugin class
 * 
 * @return object Plugin
 */
function plugin()
{
    static $instance;
    if (null === $instance) {
        $instance = new Plugin(__FILE__);
    }
    return $instance;
}

/**
 * Instantiate SimpleSAML class
 * 
 * @return object SimpleSAML
 */
function simpleSAML()
{
    static $instance;
    if (null === $instance) {
        $instance = new SimpleSAML();
    }
    return $instance;
}

/**
 * Callback function to load the plugin textdomain.
 * 
 * @return void
 */
function loadTextdomain()
{
    // Since WP 6.7.0, the text domain must be loaded using the 'init' action hook to avoid "doing_it_wrong" errors.
    // Suppress the "doing_it_wrong" error for loading textdomain just in time.
    // See: https://core.trac.wordpress.org/ticket/54411
    add_filter('doing_it_wrong_trigger_error', function ($trigger, $function) {
        return ($function === '_load_textdomain_just_in_time' && (doing_action('plugins_loaded') || did_action('plugins_loaded'))) ? false : $trigger;
    }, 10, 2);

    load_plugin_textdomain(
        'rrze-sso',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

/**
 * Handle the loading of the plugin.
 *
 * This function is responsible for initializing the plugin, loading text domains for localization,
 * checking system requirements, and displaying error notices if necessary.
 * 
 * @return void
 */
function loaded()
{
    // Trigger the 'loaded' method of the main plugin instance.
    plugin()->loaded();

    // Load the plugin's text domain for localization
    loadTextdomain();

    // Check system requirements.
    if (
        ! $wpCompatibe = is_wp_version_compatible(plugin()->getRequiresWP())
            || ! $phpCompatible = is_php_version_compatible(plugin()->getRequiresPHP())
    ) {
        // If the system requirements are not met, add an action to display an admin notice.
        add_action('admin_init', function () use ($wpCompatibe, $phpCompatible) {
            // Check if the current user has the capability to activate plugins.
            if (current_user_can('activate_plugins')) {
                // Determine the appropriate admin notice tag based on whether the plugin is network activated.
                $hookName = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';

                // Get the plugin name for display in the admin notice.
                $pluginName = plugin()->getName();

                $error = '';
                if (! $wpCompatibe) {
                    $error = sprintf(
                        /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
                        __('The server is running WordPress version %1$s. The plugin requires at least WordPress version %2$s.', 'rrze-sso'),
                        wp_get_wp_version(),
                        plugin()->getRequiresWP()
                    );
                } elseif (! $phpCompatible) {
                    $error = sprintf(
                        /* translators: 1: Server PHP version number, 2: Required PHP version number. */
                        __('The server is running PHP version %1$s. The plugin requires at least PHP version %2$s.', 'rrze-sso'),
                        PHP_VERSION,
                        plugin()->getRequiresPHP()
                    );
                }

                // Display the error notice in the admin area.
                // This will show a notice with the plugin name and the error message.
                add_action($hookName, function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' .
                            /* translators: 1: The plugin name, 2: The error string. */
                            esc_html__('Plugins: %1$s: %2$s', 'rrze-sso') .
                            '</p></div>',
                        $pluginName,
                        $error
                    );
                });
            }
        });

        // If the system requirements are not met, the plugin initialization will not proceed.
        return;
    }

    // If system requirements are met, proceed to initialize the main plugin instance.
    // This will load the main functionality of the plugin.
    (new Main)->loaded();
}
