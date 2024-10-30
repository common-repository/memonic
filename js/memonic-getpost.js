/**
 * @author Memonic
 */
(function() {
	var w = window;
	
	// prepare memonic variables
	w.memonic_button_autosave = true;
	w.memonic_button_preselect = true;
	w.memonic_button_preselect_title = '';
	w.memonic_button_preselect_content = '';
	w.memonic_button_affiliate_id = 'wordpress';
	
	jQuery(document).ready( function(){
	 
	 	jQuery('.memonic_button').click( function (ev) {
	 		var spin = jQuery(this).siblings()[0];
	 		jQuery(spin).show();
	        jQuery.post(
				MemonicWP.ajaxurl,
				{
					action : 'memonic_getPost',
					postId : jQuery(this).data('pid'),
					clipNonce : MemonicWP.clipNonce,
				},
				function (postData) {
					var clipPost = jQuery.parseJSON(postData),
						els = jQuery('<div>' + clipPost.content + '</div>');
					w.memonic_button_preselect_title = clipPost.title;
					w.memonic_button_preselect_content = els;
					w.memonic_button_fetch_source = clipPost.url;
	                try {
	                    var j = "https://www.memonic.com/bookmarklet/savebutton.js",
	                        x = document.createElement('script');
	                    x.type = 'text/javascript';
	                    x.src = j;
	                    void(document.body.appendChild(x));
	                } catch(e) {
	                    alert('clip error');
	                }
				 	jQuery(spin).hide();
				}

	        );
	 	});
	 	
	});
})();
