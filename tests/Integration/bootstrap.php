<?php

/**
 * Boots the real local WordPress and SimpleSAMLphp installations.
 *
 * Environment variables can override every inferred local path and URL:
 * RRZE_SSO_WP_ROOT, RRZE_SSO_SAML_ROOT, RRZE_SSO_SP_CONFIG,
 * RRZE_SSO_IDP_CONFIG, RRZE_SSO_WP_URL, and RRZE_SSO_IDP_URL.
 */

$pluginRoot = dirname(__DIR__, 2);
$wpRoot = getenv('RRZE_SSO_WP_ROOT') ?: dirname($pluginRoot, 3);
$samlRoot = getenv('RRZE_SSO_SAML_ROOT') ?: dirname($wpRoot) . '/simplesaml';
$spConfig = getenv('RRZE_SSO_SP_CONFIG') ?: $samlRoot . '/environments/local-sp/config';
$idpConfig = getenv('RRZE_SSO_IDP_CONFIG') ?: $samlRoot . '/environments/local-idp';
$wpUrl = rtrim(getenv('RRZE_SSO_WP_URL') ?: 'https://multisite.localhost:8890', '/');
$idpUrl = rtrim(getenv('RRZE_SSO_IDP_URL') ?: 'https://idp-simplesaml:8890', '/');

$requiredPaths = array(
    'WordPress bootstrap' => $wpRoot . '/wp-load.php',
    'SimpleSAMLphp SP configuration' => $spConfig . '/config.php',
    'SimpleSAMLphp IdP authentication sources' => $idpConfig . '/config/authsources.php',
);

foreach ($requiredPaths as $label => $path) {
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('%s was not found at %s.', $label, $path));
    }
}

putenv('SIMPLESAMLPHP_CONFIG_DIR=' . $spConfig);
$_ENV['SIMPLESAMLPHP_CONFIG_DIR'] = $spConfig;
$_SERVER['SIMPLESAMLPHP_CONFIG_DIR'] = $spConfig;

$wpParts = parse_url($wpUrl);
$wpHost = (string) ($wpParts['host'] ?? 'multisite.localhost');
$wpPort = (int) ($wpParts['port'] ?? 443);
$_SERVER['HTTP_HOST'] = $wpHost . (443 === $wpPort ? '' : ':' . $wpPort);
$_SERVER['SERVER_NAME'] = $wpHost;
$_SERVER['SERVER_PORT'] = (string) $wpPort;
$_SERVER['HTTPS'] = 'on';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

defined('WP_USE_THEMES') || define('WP_USE_THEMES', false);

define('RRZE_SSO_INTEGRATION_PLUGIN_ROOT', $pluginRoot);
define('RRZE_SSO_INTEGRATION_WP_ROOT', $wpRoot);
define('RRZE_SSO_INTEGRATION_SAML_ROOT', $samlRoot);
define('RRZE_SSO_INTEGRATION_SP_CONFIG', $spConfig);
define('RRZE_SSO_INTEGRATION_IDP_CONFIG', $idpConfig);
define('RRZE_SSO_INTEGRATION_WP_URL', $wpUrl);
define('RRZE_SSO_INTEGRATION_IDP_URL', $idpUrl);

// Keep this process focused on rrze-sso. WordPress supports preinitialized
// filters, so unrelated local plugins cannot affect the integration bootstrap.
$GLOBALS['wp_filter']['site_option_active_sitewide_plugins'][PHP_INT_MIN][] = array(
    'function' => static function ($plugins): array {
        $plugins = (array) $plugins;

        return isset($plugins['rrze-sso/rrze-sso.php'])
            ? array('rrze-sso/rrze-sso.php' => $plugins['rrze-sso/rrze-sso.php'])
            : array();
    },
    'accepted_args' => 1,
);
$GLOBALS['wp_filter']['option_active_plugins'][PHP_INT_MIN][] = array(
    'function' => static fn(): array => array(),
    'accepted_args' => 1,
);

require $wpRoot . '/wp-load.php';
