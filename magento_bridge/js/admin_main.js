/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
(function(w, $, undefined) {

    var init = function init() {
	testActions();
    }

    var testActions = function() {
	$('.test').click(function(e) {
	    e.preventDefault();
	    $.post(
		    ajaxurl,
		    {
			action: $(this).data('action'),
			method: $(this).data('method'),
			args: $(this).data('args')
		    },
	    function(data) {
		if (data) {
		    console.log(data);
		}
	    })

	});
    }
    $(document).ready(init);
})(window, jQuery)

