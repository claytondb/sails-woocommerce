<?php
/**
 * Sails Tax Email Reports
 * 
 * Sends monthly tax summary emails to store administrators.
 * Scheduled via WordPress cron for the first day of each month.
 *
 * @package SailsTax
 * @since 0.9.0
 */

if (!defined('ABSPATH')) exit;

class Sails_Tax_Email_Reports {
  const CRON_HOOK = 'sails_tax_monthly_email';
  const OPTION_KEY = 'sails_tax_email_settings';
  
  /**
   * Register hooks
   */
  public function register() {
    // Activation/deactivation
    register_activation_hook(SAILS_TAX_PLUGIN_FILE, [$this, 'schedule_cron']);
    register_deactivation_hook(SAILS_TAX_PLUGIN_FILE, [$this, 'unschedule_cron']);
    
    // Cron handler
    add_action(self::CRON_HOOK, [$this, 'send_monthly_report']);
    
    // Settings
    add_action('admin_init', [$this, 'register_settings']);
    
    // Manual send action
    add_action('admin_post_sails_tax_send_test_email', [$this, 'handle_test_email']);
    
    // Ensure cron is scheduled on plugin update
    if (!wp_next_scheduled(self::CRON_HOOK)) {
      $this->schedule_cron();
    }
  }
  
  /**
   * Schedule monthly cron job (1st of each month at 8am local time)
   */
  public function schedule_cron() {
    if (!wp_next_scheduled(self::CRON_HOOK)) {
      // Calculate next 1st of month at 8am
      $next_first = strtotime('first day of next month 08:00:00');
      wp_schedule_event($next_first, 'monthly', self::CRON_HOOK);
    }
  }
  
  /**
   * Unschedule cron on deactivation
   */
  public function unschedule_cron() {
    $timestamp = wp_next_scheduled(self::CRON_HOOK);
    if ($timestamp) {
      wp_unschedule_event($timestamp, self::CRON_HOOK);
    }
  }
  
  /**
   * Register email settings
   */
  public function register_settings() {
    // Add email settings section to the main settings page
    add_settings_section(
      'sails_tax_email_section',
      __('Monthly Email Reports', 'sails-tax'),
      function () {
        echo '<p>' . esc_html__('Receive a monthly summary of your tax calculations by email.', 'sails-tax') . '</p>';
      },
      'sails-tax'
    );
    
    // Enable email reports
    add_settings_field(
      'email_reports_enabled',
      __('Enable Monthly Reports', 'sails-tax'),
      [$this, 'render_enabled_field'],
      'sails-tax',
      'sails_tax_email_section'
    );
    
    // Email recipients
    add_settings_field(
      'email_recipients',
      __('Email Recipients', 'sails-tax'),
      [$this, 'render_recipients_field'],
      'sails-tax',
      'sails_tax_email_section'
    );
    
    // Test email button
    add_settings_field(
      'email_test',
      __('Test Email', 'sails-tax'),
      [$this, 'render_test_field'],
      'sails-tax',
      'sails_tax_email_section'
    );
    
    // Register option
    register_setting(
      Sails_Tax_Settings::OPTION_GROUP,
      self::OPTION_KEY,
      [
        'type' => 'array',
        'sanitize_callback' => [$this, 'sanitize_settings'],
        'default' => [
          'enabled' => 'no',
          'recipients' => get_option('admin_email'),
        ],
      ]
    );
  }
  
  /**
   * Sanitize email settings
   */
  public function sanitize_settings($input) {
    $out = [];
    $out['enabled'] = (isset($input['enabled']) && $input['enabled'] === 'yes') ? 'yes' : 'no';
    
    // Sanitize email list
    $emails = isset($input['recipients']) ? $input['recipients'] : get_option('admin_email');
    $emails_array = array_map('trim', explode(',', $emails));
    $valid_emails = array_filter($emails_array, 'is_email');
    $out['recipients'] = implode(', ', $valid_emails);
    
    return $out;
  }
  
  /**
   * Get email settings
   */
  public static function get_settings() {
    return get_option(self::OPTION_KEY, [
      'enabled' => 'no',
      'recipients' => get_option('admin_email'),
    ]);
  }
  
  /**
   * Render enabled field
   */
  public function render_enabled_field() {
    $settings = self::get_settings();
    $enabled = isset($settings['enabled']) ? $settings['enabled'] : 'no';
    ?>
    <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[enabled]">
      <option value="yes" <?php selected($enabled, 'yes'); ?>><?php esc_html_e('Yes', 'sails-tax'); ?></option>
      <option value="no" <?php selected($enabled, 'no'); ?>><?php esc_html_e('No', 'sails-tax'); ?></option>
    </select>
    <p class="description">
      <?php esc_html_e('Send a tax summary email on the 1st of each month.', 'sails-tax'); ?>
    </p>
    <?php
  }
  
  /**
   * Render recipients field
   */
  public function render_recipients_field() {
    $settings = self::get_settings();
    $recipients = isset($settings['recipients']) ? $settings['recipients'] : get_option('admin_email');
    ?>
    <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[recipients]" value="<?php echo esc_attr($recipients); ?>">
    <p class="description">
      <?php esc_html_e('Comma-separated list of email addresses to receive the monthly report.', 'sails-tax'); ?>
    </p>
    <?php
  }
  
  /**
   * Render test email button
   */
  public function render_test_field() {
    $test_url = wp_nonce_url(
      admin_url('admin-post.php?action=sails_tax_send_test_email'),
      'sails_tax_test_email'
    );
    ?>
    <a href="<?php echo esc_url($test_url); ?>" class="button button-secondary">
      <?php esc_html_e('Send Test Email Now', 'sails-tax'); ?>
    </a>
    <p class="description">
      <?php esc_html_e('Send a test email with last month\'s data to verify your settings.', 'sails-tax'); ?>
    </p>
    <?php
    
    // Show success/error messages
    if (isset($_GET['sails_email_sent'])) {
      echo '<p style="color: #46b450; margin-top: 10px;"><strong>✓ ' . esc_html__('Test email sent successfully!', 'sails-tax') . '</strong></p>';
    } elseif (isset($_GET['sails_email_error'])) {
      echo '<p style="color: #dc3232; margin-top: 10px;"><strong>✗ ' . esc_html__('Failed to send test email.', 'sails-tax') . '</strong></p>';
    }
  }
  
  /**
   * Handle test email request
   */
  public function handle_test_email() {
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'sails_tax_test_email')) {
      wp_die(__('Security check failed.', 'sails-tax'));
    }
    
    if (!current_user_can('manage_woocommerce')) {
      wp_die(__('Unauthorized.', 'sails-tax'));
    }
    
    // Send email for last month
    $success = $this->send_monthly_report(true);
    
    $redirect_url = admin_url('admin.php?page=sails-tax');
    if ($success) {
      $redirect_url = add_query_arg('sails_email_sent', '1', $redirect_url);
    } else {
      $redirect_url = add_query_arg('sails_email_error', '1', $redirect_url);
    }
    
    wp_redirect($redirect_url);
    exit;
  }
  
  /**
   * Send the monthly report email
   *
   * @param bool $is_test Whether this is a test email
   * @return bool Success/failure
   */
  public function send_monthly_report($is_test = false) {
    $settings = self::get_settings();
    
    // Check if enabled (skip check for test emails)
    if (!$is_test && $settings['enabled'] !== 'yes') {
      return false;
    }
    
    $recipients = $settings['recipients'];
    if (empty($recipients)) {
      $recipients = get_option('admin_email');
    }
    
    // Calculate last month's date range
    $last_month_start = date('Y-m-01', strtotime('first day of last month'));
    $last_month_end = date('Y-m-t', strtotime('last day of last month'));
    $month_name = date('F Y', strtotime('last month'));
    
    // Get statistics (reuse reports class methods)
    $stats = $this->get_tax_statistics($last_month_start, $last_month_end);
    $state_breakdown = $this->get_state_breakdown($last_month_start, $last_month_end);
    $refund_stats = Sails_Tax_Refunds::get_refund_statistics($last_month_start, $last_month_end);
    $confidence_breakdown = $this->get_confidence_breakdown($last_month_start, $last_month_end);
    
    $net_tax = $stats['total_tax'] - $refund_stats['total_refund_tax'];
    
    // Build email
    $site_name = get_bloginfo('name');
    $subject = sprintf(
      /* translators: 1: site name 2: month year */
      __('[%1$s] Tax Report for %2$s', 'sails-tax'),
      $site_name,
      $month_name
    );
    
    if ($is_test) {
      $subject = '[TEST] ' . $subject;
    }
    
    $html = $this->build_email_html(
      $month_name,
      $stats,
      $refund_stats,
      $net_tax,
      $state_breakdown,
      $confidence_breakdown,
      $is_test
    );
    
    // Send email
    $headers = [
      'Content-Type: text/html; charset=UTF-8',
      'From: ' . $site_name . ' <' . get_option('admin_email') . '>',
    ];
    
    $sent = wp_mail($recipients, $subject, $html, $headers);
    
    // Log
    if ($sent) {
      error_log('[Sails Tax] Monthly report sent to: ' . $recipients);
    } else {
      error_log('[Sails Tax] Failed to send monthly report to: ' . $recipients);
    }
    
    return $sent;
  }
  
  /**
   * Build the email HTML
   */
  private function build_email_html($month_name, $stats, $refund_stats, $net_tax, $state_breakdown, $confidence_breakdown, $is_test) {
    $site_name = get_bloginfo('name');
    $site_url = home_url();
    $reports_url = admin_url('admin.php?page=sails-tax-reports');
    
    // Format currency values
    $gross_tax = wc_price($stats['total_tax']);
    $refunded_tax = wc_price($refund_stats['total_refund_tax']);
    $net_tax_formatted = wc_price($net_tax);
    $avg_rate = number_format($stats['avg_rate'] * 100, 2) . '%';
    $order_count = number_format($stats['order_count']);
    $exact_zip = number_format($stats['exact_zip_percent'], 1) . '%';
    
    ob_start();
    ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo esc_html($month_name); ?> Tax Report</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f5f5f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f5f5f5;">
    <tr>
      <td align="center" style="padding: 40px 20px;">
        
        <!-- Main Container -->
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
          
          <!-- Header -->
          <tr>
            <td style="background: linear-gradient(135deg, #2271b1 0%, #135e96 100%); padding: 30px 40px; border-radius: 8px 8px 0 0;">
              <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 600;">
                ⛵ <?php esc_html_e('Monthly Tax Report', 'sails-tax'); ?>
              </h1>
              <p style="margin: 8px 0 0; color: rgba(255,255,255,0.9); font-size: 16px;">
                <?php echo esc_html($month_name); ?> • <?php echo esc_html($site_name); ?>
              </p>
              <?php if ($is_test): ?>
              <p style="margin: 12px 0 0; padding: 8px 12px; background: rgba(255,255,255,0.15); border-radius: 4px; color: #ffffff; font-size: 13px; display: inline-block;">
                🧪 <?php esc_html_e('This is a test email', 'sails-tax'); ?>
              </p>
              <?php endif; ?>
            </td>
          </tr>
          
          <!-- Summary Section -->
          <tr>
            <td style="padding: 30px 40px;">
              <h2 style="margin: 0 0 20px; font-size: 18px; color: #1d2327; font-weight: 600;">
                <?php esc_html_e('Tax Summary', 'sails-tax'); ?>
              </h2>
              
              <!-- Stats Grid -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td width="50%" style="padding: 0 10px 15px 0; vertical-align: top;">
                    <div style="background: #f0f6fc; padding: 20px; border-radius: 6px; border-left: 4px solid #2271b1;">
                      <div style="font-size: 13px; color: #50575e; margin-bottom: 5px;"><?php esc_html_e('Gross Tax Collected', 'sails-tax'); ?></div>
                      <div style="font-size: 24px; font-weight: 600; color: #1d2327;"><?php echo $gross_tax; ?></div>
                    </div>
                  </td>
                  <td width="50%" style="padding: 0 0 15px 10px; vertical-align: top;">
                    <div style="background: #fcf0f0; padding: 20px; border-radius: 6px; border-left: 4px solid #b32d2e;">
                      <div style="font-size: 13px; color: #50575e; margin-bottom: 5px;"><?php esc_html_e('Tax Refunded', 'sails-tax'); ?></div>
                      <div style="font-size: 24px; font-weight: 600; color: #b32d2e;">-<?php echo $refunded_tax; ?></div>
                    </div>
                  </td>
                </tr>
                <tr>
                  <td colspan="2" style="padding: 0 0 15px;">
                    <div style="background: #f0faf0; padding: 20px; border-radius: 6px; border-left: 4px solid #46b450;">
                      <div style="font-size: 13px; color: #50575e; margin-bottom: 5px;"><?php esc_html_e('Net Tax (Your Liability)', 'sails-tax'); ?></div>
                      <div style="font-size: 28px; font-weight: 700; color: #46b450;"><?php echo $net_tax_formatted; ?></div>
                    </div>
                  </td>
                </tr>
              </table>
              
              <!-- Additional Stats -->
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top: 5px;">
                <tr>
                  <td width="33%" style="padding: 10px; text-align: center; background: #fafafa; border-radius: 4px;">
                    <div style="font-size: 20px; font-weight: 600; color: #1d2327;"><?php echo $order_count; ?></div>
                    <div style="font-size: 12px; color: #50575e;"><?php esc_html_e('Orders', 'sails-tax'); ?></div>
                  </td>
                  <td width="33%" style="padding: 10px; text-align: center; background: #fafafa; border-radius: 4px; margin: 0 10px;">
                    <div style="font-size: 20px; font-weight: 600; color: #1d2327;"><?php echo $avg_rate; ?></div>
                    <div style="font-size: 12px; color: #50575e;"><?php esc_html_e('Avg Rate', 'sails-tax'); ?></div>
                  </td>
                  <td width="33%" style="padding: 10px; text-align: center; background: #fafafa; border-radius: 4px;">
                    <div style="font-size: 20px; font-weight: 600; color: #1d2327;"><?php echo $exact_zip; ?></div>
                    <div style="font-size: 12px; color: #50575e;"><?php esc_html_e('Exact Match', 'sails-tax'); ?></div>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          
          <?php if (!empty($state_breakdown)): ?>
          <!-- State Breakdown -->
          <tr>
            <td style="padding: 0 40px 30px;">
              <h2 style="margin: 0 0 15px; font-size: 18px; color: #1d2327; font-weight: 600;">
                <?php esc_html_e('Top States', 'sails-tax'); ?>
              </h2>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border: 1px solid #e1e1e1; border-radius: 6px; overflow: hidden;">
                <tr style="background: #f5f5f5;">
                  <td style="padding: 10px 15px; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase;"><?php esc_html_e('State', 'sails-tax'); ?></td>
                  <td style="padding: 10px 15px; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; text-align: right;"><?php esc_html_e('Orders', 'sails-tax'); ?></td>
                  <td style="padding: 10px 15px; font-size: 12px; font-weight: 600; color: #50575e; text-transform: uppercase; text-align: right;"><?php esc_html_e('Tax', 'sails-tax'); ?></td>
                </tr>
                <?php foreach (array_slice($state_breakdown, 0, 5) as $i => $row): ?>
                <tr style="<?php echo $i % 2 === 0 ? '' : 'background: #fafafa;'; ?>">
                  <td style="padding: 12px 15px; font-size: 14px; color: #1d2327; font-weight: 500;"><?php echo esc_html($row['state']); ?></td>
                  <td style="padding: 12px 15px; font-size: 14px; color: #50575e; text-align: right;"><?php echo esc_html(number_format($row['count'])); ?></td>
                  <td style="padding: 12px 15px; font-size: 14px; color: #1d2327; font-weight: 500; text-align: right;"><?php echo wc_price($row['total_tax']); ?></td>
                </tr>
                <?php endforeach; ?>
              </table>
              <?php if (count($state_breakdown) > 5): ?>
              <p style="margin: 10px 0 0; font-size: 13px; color: #666;">
                <?php printf(esc_html__('+ %d more states. View full breakdown in your dashboard.', 'sails-tax'), count($state_breakdown) - 5); ?>
              </p>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          
          <?php if (!empty($confidence_breakdown)): ?>
          <!-- Confidence Breakdown -->
          <tr>
            <td style="padding: 0 40px 30px;">
              <h2 style="margin: 0 0 15px; font-size: 18px; color: #1d2327; font-weight: 600;">
                <?php esc_html_e('Calculation Accuracy', 'sails-tax'); ?>
              </h2>
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <?php foreach ($confidence_breakdown as $row): 
                  $color = $this->get_confidence_color($row['confidence']);
                  $bar_width = min(100, max(5, $row['percentage']));
                ?>
                <tr>
                  <td style="padding: 8px 0;">
                    <div style="display: flex; align-items: center; margin-bottom: 4px;">
                      <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php echo esc_attr($color); ?>; margin-right: 8px;"></span>
                      <span style="font-size: 13px; color: #1d2327;"><?php echo esc_html(ucwords(str_replace('_', ' ', $row['confidence']))); ?></span>
                      <span style="font-size: 13px; color: #666; margin-left: auto;"><?php echo esc_html(number_format($row['percentage'], 1)); ?>%</span>
                    </div>
                    <div style="background: #e1e1e1; border-radius: 3px; height: 6px; overflow: hidden;">
                      <div style="background: <?php echo esc_attr($color); ?>; height: 100%; width: <?php echo esc_attr($bar_width); ?>%;"></div>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </table>
            </td>
          </tr>
          <?php endif; ?>
          
          <!-- CTA Button -->
          <tr>
            <td style="padding: 0 40px 30px; text-align: center;">
              <a href="<?php echo esc_url($reports_url); ?>" style="display: inline-block; background: #2271b1; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 6px; font-size: 15px; font-weight: 500;">
                <?php esc_html_e('View Full Report →', 'sails-tax'); ?>
              </a>
            </td>
          </tr>
          
          <!-- Footer -->
          <tr>
            <td style="background: #f5f5f5; padding: 25px 40px; border-radius: 0 0 8px 8px; text-align: center;">
              <p style="margin: 0; font-size: 13px; color: #666;">
                <?php esc_html_e('Sent by', 'sails-tax'); ?> <a href="https://sails.tax" style="color: #2271b1; text-decoration: none;">Sails Tax</a> • <a href="<?php echo esc_url($site_url); ?>" style="color: #2271b1; text-decoration: none;"><?php echo esc_html($site_name); ?></a>
              </p>
              <p style="margin: 10px 0 0; font-size: 12px; color: #999;">
                <?php esc_html_e('To change email settings, visit WooCommerce → Sails Tax in your admin.', 'sails-tax'); ?>
              </p>
            </td>
          </tr>
          
        </table>
        
      </td>
    </tr>
  </table>
</body>
</html>
    <?php
    return ob_get_clean();
  }
  
  /**
   * Get tax statistics for date range
   * Reuses logic from Sails_Tax_Reports
   */
  private function get_tax_statistics($start_date, $end_date) {
    global $wpdb;
    
    $use_hpos = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
      && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();

    if ($use_hpos) {
      $orders_table = $wpdb->prefix . 'wc_orders';
      $meta_table = $wpdb->prefix . 'wc_orders_meta';
      
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
   * Get state breakdown
   */
  private function get_state_breakdown($start_date, $end_date) {
    global $wpdb;
    
    $use_hpos = class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') 
      && wc_get_container()->get(\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled();
    
    if ($use_hpos) {
      $orders_table = $wpdb->prefix . 'wc_orders';
      $meta_table = $wpdb->prefix . 'wc_orders_meta';
      $addr_table = $wpdb->prefix . 'wc_order_addresses';
      
      $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
          a.state as state,
          COUNT(DISTINCT o.id) as count,
          COALESCE(SUM(CAST(ma.meta_value AS DECIMAL(10,2))), 0) as total_tax,
          COALESCE(AVG(CAST(mr.meta_value AS DECIMAL(10,6))), 0) as avg_rate
        FROM {$orders_table} o
        INNER JOIN {$addr_table} a ON o.id = a.order_id AND a.address_type = 'billing'
        INNER JOIN {$meta_table} ma ON o.id = ma.order_id AND ma.meta_key = '_sails_tax_amount'
        LEFT JOIN {$meta_table} mr ON o.id = mr.order_id AND mr.meta_key = '_sails_tax_rate'
        WHERE o.date_created_gmt >= %s AND o.date_created_gmt <= %s
        AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        AND a.state IS NOT NULL AND a.state != ''
        GROUP BY a.state
        ORDER BY total_tax DESC",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ), ARRAY_A);
    } else {
      $results = $wpdb->get_results($wpdb->prepare(
        "SELECT 
          ms.meta_value as state,
          COUNT(DISTINCT p.ID) as count,
          COALESCE(SUM(CAST(ma.meta_value AS DECIMAL(10,2))), 0) as total_tax,
          COALESCE(AVG(CAST(mr.meta_value AS DECIMAL(10,6))), 0) as avg_rate
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} ms ON p.ID = ms.post_id AND ms.meta_key = '_billing_state'
        INNER JOIN {$wpdb->postmeta} ma ON p.ID = ma.post_id AND ma.meta_key = '_sails_tax_amount'
        LEFT JOIN {$wpdb->postmeta} mr ON p.ID = mr.post_id AND mr.meta_key = '_sails_tax_rate'
        WHERE p.post_type = 'shop_order'
        AND p.post_date >= %s AND p.post_date <= %s
        AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
        AND ms.meta_value IS NOT NULL AND ms.meta_value != ''
        GROUP BY ms.meta_value
        ORDER BY total_tax DESC",
        $start_date . ' 00:00:00',
        $end_date . ' 23:59:59'
      ), ARRAY_A);
    }
    
    return $results ?: [];
  }
  
  /**
   * Get confidence breakdown
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
        return '#666666';
    }
  }
}
