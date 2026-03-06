<?php
/**
 * Sails Tax Order Display
 * 
 * Displays tax calculation details in the WooCommerce order admin view.
 */

if (!defined('ABSPATH')) exit;

class Sails_Tax_Order_Display {
  public function register() {
    // Add meta box to order page
    add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
  }

  /**
   * Add meta box to order edit screen
   */
  public function add_order_meta_box() {
    $screen = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
      ? wc_get_page_screen_id('shop-order')
      : 'shop_order';
    
    add_meta_box(
      'sails_tax_details',
      __('Sails Tax Details', 'sails-tax'),
      [$this, 'render_meta_box'],
      $screen,
      'side',
      'default'
    );
  }

  /**
   * Render the meta box content
   */
  public function render_meta_box($post_or_order) {
    // Get order object (works with both HPOS and legacy)
    if ($post_or_order instanceof WC_Order) {
      $order = $post_or_order;
    } else {
      $order = wc_get_order($post_or_order->ID);
    }
    
    if (!$order) {
      echo '<p>' . esc_html__('Order not found.', 'sails-tax') . '</p>';
      return;
    }

    $confidence = $order->get_meta('_sails_tax_confidence');
    $rate = $order->get_meta('_sails_tax_rate');
    $amount = $order->get_meta('_sails_tax_amount');
    $message = $order->get_meta('_sails_tax_message');

    if (!$confidence && !$rate && !$amount) {
      echo '<p style="color: #666;">' . esc_html__('No Sails tax data recorded for this order.', 'sails-tax') . '</p>';
      echo '<p style="font-size: 11px; color: #999;">' . esc_html__('Tax data is only recorded for orders placed after Sails Tax was installed.', 'sails-tax') . '</p>';
      return;
    }

    echo '<table class="widefat striped" style="border: 0;">';
    
    if ($confidence) {
      $confidence_label = ucwords(str_replace('_', ' ', $confidence));
      $confidence_color = $this->get_confidence_color($confidence);
      echo '<tr><th style="width: 40%;">' . esc_html__('Confidence', 'sails-tax') . '</th><td><span style="color: ' . esc_attr($confidence_color) . ';">● ' . esc_html($confidence_label) . '</span></td></tr>';
    }
    
    if ($rate) {
      $rate_percent = floatval($rate) * 100;
      echo '<tr><th>' . esc_html__('Tax Rate', 'sails-tax') . '</th><td>' . esc_html(number_format($rate_percent, 2)) . '%</td></tr>';
    }
    
    if ($amount) {
      echo '<tr><th>' . esc_html__('Tax Amount', 'sails-tax') . '</th><td>' . wc_price($amount) . '</td></tr>';
    }
    
    echo '</table>';
    
    if ($message) {
      echo '<p style="margin-top: 10px; font-size: 12px; color: #666;">' . esc_html($message) . '</p>';
    }
  }

  /**
   * Get color for confidence level indicator
   */
  private function get_confidence_color($confidence) {
    switch ($confidence) {
      case 'exact_zip':
        return '#46b450'; // green
      case 'city_match':
        return '#00a0d2'; // blue
      case 'county_match':
        return '#ffb900'; // yellow
      case 'state_only':
        return '#dc3232'; // red
      case 'error':
        return '#666'; // gray
      default:
        return '#666';
    }
  }
}
