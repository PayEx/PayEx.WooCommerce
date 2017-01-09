(function( $ ) {
    $.blockUI.defaults.overlayCSS.cursor = 'default';
    var xhr;

    function process_ssn() {
        var social_security_number = $('.payex-ssn-class input').val();
        var billing_country = $('[name="payex_ssn_country"]').val();
        var billing_postcode = $('[name="payex_ssn_zip"]').val();

        // wc_checkout_params is required to continue, ensure the object exists
        if (typeof wc_checkout_params === 'undefined')
            return false;

        if ($.trim(social_security_number).length === 0) {
            alert(WC_Payex_Addons_SSN.text_require_ssn);
            return false;
        }

        if (xhr) {
            xhr.abort();
        }

        $('#customer_details').block({
            message: null,
            overlayCSS: {
                background: '#fff url(' + wc_checkout_params.ajax_loader_url + ') no-repeat center',
                backgroundSize: '16px 16px',
                opacity: 0.6
            }
        });

        xhr = $.ajax({
            type: 'POST',
            url: wc_checkout_params.ajax_url,
            data: {
                action: 'payex_process_ssn',
                social_security_number: social_security_number,
                billing_country: billing_country,
                billing_postcode: billing_postcode
            },
            success: function (response) {
                if (!response.success) {
                    alert(response.data.message);
                    $('#customer_details').unblock();
                    return;
                }

                var data = response.data;

                // Process Billing
                $.each(data, function (index, value) {
                    index = 'billing_' + index;
                    $('input[name="' + index + '"]').val(value);

                    if (index === 'billing_country') {
                        if (typeof window.Select2 !== 'undefined') {
                            // Select2: WC 2.3+
                            $('#billing_country').select2('val', value);
                        } else if (typeof $.fn.chosen !== 'undefined') {
                            // Chosen
                            $('#billing_country').val(value).trigger('chosen:updated');
                            $('#billing_country').chosen().change();
                        } else {
                            $('#billing_country').val(value).change();
                        }
                    }
                });

                // Process Shipping
                $.each(data, function (index, value) {
                    index = 'shipping_' + index;
                    $('input[name="' + index + '"]').val(value);

                    if (index === 'shipping_country') {
                        if (typeof window.Select2 !== 'undefined') {
                            // Select2: WC 2.3+
                            $('#shipping_country').select2('val', value);
                        } else if (typeof $.fn.chosen !== 'undefined') {
                            // Chosen
                            $('#shipping_country').val(value).trigger('chosen:updated');
                            $('#shipping_country').chosen().change();
                        } else {
                            $('#shipping_country').val(value).change();
                        }
                    }
                });

                $('#customer_details').unblock();
            }
        });
    }

    // Event for processing SSN
    $('body').bind('process_ssn', function () {
        process_ssn();
    });

    $(document.body).on('update_checkout', function () {
        // Lock fields when "Financing Invoice" selected
        var payment_method = $('#order_review').find('input[name="payment_method"]:checked').val();
        var fields = ['first_name', 'last_name', 'address_1', 'address_2', 'postcode', 'city', 'country'];
        $.each(fields, function (index, field) {
            var billing_field = 'billing_' + field;
            var shipping_field = 'shipping_' + field;
            if (payment_method === 'payex_invoice') {
                $('[name="' + billing_field + '"]').prop('readonly', true);
                $('[name="' + shipping_field + '"]').prop('readonly', true);
            } else {
                $('[name="' + billing_field + '"]').prop('readonly', false);
                $('[name="' + shipping_field + '"]').prop('readonly', false);
            }
        });
	});

    $(document).ready(function () {
        $(document.body).on('click', 'input[name="woocommerce_checkout_payex_ssn"]', function () {
            $('body').trigger('process_ssn');
        });

        // Select2 Enhancement if it exists
        if ($().select2) {
            $( 'select[name="payex_ssn_country"]' ).select2({
                placeholderOption: 'first',
                width: '100%'
            });
        }

        $(document.body).on('keyup', 'input[name="payex_ssn"]', function () {
            var field = $('input[name="payex_ssn"]');
            if (field.val() !== '') {
                $('.payex-ssn-zip-class').show();
                $('.payex-ssn-country-class').show();
            } else {
                $('.payex-ssn-zip-class').hide();
                $('.payex-ssn-country-class').hide();
            }
        });

        $(document.body).on('keyup', 'input[name="payex_ssn_zip"]', function () {
            var value = $('input[name="payex_ssn_zip"]').val();
            $('input[name="billing_postcode"]').val(value);
            $('input[name="shipping_postcode"]').val(value);
        });
    });
})(jQuery);
