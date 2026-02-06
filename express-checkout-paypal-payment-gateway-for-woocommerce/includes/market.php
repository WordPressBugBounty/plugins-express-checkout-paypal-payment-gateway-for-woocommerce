<style>
    .box14 {
        width: 24%;
        margin-top: 2px;
        min-height: 310px;
        margin-right: 20px;
        position: absolute;
		border: 1px solid #5408DF;
        z-index: 1;
    }
    .eh_gopro_block {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); 
        overflow: hidden; 
    }

    .eh_gopro_block h3 {
        text-align: left;
    }

    .eh_premium_upgrade {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 10px 10px 0px 20px;
        background-color: #F7F6FC;
    }

    .eh_premium_upgrade_head {
        font-weight: 600;
        font-size: 15px;
        line-height: 25px;
        color: #000000;
        padding: 0 10px ;
    }

    .eh_premium_features {
        padding: 5px 10px 5px 20px;
        font-weight: 600;
        font-size: 16px;
    }
    .eh_premium_features li {
        padding-left: 35px;
        padding-right: 10px;
        font-weight: 500;
        font-size: 15px;
        line-height: 19px;
        padding-top: 10px;
    }
    .eh_premium_features li::before {
        background-image: url(<?php echo esc_url(EH_PAYPAL_MAIN_URL.'assets/img/cta-green-tick.svg'); ?>);
        font-weight: 400;
        font-style: normal;
        vertical-align: top;
        text-align: center;
        content: "";
        margin-right: 10px;
        margin-left: -25px;
        font-size: 16px;
        color: #3085bb;
        height: 18px;
        width: 18px;
        position: absolute;
        background-repeat: no-repeat;
    }

    .eh_premium_button {
        padding-top: 0px 10px 10px 10px;
    }
    .eh-button-go-pro {
        box-shadow: none;
        border: 0;
        width: 80%;
        text-shadow: none;
        padding: 10px 15px;
        height: auto;
        font-size: 16px;
        border-radius: 6px;
        font-weight: 600;
        background: #5408DF;
        color: #fff;
        margin-top: 5px;
        text-decoration: none;
        display: inline-flex; 
        display: inline-flex;
        justify-content: center; 
        align-items: center;
    }

    .eh-button-go-pro img {
        max-width: 20px; 
        height: auto; 
        margin-right: 8px; 
    }
    .eh_premium_button p {
    text-align: center; 
    }
    .eh-cs-rating-money-back {
        
        margin: 10px 15px 10px 15px;
        padding: 0px;
        background-color: #EFEDFF54;
    }
    .eh-money-back {
        font-size: 14px;
        border-bottom: 1px solid #ccc;
        padding: 10px 10px 10px 30px;
        display: flex;
    }
    .eh-money-back img {
        margin-right: 10px;
    }   
    .eh-cs-rating img {
        margin-right: 10px;
    }
    .eh-cs-rating {
        font-size: 14px;
        display: flex;
        padding: 10px 10px 10px 30px;
    }
    .eh_money_back_text {
        font-family: Roboto;
        font-size: 15px;
        font-weight: 1000;
        line-height: 17.88px;
        letter-spacing: -0.01em;
        text-align: left;
        padding: 5px 0px 0px 30px;
    }
    .bfcm_doc_settings {
        position: absolute; /* Allows positioning relative to the closest positioned ancestor */
        top: 0px; /* Adjust as needed */
        right: 0px; /* Adjust to position it on the right */
        z-index: 2; /* Ensure it appears above other content */
        border: none;
        background-color:#F7F6FC;
    }
</style>

<div class="box14 table-box-main">
    <?php 
    // if (Wteh_Bfcm_Twenty_Twenty_Four::is_bfcm_season()) { ?>
        <!-- <div class="bfcm_doc_settings">
            <img class="bfcm-coupon-img" src="<?php // echo esc_url( plugins_url( 'assets/img/bfcm-30-off-coupon.svg', dirname(__FILE__)) ); ?>" alt="30% Off Coupon">
        </div> -->
    <?php // } ?>
    <div class="eh_gopro_block">
        <div class="eh_premium_upgrade">
            <img src="<?php echo esc_url(EH_PAYPAL_MAIN_URL . 'assets/img/paypal-cta-img.svg'); ?>" alt="paypal img">
            <span class="eh_premium_upgrade_head">
                <h4><?php esc_html_e('PayPal Express Checkout Payment Gateway', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></h3>
            </span>
            
           
        </div>

        <div>
            <ul class="eh_premium_features">  
                <li><?php esc_html_e('Add PayPal Smart Buttons on product pages.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Provide alternative payment methods based on customer’s country.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Add PayPal Express Checkout buttons on product pages and mini-cart.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Authorize payments and capture funds later.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Process full or partial refunds directly from order edit pages.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>
                <li><?php esc_html_e('Tailor button styles, positions, and more to fit your store’s design.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>
                <li><?php esc_html_e('In-context checkout for secure payment using PayPal without leaving your site.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li> 
                <li><?php esc_html_e('Compatible with WooCommerce Subscriptions.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>   
                <li><?php esc_html_e('Set up a specific PayPal locale.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>   
                <li><?php esc_html_e('Add PayPal Express buttons anywhere with shortcode integration.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>   
                <li><?php esc_html_e('Receive timely compatibility updates and bug fixes.', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></li>     
            </ul>
        </div>
                
        <div class="eh_premium_button">
            <p style="text-align: center;">
                <?php
                    $href_attr = 'https://www.themehigh.com/product/paypal-express-checkout-gateway-for-woocommerce/';
                ?>
                <a href="<?php print( esc_url( $href_attr ) ); ?>" target="_blank" class="eh-button eh-button-go-pro"><img src="<?php echo esc_url(EH_PAYPAL_MAIN_URL.'assets/img/crown.svg'); ?>" ><?php echo esc_html__( 'Upgrade to Premium', 'express-checkout-paypal-payment-gateway-for-woocommerce' ); ?></a>
            </p>
        </div>
        <div>
            <div class="eh_money_back_text">
               <?php esc_html_e('Try with confidence', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?>
            </div>

            <div class="eh-cs-rating-money-back">
                <div class="eh-money-back">
                    <img src="<?php echo esc_url(EH_PAYPAL_MAIN_URL . 'assets/img/eh-money-back.svg'); ?>"alt="alt"/>
                    <p><?php esc_html_e('100% No Risk Money Back Guarantee', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></p>
                </div>
                <div class="eh-cs-rating">
                    <img src="<?php echo esc_url(EH_PAYPAL_MAIN_URL . 'assets/img/eh-satisfaction-rating.svg'); ?>" alt="alt"/>
                    <p><?php esc_html_e('Fast and Proirity Support with 99% Satisfaction Rating', 'express-checkout-paypal-payment-gateway-for-woocommerce'); ?></p>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
if (is_rtl()) {
    ?>
    <style type="text/css"> .box14 { left: 0px; float: left; }</style>
    <?php
} else {
    ?>
    <style type="text/css"> .box14 { right: 0px; float: right; }</style>
    <?php
}
