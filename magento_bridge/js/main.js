/* 
 * MagentoBridge main.js
 * @version 0.1
 * @author Stuart Laverick
 * (c) 2013 Changing Horizon www.changinghorizon.co.uk
 */
(function(w, $, undefined) {

    var init = function init() {

	$('.magento_bridge_product_list').each(displayProductList);
    }

    var displayProductList = function displayProductList() {
	var parent = $(this), strTemplate = $('#' + parent.data('template')).html();
        
	$.post(
		magentoBridge.ajaxurl,
		{
		    action: $(this).data('action'),
		    method: $(this).data('method'),
		    args: $(this).data('args')
		},
	function(data, textStatus, jqXHR) {
	    if (data) {
		if (!data.error) {
		    var template = _.template(strTemplate);
		    parent.empty().hide().append(template({products: data})).show();
		}
	    }
	}, 'json');
    }

    $(document).ready(init);
})(window, jQuery)

