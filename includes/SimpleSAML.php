<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * [SimpleSAML description]
 */
class SimpleSAML
{
    /**
     * Settings options
     * @var object
     */
    protected $options;

    /**
     * SimpleSAML file to include.
     * @var mixed string
     */
    protected $simplesamlInclude = '';

    /**
     * NULL or \SimpleSAML\Auth\Simple object
     * @var mixed null|\SimpleSAML\Auth\Simple
     */
    protected $authSimple;

    /**
     * Array of available Identity Providers.
     * @var array
     */
    protected $identityProviders = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = Options::getOptions();
    }

    /**
     * loaded method
     * @return void
     */
    public function loaded()
    {
        $error = $this->loadSimpleSAML();
        if (is_wp_error($error)) {
            $this->errorOnLoaded($error);
            return false;
        }
        $this->setAuthSimple()
            ->setIdentityProviders();
        return true; 
    }

    /**
     * Get NULL or \SimpleSAML\Auth\Simple object.
     * @return mixed null|\SimpleSAML\Auth\Simple object
     */
    public function getAuthSimple()
    {
        return $this->authSimple;
    }

    /**
     * Load/Instantiate \SimpleSAML\Auth\Simple class.
     * @return object This SimpleSAML object.
     */
    protected function setAuthSimple()
    {
        try {
            $authSimple = new \SimpleSAML\Auth\Simple($this->options->simplesaml_auth_source);
        } catch (\Exception $e) {
            $error = new \WP_Error('simplesaml_auth_error', $e->getMessage());
            $this->errorOnLoaded($error);
            $this->authSimple = null;
            return $this;
        }
        $this->authSimple = $authSimple;
        return $this;
    }

    /**
     * Get NULL or an array of available Identity Providers.
     * @return mixed null|array
     */
    public function getIdentityProviders()
    {
        return $this->identityProviders;
    }

    /**
     * Set the available Identity Providers list.
     * @return object This SimpleSAML object.
     */
    protected function setIdentityProviders()
    {
        if (!method_exists('\SimpleSAML\Configuration', 'getConfig')) {
            return $this;
        }

        try {
            // Get the authsources file, which should contain the config.
            $authsource = \SimpleSAML\Configuration::getConfig('authsources.php');
        } catch (\Exception $e) {
            $error = new \WP_Error('simplesaml_configuration_error', $e->getMessage());
            $this->errorOnLoaded($error);
            return $this;
        }

        // Get just the specified authsource config values.
        $authsource = $authsource->toArray();
        $idp = $authsource[$this->options->simplesaml_auth_source]['idp'] ?? 'null';

        $saml20IdpRemoteFile = dirname($this->simplesamlInclude, 2) . '/metadata/saml20-idp-remote.php';
        if (!file_exists($saml20IdpRemoteFile)) {
            return $this;
        }
        // Load $metadata array.
        require_once($saml20IdpRemoteFile);

        $metadata = $metadata ?? [];
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

        if ($idp && isset($idps[$idp])) {
            $idps = [
                $idp => $idps[$idp]
            ];
        }

        $this->identityProviders = $idps;
        return $this;
    }

    /**
     * Load SimpleSAML library.
     * @return mixed void|\WP_Error
     */
    private function loadSimpleSAML()
    {
        $this->simplesamlInclude = WP_CONTENT_DIR . $this->options->simplesaml_include;
        if (file_exists($this->simplesamlInclude)) {
            require_once($this->simplesamlInclude);
        } else {
            $this->simplesamlInclude = null;
            return new \WP_Error('simplesaml_could_not_be_loaded', __('The simpleSAML library could not be loaded.', 'rrze-sso'));
        }
    }

    /**
     * Error notice on loaded.
     * @param object $wpError \WP_Error
     * @return void
     */
    private function errorOnLoaded($wpError)
    {
        add_action('admin_init', function () use ($wpError) {
            if (current_user_can('activate_plugins')) {
                $error = $wpError->get_error_message();
                $pluginData = get_plugin_data(plugin()->getFile());
                $pluginName = $pluginData['Name'];
                $tag = is_plugin_active_for_network(plugin()->getBaseName()) ? 'network_admin_notices' : 'admin_notices';
                add_action($tag, function () use ($pluginName, $error) {
                    printf(
                        '<div class="notice notice-error"><p>' .
                            /* translators: 1: The plugin name, 2: The error string. */
                            __('Plugins: %1$s: %2$s', 'rrze-sso') .
                            '</p></div>',
                        esc_html($pluginName),
                        esc_html($error)
                    );
                });
            }
        });
    }

    /**
     * __call method
     * Method overloading.
     */
    public function __call(string $name, array $arguments)
    {
        if (!method_exists($this, $name)) {
            $message = sprintf('Call to undefined method %1$s::%2$s', __CLASS__, $name);
            do_action(
                'rrze.log.error',
                $message,
                [
                    'class' => __CLASS__,
                    'method' => $name,
                    'arguments' => $arguments
                ]
            );
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw new \Exception($message);
            }
        }
    }
}
