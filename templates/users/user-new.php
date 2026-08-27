<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * Site administration forms for adding existing and new users.
 *
 * @var bool                  $is_multisite
 * @var bool                  $can_create_users
 * @var bool                  $can_promote_users
 * @var bool                  $can_manage_network_users
 * @var bool                  $is_super_admin
 * @var bool                  $do_both
 * @var array<int, string>    $messages
 * @var \WP_Error|string      $add_user_errors
 * @var array<string, string> $identity_providers
 * @var string                $form_action
 * @var string                $existing_user_label
 * @var string                $existing_user_input_type
 * @var string                $new_user_idp
 * @var string                $new_user_login
 * @var string                $new_user_email
 * @var string                $new_user_role
 * @var bool                  $new_user_send_notification
 * @var string                $new_user_ignore_pass
 */

?>
<div class="wrap">
    <h2 id="add-new-user">
        <?php if ($can_create_users) : ?>
            <?php esc_html_e('Add New User', 'rrze-sso'); ?>
        <?php elseif ($can_promote_users) : ?>
            <?php esc_html_e('Add Existing User', 'rrze-sso'); ?>
        <?php endif; ?>
    </h2>

    <?php foreach ($messages as $message) : ?>
        <div id="message" class="updated"><p><?php echo wp_kses_post($message); ?></p></div>
    <?php endforeach; ?>

    <?php if (is_wp_error($add_user_errors)) : ?>
        <div class="error">
            <?php foreach ($add_user_errors->get_error_messages() as $message) : ?>
                <p><?php echo esc_html($message); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div id="ajax-response"></div>

    <?php if ($is_multisite && $can_promote_users) : ?>
        <?php if ($do_both) : ?>
            <h2 id="add-existing-user"><?php esc_html_e('Add Existing User'); ?></h2>
        <?php endif; ?>

        <?php if ($can_manage_network_users) : ?>
            <p><?php esc_html_e('Enter the email address or username of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.'); ?></p>
        <?php else : ?>
            <p><?php esc_html_e('Enter the email address of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.'); ?></p>
        <?php endif; ?>

        <form action="<?php echo esc_url($form_action); ?>" method="post" name="adduser" id="adduser" class="validate" novalidate="novalidate">
            <input type="hidden" name="action" value="_admin_add-user" />
            <?php wp_nonce_field('add-user', '_wpnonce_add-user'); ?>

            <table class="form-table" role="presentation">
                <tr class="form-field form-required">
                    <th scope="row"><label for="adduser-email"><?php echo esc_html($existing_user_label); ?></label></th>
                    <td><input name="email" type="<?php echo esc_attr($existing_user_input_type); ?>" id="adduser-email" class="wp-suggest-user" value="" /></td>
                </tr>
                <tr class="form-field">
                    <th scope="row"><label for="adduser-role"><?php esc_html_e('Role'); ?></label></th>
                    <td>
                        <select name="role" id="adduser-role">
                            <?php wp_dropdown_roles(get_option('default_role')); ?>
                        </select>
                    </td>
                </tr>
                <?php if ($is_super_admin) : ?>
                    <tr>
                        <th scope="row"><label for="adduser-noconfirmation"><?php esc_html_e('Skip Confirmation Email'); ?></label></th>
                        <td>
                            <label for="adduser-noconfirmation">
                                <input type="checkbox" name="noconfirmation" id="adduser-noconfirmation" value="1" />
                                <?php esc_html_e('Add the user without sending an email that requires their confirmation.'); ?>
                            </label>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <?php submit_button(__('Add Existing User'), 'primary', 'adduser', true, array('id' => 'addusersub')); ?>
        </form>
    <?php endif; ?>

    <?php if ($can_create_users) : ?>
        <?php if ($do_both) : ?>
            <h3 id="create-new-user"><?php esc_html_e('Add New User'); ?></h3>
        <?php endif; ?>

        <p><?php esc_html_e('Create a brand new user and add them to this site.'); ?></p>
        <form action="<?php echo esc_url($form_action); ?>" method="post" name="createuser" id="createuser" class="validate" novalidate="novalidate">
            <input type="hidden" name="action" value="_admin_create-user" />
            <?php wp_nonce_field('create-user', '_wpnonce_create-user'); ?>

            <table class="form-table" role="presentation">
                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="user_idp">
                            <?php esc_html_e('Identity Provider', 'rrze-sso'); ?>
                            <span class="description"><?php esc_html_e('(required)'); ?></span>
                        </label>
                    </th>
                    <td>
                        <select id="user_idp" name="user_idp">
                            <option value="">&mdash; <?php esc_html_e('Select an Identity Provider', 'rrze-sso'); ?> &mdash;</option>
                            <?php foreach ($identity_providers as $key => $value) : ?>
                                <?php $key = sanitize_title($key); ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($new_user_idp, $key); ?>><?php echo esc_html($value); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="user_login">
                            <?php esc_html_e('User Identifier', 'rrze-sso'); ?>
                            <span class="description"><?php esc_html_e('(required)'); ?></span>
                        </label>
                    </th>
                    <td><input name="user_login" type="text" id="user_login" value="<?php echo esc_attr($new_user_login); ?>" aria-required="true" /></td>
                </tr>
                <tr class="form-field form-required">
                    <th scope="row">
                        <label for="email">
                            <?php esc_html_e('Email'); ?>
                            <span class="description"><?php esc_html_e('(required)'); ?></span>
                        </label>
                    </th>
                    <td><input name="email" type="email" id="email" value="<?php echo esc_attr($new_user_email); ?>" /></td>
                </tr>
                <?php if (!$is_multisite) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Send User Notification'); ?></th>
                        <td>
                            <input type="checkbox" name="send_user_notification" id="send_user_notification" value="1" <?php checked($new_user_send_notification); ?> />
                            <label for="send_user_notification"><?php esc_html_e('Send the new user an email about their account.'); ?></label>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if ($can_promote_users) : ?>
                    <tr class="form-field">
                        <th scope="row"><label for="role"><?php esc_html_e('Role'); ?></label></th>
                        <td>
                            <select name="role" id="role">
                                <?php wp_dropdown_roles($new_user_role); ?>
                            </select>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if ($is_multisite && $is_super_admin) : ?>
                    <tr>
                        <th scope="row"><label for="noconfirmation"><?php esc_html_e('Skip Confirmation Email'); ?></label></th>
                        <td>
                            <label for="noconfirmation">
                                <input type="checkbox" name="noconfirmation" id="noconfirmation" value="1" <?php checked($new_user_ignore_pass); ?> />
                                <?php esc_html_e('Add the user without sending an email that requires their confirmation.'); ?>
                            </label>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>

            <?php submit_button(__('Add New User'), 'primary', 'createuser', true, array('id' => 'createusersub')); ?>
        </form>
    <?php endif; ?>
</div>
