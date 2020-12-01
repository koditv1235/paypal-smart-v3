<?php
/*
Plugin Name: PayPal Smart Button for Paid Memberships Pro
Description: PayPal Smart Button for Paid Memberships Pro
Version: .1
*/

define("PMPRO_PAYPAL_SMART_DIR", dirname(__FILE__));

//load payment gateway class
require_once(PMPRO_PAYPAL_SMART_DIR . "/classes/class.pmprogateway_paypal_smart.php");