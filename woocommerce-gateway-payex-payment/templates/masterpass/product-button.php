<p class="masterpass-payex button">
    <button class="btn-masterpass" type="submit" name="mp_from_product_page" value="1">
        <img src="<?php echo $image; ?>" width="120" alt="<?php echo $description; ?>">
    </button>
    <a target="_blank" href="<?php echo WC_Gateway_Payex_MasterPass::get_read_more_url(); ?>" rel="external"><small><?php _e('Read more', 'woocommerce-gateway-payex-payment'); ?></small></a>
</p>