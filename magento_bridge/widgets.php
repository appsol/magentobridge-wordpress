<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class magentoBridgeFeaturedProductsWidget extends WP_Widget {

    function __construct() {
        parent::__construct(
                'magento-bridge-featured-products', 'Featured Products', array(
            'description' => __('Displays the featured products from the connected Magento store'),
            'classname' => 'featured-products'
        ));
    }

    function widget($args, $instance) {
        if (is_admin())
            return false;
        extract($args);

        $title = apply_filters('widget_title', $instance['title']);
        echo $before_widget;

        if ($title)
            echo $before_title . $title . $after_title;

            ?>
        <script id="magento-bridge-featured-product" type="text/template">
            <% _.each( products, function (product) { %>
                    <li class="product">
              <% if (product.media) { %>
                        <div class="media">
                    <a href="<%= product.link %>">
                        <img src="<%= product.media.url %>" alt="<%= product.media.label %>" />
                            </a>
                        </div>
                <% } %>
                <p class="title"><a href="<%= product.link %>"><%= product.post_title %></a></p>
                        <p class="price">
                            <% if (product.price.special) { %>
                    <span class="standard was"><span class="prefix">Was</span><span class="amount">£<%= product.price.std %></span></span>
                    <span class="special"><span class="prefix">Now</span><span class="amount">£<%= product.price.special %></span></span>
                        <% } else { %>
                    <span class="standard"><span class="amount">£<%= product.price.std %></span></span>
                                <% } %>
                        </p>
                <p class="action"><a href="<%= product.link %>">Buy Now</a></p>
                    </li>
            <% }); %>
        </script>
        <ul 
            class="magento_bridge_product_list" 
            data-action="magento_bridge_request" 
            data-method="media_product_list" 
            data-args='{"filters":{"status":1,"visibility":4,"featured":1},"count":<?php echo $instance['count']; ?>}'
            data-template="magento-bridge-featured-product">
            </ul>
            <?php
        echo $after_widget;
    }

    function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['count'] = intval($new_instance['count']);

        magentoBridge::purgeCache(true);
        return $instance;
    }

    function form($instance) {
        $defaults = array(
            'title' => 'Featured Products',
            'count' => 5
        );

        $instance = wp_parse_args((array) $instance, $defaults);
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Title:</label>
            <input type="text" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" value="<?php echo $instance['title']; ?>" style="width:100%;" />
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('count'); ?>">Max:</label>
            <input type="text" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>" value="<?php echo $instance['count']; ?>" style="width:100%;" />
            <span class="help-block">The total number of featured items to display</span>
        </p>
        <?php
    }

}