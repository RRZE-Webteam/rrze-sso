<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class Helper
{
    public static function getCurrentUserSamlAtts()
    {
        $options = Options::getOptions();
        if (!$options->force_sso) {
            return new \WP_Error('sso_is_not_activated', 'SSO is not activated');
        }

        $authSimple = simpleSAML()->getAuthSimple();
        if (!is_a($authSimple, '\SimpleSAML\Auth\Simple')) {
            return new \WP_Error('unable_to_instantiate_simplesaml_auth', 'Unable to instantiate \SimpleSAML\Auth\Simple');
        }

        if (!$authSimple->isAuthenticated()) {
            return new \WP_Error('user_not_authenticated', 'User is not authenticated');
        }

        // Retrieve the attributes of the authenticated user.
        if (empty($_atts = $authSimple->getAttributes())) {
            return new \WP_Error('unable_to_retrieve_user_attributes', 'Unable to retrieve user attributes');
        }

        // The entityID of the IdP the user is authenticated against.
        $samlSpIdp = $authSimple->getAuthData('saml:sp:IdP');

        $atts = [];

        foreach ($_atts as $key => $value) {
            $_keyAry = explode(':', $key);
            $_key = $_keyAry[array_key_last($_keyAry)];
            if (
                is_array($value)
                && in_array(
                    $_key,
                    [
                        'uid',
                        'subject-id',
                        'eduPersonUniqueId',
                        'eduPersonPrincipalName',
                        'mail',
                        'displayName',
                        'cn',
                        'sn',
                        'givenName',
                        'o'
                    ]
                )
            ) {
                $atts[$_key] = $value[0];
            } else {
                $atts[$key] = $value;
            }
        }

        $domainScope = '';
        $identityProviders = simpleSAML()->getIdentityProviders();

        $userLogin = $atts['uid'] ?? '';
        $subjectId = $atts['subject-id'] ?? $atts['eduPersonUniqueId'] ?? $atts['eduPersonPrincipalName'] ?? '';
        $userLogin = $userLogin ?: explode('@', $subjectId)[0];

        foreach (array_keys($identityProviders) as $key) {
            $key = sanitize_title($key);
            $domainScope = $options->domain_scope[$key] ?? '';
            $domainScope = $domainScope ? '@' . $domainScope : $domainScope;
            if (sanitize_title($samlSpIdp) == $key) {
                $userLogin = $userLogin . $domainScope;
                break;
            }
        }

        $atts['wp_user_login'] = $userLogin;

        return $atts;
    }
}
