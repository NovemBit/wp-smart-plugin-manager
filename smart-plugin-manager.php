<?php
/**
 * Plugin Name: WordPress Smart Plugin Manager
 * Plugin URI:
 * Description:
 * Version: 1.0.1
 * Author: Novembit
 * Author URI:
 * License: GPLv3
 * Text Domain: novembit-spm
 */

use NovemBit\wp\plugins\spm\Bootstrap;

defined('ABSPATH') || exit;

include_once __DIR__ . '/vendor/autoload.php';

Bootstrap::instance(WP_PLUGIN_DIR . '/smart-plugin-manager/smart-plugin-manager.php');