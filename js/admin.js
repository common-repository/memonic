/**
 *	handling of backend UI behavior
 */
 
 jQuery(document).ready( function(){
 
 /*	jQuery( "#memonic_collection .note" ).draggable({
 		cursorAt: { cursor: "move", top: 20, left: 20 },
 		opacity: 0.6,
 		helper: "clone",
 	});
 	
	jQuery( "#memtarget").droppable({
		hoverClass: "dragActive",
		drop: function( event, ui ) {
				$("#memtarget")
					.addClass("target");
			}
	}); 	*/
	
	jQuery("#memonic_list .note").live("click", function (ev) {
        ev.preventDefault();
        var targetElement = jQuery(this).attr('id');
		if (jQuery("#"+targetElement+" .data").length == 0) {
			jQuery.post(
				ajaxurl,
				{
					action : 'memonic_noteDetail',
					itemId : jQuery(this).data('nid'),
					postEditNonce: memonic.postEditNonce,
				},
				function (res) {
					jQuery(res).insertAfter("#"+targetElement+" .abstract");
					jQuery("#"+targetElement+" .insert-toolbar .insertFullQuote").removeAttr('disabled');
				}
			);
			jQuery(".abstract", this).hide();
		} else {
			jQuery(".abstract", this).toggle();
			jQuery(".data", this).toggle();
		}
	});
	
	jQuery("#memonic_bar #listFolders").click( function (ev) {
		jQuery("#memonicFolders").removeAttr('disabled');
		jQuery("#memonicGroups").attr('disabled', 'disabled');
		jQuery("#memonic_list").empty();
		refreshList('f', jQuery("#memonicFolders").val(), 1);
	});
	
	jQuery("#memonic_bar #listGroups").click( function (ev) {
		jQuery("#memonicGroups").removeAttr('disabled');
		jQuery("#memonicFolders").attr('disabled', 'disabled');
		jQuery("#memonic_list").empty();
		refreshList('g', jQuery("#memonicGroups").val(), 1);
	});
	
	jQuery("#memonic_list .insertTitleLinkSource").live("click", function (ev) {
        var nid = jQuery(this).parent().parent().data('nid');
		var output = '<a href="'+jQuery("#note_"+nid+" .source a").attr("href")+'">'+jQuery("#note_"+nid+" h2").html()+'</a>';
		editor_insert(ev, output);
     });
	
	jQuery("#memonic_list .insertTitleLinkNote").live("click", function (ev) {
        var nid = jQuery(this).parent().parent().data('nid');
        jQuery.post(
			ajaxurl,
			{
				action : 'memonic_noteGuestpass',
				itemId : nid,
				postEditNonce : memonic.postEditNonce,
			},
			function (nurl) {
				var output = '<a href="'+nurl+'">'+jQuery("#note_"+nid+" h2").html()+'</a>'
				editor_insert(ev, output);
			}
        );
     });
	
	jQuery("#memonic_list .insertAbstractQuote").live("click", function (ev) {
        var nid = jQuery(this).parent().parent().data('nid');
        var output = '<blockquote>'+jQuery("#note_"+nid+" .abstract").html();
        if (jQuery("#note_"+nid+" .source a").attr('href').length > 0)
	        output += '<p><em>Source:</em> '+jQuery("#note_"+nid+" .source a").attr('href')+'</p>'+"\n";
        output += '</blockquote>'+"\n";
		editor_insert(ev, output);
     });
	
	jQuery("#memonic_list .insertFullQuote").live("click", function (ev) {
        var nid = jQuery(this).parent().parent().data('nid');
        var output = '<blockquote>'+jQuery("#note_"+nid+" .data").html();
        if (jQuery("#note_"+nid+" .source a").attr('href').length > 0)
	        output += '<p><em>Source:</em> '+jQuery("#note_"+nid+" .source a").attr('href')+'</p>'+"\n";
        output += '</blockquote>'+"\n";
		editor_insert(ev, output);
     });
    
    jQuery("#memonic_list").ready( function() {
    	refreshList('f', '__inbox__', 1);
    });
    
    jQuery("#memonic_refresh_list").click( function (ev) {
		ev.preventDefault();
		jQuery("#memonic_list").empty();
		if (jQuery("#memonic_bar input[name=itemSource]:checked").val() == 'folder')
	    	refreshList('f', jQuery("#memonicFolders").val(), 1, 1);
	    else
	    	refreshList('g', jQuery("#memonicGroups").val(), 1, 1);
    });
    
    jQuery("#memonic_sort_list").change( function (ev) {
    	ev.preventDefault();
    	sortList(jQuery(this).val());
    });

	jQuery("#memonicFolders").change( function (ev) {
		ev.preventDefault();
		jQuery("#memonic_list").empty();
		refreshList('f', jQuery(this).val(), 1);
	});
	
	jQuery("#memonicGroups").change( function (ev) {
		ev.preventDefault();
		jQuery("#memonic_list").empty();
		refreshList('g', jQuery(this).val(), 1);
	});
	
	jQuery("#memonic_moreNotes").live("click", function (ev) {
		ev.preventDefault();
		jQuery(this).parent().parent().hide();
		if (jQuery("#memonic_bar input[name=itemSource]:checked").val() == 'folder')
			refreshList('f', jQuery("#memonicFolders").val(), jQuery(this).data('nextpage'));
		else
			refreshList('g', jQuery("#memonicGroups").val(), jQuery(this).data('nextpage'));
		jQuery(this).parent().parent().remove();
	});
	
	jQuery("#memonic_user_disable").click( function (ev) {
		ev.preventDefault();
        jQuery.post(
			ajaxurl,
			{
				action : 'memonic_userDisable',
				postEditNonce : memonic.postEditNonce,
			},
			function (res) {
				if (res == 1) jQuery("#memonic_user_disable").parent().parent().remove();
			}
        );
	});
		
 });
 
 function refreshList(src, srcId, page, force) {
 	if (src == "f") {
		var fid = srcId;
		var gid = '';
	} else {
		var fid = '';
		var gid = srcId;
	}
		
 	if (typeof force == "undefined" ) force = 0;
 	
	jQuery.post(
		ajaxurl,
		{
			action : 'memonic_notesList',
			folder : fid,
			group : gid,
			page : page,
			update : force,
			postEditNonce : memonic.postEditNonce,
		},
		function (res) {
			jQuery("#memonic_list").append(res);
			sortList(jQuery("#memonic_sort_list").val());
		}
	)
 }
 
 function get_note(iid) {
 	console.log(iid);
 	jQuery.post(
 		ajaxurl,
 		{
 			action : 'memonic_noteDetail',
 			itemId : iid,
 			postEditNonce: memonic.postEditNonce,
 		},
 		function (res) {
 			jQuery("#note_"+iid).append(res);
 		}
 	);
 }

function editor_insert(ev, snippet) {
    ev.preventDefault();
	ev.stopPropagation();
	tinyMCE.activeEditor.execCommand('mceInsertContent', false, snippet);
}

function sortList(sortOpt) {
	var notes = jQuery("#memonic_list .note");
	if (sortOpt == undefined) sortOpt = ['date', 'desc'];
	else sortOpt = sortOpt.split('_')
	notes.detach().sort(function (a,b) {
		var c,d;
		if (sortOpt[0] == 'title') {
			c = jQuery("h2", a).html();
			d = jQuery("h2", b).html();
		}
		if (sortOpt[0] == 'date') {
			c = jQuery(".date", a).attr('data-date');
			d = jQuery(".date", b).attr('data-date');
		}
		if (sortOpt[1] == 'desc') {
			var e = c;
			c = d;
			d = e;
		}
		if (c < d) return -1;
		if (c > d) return 1;
		return 0;		
	});
	jQuery("#memonic_list").prepend(notes);
	return true;
}
