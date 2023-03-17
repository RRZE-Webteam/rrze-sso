<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * [Utils description]
 */
class Utils
{
    /**
     * Get available IdPs list.
     * @param string $simplesamlInclude
     * @return mixed
     */
    public static function getIdps(string $simplesamlInclude)
    {
        if (!file_exists(WP_CONTENT_DIR . $simplesamlInclude)) {
            return null;
        }

        $saml20IdpRemoteFile = WP_CONTENT_DIR . explode('/lib/', $simplesamlInclude)[0] . '/metadata/saml20-idp-remote.php';
        if (!file_exists($saml20IdpRemoteFile)) {
            return null;
        }
        // Load $metadata array.
        require_once($saml20IdpRemoteFile);

        $locale = get_locale();
        $lang = explode('_', $locale)[0];
        $idps = [];
        foreach ($metadata as $key => $value) {
            if (isset($value['name'][$lang])) {
                $name = $value['name'][$lang];
            } elseif (isset($value['name']) && is_string($value['name'])) {
                $name = $value['name'];
            } else {
                $name = parse_url($key, PHP_URL_HOST);
            }
            $idps[$key] = $name;
        }

        return $idps;
    }

    /**
     * Log errors by writing to the debug.log file.
     */
    public static function debug($input, string $level = 'i')
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        if (in_array(strtolower((string) WP_DEBUG_LOG), ['true', '1'], true)) {
            $logPath = WP_CONTENT_DIR . '/debug.log';
        } elseif (is_string(WP_DEBUG_LOG)) {
            $logPath = WP_DEBUG_LOG;
        } else {
            return;
        }
        if (is_array($input) || is_object($input)) {
            $input = print_r($input, true);
        }
        switch (strtolower($level)) {
            case 'e':
            case 'error':
                $level = 'Error';
                break;
            case 'i':
            case 'info':
                $level = 'Info';
                break;
            case 'd':
            case 'debug':
                $level = 'Debug';
                break;
            default:
                $level = 'Info';
        }
        error_log(
            date("[d-M-Y H:i:s \U\T\C]")
                . " WP $level: "
                . basename(__FILE__) . ' '
                . $input
                . PHP_EOL,
            3,
            $logPath
        );
    }
}
