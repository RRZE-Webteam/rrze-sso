<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Options
{
    /**
     * Option group.
     * @var string
     */
    protected static $optionGroup = 'sso';

    /**
     * Option name.
     * @var string
     */
    protected static $optionName = 'rrze_sso';

    /**
     * Default options.
     * @return array
     */
    protected static function defaultOptions()
    {
        $options = [
            'simplesaml_include' => '/simplesamlphp/lib/_autoload.php',
            'simplesaml_auth_source' => 'default-sp',
            'force_sso' => 0,
            'domain_scope' => [],
            'allowed_user_email_domains' => '',
            'username_regex_pattern' => '',
        ];

        return $options;
    }

    /**
     * Returns the options.
     * @return object
     */
    public static function getOptions()
    {
        $defaults = self::defaultOptions();

        if (is_multisite()) {
            $options = (array) get_site_option(self::$optionName);
        } else {
            $options = (array) get_option(self::$optionName);
        }

        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return (object) $options;
    }

    /**
     * Returns the name of the option group.
     * @return string
     */
    public static function getOptionGroup()
    {
        return self::$optionGroup;
    }

    /**
     * Returns the name of the option.
     * @return string
     */
    public static function getOptionName()
    {
        return self::$optionName;
    }
}
