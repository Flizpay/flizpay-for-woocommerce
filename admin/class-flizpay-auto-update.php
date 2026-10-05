<?php

/**
 * Opt-in prompt for WordPress automatic updates of FLIZpay.
 *
 * WordPress.org does not allow plugins to switch on auto-updates by themselves, so this
 * only asks: an administrator either enables them with one click or dismisses the prompt.
 *
 * @package    Flizpay
 * @subpackage Flizpay/admin
 */
class Flizpay_Auto_Update
{
    private const DISMISSED_META = 'flizpay_auto_update_notice_dismissed';

    public function render_admin_notice()
    {
        $result = isset($_GET['flizpay_auto_updates']) ? sanitize_key(wp_unslash($_GET['flizpay_auto_updates'])) : '';
        if ($result === 'enabled') {
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                esc_html__('Automatic updates for FLIZpay are enabled.', 'flizpay-for-woocommerce')
            );
            return;
        }

        if (!$this->should_prompt()) {
            return;
        }
        ?>
        <div class="notice notice-info">
            <p><?php echo esc_html__('Enable automatic updates so FLIZpay always runs the latest fixes and improvements.', 'flizpay-for-woocommerce'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="flizpay_auto_update">
                <?php wp_nonce_field('flizpay_auto_update'); ?>
                <p>
                    <button type="submit" name="choice" value="enable" class="button button-primary">
                        <?php echo esc_html__('Enable automatic updates', 'flizpay-for-woocommerce'); ?>
                    </button>
                    <button type="submit" name="choice" value="dismiss" class="button button-link" style="margin-left: 16px; font-size: 12px;">
                        <?php echo esc_html__("Don't ask again", 'flizpay-for-woocommerce'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    public function handle_choice()
    {
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('You are not allowed to manage plugin updates.', 'flizpay-for-woocommerce'), 403);
        }
        check_admin_referer('flizpay_auto_update');

        $choice = isset($_POST['choice']) ? sanitize_key(wp_unslash($_POST['choice'])) : '';
        $redirect = remove_query_arg('flizpay_auto_updates', wp_get_referer() ?: admin_url('plugins.php'));

        if ($choice === 'enable') {
            $plugins = (array) get_site_option('auto_update_plugins', array());
            $plugins[] = $this->plugin_basename();
            update_site_option('auto_update_plugins', array_values(array_unique($plugins)));
            $redirect = add_query_arg('flizpay_auto_updates', 'enabled', $redirect);
        } else {
            update_user_meta(get_current_user_id(), self::DISMISSED_META, 1);
        }

        wp_safe_redirect($redirect);
        exit;
    }

    private function should_prompt()
    {
        // Multisite manages auto-updates per network; keep this to single sites for now.
        if (is_multisite() || !current_user_can('update_plugins') || !$this->is_relevant_screen()) {
            return false;
        }

        if (get_user_meta(get_current_user_id(), self::DISMISSED_META, true)) {
            return false;
        }

        $settings = get_option('woocommerce_flizpay_settings');
        if (!is_array($settings) || empty($settings['flizpay_api_key'])) {
            return false;
        }

        // Auto-updates can be switched off site-wide (e.g. by the host or wp-config).
        if (!function_exists('wp_is_auto_update_enabled_for_type') || !wp_is_auto_update_enabled_for_type('plugin')) {
            return false;
        }

        $basename = $this->plugin_basename();
        if (in_array($basename, (array) get_site_option('auto_update_plugins', array()), true)) {
            return false;
        }

        // A filter that forces auto-updates on or off for this plugin makes the choice moot.
        if (function_exists('wp_is_auto_update_forced_for_item')
            && wp_is_auto_update_forced_for_item('plugin', null, (object) array('plugin' => $basename)) !== null) {
            return false;
        }

        return true;
    }

    private function is_relevant_screen()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen) {
            return false;
        }
        if ($screen->id === 'plugins') {
            return true;
        }

        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';

        return $screen->id === 'woocommerce_page_wc-settings' && $tab === 'checkout' && $section === 'flizpay';
    }

    private function plugin_basename()
    {
        return plugin_basename(dirname(__DIR__) . '/flizpay.php');
    }
}
