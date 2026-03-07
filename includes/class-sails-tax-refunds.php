<?php
/**
 * Sails Tax Refund Tracking
 * 
 * Tracks refunds and adjusts tax reports accordingly.
 * Stores refund tax data for accurate reporting.
 *
 * @package SailsTax
 * @since 0.7.0
 */

if (!defined('ABSPATH')) exit;

class Sails_Tax_Refunds {
  /**
   * Register hooks
   */
  public function register() {
    // Hook into refund creation
    add_action('woocommerce_refund_created', [$this, 'track_refund'], 10, 2);
    
    // Hook into refund deletion (if admin deletes a refund)
    add_action('woocommerce_refund_deleted', [$this, 'handle_refund_deleted'], 10, 2);
    
    // Add refund column to admin order list
    add_filter('manage_edit-shop_order_columns', [$this, 'add_refund_column'], 20);
    add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'add_refund_column'], 20);
    add_action('manage_shop_order_posts_custom_column', [$this, 'render_refund_column'], 10, 2);
    add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'render_refund_column_hpos'], 10, 2);
  }

  /**
   * Track refund and calculate tax refund amount
   * 
   * @param int $refund_id Refund ID
   * @param array $args Refund arguments
   */
  public function track_refund($refund_id, $args) {
    $refund = wc_get_order($refund_id);
    if (!$refund) return;

    $order_id = $refund->get_parent_id();
    $order = wc_get_order($order_id);
    if (!$order) return;

    // Check if original order has Sails tax data
    $original_tax = floatval($order->get_meta('_sails_tax_amount'));
    $original_rate = floatval($order->get_meta('_sails_tax_rate'));
    $confidence = $order->get_meta('_sails_tax_confidence');

    if (!$confidence || $confidence === 'exempt' || $confidence === 'product_exempt') {
      // Order was exempt or has no Sails tax data
      return;
    }

    // Calculate refund tax based on refund amount and original tax rate
    $refund_amount = abs($refund->get_total());
    $refund_tax = 0;

    if ($original_rate > 0) {
      // Use the original tax rate to calculate refund tax
      $refund_tax = round($refund_amount * $original_rate, 2);
    } elseif ($original_tax > 0) {
      // Fall back to proportional calculation based on order totals
      $order_subtotal = floatval($order->get_subtotal()) + floatval($order->get_shipping_total());
      if ($order_subtotal > 0) {
        $refund_proportion = $refund_amount / $order_subtotal;
        $refund_tax = round($original_tax * $refund_proportion, 2);
      }
    }

    // Store refund tax metadata
    $refund->update_meta_data('_sails_refund_tax', $refund_tax);
    $refund->update_meta_data('_sails_refund_rate', $original_rate);
    $refund->save();

    // Update order's cumulative refund tax total
    $existing_refund_tax = floatval($order->get_meta('_sails_tax_refunded'));
    $new_refund_tax = $existing_refund_tax + $refund_tax;
    $order->update_meta_data('_sails_tax_refunded', $new_refund_tax);
    $order->save();

    // Add order note
    if ($refund_tax > 0) {
      $order->add_order_note(
        sprintf(
          /* translators: 1: refund amount 2: estimated tax refund */
          __('Sails Tax: Refund of %1$s processed. Estimated tax refund: %2$s', 'sails-tax'),
          wc_price($refund_amount),
          wc_price($refund_tax)
        ),
        false
      );
    }
  }

  /**
   * Handle refund deletion - reverse the tax tracking
   * 
   * @param int $refund_id Refund ID
   * @param int $order_id Parent order ID
   */
  public function handle_refund_deleted($refund_id, $order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;

    // We need to recalculate from all remaining refunds
    $total_refund_tax = $this->calculate_order_refund_tax($order);
    $order->update_meta_data('_sails_tax_refunded', $total_refund_tax);
    $order->save();
  }

  /**
   * Calculate total refund tax for an order from all its refunds
   * 
   * @param WC_Order $order Order object
   * @return float Total refund tax
   */
  public function calculate_order_refund_tax($order) {
    $refunds = $order->get_refunds();
    $total = 0;

    foreach ($refunds as $refund) {
      $refund_tax = floatval($refund->get_meta('_sails_refund_tax'));
      $total += $refund_tax;
    }

    return $total;
  }

  /**
   * Get refund statistics for a date range
   * 
   * @param string $start_date Start date (Y-m-d)
   * @param string $end_date End date (Y-m-d)
   * @return array Refund statistics
   */
  public static function get_refund_statistics($start_date, $end_date) {
    global $wpdb;

    $use_hpos = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
      && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();

    if ($use_hpos) {
      $orders_table = $wpdb->prefix . 'wc_orders';
      $meta_table = $wpdb->prefix . 'wc_orders_meta';
      
      $result = $wpdb->get_row($wpdb->prepare(
        "SELECT 
          COUNT(DISTINCT o.id) as refund_count,
          COALESCE(SUM(CAST(m.meta_value AS DECIMAL(10,2))), 0) as total_refund_tax
        FROM {$orders_table} o
        INNER JOIN {$meta_table} m ON o.id = m.order_id AND m.meta_key = '_sails_tax_refunded'
        WHERE o.date_created_gmt >= %s AND o.date_created_gmt <= %s
        AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded')
        AND CAST(m.meta_value AS DECIMAL(10,2)) > 0",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ), ARRAY_A);
    } else {
      $result = $wpdb->get_row($wpdb->prepare(
        "SELECT 
          COUNT(DISTINCT p.ID) as refund_count,
          COALESCE(SUM(CAST(m.meta_value AS DECIMAL(10,2))), 0) as total_refund_tax
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_sails_tax_refunded'
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s AND p.post_date <= %s
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded')
        AND CAST(m.meta_value AS DECIMAL(10,2)) > 0",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ), ARRAY_A);
    }

    return [
      'refund_count' => intval($result['refund_count'] ?? 0),
      'total_refund_tax' => floatval($result['total_refund_tax'] ?? 0),
    ];
  }

  /**
   * Get recent refunds with Sails tax data
   * 
   * @param int $limit Number of refunds to return
   * @return array Recent refunds
   */
  public static function get_recent_refunds($limit = 5) {
    $orders = wc_get_orders([
      'limit' => $limit * 2, // Fetch more to ensure we get enough with refund data
      'orderby' => 'date',
      'order' => 'DESC',
      'meta_key' => '_sails_tax_refunded',
      'meta_compare' => '>',
      'meta_value' => 0,
      'meta_type' => 'NUMERIC',
    ]);

    $result = [];
    foreach ($orders as $order) {
      if (count($result) >= $limit) break;
      
      $refund_tax = floatval($order->get_meta('_sails_tax_refunded'));
      if ($refund_tax > 0) {
        $result[] = [
          'id' => $order->get_id(),
          'date' => $order->get_date_created()->date('M j, Y'),
          'refund_tax' => $refund_tax,
          'original_tax' => floatval($order->get_meta('_sails_tax_amount')),
        ];
      }
    }

    return $result;
  }

  /**
   * Add refund column to order list (legacy)
   */
  public function add_refund_column($columns) {
    // Insert after order_total column
    $new_columns = [];
    foreach ($columns as $key => $value) {
      $new_columns[$key] = $value;
      if ($key === 'order_total') {
        $new_columns['sails_refund_tax'] = __('Tax Refunded', 'sails-tax');
      }
    }
    return $new_columns;
  }

  /**
   * Render refund column content (legacy)
   */
  public function render_refund_column($column, $post_id) {
    if ($column !== 'sails_refund_tax') return;
    
    $order = wc_get_order($post_id);
    if (!$order) return;
    
    $this->output_refund_cell($order);
  }

  /**
   * Render refund column content (HPOS)
   */
  public function render_refund_column_hpos($column, $order) {
    if ($column !== 'sails_refund_tax') return;
    $this->output_refund_cell($order);
  }

  /**
   * Output refund cell content
   */
  private function output_refund_cell($order) {
    $refund_tax = floatval($order->get_meta('_sails_tax_refunded'));
    
    if ($refund_tax > 0) {
      echo '<span style="color: #b32d2e;">-' . wc_price($refund_tax) . '</span>';
    } else {
      echo '—';
    }
  }
}
