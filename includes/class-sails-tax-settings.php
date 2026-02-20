<?php

if (!defined('ABSPATH')) exit;

class Sails_Tax_Settings {
  const OPTION_GROUP = 'sails_tax_options';
  const OPTION_NAME  = 'sails_tax_settings';

  public function register() {
    add_action('admin_menu', [$this, 'add_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('admin_init', [$this, 'handle_cache_clear']);
  }

  /**
   * Handle cache clear action
   */
  public function handle_cache_clear() {
    if (!isset($_GET['sails_clear_cache']) || $_GET['sails_clear_cache'] !== '1') {
      return;
    }
    
    if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'sails_clear_cache')) {
      return;
    }
    
    if (!current_user_can('manage_woocommerce')) {
      return;
    }
    
    global $wpdb;
    $deleted = $wpdb->query(
      "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sails_tax_%' OR option_name LIKE '_transient_timeout_sails_tax_%'"
    );
    
    // Store success message for display
    set_transient('sails_cache_cleared', true, 30);
    
    // Redirect back without the action params
    wp_redirect(admin_url('admin.php?page=sails-tax'));
    exit;
  }

  public function add_menu() {
    add_submenu_page(
      'woocommerce',
      'Sails Tax',
      'Sails Tax',
      'manage_woocommerce',
      'sails-tax',
      [$this, 'render_page']
    );
  }

  public function register_settings() {
    // Register AJAX handler for connection test
    add_action('wp_ajax_sails_tax_test_connection', [$this, 'ajax_test_connection']);
    
    register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
      'type' => 'array',
      'sanitize_callback' => [$this, 'sanitize'],
      'default' => [
        'enabled' => 'yes',
        'api_base_url' => 'https://sails.tax',
        'api_key' => '',
        'customer_disclaimer' => 'no',
      ],
    ]);

    add_settings_section(
      'sails_tax_main',
      'Sails Tax Settings',
      function () {
        echo '<p>Configure Sails Tax estimation for checkout.</p>';
      },
      'sails-tax'
    );

    $this->add_field_yesno('enabled', 'Enable Sails Tax', 'Turn on tax estimation at checkout.');
    $this->add_field_text('api_base_url', 'Sails API Base URL', 'Example: https://sails.tax');
    $this->add_field_password('api_key', 'Sails API Key', 'Create an API key in Sails and paste it here.');

    add_settings_field(
      'customer_disclaimer',
      'Show estimate note to customer',
      [$this, 'field_customer_disclaimer'],
      'sails-tax',
      'sails_tax_main'
    );

    $this->add_field_yesno('debug_logging', 'Enable Debug Logging', 'Log API calls to WooCommerce logs for troubleshooting.');
  }

  public function sanitize($input) {
    $out = [];
    $out['enabled'] = (isset($input['enabled']) && $input['enabled'] === 'yes') ? 'yes' : 'no';
    $out['api_base_url'] = isset($input['api_base_url']) ? esc_url_raw(trim($input['api_base_url'])) : 'https://sails.tax';
    $out['api_key'] = isset($input['api_key']) ? sanitize_text_field(trim($input['api_key'])) : '';
    $out['customer_disclaimer'] = (isset($input['customer_disclaimer']) && $input['customer_disclaimer'] === 'yes') ? 'yes' : 'no';
    $out['debug_logging'] = (isset($input['debug_logging']) && $input['debug_logging'] === 'yes') ? 'yes' : 'no';
    return $out;
  }

  public static function get() {
    return get_option(self::OPTION_NAME, []);
  }

  private function add_field_text($key, $label, $help) {
    add_settings_field(
      $key,
      $label,
      function () use ($key, $help) {
        $opts = self::get();
        $val = isset($opts[$key]) ? esc_attr($opts[$key]) : '';
        echo "<input type='text' class='regular-text' name='" . self::OPTION_NAME . "[$key]' value='$val'/>";
        echo "<p class='description'>$help</p>";
      },
      'sails-tax',
      'sails_tax_main'
    );
  }

  private function add_field_password($key, $label, $help) {
    add_settings_field(
      $key,
      $label,
      function () use ($key, $help) {
        $opts = self::get();
        $val = isset($opts[$key]) ? esc_attr($opts[$key]) : '';
        echo "<input type='password' class='regular-text' name='" . self::OPTION_NAME . "[$key]' value='$val' autocomplete='off'/>";
        echo "<p class='description'>$help</p>";
      },
      'sails-tax',
      'sails_tax_main'
    );
  }

  private function add_field_yesno($key, $label, $help) {
    add_settings_field(
      $key,
      $label,
      function () use ($key, $help) {
        $opts = self::get();
        $val = isset($opts[$key]) ? $opts[$key] : 'no';
        echo "<select name='" . self::OPTION_NAME . "[$key]'>";
        echo "<option value='yes'" . selected($val, 'yes', false) . ">Yes</option>";
        echo "<option value='no'" . selected($val, 'no', false) . ">No</option>";
        echo "</select>";
        echo "<p class='description'>$help</p>";
      },
      'sails-tax',
      'sails_tax_main'
    );
  }

  public function field_customer_disclaimer() {
    $opts = self::get();
    $val = isset($opts['customer_disclaimer']) ? $opts['customer_disclaimer'] : 'no';

    echo "<select name='" . self::OPTION_NAME . "[customer_disclaimer]'>";
    echo "<option value='no'" . selected($val, 'no', false) . ">No (recommended)</option>";
    echo "<option value='yes'" . selected($val, 'yes', false) . ">Yes</option>";
    echo "</select>";

    echo "<p class='description'>";
    echo "If enabled, customers will see a short note at checkout whenever Sails can only provide an estimated rate (for example, state-only). ";
    echo "This may reduce conversion but improves transparency.";
    echo "</p>";
  }

  public function render_page() {
    echo '<div class="wrap">';
    echo '<h1>Sails Tax</h1>';
    
    // Show cache cleared message
    if (get_transient('sails_cache_cleared')) {
      delete_transient('sails_cache_cleared');
      echo '<div class="notice notice-success is-dismissible"><p>Tax rate cache cleared successfully.</p></div>';
    }
    
    echo '<form method="post" action="options.php">';
    settings_fields(self::OPTION_GROUP);
    do_settings_sections('sails-tax');
    submit_button();
    echo '</form>';
    
    // Connection test section
    echo '<hr style="margin: 30px 0;">';
    echo '<h2>Test API Connection</h2>';
    echo '<p>Verify your API key is working correctly.</p>';
    echo '<button type="button" id="sails-test-connection" class="button button-secondary">Test Connection</button>';
    echo '<span id="sails-test-result" style="margin-left: 15px;"></span>';
    
    // Cache management section
    echo '<hr style="margin: 30px 0;">';
    echo '<h2>Cache Management</h2>';
    echo '<p>Tax rates are cached for 5 minutes to improve checkout speed. Clear the cache if you need fresh rates immediately.</p>';
    $clear_url = wp_nonce_url(admin_url('admin.php?page=sails-tax&sails_clear_cache=1'), 'sails_clear_cache');
    echo '<a href="' . esc_url($clear_url) . '" class="button button-secondary">Clear Tax Cache</a>';
    
    // Inline script for AJAX test
    ?>
    <script type="text/javascript">
    document.getElementById('sails-test-connection').addEventListener('click', function() {
      var btn = this;
      var result = document.getElementById('sails-test-result');
      
      btn.disabled = true;
      btn.textContent = 'Testing...';
      result.innerHTML = '';
      
      fetch(ajaxurl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=sails_tax_test_connection&_wpnonce=<?php echo wp_create_nonce('sails_tax_test'); ?>'
      })
      .then(function(response) { return response.json(); })
      .then(function(data) {
        btn.disabled = false;
        btn.textContent = 'Test Connection';
        if (data.success) {
          result.innerHTML = '<span style="color: #46b450;">✓ ' + data.data.message + '</span>';
        } else {
          result.innerHTML = '<span style="color: #dc3232;">✗ ' + data.data.message + '</span>';
        }
      })
      .catch(function(err) {
        btn.disabled = false;
        btn.textContent = 'Test Connection';
        result.innerHTML = '<span style="color: #dc3232;">✗ Request failed</span>';
      });
    });
    </script>
    <?php
    echo '</div>';
  }

  /**
   * AJAX handler for API connection test
   */
  public function ajax_test_connection() {
    check_ajax_referer('sails_tax_test', '_wpnonce');
    
    if (!current_user_can('manage_woocommerce')) {
      wp_send_json_error(['message' => 'Unauthorized']);
      return;
    }

    $opts = self::get();
    $api_key = isset($opts['api_key']) ? $opts['api_key'] : '';
    $base_url = isset($opts['api_base_url']) ? rtrim($opts['api_base_url'], '/') : 'https://sails.tax';

    if (empty($api_key)) {
      wp_send_json_error(['message' => 'No API key configured']);
      return;
    }

    // Test with a sample calculation
    $api = new Sails_Tax_API();
    $result = $api->calculate(100.00, '90210', 'CA');

    if (is_wp_error($result)) {
      wp_send_json_error(['message' => $result->get_error_message()]);
      return;
    }

    // Success - show what we got back
    $confidence = isset($result['confidence']) ? $result['confidence'] : 'unknown';
    $rate = isset($result['rate']) ? ($result['rate'] * 100) . '%' : 'N/A';
    
    wp_send_json_success([
      'message' => "Connected! Test rate for 90210, CA: {$rate} ({$confidence} confidence)"
    ]);
  }
}
