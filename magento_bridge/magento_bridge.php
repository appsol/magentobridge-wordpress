<?php

/*
  Plugin Name: Magento Bridge
  Plugin URI: http://www.appropriatesolutions.co.uk/wordpress/plugins
  Description: Provides a bridge between Magento and Wordpress via the magento API
  Version: 0.1.0
  Author: Stuart
  Author URI: http://www.mouse-cheese.com
  License: GPL2
  Copyright 2013  Stuart Laverick  (email : stuart@mouse-cheese.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define('MAGENTO_BRIDGE_PATH', plugin_dir_path(__FILE__));
if (is_admin())
    require_once MAGENTO_BRIDGE_PATH . 'magento_bridge_options.php';

require_once MAGENTO_BRIDGE_PATH . 'widgets.php';

/**
 * Description of magentoBridge
 *
 * @author stuart
 */
class magentoBridge {

    public static $soap_api = 'api/v2_soap/?wsdl';
    public static $store_wsdl;
    public static $client;
    public static $transient_prefix = 'magbrdg';
    public static $request_cache_name;
    public static $cache_lifetime;
    public static $force = false;

    public static function activate() {
        // Create the WP_Cron hook
        wp_schedule_event(time(), 'hourly', 'magento_cache');
    }

    public static function deactivate() {
        // Remove the WP_Cron hook
        wp_clear_scheduled_hook('magento_cache');
    }

    public static function uninstall() {
        // important: check if the file is the one that was registered with the uninstall hook (function)
        if (__FILE__ != WP_UNINSTALL_PLUGIN)
            return;
        // Remove the WP_Cron hook
        wp_clear_scheduled_hook('magento_cache');
        // Clear the transient cache
        self::purgeCache(true);
        delete_option(self::$request_cache_name);
    }

    /**
     * Initialisation point for the Plugin
     */
    public static function init() {
        // Register the activate / deactivate / uninstall hooks
        register_deactivation_hook(__FILE__, array('magentoBridge', 'deactivate'));
        register_activation_hook(__FILE__, array('magentoBridge', 'activate'));
        register_uninstall_hook(__FILE__, array('magentoBridge', 'uninstall'));
        
        self::$cache_lifetime = 60 * 60 * 24 * 30;
        self::$request_cache_name = self::$transient_prefix . '_request_cache';
        if ($url = get_option('magento_store_url'))
            self::$store_wsdl = $url . self::$soap_api;

        // Add the general actions
        add_action('init', array('magentoBridge', 'register_product_post_type'));
        add_action('magento_cache', array('magentoBridge', 'cycle_cache'));
        add_action('widgets_init', array('magentoBridge', 'load_widgets'));
        add_action('wp_ajax_magento_bridge_request', array('magentoBridge', 'request_json'));
        add_action('wp_ajax_nopriv_magento_bridge_request', array('magentoBridge', 'request_json'));
        // Complete further initialisation based on context
        if (is_admin())
            self::admin_init();
        else
            self::public_init();
    }

    /**
     * Initialisation point for the Administration side
     */
    public static function admin_init() {
//        add_action('admin_notices', array('magentoBridge', 'show_admin_messages'));
        add_action('admin_menu', array('magentoBridge', 'create_menu'));
        add_action('admin_init', array('magentoBridge', 'register_settings'));
        add_action('admin_enqueue_scripts', array('magentoBridge', 'admin_resources'));
    }

    /**
     * Initialisation point for the Public side
     */
    public static function public_init() {
        add_action('wp_enqueue_scripts', array('magentoBridge', 'public_resources'));
    }

    public static function register_product_post_type() {
        $labels = array(
            'name' => _x('Magento Products', 'post type general name'),
            'singular_name' => _x('Magento Product', 'post type singular name')
        );
        $args = array(
            'labels' => $labels,
            'description' => 'Magento product data from the Magento API',
            'public' => false,
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
            'has_archive' => true,
        );
        register_post_type('magento_product', $args);
    }

    public static function load_widgets() {
        register_widget('magentoBridgeFeaturedProductsWidget');
    }

    public static function create_menu() {
        $page = add_plugins_page('Magento Bridge Settings', 'Magento Bridge', 'manage_options', 'magento_bridge', array('magentoBridgeOptions', 'init'));
    }

    public static function register_settings() {
        // Add the Social Streams Settings sections
        add_settings_section('magento_connection', 'Magento Connection', array('magentoBridgeOptions', 'magento_connect_section'), 'magento_bridge');
        add_settings_field('magento_store_url', 'Store URL', array('magentoBridgeOptions', 'textField'), 'magento_bridge', 'magento_connection', array('label_for' => 'magento_store_url', 'default' => '', 'description' => 'The URL (with http(s)://) of the Magento store'));
        register_setting('magento_connection', 'magento_store_url', array('magentoBridge', 'check_url'));
        add_settings_field('magento_store_user', 'Web Services User Name', array('magentoBridgeOptions', 'textField'), 'magento_bridge', 'magento_connection', array('label_for' => 'magento_store_user', 'default' => '', 'description' => 'The User Name of the Magento Store user to connect to the Magento Store as'));
        register_setting('magento_connection', 'magento_store_user');
        add_settings_field('magento_user_key', 'Web Services User API Key', array('magentoBridgeOptions', 'textField'), 'magento_bridge', 'magento_connection', array('label_for' => 'magento_user_key', 'default' => '', 'description' => 'The API Key of the Magento Store user'));
        register_setting('magento_connection', 'magento_user_key');
    }

    public static function public_resources() {
        wp_enqueue_script('underscore', plugins_url('js/lib/underscore-min.js', __FILE__), array(), '', true);
        wp_enqueue_script('magento_bridge_js', plugins_url('js/main.js', __FILE__), array('jquery', 'underscore'), '', true);
        wp_localize_script('magento_bridge_js', 'magentoBridge', array('ajaxurl' => admin_url('admin-ajax.php')));
    }

    public static function admin_resources() {
        wp_register_script('magento_bridge_admin_js', plugins_url('js/admin_main.js', __FILE__), array('jquery', 'jquery-ui-dialog'));
        wp_enqueue_script('magento_bridge_admin_js');
    }

    public static function createTransientName($api_call, $args = array()) {
        return substr(self::$transient_prefix . md5($api_call . json_encode($args)), 0, 40);
    }

    public static function register_request($method, $args = array()) {
        $key = self::createTransientName($method, $args);
        $requests = get_option(self::$request_cache_name, array());
        $requests[$key] = array(
            'method' => $method,
            'args' => $args
        );
        update_option(self::$request_cache_name, $requests);
    }

    public static function cycle_cache() {
        $requests = get_option(self::$request_cache_name, array());
        foreach ($requests as $request) {
            if (method_exists('magentoBridge', $request['method'])) {
                call_user_func(array('magentoBridge', $request['method']), $request['args']);
            }
        }
        self::purgeCache();
    }

    /**
     * Delete any product posts that are out of date
     * 
     * @param bool Flag delete all posts?
     */
    public static function purgeCache($all = false) {
        // If all the products are not being cleared, add a filter to limit by date
        if (!$all)
            add_filter('posts_where', array('magentoBridge', 'filter_cache'));
        // Get all Magento Product type posts
        $query = new WP_Query(array(
            'post_type' => 'magento_product',
            'posts_per_page' => -1));
        if (!$all)
            remove_filter('posts_where', array('magentoBridge', 'filter_cache'));
        
        $deleted_product_posts = array();
        // Call wp_delete_post on each product as this will deal with attachments, meta, etc as well
        foreach ($query->posts as $product) {
            $product_id = get_post_meta($product->ID, 'product_id', true);
            if (wp_delete_post($product->ID, true))
                $deleted_product_posts[$product->ID] = $product_id;
        }
        // Return a count of the deleted products
        return count($deleted_product_posts);
    }

    /**
     * Adds a clause into the query to limit selection by age
     * @param string $where
     * @return string
     */
    public static function filter_cache($where = '') {
        $cut = time() - self::$cache_lifetime;
        
        $where.= ' AND post_date <= "' . date('Y-m-d', $cut) . '"';
        return $where;
    }

    public static function check_url($url) {
        if (strpos($url, 'http') === false)
            $url = 'http://' . $url;
        if (!$parts = parse_url(trim($url)))
            return '';
        return $parts['scheme'] . '://' . $parts['host'] . '/';
    }

    public static function auth_client(&$error) {
        $user = get_option('magento_store_user');
        $key = get_option('magento_user_key');
        self::$client = new SoapClient(self::$store_wsdl);
        try {
            $sessionId = self::$client->login($user, $key);
        } catch (SoapFault $e) {
            $error = $e->getMessage();
            self::log($error);
            return false;
        }
        return $sessionId;
    }

    public static function request_json() {
        $method = isset($_POST['method']) && method_exists('magentoBridge', $_POST['method']) ?
                $_POST['method'] : 'api_store_info';

        $args = (isset($_POST['args']) && !empty($_POST['args'])) ?
                $_POST['args'] : null;
        
        $result = self::$method($args);
        
        $json = json_encode($result);
        header('Content-type: application/json;');
        echo $json ? $json : json_encode(array('error' => 'Failed to JSON encode result'));
        die();
    }

    public static function get_products($product_ids) {
        $product_ids = (array) $product_ids;
        $products = self::get_product_posts($product_ids);

        // If we got them all return them
        if (count($product_ids) == count($products))
            return $products;
        // Missing some product posts, so find the missing product_ids
        foreach ($products as $product) {
            $product_id = get_post_meta($product->ID, 'product_id', true);
            if (($key = array_search($product_id, $product_ids)) !== false)
                unset($product_ids[$key]);
        }

        // Get each of the missing products from Magento
        foreach ($product_ids as $product_id) {
            $api_product = self::api_store_product_info(array('productId' => $product_id));

            if (!isset($api_product->error)) {
                // Create the local product post
                if ($post_id = self::update_product_post($api_product))
                    $products[] = get_post($post_id);
            }
        }
        return $products;
    }

    /**
     * Returns the product posts associated with the Magento Product IDs
     * @param int|array $product_ids
     * @return array
     */
    public static function get_product_posts($product_ids) {
        $args = array(
            'post_type' => 'magento_product',
            'meta_query' => array(
                array(
                    'key' => 'product_id',
                    'value' => (array) $product_ids,
                    'compare' => 'IN'
                )
            )
        );
        $query = new WP_Query($args);
        
        return $query->posts;
    }

    /**
     * 
     * @param stdClass $product
     * @return int|bool
     */
    public static function update_product_post($product) {
        $post_id = false;
        // Get the Magento product data into a format to import to Wordpress
        $product_post = self::product_to_post($product);
        
        // Try to load a post for this product
        $posts = self::get_product_posts($product->product_id);

        // If a post was found, update it
        if (count($posts)) {
            $post = $posts[0];
            $product_post = array_merge($post, $product_post);
            $post_id = wp_update_post($product_post);
            self::log('WP Update Post ID: ' . $post_id);
        }
        // If no post found then create one
        if (!$post_id) {
            $post_id = wp_insert_post($product_post);
            self::log('WP Insert Post ID: ' . $post_id);
        }
        // If failed to craete post bail
        if (is_wp_error($post_id))
            return false;
        // Update the product meta
        self::product_to_post_meta($product, $post_id);
        // Update the product images
        self::product_to_post_attachments($product, $post_id);

        return $post_id;
    }

    public static function delete_product_post($product_id) {
        
    }

    public static function product_to_post($product) {
        $now = time();
        $post = array(
            'post_date' => date('Y-m-d H:i:s', $now),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $now),
            'post_content' => $product->description,
            'post_title' => $product->name,
            'post_excerpt' => strip_tags($product->short_description),
            'post_name' => $product->url_key,
            'post_type' => 'magento_product',
            'post_status' => 'publish',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        );
        return $post;
    }

    public static function product_to_post_meta($product, $post_id) {
        if (isset($product->product_id))
            update_post_meta($post_id, 'product_id', $product->product_id);
        if (isset($product->sku))
            update_post_meta($post_id, 'sku', $product->sku);
        if (isset($product->price))
            update_post_meta($post_id, 'price', $product->price);
        if (isset($product->special_price))
            update_post_meta($post_id, 'special_price', $product->special_price);
        if (isset($product->url_path))
            update_post_meta($post_id, 'url', get_option('magento_store_url') . $product->url_path);
    }

    public static function product_to_post_attachments($product, $post_id) {

        $media = self::api_store_attribute_media_list(array('productId' => $product->product_id));

        $attachment_ids = array();
        foreach ((array) $media as $image)
            $attachment_ids[] = self::cache_product_image($image->url, $post_id, $image->label);
        if (count($attachment_ids) && $attachment_ids[0])
            set_post_thumbnail($post_id, $attachment_ids[0]);
    }

    /**
     * replicates Wordpress function 'media_sideload_image' but
     * returns ID not img element
     * @param str $file
     * @param str $desc
     * @return int attachment post ID
     */
    public static function cache_product_image($file, $post_id, $desc = null) {
        if (!empty($file)) {
            require_once(ABSPATH . "wp-admin" . '/includes/image.php');
            require_once(ABSPATH . "wp-admin" . '/includes/file.php');
            require_once(ABSPATH . "wp-admin" . '/includes/media.php');
            // Download file to temp location
            $tmp = download_url($file);

            // Set variables for storage
//            if ($name) {
//                // If no extension specified on the file name, then use the same as the file
//                if (!strpos($name, '.')) {
//                    $name.= '.' . pathinfo($file, PATHINFO_EXTENSION);
//                }
//            } else {
            // fix file filename for query strings
            preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $file, $matches);
            $name = basename($matches[0]);
//            }
            $file_array['name'] = urlencode($name);
            $file_array['tmp_name'] = $tmp;

            // If error storing temporarily, unlink
            if (is_wp_error($tmp)) {
                @unlink($file_array['tmp_name']);
                return false;
            }

            // do the validation and storage stuff
            $id = media_handle_sideload($file_array, $post_id, $desc);

            // If error storing permanently, unlink
            if (is_wp_error($id)) {
                @unlink($file_array['tmp_name']);
                return false;
            }

            $src = wp_get_attachment_url($id);
        }

        // Finally check to make sure the file has been saved, then return the ID
        return empty($src) ? false : $id;
    }

    /**
     * View function that returns the WP_Posts Product objects that match the
     * passed filters argument.
     * If a cached version of the post list is found it will return that.
     * @param array $args
     * @return array Array of WP_Post objects
     */
    public static function media_product_list($args) {

        $args = wp_parse_args((array) $args, array(
            'count' => 5,
            'filters' => null,
            'storeView' => null
        ));
        if (!self::$force && $cache = get_transient(self::createTransientName('media_product_list', $args)))
            return $cache;
        $products = array();
        $items = self::api_store_product_list($args);

        if (!isset($items['error'])) {
            if (count($items) > $args['count']) {
                shuffle($items);
                $items = array_slice($items, 0, $args['count']);
            }
            $product_ids = array();
            foreach ($items as $item)
                $product_ids[] = $item->product_id;

            $posts = self::get_products($product_ids);
            
            foreach ($posts as $product) {
                $product->link = get_post_meta($product->ID, 'url', true);
                $price = new stdClass();
                $price->std = sprintf("%01.2f", get_post_meta($product->ID, 'price', true));
                $price->special = get_post_meta($product->ID, 'special_price', true) ?
                        sprintf("%01.2f", get_post_meta($product->ID, 'special_price', true)) : false;
                $product->price = $price;
                $product->media = new stdClass();
                if (has_post_thumbnail($product->ID))
                    $product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->ID), 'micro-thumb');
                    $product->media->url = $product_image[0];
                    $product->media->width = $product_image[1];
                    $product->media->height = $product_image[2];
                    $product->media->label = $product->post_title;
                $products[$product->ID] = $product;
            }
        } else {
            self::log($items['error']);
            return $items;
        }
        set_transient(self::createTransientName('media_product_list', $args), $products, floor(60 * 30));
        return $products;
    }

    public static function api_store_info($force = false) {
        $error = false;
//        if (!self::$force && $cache = get_transient(self::createTransientName('api_store_info')))
//            return $cache;
        self::register_request('api_store_info');
        if ($session_id = self::auth_client($error)) {
            try {
                $result = self::$client->storeList($session_id);
            } catch (SoapFault $e) {
                $error = $e->getMessage();
            }
            if (!$error) {
                if (count($result))
//                    set_transient(self::createTransientName('api_store_info'), $result, self::$cache_lifetime);
                return $result;
            }
        }
        return array('error' => $error);
    }

    public static function api_store_category_tree($args) {
        $params = wp_parse_args((array) $args, array(
            'storeView' => null,
            'parentId' => null
        ));
        $error = false;
//        if (!self::$force && $cache = get_transient(self::createTransientName('api_store_category_tree'), $args))
//            return $cache;
        self::register_request('api_store_category_tree', $args);
        if ($session_id = self::auth_client($error)) {
            try {
                $result = self::$client->catalogCategoryTree($session_id, $params['storeView'], $params['parentId']
                );
            } catch (SoapFault $e) {
                $error = $e->getMessage();
            }
            if (!$error) {
                if (count($result))
//                    set_transient(self::createTransientName('api_store_category_tree', $args), $result, self::$cache_lifetime);
                return $result;
            }
        }
        return array('error' => $error);
    }

    public static function api_store_category_level($args) {
        $params = wp_parse_args((array) $args, array(
            'website' => null,
            'storeView' => null,
            'parentCategory' => null
        ));
        $error = false;
//        if (!self::$force && $cache = get_transient(self::createTransientName('api_store_category_level'), $args))
//            return $cache;
        self::register_request('api_store_category_level', $args);
        if ($session_id = self::auth_client($error)) {
            try {
                $result = self::$client->catalogCategoryLevel($session_id, $params['website'], $params['storeView'], $params['parentCategory']
                );
            } catch (SoapFault $e) {
                $error = $e->getMessage();
            }
            if (!$error) {
                if (count($result))
//                    set_transient(self::createTransientName('api_store_category_level', $args), $result, self::$cache_lifetime);
                return $result;
            }
        }
        return array('error' => $error);
    }

    public static function api_store_product_list($args) {
        $params = wp_parse_args((array) $args, array(
            'filters' => null,
            'storeView' => null
        ));
        $filters = array(
//                'complex_filter' => array(
//                    array(
//                        'key' => 'type',
//                        'value' => array(
//                            'key' => 'in',
//                            'value' => '"simple","configurable"'
//                        )
//                    )
//                )
        );
        if ($params['filters']) {
            $filter = array();
            foreach ($params['filters'] as $key => $value) {
                $filter[] = array(
                    'key' => $key,
                    'value' => $value
                );
            }
            $filters['filter'] = $filter;
        }
        $params['filters'] = $filters;

        $error = false;
//        if (!self::$force && $cache = get_transient(self::createTransientName('api_store_product_list'), $args))
//            return $cache;

        if ($session_id = self::auth_client($error)) {
            try {
                $result = self::$client->catalogProductList($session_id, $params['filters'], $params['storeView']);
            } catch (SoapFault $e) {
                $error = $e->getMessage();
            }
            if (!$error) {
                if (count($result))
//                    set_transient(self::createTransientName('api_store_product_list', $args), $result, self::$cache_lifetime);
                return $result;
            }
        }
        return array('error' => $error);
    }

    /**
     * Requests details on a single Product from the Magento API
     * @param array $args
     * @return stdClass API response object
     */
    public static function api_store_product_info($args) {
        $params = wp_parse_args((array) $args, array(
            'productId' => null,
            'storeView' => null,
            'attributes' => null,
            'productIdentifierType' => null
        ));
        $error = false;
        $result = new stdClass();
        // Product ID is required
        if (empty($params['productId'])) {
            $result->error = 'No product ID specified';
            return $result;
        }
//        if (!self::$force && $cache = get_transient(self::createTransientName('api_store_product_info'), $args))
//            return $cache;
        self::register_request('api_store_product_info', $args);
        if ($session_id = self::auth_client($error)) {
            // Call the API
            try {
                $result = self::$client->catalogProductInfo($session_id, $params['productId'], $params['storeView'], $params['attributes'], $params['productIdentifierType']);
            } catch (SoapFault $e) {
                $error = $e->getMessage();
            }
            if (!$error) {
                // Update the cache if successful
                if (count($result))
//                    set_transient(self::createTransientName('api_store_product_info', $args), $result, self::$cache_lifetime);
                return $result;
            }
        }
        $result->error = $error;
        return $result;
    }

    public static function api_store_attribute_media_list($args) {
        $params = wp_parse_args((array) $args, array(
            'productId' => null,
            'storeView' => null,
            'identifierType' => null
        ));
        $error = false;
        $result = new stdClass();
        if (empty($params['productId'])) {
            $result->error = 'No product ID specified';
            return $result;
        }
//        if (!self::$force && $cache = get_transient(self::createTransientName('api_store_attribute_media_list'), $args))
//            return $cache;
        self::register_request('api_store_attribute_media_list', $args);
        if ($session_id = self::auth_client($error)) {
            try {
                $result = self::$client->catalogProductAttributeMediaList($session_id, $params['productId'], $params['storeView'], $params['identifierType']);
            } catch (SoapFault $e) {
                $error = $e->getMessage();
            }
            if (!$error) {
//                set_transient(self::createTransientName('api_store_attribute_media_list', $args), $result, self::$cache_lifetime);
                return $result;
            }
        }
        $result->error = $error;
        return $result;
    }

    public static function log($message = '', $backtrace = false) {
        if (WP_DEBUG === true) {
            $trace = debug_backtrace();
            $caller = $trace[1];
            error_log(isset($caller['class']) ? $caller['class'] . '::' . $caller['function'] : $caller['function']);
            if ($message)
                error_log(is_array($message) || is_object($message) ? print_r($message, true) : $message);
            if ($backtrace)
                error_log(print_r($backtrace, true));
        }
    }

}

magentoBridge::init();
