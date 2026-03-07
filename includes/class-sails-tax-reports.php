<?php
/**
 * Sails Tax Reports
 * 
 * Provides tax reporting dashboard for WooCommerce admins.
 * Shows tax calculations, confidence levels, and totals over time.
 *
 * @package SailsTax
 * @since 0.4.0
 */

if (!defined('ABSPATH')) exit;

class Sails_Tax_Reports {
  /**
   * Register hooks
   */
  public function register() {
    add_action('admin_menu', [$this, 'add_reports_page']);
  }

  /**
   * Add reports submenu under WooCommerce
   */
  public function add_reports_page() {
    add_submenu_page(
      'woocommerce',
      __('Sails Tax Reports', 'sails-tax'),
      __('Tax Reports', 'sails-tax'),
      'view_woocommerce_reports',
      'sails-tax-reports',
      [$this, 'render_reports_page']
    );
  }

  /**
   * Render the reports page
   */
  public function render_reports_page() {
    // Get date range from query params or default to last 30 days
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : date('Y-m-d');
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : date('Y-m-d', strtotime('-30 days'));

    // Fetch statistics
    $stats = $this->get_tax_statistics($start_date, $end_date);
    $confidence_breakdown = $this->get_confidence_breakdown($start_date, $end_date);
    $recent_orders = $this->get_recent_tax_orders(10);
    
    // Fetch refund statistics
    $refund_stats = Sails_Tax_Refunds::get_refund_statistics($start_date, $end_date);
    $net_tax = $stats['total_tax'] - $refund_stats['total_refund_tax'];
    $recent_refunds = Sails_Tax_Refunds::get_recent_refunds(5);

    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Sails Tax Reports', 'sails-tax'); ?></h1>
      
      <!-- Date Range Filter -->
      <form method="get" style="margin: 20px 0;">
        <input type="hidden" name="page" value="sails-tax-reports">
        <label for="start_date"><?php esc_html_e('From:', 'sails-tax'); ?></label>
        <input type="date" id="start_date" name="start_date" value="<?php echo esc_attr($start_date); ?>">
        <label for="end_date" style="margin-left: 15px;"><?php esc_html_e('To:', 'sails-tax'); ?></label>
        <input type="date" id="end_date" name="end_date" value="<?php echo esc_attr($end_date); ?>">
        <button type="submit" class="button" style="margin-left: 15px;"><?php esc_html_e('Filter', 'sails-tax'); ?></button>
      </form>

      <!-- Summary Cards -->
      <div style="display: flex; gap: 20px; margin-bottom: 30px; flex-wrap: wrap;">
        <?php $this->render_stat_card(__('Gross Tax Collected', 'sails-tax'), wc_price($stats['total_tax']), 'dashicons-money-alt'); ?>
        <?php $this->render_stat_card(__('Tax Refunded', 'sails-tax'), '-' . wc_price($refund_stats['total_refund_tax']), 'dashicons-undo', '#b32d2e'); ?>
        <?php $this->render_stat_card(__('Net Tax', 'sails-tax'), wc_price($net_tax), 'dashicons-chart-area', '#46b450'); ?>
        <?php $this->render_stat_card(__('Orders with Tax', 'sails-tax'), number_format($stats['order_count']), 'dashicons-cart'); ?>
        <?php $this->render_stat_card(__('Average Tax Rate', 'sails-tax'), number_format($stats['avg_rate'] * 100, 2) . '%', 'dashicons-chart-line'); ?>
        <?php $this->render_stat_card(__('Exact ZIP Matches', 'sails-tax'), number_format($stats['exact_zip_percent'], 1) . '%', 'dashicons-yes-alt'); ?>
      </div>

      <!-- Confidence Breakdown -->
      <div style="display: flex; gap: 30px; flex-wrap: wrap;">
        <div style="flex: 1; min-width: 300px;">
          <h2><?php esc_html_e('Confidence Level Breakdown', 'sails-tax'); ?></h2>
          <table class="widefat striped">
            <thead>
              <tr>
                <th><?php esc_html_e('Confidence', 'sails-tax'); ?></th>
                <th><?php esc_html_e('Orders', 'sails-tax'); ?></th>
                <th><?php esc_html_e('Tax Amount', 'sails-tax'); ?></th>
                <th><?php esc_html_e('Percentage', 'sails-tax'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($confidence_breakdown as $row): ?>
              <tr>
                <td>
                  <span style="color: <?php echo esc_attr($this->get_confidence_color($row['confidence'])); ?>;">●</span>
                  <?php echo esc_html(ucwords(str_replace('_', ' ', $row['confidence']))); ?>
                </td>
                <td><?php echo esc_html(number_format($row['count'])); ?></td>
                <td><?php echo wc_price($row['total_tax']); ?></td>
                <td><?php echo esc_html(number_format($row['percentage'], 1)); ?>%</td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($confidence_breakdown)): ?>
              <tr>
                <td colspan="4" style="text-align: center; color: #666;">
                  <?php esc_html_e('No tax data in this date range.', 'sails-tax'); ?>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Recent Orders -->
        <div style="flex: 1; min-width: 400px;">
          <h2><?php esc_html_e('Recent Orders with Sails Tax', 'sails-tax'); ?></h2>
          <table class="widefat striped">
            <thead>
              <tr>
                <th><?php esc_html_e('Order', 'sails-tax'); ?></th>
                <th><?php esc_html_e('Date', 'sails-tax'); ?></th>
                <th><?php esc_html_e('Tax', 'sails-tax'); ?></th>
                <th><?php esc_html_e('Confidence', 'sails-tax'); ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_orders as $order_data): ?>
              <tr>
                <td>
                  <a href="<?php echo esc_url(admin_url('post.php?post=' . $order_data['id'] . '&action=edit')); ?>">
                    #<?php echo esc_html($order_data['id']); ?>
                  </a>
                </td>
                <td><?php echo esc_html($order_data['date']); ?></td>
                <td><?php echo wc_price($order_data['tax_amount']); ?></td>
                <td>
                  <span style="color: <?php echo esc_attr($this->get_confidence_color($order_data['confidence'])); ?>;">●</span>
                  <?php echo esc_html(ucwords(str_replace('_', ' ', $order_data['confidence']))); ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($recent_orders)): ?>
              <tr>
                <td colspan="4" style="text-align: center; color: #666;">
                  <?php esc_html_e('No orders with Sails tax data yet.', 'sails-tax'); ?>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if (!empty($recent_refunds)): ?>
      <!-- Refunds Section -->
      <div style="margin-top: 30px;">
        <h2><?php esc_html_e('Recent Refunds with Tax Adjustments', 'sails-tax'); ?></h2>
        <table class="widefat striped" style="max-width: 600px;">
          <thead>
            <tr>
              <th><?php esc_html_e('Order', 'sails-tax'); ?></th>
              <th><?php esc_html_e('Date', 'sails-tax'); ?></th>
              <th><?php esc_html_e('Original Tax', 'sails-tax'); ?></th>
              <th><?php esc_html_e('Tax Refunded', 'sails-tax'); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_refunds as $refund_data): ?>
            <tr>
              <td>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $refund_data['id'] . '&action=edit')); ?>">
                  #<?php echo esc_html($refund_data['id']); ?>
                </a>
              </td>
              <td><?php echo esc_html($refund_data['date']); ?></td>
              <td><?php echo wc_price($refund_data['original_tax']); ?></td>
              <td style="color: #b32d2e;">-<?php echo wc_price($refund_data['refund_tax']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <!-- Info Box -->
      <div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
        <strong><?php esc_html_e('About Confidence Levels', 'sails-tax'); ?></strong>
        <ul style="margin: 10px 0 0 20px;">
          <li><span style="color: #46b450;">●</span> <strong><?php esc_html_e('Exact ZIP', 'sails-tax'); ?></strong> – <?php esc_html_e('Full address match with local jurisdiction rates', 'sails-tax'); ?></li>
          <li><span style="color: #00a0d2;">●</span> <strong><?php esc_html_e('City Match', 'sails-tax'); ?></strong> – <?php esc_html_e('City-level rate (may miss special districts)', 'sails-tax'); ?></li>
          <li><span style="color: #ffb900;">●</span> <strong><?php esc_html_e('County Match', 'sails-tax'); ?></strong> – <?php esc_html_e('County-level fallback', 'sails-tax'); ?></li>
          <li><span style="color: #dc3232;">●</span> <strong><?php esc_html_e('State Only', 'sails-tax'); ?></strong> – <?php esc_html_e('Only state tax rate (missing local)', 'sails-tax'); ?></li>
        </ul>
      </div>
    </div>
    <?php
  }

  /**
   * Render a stat card
   */
  private function render_stat_card($label, $value, $icon, $color = '#2271b1') {
    ?>
    <div style="flex: 1; min-width: 150px; background: #fff; padding: 20px; border: 1px solid #c3c4c7; border-radius: 4px;">
      <span class="dashicons <?php echo esc_attr($icon); ?>" style="color: <?php echo esc_attr($color); ?>; font-size: 24px; margin-bottom: 10px; display: block;"></span>
      <div style="font-size: 24px; font-weight: 600; color: #1d2327;"><?php echo $value; ?></div>
      <div style="color: #50575e; margin-top: 5px;"><?php echo esc_html($label); ?></div>
    </div>
    <?php
  }

  /**
   * Get tax statistics for date range
   */
  private function get_tax_statistics($start_date, $end_date) {
    global $wpdb;

    // Build query for orders with Sails tax data
    $orders_table = $wpdb->prefix . 'wc_orders';
    $meta_table = $wpdb->prefix . 'wc_orders_meta';

    // Check if HPOS is enabled
    $use_hpos = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
      && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();

    if ($use_hpos) {
      $query = $wpdb->prepare(
        "SELECT 
          COUNT(DISTINCT o.id) as order_count,
          COALESCE(SUM(CAST(m1.meta_value AS DECIMAL(10,2))), 0) as total_tax,
          COALESCE(AVG(CAST(m2.meta_value AS DECIMAL(10,6))), 0) as avg_rate
        FROM {$orders_table} o
        INNER JOIN {$meta_table} m1 ON o.id = m1.order_id AND m1.meta_key = '_sails_tax_amount'
        LEFT JOIN {$meta_table} m2 ON o.id = m2.order_id AND m2.meta_key = '_sails_tax_rate'
        WHERE o.date_created_gmt >= %s AND o.date_created_gmt <= %s
        AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      );
    } else {
      // Legacy postmeta query
      $query = $wpdb->prepare(
        "SELECT 
          COUNT(DISTINCT p.ID) as order_count,
          COALESCE(SUM(CAST(m1.meta_value AS DECIMAL(10,2))), 0) as total_tax,
          COALESCE(AVG(CAST(m2.meta_value AS DECIMAL(10,6))), 0) as avg_rate
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} m1 ON p.ID = m1.post_id AND m1.meta_key = '_sails_tax_amount'
        LEFT JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = '_sails_tax_rate'
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s AND p.post_date <= %s
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      );
    }

    $result = $wpdb->get_row($query, ARRAY_A);

    // Calculate exact ZIP percentage
    $exact_zip_count = $this->count_by_confidence('exact_zip', $start_date, $end_date);
    $exact_zip_percent = ($result['order_count'] > 0) 
      ? ($exact_zip_count / $result['order_count']) * 100 
      : 0;

    return [
      'total_tax' => floatval($result['total_tax'] ?? 0),
      'order_count' => intval($result['order_count'] ?? 0),
      'avg_rate' => floatval($result['avg_rate'] ?? 0),
      'exact_zip_percent' => $exact_zip_percent,
    ];
  }

  /**
   * Count orders by confidence level
   */
  private function count_by_confidence($confidence, $start_date, $end_date) {
    global $wpdb;

    $use_hpos = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
      && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();

    if ($use_hpos) {
      $orders_table = $wpdb->prefix . 'wc_orders';
      $meta_table = $wpdb->prefix . 'wc_orders_meta';
      
      $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT o.id)
        FROM {$orders_table} o
        INNER JOIN {$meta_table} m ON o.id = m.order_id AND m.meta_key = '_sails_tax_confidence'
        WHERE m.meta_value = %s
        AND o.date_created_gmt >= %s AND o.date_created_gmt <= %s",
        $confidence,
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ));
    } else {
      $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID)
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = '_sails_tax_confidence'
        WHERE m.meta_value = %s
        AND p.post_date >= %s AND p.post_date <= %s",
        $confidence,
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ));
    }

    return intval($count);
  }

  /**
   * Get confidence level breakdown
   */
  private function get_confidence_breakdown($start_date, $end_date) {
    global $wpdb;

    $use_hpos = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
      && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();

    if ($use_hpos) {
      $orders_table = $wpdb->prefix . 'wc_orders';
      $meta_table = $wpdb->prefix . 'wc_orders_meta';
      
      $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
          mc.meta_value as confidence,
          COUNT(DISTINCT o.id) as count,
          COALESCE(SUM(CAST(ma.meta_value AS DECIMAL(10,2))), 0) as total_tax
        FROM {$orders_table} o
        INNER JOIN {$meta_table} mc ON o.id = mc.order_id AND mc.meta_key = '_sails_tax_confidence'
        LEFT JOIN {$meta_table} ma ON o.id = ma.order_id AND ma.meta_key = '_sails_tax_amount'
        WHERE o.date_created_gmt >= %s AND o.date_created_gmt <= %s
        AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        GROUP BY mc.meta_value
        ORDER BY count DESC",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ), ARRAY_A);
    } else {
      $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
          mc.meta_value as confidence,
          COUNT(DISTINCT p.ID) as count,
          COALESCE(SUM(CAST(ma.meta_value AS DECIMAL(10,2))), 0) as total_tax
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} mc ON p.ID = mc.post_id AND mc.meta_key = '_sails_tax_confidence'
        LEFT JOIN {$wpdb->postmeta} ma ON p.ID = ma.post_id AND ma.meta_key = '_sails_tax_amount'
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s AND p.post_date <= %s
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        GROUP BY mc.meta_value
        ORDER BY count DESC",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ), ARRAY_A);
    }

    $total_orders = array_sum(array_column($results, 'count'));

    foreach ($results as &$row) {
      $row['percentage'] = ($total_orders > 0) ? ($row['count'] / $total_orders) * 100 : 0;
    }

    return $results;
  }

  /**
   * Get recent orders with Sails tax data
   */
  private function get_recent_tax_orders($limit = 10) {
    $orders = wc_get_orders([
      'limit' => $limit,
      'orderby' => 'date',
      'order' => 'DESC',
      'meta_key' => '_sails_tax_confidence',
      'meta_compare' => 'EXISTS',
    ]);

    $result = [];
    foreach ($orders as $order) {
      $result[] = [
        'id' => $order->get_id(),
        'date' => $order->get_date_created()->date('M j, Y'),
        'tax_amount' => floatval($order->get_meta('_sails_tax_amount')),
        'confidence' => $order->get_meta('_sails_tax_confidence'),
      ];
    }

    return $result;
  }

  /**
   * Get color for confidence level
   */
  private function get_confidence_color($confidence) {
    switch ($confidence) {
      case 'exact_zip':
        return '#46b450';
      case 'city_match':
        return '#00a0d2';
      case 'county_match':
        return '#ffb900';
      case 'state_only':
        return '#dc3232';
      default:
        return '#666';
    }
  }
}
