jQuery(document).ready(function ($) {
    $(document).on('click', '.payex-action', function (e) {
        e.preventDefault();

        var nonce = $(this).data('nonce');
        var transaction_id = $(this).data('transaction-id');
        var order_id = $(this).data('order-id');
        var payex_action = $(this).data('action');
        var self = $(this);
        $.ajax({
            url       : Payex_Payments_Admin.ajax_url,
            type      : 'POST',
            data      : {
                nonce         : nonce,
                transaction_id: transaction_id,
                order_id      : order_id,
                payex_action  : payex_action
            },
            beforeSend: function () {
                self.data('text', self.html());
                self.html(Payex_Payments_Admin.text_wait);
                self.prop('disabled', true);
            }
        }).done(function (response) {
            self.html(self.data('text'));
            self.prop('disabled', false);
            if (!response.success) {
                alert(response.data);
                return false;
            }

            window.location.href = location.href;
        });
    });
});
