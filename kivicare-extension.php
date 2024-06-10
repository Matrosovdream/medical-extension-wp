<?php
/**
 * @wordpress-plugin
 * Plugin Name:       KiviCare Extension 
 * Plugin URI:        
 * Description:       This plugin extends the functionality of the Appointments Kivicare plugin
 * Version:           1.0.0
 * Author:            Stanislav Matrosov
 * Author URI:        https://github.com/Matrosovdream
 * Author Email:      matrosovdream@gmail.com
 */

if (!defined('ABSPATH')) {
    die('You are not allowed to call this page directly.');
}

define('KC_ABS', __DIR__);
define('KC_URL', plugin_dir_url(__FILE__));

// Initial class
require_once KC_ABS . '/classes/init.class.php';