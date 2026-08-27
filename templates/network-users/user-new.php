<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Network administration form for adding a user.
 *
 * @var array<int, string>    $messages
 * @var \WP_Error|string      $add_user_errors
 * @var array<string, string> $identity_providers
 * @var string                $form_action
 */

?>
<div class="wrap">
    <h2 id="add-new-user"><?php esc_html_e('Add New User'); ?></h2>

    <?php foreach ($messages as $message) : ?>
        <div id="message" class="updated"><p><?php echo esc_html($message); ?></p></div>
    <?php endforeach; ?>

    <?php if (is_wp_error($add_user_errors)) : ?>
        <div class="error">
            <?php foreach ($add_user_errors->get_error_messages() as $message) : ?>
                <p><?php echo esc_html($message); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="<?php echo esc_url($form_action); ?>" id="adduser" method="post" novalidate="novalidate">
        <input type="hidden" name="action" value="_network_add-user" />
        <?php wp_nonce_field('add-user', '_wpnonce_add-user'); ?>

        <table class="form-table" role="presentation">
            <tr class="form-field form-required">
                <th scope="row"><?php esc_html_e('Identity Provider', 'rrze-sso'); ?></th>
                <td>
                    <select name="user[idp]">
                        <option value="">&mdash; <?php esc_html_e('Select an Identity Provider', 'rrze-sso'); ?> &mdash;</option>
                        <?php foreach ($identity_providers as $key => $value) : ?>
                            <option value="<?php echo esc_attr(sanitize_title($key)); ?>"><?php echo esc_html($value); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr class="form-field form-required">
                <th scope="row">
                    <label for="username"><?php esc_html_e('User Identifier', 'rrze-sso'); ?></label>
                </th>
                <td>
                    <input type="text" class="regular-text" name="user[username]" id="username" autocapitalize="none" autocorrect="off" maxlength="60" />
                </td>
            </tr>
            <tr class="form-field form-required">
                <th scope="row"><label for="email"><?php esc_html_e('Email'); ?></label></th>
                <td><input type="email" class="regular-text" name="user[email]" id="email" /></td>
            </tr>
        </table>

        <?php submit_button(__('Add User'), 'primary', 'add-user'); ?>
    </form>
</div>
