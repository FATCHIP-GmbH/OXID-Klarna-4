<?php

$sLangName = "English";
// -------------------------------
// RESOURCE IDENTITFIER = STRING
// -------------------------------
$aLang = array(
    'charset' => 'UTF-8',

    'KL_EXCEPTION_OUT_OF_STOCK' => 'Validation error',
    'KL_CHECKOUT'               => 'Checkout',

    'KL_DISCOUNT_TITLE'                     => 'Discount',
    'TCKLARNA_SURCHARGE_TITLE'              => 'Surcharge',
    'KL_VOUCHER_DISCOUNT'                   => 'Coupon Discount',
    'KL_GIFT_WRAPPING_TITLE'                => 'Gift wrapping',
    'KL_GIFT_CARD_TITLE'                    => 'Gift card',
    'KL_PAYMENT_FEE_TITLE'                  => 'Payment method fee',
    'KL_TRUSTED_SHOPS_EXCELLENCE_FEE_TITLE' => 'Trusted shops excellence fee',

    'KL_PASSWORD'                          => 'Password',
    'KL_TRUSTED_SHOP_BUYER_PROTECTION'     => 'Trusted Shops Buyer Protection',
    'KL_ALREADY_A_CUSTOMER'                => 'I am already a customer',
    'KL_LAW_NOTICE'                        => 'The <a href="%s" class="klarna-notification" target="_blank">conditions</a> of use for data transmission apply.',
    'KL_OUTSIDE_VOUCHER'                   => 'Do you have a voucher?',
    'KL_GO_TO_CHECKOUT'                    => 'Go to Checkout',
    'KL_BUY_NOW'                           => 'Buy Now',
    'KL_USE_AS_DELIVERY_ADDRESS'           => 'Use as the delivery address',
    'KL_CHOOSE_DELIVERY_ADDRESS'           => 'Choose delivery address',
    'KL_CREATE_USER_ACCOUNT'               => 'Create Customer Account',
    'KL_SUBSCRIBE_TO_NEWSLETTER'           => 'Subscribe to Newsletter',
    'KL_CREATE_USER_ACCOUNT_AND_SUBSCRIBE' => 'Create Customer Account AND subscribe to Newsletter',
    'KL_NO_CHECKBOX'                       => 'Do not show a Checkbox',
    'KL_ALLOW_SEPARATE_SHIPPING_ADDRESS'   => 'Allow separate shipping address',
    'KL_PHONE_NUMBER_MANDATORY'            => 'Phone number mandatory',
    'KL_DATE_OF_BIRTH_MANDATORY'           => 'Date of birth mandatory',
    'KL_CHOOSE_YOUR_SHIPPING_COUNTRY'      => 'Please choose your shipping country:',
    'KL_CHOOSE_YOUR_NOT_SUPPORTED_COUNTRY' => 'Your country is not supported?',
    'KL_MORE_COUNTRIES'                    => 'More countries',
    'KL_MY_COUNTRY_IS_NOT_LISTED'          => 'My country is not listed',
    'KL_OTHER_COUNTRY'                     => 'Other country',
    'KL_RESET_COUNTRY'                     => 'Your chosen country: <strong>%s</strong> ',
    'KL_CHANGE_COUNTRY'                    => 'change country',
    'KL_LOGIN_INTO_AMAZON'                 => 'Please click the button below to login into amazon service',
    'KLARNA_ORDER_NOT_IN_SYNC'             => '<strong>Warning!</strong> This order\'s data is different on Klarna\'s side. ',
    'KLARNA_ORDER_IS_CANCELLED'            => 'Order is cancelled. ',
    'KLARNA_SEE_ORDER_IN_PORTAL'           => '<a href="%s" target="_blank" class="alert-link">See this order in the Klarna Portal</a>',//todo:translate

    'KLARNA_WENT_WRONG_TRY_AGAIN' => 'Something went wrong. Please try again',
    'KLARNA_WRONG_URLS_CONFIG'    => 'Configuration error - check terms/cancellation terms settings',

    'KL_KP_CURRENCY_DONT_MATCH'      => 'In order to being able to use Klarna payments, the selected currency has to match the official currency of your billing country.',
    'KL_KP_MATCH_ERROR'              => 'In order to being able to use Klarna payments, both person and country in billing and shipping address must match.',
    'KL_KP_INVALID_TOKEN'            => 'Invalid authorization token. Please try again.',
    'KL_KP_ORDER_DATA_CHANGED'       => 'Order data have been changed. Please try again.',
    'KP_NOT_AVAILABLE_FOR_COMPANIES'  => 'Payment with this Klarna payment method is currently not available for companies.',
    'KP_AVAILABLE_FOR_PRIVATE_ONLY'   => 'This Klarna payment method is only available for private orders.',
    'KP_AVAILABLE_FOR_COMPANIES_ONLY' => 'Payment with this Klarna payment method is currently available only for companies.',
    'KL_KP_NOT_KLARNA_CORE_COUNTRY'  => 'Configuration error: No Klarna payment methods available for this country.',

    'KL_PLEASE_AGREE_TO_TERMS'            => 'Please agree to Terms and Conditions and Right to Withdrawal for a downloadable item.',
    'KL_ERROR_NOT_ENOUGH_IN_STOCK'        => 'Not enough items of product %s in stock.',
    'KL_ERROR_NO_SHIPPING_METHODS_SET_UP' => 'Currently we have no shipping method set up for this country: %s',

    'KL_PAY_LATER_SUBTITLE'    => 'Pay X days after delivery',
    'KL_SLICE_IT_SUBTITLE'     => 'Pay over time',
    'KL_PAY_NOW_SUBTITLE'      => 'Easy and direct payment',
    'KL_ORDER_AMOUNT_TOO_HIGH' => 'The order amount is too high.',

    'KL_ANONYMIZED_PRODUCT'                  => 'Anonymized product title:',

    'TCKLARNA_USER_GUIDE_DESCRIPTION'        => 'Click here for download of latest documentation PDF of Klarna extension for OXID eShop',
    'TCKLARNA_EASY_AND_SECURE_SHOPPING'      => 'The most popular payment methods. Secured and easy to integrate.',
    'TCKLARNA_WELCOME_TO_CONFIGURATION'      => 'Welcome to Klarna’s configuration side. Here you can find everything you need to activate Klarna Payments or Klarna Checkout. Click on the Klarna sub-categories to complete the installation of this module.',
    'TCKLARNA_EMAIL'                         => 'Email',
    'TCKLARNA_PAY_LATER_START'               => 'For customers that want to buy now but pay later. Easy in 14, 21 or 28 days (depending on market).',
    'TCKLARNA_SLICE_IT'                      => 'Financing',
    'TCKLARNA_SLICE_IT_START'                => 'Increase your shoppers purchase power by letting them slice their payments. Customers can chose between fixed and flexible payments splitted in 6 - 36 month.',
    'TCKLARNA_PAY_NOW_START'                 => 'Quick and easy direct payment with Klarna’s Pay Now. In Germany, Sofortüberweisung, credit card and direct debit should not be missing in the portfolio.',
    'TCKLARNA_CHECKOUT_START'                => 'The best checkout experience for your customers. Reliable customer identification and shipping selection. All payments including worldwide availability, locally optimized. We take all the risk and you always get your money.',
    'TCKLARNA_RB_HOW_TO_ACTIVATE'            => 'How-to-activate<br>Klarna guide.',
    'TCKLARNA_RB_HOW_TO_ACTIVATE_LIST_ONE'   => 'In the OXID backend click on the new menu item Klarna, the sub-category “General” and then select the Klarna product you want. You have the choice between Klarna Payments and Klarna Checkout.',
    'TCKLARNA_RB_HOW_TO_ACTIVATE_LIST_TWO'   => 'Enter your access data (user name and password) and make the OXID-typical assignments for payment methods under Shop settings> Payment methods and> Shipping methods.',
    'TCKLARNA_RB_HOW_TO_ACTIVATE_LIST_THREE' => 'Save the changes.',
    'TCKLARNA_RB_HOW_TO_ACTIVATE_LIST_FOUR'  => 'You are now online and you can start selling with Klarna.',
    'TCKLARNA_RB_MERCHANT_SUPPORT'           => 'Merchant support',
    'TCKLARNA_DEVICE_IMG'                    => 'device-en.png',
);

