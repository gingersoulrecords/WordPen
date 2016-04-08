jQuery(function($){
	jQuery('button.wordpen-update').click(function(){
		jQuery(this).parent().find('input').removeAttr('readonly').prop('name', '_codepen_uri' );
	});
	jQuery('input.wordpen-shortcode').focus(function(){
		jQuery(this).select();
	});
});