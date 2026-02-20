<?php
/**
 * Plugin Name: Sails Tax for WooCommerce
 * Plugin URI:  https://sails.tax/
 * Description: Sales tax estimation and tracking powered by Sails.tax. Calculates estimated sales tax at checkout and records calculation confidence.
 * Version:     0.2.1
 * Author:      Sails
 * License:     GPLv2 or later
 * Text Domain: sails-tax
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

if (!defined('ABSPATH')) exit;

define('SAILS_TAX_VERSION', '0.2.1');
define('SAILS_TAX_PLUGIN_FILE', __FILE__);
define('SAILS_TAX_PLUGIN_DIR', plugin_dir_path(__FILE__));

action_init();

function action_init() {
  add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
      add_action('admin_notices', function () {
        echo '<div class="notice notice-warning"><p><strong>Sails Tax:</strong> WooCommerce is required.</p></div>';
      });
      return;
    }

    require_once SAILS_TAX_PLUGIN_DIR . 'includes/class-sails-tax-settings.php';
    require_once SAILS_TAX_PLUGIN_DIR . 'includes/class-sails-tax-api.php';
    require_once SAILS_TAX_PLUGIN_DIR . 'includes/class-sails-tax-checkout.php';

    (new Sails_Tax_Settings())->register();
    (new Sails_Tax_Checkout())->register();
  });
}
