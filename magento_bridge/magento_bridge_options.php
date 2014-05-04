<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
//if (!function_exists('wp_get_current_user'))
//    require_once ABSPATH . 'wp-includes/pluggable.php';

/**
 * Description of magentoBridgeOptions
 *
 * @author stuart
 */
class magentoBridgeOptions {

    /**
     * Main entry point for Options Page 
     */
    public static function init() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }
        if (!session_id())
            @session_start();
        // Show the admin page
        self::showPage();
    }

    public static function magento_connect_section() {
        echo 'Enter the details of your magento store below:';
    }

    /**
     * Text Field
     * @param type $args 
     */
    public static function textField($args) {
        $setting = $args['label_for'];
        $option = get_option($setting) ? get_option($setting) : $args['default'];
        echo '<input title="' . $args["description"] . '" type="text" id="' . $setting . '" name="' . $setting . '" value="' . $option . '" />';
    }

    /**
     * Hidden Field
     * @param type $args 
     */
    public static function hiddenField($args) {
        $setting = $args['label_for'];
        $option = get_option($setting) ? get_option($setting) : $args['default'];
        echo '<input type="hidden" id="' . $setting . '" name="' . $setting . '" value="' . $option . '" />';
    }

    /**
     * Yes / No radio fields
     * @param type $args 
     */
    public static function yesnoField($args) {
        $setting = $args['label_for'];
        $option = get_option($setting) ? get_option($setting) : $args['default'];
        echo '<input title="' . $args["description"] . '" type="radio" id="' . $setting . '_yes" name="' . $setting . '" value="1" ' . ($option ? 'checked="checked" ' : '') . '/>Yes ';
        echo '<input title="' . $args["description"] . '" type="radio" id="' . $setting . '_no" name="' . $setting . '" value="0" ' . ($option ? '' : 'checked="checked" ') . '/>No';
    }

    public static function showPage() {
        ?>
        <div class="wrap">
        <?php // screen_icon(); ?>
            <h2>Magento Bridge</h2>
            <form method="post" action="options.php"> 
                <?php settings_fields('magento_connection'); ?>
                <?php do_settings_sections('magento_bridge'); ?>
        <?php submit_button(); ?>
            </form>
            <p>
                <button class="test button button-secondary" data-action="magento_bridge_request" data-method="api_store_info">Store Info</button>
                <button class="test button button-secondary" data-action="magento_bridge_request" data-method="api_store_category_level">Categories</button>
                <button class="test button button-secondary" data-action="magento_bridge_request" data-method="api_store_category_tree">Category Tree</button>
                <!--<button class="test" data-action="magento_bridge_request" data-method="store_product_list" data-args='{"filters":{"featured":1,"status":1,"visibility":4}}'>Featured Products</button>-->
                <button class="test button button-secondary" data-action="magento_bridge_request" data-method="api_store_product_list" data-args='{"filters":{"status":1,"visibility":4,"featured":1}}'>Featured Products</button>
            </p>
            <p>
                <button class="test button button-secondary" data-action="magento_bridge_request" data-method="purgeCache" data-args='1'>Purge Product Posts</button>
            </p>
        </div>
        <?php
    }

}
