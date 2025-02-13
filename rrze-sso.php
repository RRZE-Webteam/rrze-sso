<?php

/*
Plugin Name:        RRZE SSO
Plugin URI:         https://github.com/RRZE-Webteam/rrze-sso
Version:            1.6.11
Description:        Single-Sign-On (SSO) SAML-Integrations-Plugin für WordPress.
Author:             RRZE-Webteam
Author URI:         https://blogs.fau.de/webworking/
License:            GNU General Public License Version 3
License URI:        https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:        rrze-sso
Domain Path:        /languages
Requires at least:  6.7
Requires PHP:       8.2
*/

namespace RRZE\SSO;

defined('ABSPATH') || exit;

const RRZE_PHP_VERSION = '8.2';
const RRZE_WP_VERSION = '6.5';

/**
 * SPL Autoloader (PSR-4).
 * @param string $class The fully-qualified class name.
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

// Register plugin hooks.
register_activation_hook(__FILE__, __NAMESPACE__ . '\activation');
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\deactivation');

add_action('plugins_loaded', __NAMESPACE__ . '\loaded');

// Load the plugin's text domain for localization.
add_action('init', fn() => load_plugin_textdomain('rrze-sso', false, dirname(plugin_basename(__FILE__)) . '/languages'));

/**
 * Activation callback function.
 */
function activation()
{
    //
}

/**
 * Deactivation callback function.
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
 * Instantiate Plugin class.
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
 * Instantiate SimpleSAML class.
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
 * Check system requirements for the plugin.
 *
 * This method checks if the server environment meets the minimum WordPress and PHP version requirements
 * for the plugin to function properly.
 *
 * @return string An error message string if requirements are not met, or an empty string if requirements are satisfied.
 */
function systemRequirements(): string
{
    // Get the global WordPress version.
    global $wp_version;

    // Get the PHP version.
    $phpVersion = phpversion();

    // Initialize an error message string.
    $error = '';

    // Check if the WordPress version is compatible with the plugin's requirement.
    if (!is_wp_version_compatible(plugin()->getRequiresWP())) {
        $error = sprintf(
            /* translators: 1: Server WordPress version number, 2: Required WordPress version number. */
            __('The server is running WordPress version %1$s. The Plugin requires at least WordPress version %2$s.', 'rrze-sso'),
            $wp_version,
            plugin()->getRequiresWP()
        );
    } elseif (!is_php_version_compatible(plugin()->getRequiresPHP())) {
        // Check if the PHP version is compatible with the plugin's requirement.
        $error = sprintf(
            /* translators: 1: Server PHP version number, 2: Required PHP version number. */
            __('The server is running PHP version %1$s. The Plugin requires at least PHP version %2$s.', 'rrze-sso'),
            $phpVersion,
            plugin()->getRequiresPHP()
        );
    }

    // Return the error message string, which will be empty if requirements are satisfied.
    return $error;
}

/**
 * Handle the loading of the plugin.
 *
 * This function is responsible for initializing the plugin, loading text domains for localization,
 * checking system requirements, and displaying error notices if necessary.
 */
function loaded()
{
    // Trigger the 'loaded' method of the main plugin instance.
    plugin()->loaded();

    // Check system requirements and store any error messages.
    if (systemRequirements()) {
        // If there is an error, add an action to display an admin notice with the error message.
        add_action('admin_init', function () {
            $error = systemRequirements();
            // Check if the current user has the capability to activate plugins.
            if (current_user_can('activate_plugins')) {
                // Get plugin data to retrieve the plugin's name.
                $pluginName = plugin()->getName();

                // Determine the admin notice tag based on network-wide activation.
                $tag = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';

                // Add an action to display the admin notice.
                add_action($tag, function () use ($pluginName, $error) {
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

        // Return to prevent further initialization if there is an error.
        return;
    }

    // If there are no errors, create an instance of the 'Main' class and trigger its 'loaded' method.
    (new Main)->loaded();
}
