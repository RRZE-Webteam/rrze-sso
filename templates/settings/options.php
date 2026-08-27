<?php

namespace RRZE\SSO;

defined('ABSPATH') || exit;

/**
 * SSO settings page form.
 *
 * @var string $page_title  Page heading.
 * @var string $form_action Form submission URL. Empty for the current page.
 * @var string $option_group Settings API option group.
 */

?>
<div class="wrap">
    <h1><?php echo esc_html($page_title); ?></h1>
    <form method="post"<?php echo $form_action ? ' action="' . esc_url($form_action) . '"' : ''; ?>>
        <?php do_settings_sections($option_group); ?>
        <?php settings_fields($option_group); ?>
        <?php submit_button(); ?>
    </form>
</div>
