jQuery(document).ready(function ($) {
	$(document).on('click', '.payex-set-default', function () {
		var id = $(this).data('id');
		var nonce = $(this).data('nonce');
		$.ajax({
			url    : Payex_CC.ajaxurl,
			type   : 'POST',
			data   : {
				action: 'set_default_card_payex',
				nonce : nonce,
				id    : id
			},
			success: function (result) {
				self.location.href = location.href;
			}
		});
		return false;
	});

	$(document).on('click', '.payex-delete-card', function () {
		var id = $(this).data('id');
		var nonce = $(this).data('nonce');
		$.ajax({
			url    : Payex_CC.ajaxurl,
			type   : 'POST',
			data   : {
				action: 'delete_card_payex',
				nonce : nonce,
				id    : id
			},
			success: function (result) {
				self.location.href = location.href;
			}
		});
		return false;
	});

});