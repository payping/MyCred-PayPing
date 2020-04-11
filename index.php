<?php

/*
Plugin Name: درگاه پرداخت افزونه MyCred پی‌پینگ.
Version: 1.0.0
Description: افزونه درگاه پرداخت برای MyCred توسط پی‌پینگ.
Plugin URI: https://payping.ir/
Author: Mahdi Sarani
Author URI: https://mahdisarani.ir/
*/

define('PPINGPDP', plugin_dir_path( __FILE__ ));

require_once( PPINGPDP . '/inc/class-mycred-gateway-payping.php');
