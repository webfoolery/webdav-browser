jQuery(document).ready(function($) {
	$('.fileList tr').click(function(el) {
		console.log($(this).text());
		// $('input[name="endpoint"]').val($(this).text());
		$('input[name="endpoint"]').val($(this).data('endpoint'));
		$('select[name="task"]').val('getlistFilesArray');
		$('#adminForm').submit();
	});
	$('#unlockFile').click(function(el) {
		$('select[name="task"]').val('unlock');
		if ($('input[name="altUserEntry"]').val().length) {
			$('input[name="altUsername"]').val($('input[name="altUserEntry"]').val());
		}
		if ($('input[name="altPassEntry"]').val().length) {
			$('input[name="altPassword"]').val($('input[name="altPassEntry"]').val());
		}
		$('#adminForm').submit();
	});
});