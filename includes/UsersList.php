<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

class UsersList
{
    public function loaded()
    {
        add_filter('manage_users_columns', [$this, 'columns']);
        add_action('manage_users_custom_column', [$this, 'organizationColumn'], 10, 3);
        add_action('manage_users_custom_column', [$this, 'attributesColumn'], 10, 3);
        add_filter('wpmu_users_columns', [$this, 'columns']);
        add_action('wpmu_users_custom_column', [$this, 'organizationColumn'], 10, 3);
        add_action('wpmu_users_custom_column', [$this, 'attributesColumn'], 10, 3);
    }

    public function columns($columns)
    {
        $columns['organization'] = __('Organization', 'rrze-sso');
        $columns['attributes'] = __('Attributes', 'rrze-sso');
        return $columns;
    }

    public function organizationColumn($value, $columnName, $userId)
    {
        if ('organization' != $columnName) {
            return $value;
        }

        $organizationName = get_user_meta($userId, 'organization_name', true);
        return $organizationName ?: '&mdash;';
    }

    public function attributesColumn($value, $columnName, $userId)
    {
        if ('attributes' != $columnName) {
            return $value;
        }

        $attributes = [];

        $eduPersonAffiliation = get_user_meta($userId, 'edu_person_affiliation', true);
        if ($eduPersonAffiliation) {
            $attributes[] = is_array($eduPersonAffiliation) ? implode('<br>', $eduPersonAffiliation) : $eduPersonAffiliation;
        }

        $eduPersonEntitlement = get_user_meta($userId, 'edu_person_entitlement', true);
        if ($eduPersonEntitlement) {
            $attributes[] = is_array($eduPersonEntitlement) ? implode('<br>', $eduPersonEntitlement) : $eduPersonEntitlement;
        }

        return $attributes ? implode('<br>', $attributes) : '&mdash;';
    }
}
