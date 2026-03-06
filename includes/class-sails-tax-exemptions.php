<?php
/**
 * Tax Exemption Handler for Sails Tax
 *
 * Manages tax-exempt customers and exemption certificates.
 *
 * @package Sails_Tax
 */

if (!defined('ABSPATH')) exit;

class Sails_Tax_Exemptions {
  const META_KEY_EXEMPT = '_sails_tax_exempt';
  const META_KEY_CERT_NUMBER = '_sails_tax_exempt_cert';
  const META_KEY_CERT_STATES = '_sails_tax_exempt_states';
  const META_KEY_CERT_EXPIRY = '_sails_tax_exempt_expiry';
  const META_KEY_CERT_REASON = '_sails_tax_exempt_reason';

  public function register() {
    // Add fields to user profile (admin)
    add_action('show_user_profile', [$this, 'render_user_profile_fields']);
    add_action('edit_user_profile', [$this, 'render_user_profile_fields']);
    add_action('personal_options_update', [$this, 'save_user_profile_fields']);
    add_action('edit_user_profile_update', [$this, 'save_user_profile_fields']);

    // Add column to users list
    add_filter('manage_users_columns', [$this, 'add_exempt_column']);
    add_filter('manage_users_custom_column', [$this, 'render_exempt_column'], 10, 3);
    
    // Add exemption info to order admin
    add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'render_order_exemption_info']);
  }

  /**
   * Check if a customer is tax exempt
   *
   * @param int|null $user_id User ID (null for current user)
   * @param string|null $state State code to check (if exemption is state-specific)
   * @return bool
   */
  public static function is_customer_exempt($user_id = null, $state = null) {
    // Check if exemptions are enabled
    $opts = Sails_Tax_Settings::get();
    if (($opts['exemptions_enabled'] ?? 'no') !== 'yes') {
      return false;
    }

    if (!$user_id) {
      $user_id = get_current_user_id();
    }

    if (!$user_id) {
      return false; // Guest checkout - not exempt
    }

    // Check if user is marked as exempt
    $is_exempt = get_user_meta($user_id, self::META_KEY_EXEMPT, true);
    if ($is_exempt !== 'yes') {
      return false;
    }

    // Check expiry date
    $expiry = get_user_meta($user_id, self::META_KEY_CERT_EXPIRY, true);
    if ($expiry && strtotime($expiry) < time()) {
      return false; // Certificate expired
    }

    // Check state-specific exemption
    if ($state) {
      $exempt_states = get_user_meta($user_id, self::META_KEY_CERT_STATES, true);
      if (!empty($exempt_states) && is_array($exempt_states)) {
        // If states are specified, check if this state is included
        if (!in_array($state, $exempt_states, true) && !in_array('ALL', $exempt_states, true)) {
          return false;
        }
      }
      // If no states specified, exemption applies to all states
    }

    return true;
  }

  /**
   * Get exemption details for a customer
   *
   * @param int $user_id
   * @return array|null
   */
  public static function get_exemption_details($user_id) {
    if (!self::is_customer_exempt($user_id)) {
      return null;
    }

    return [
      'cert_number' => get_user_meta($user_id, self::META_KEY_CERT_NUMBER, true),
      'states' => get_user_meta($user_id, self::META_KEY_CERT_STATES, true) ?: [],
      'expiry' => get_user_meta($user_id, self::META_KEY_CERT_EXPIRY, true),
      'reason' => get_user_meta($user_id, self::META_KEY_CERT_REASON, true),
    ];
  }

  /**
   * Render tax exemption fields on user profile
   */
  public function render_user_profile_fields($user) {
    // Only show for users with WooCommerce management caps
    if (!current_user_can('manage_woocommerce')) {
      return;
    }

    // Check if exemptions are enabled
    $opts = Sails_Tax_Settings::get();
    if (($opts['exemptions_enabled'] ?? 'no') !== 'yes') {
      return;
    }

    $is_exempt = get_user_meta($user->ID, self::META_KEY_EXEMPT, true);
    $cert_number = get_user_meta($user->ID, self::META_KEY_CERT_NUMBER, true);
    $cert_states = get_user_meta($user->ID, self::META_KEY_CERT_STATES, true) ?: [];
    $cert_expiry = get_user_meta($user->ID, self::META_KEY_CERT_EXPIRY, true);
    $cert_reason = get_user_meta($user->ID, self::META_KEY_CERT_REASON, true);

    // Get list of US states
    $states = self::get_us_states();
    ?>
    <h2><?php esc_html_e('Tax Exemption (Sails Tax)', 'sails-tax'); ?></h2>
    <table class="form-table" role="presentation">
      <tr>
        <th><label for="sails_tax_exempt"><?php esc_html_e('Tax Exempt', 'sails-tax'); ?></label></th>
        <td>
          <label>
            <input type="checkbox" name="sails_tax_exempt" id="sails_tax_exempt" value="yes" <?php checked($is_exempt, 'yes'); ?> />
            <?php esc_html_e('This customer is tax exempt', 'sails-tax'); ?>
          </label>
        </td>
      </tr>
      <tr class="sails-exempt-field" style="<?php echo $is_exempt !== 'yes' ? 'display:none;' : ''; ?>">
        <th><label for="sails_tax_cert_number"><?php esc_html_e('Certificate Number', 'sails-tax'); ?></label></th>
        <td>
          <input type="text" name="sails_tax_cert_number" id="sails_tax_cert_number" value="<?php echo esc_attr($cert_number); ?>" class="regular-text" />
          <p class="description"><?php esc_html_e('Exemption certificate or resale permit number.', 'sails-tax'); ?></p>
        </td>
      </tr>
      <tr class="sails-exempt-field" style="<?php echo $is_exempt !== 'yes' ? 'display:none;' : ''; ?>">
        <th><label for="sails_tax_cert_reason"><?php esc_html_e('Exemption Reason', 'sails-tax'); ?></label></th>
        <td>
          <select name="sails_tax_cert_reason" id="sails_tax_cert_reason">
            <option value=""><?php esc_html_e('— Select —', 'sails-tax'); ?></option>
            <option value="resale" <?php selected($cert_reason, 'resale'); ?>><?php esc_html_e('Resale / Wholesale', 'sails-tax'); ?></option>
            <option value="government" <?php selected($cert_reason, 'government'); ?>><?php esc_html_e('Government Entity', 'sails-tax'); ?></option>
            <option value="nonprofit" <?php selected($cert_reason, 'nonprofit'); ?>><?php esc_html_e('Non-Profit Organization', 'sails-tax'); ?></option>
            <option value="tribal" <?php selected($cert_reason, 'tribal'); ?>><?php esc_html_e('Tribal / Native American', 'sails-tax'); ?></option>
            <option value="agriculture" <?php selected($cert_reason, 'agriculture'); ?>><?php esc_html_e('Agricultural', 'sails-tax'); ?></option>
            <option value="manufacturing" <?php selected($cert_reason, 'manufacturing'); ?>><?php esc_html_e('Manufacturing', 'sails-tax'); ?></option>
            <option value="diplomatic" <?php selected($cert_reason, 'diplomatic'); ?>><?php esc_html_e('Diplomatic / Foreign Mission', 'sails-tax'); ?></option>
            <option value="other" <?php selected($cert_reason, 'other'); ?>><?php esc_html_e('Other', 'sails-tax'); ?></option>
          </select>
        </td>
      </tr>
      <tr class="sails-exempt-field" style="<?php echo $is_exempt !== 'yes' ? 'display:none;' : ''; ?>">
        <th><label for="sails_tax_cert_states"><?php esc_html_e('Exempt States', 'sails-tax'); ?></label></th>
        <td>
          <select name="sails_tax_cert_states[]" id="sails_tax_cert_states" multiple="multiple" style="width: 300px; height: 150px;">
            <option value="ALL" <?php echo in_array('ALL', $cert_states, true) ? 'selected' : ''; ?>><?php esc_html_e('All States', 'sails-tax'); ?></option>
            <?php foreach ($states as $code => $name) : ?>
              <option value="<?php echo esc_attr($code); ?>" <?php echo in_array($code, $cert_states, true) ? 'selected' : ''; ?>>
                <?php echo esc_html($name); ?>
              </option>
            <?php endforeach; ?>
          </select>
          <p class="description"><?php esc_html_e('Hold Ctrl/Cmd to select multiple states. Leave empty or select "All States" for nationwide exemption.', 'sails-tax'); ?></p>
        </td>
      </tr>
      <tr class="sails-exempt-field" style="<?php echo $is_exempt !== 'yes' ? 'display:none;' : ''; ?>">
        <th><label for="sails_tax_cert_expiry"><?php esc_html_e('Expiry Date', 'sails-tax'); ?></label></th>
        <td>
          <input type="date" name="sails_tax_cert_expiry" id="sails_tax_cert_expiry" value="<?php echo esc_attr($cert_expiry); ?>" />
          <p class="description"><?php esc_html_e('Leave empty for no expiration.', 'sails-tax'); ?></p>
        </td>
      </tr>
    </table>
    <script>
    document.getElementById('sails_tax_exempt').addEventListener('change', function() {
      var fields = document.querySelectorAll('.sails-exempt-field');
      fields.forEach(function(field) {
        field.style.display = this.checked ? '' : 'none';
      }, this);
    });
    </script>
    <?php
  }

  /**
   * Save tax exemption fields from user profile
   */
  public function save_user_profile_fields($user_id) {
    if (!current_user_can('manage_woocommerce')) {
      return;
    }

    // Verify nonce (WP handles this for profile updates)
    if (!current_user_can('edit_user', $user_id)) {
      return;
    }

    $is_exempt = isset($_POST['sails_tax_exempt']) && $_POST['sails_tax_exempt'] === 'yes' ? 'yes' : 'no';
    update_user_meta($user_id, self::META_KEY_EXEMPT, $is_exempt);

    if ($is_exempt === 'yes') {
      update_user_meta($user_id, self::META_KEY_CERT_NUMBER, sanitize_text_field($_POST['sails_tax_cert_number'] ?? ''));
      update_user_meta($user_id, self::META_KEY_CERT_REASON, sanitize_text_field($_POST['sails_tax_cert_reason'] ?? ''));
      
      $states = isset($_POST['sails_tax_cert_states']) ? array_map('sanitize_text_field', $_POST['sails_tax_cert_states']) : [];
      update_user_meta($user_id, self::META_KEY_CERT_STATES, $states);
      
      $expiry = !empty($_POST['sails_tax_cert_expiry']) ? sanitize_text_field($_POST['sails_tax_cert_expiry']) : '';
      update_user_meta($user_id, self::META_KEY_CERT_EXPIRY, $expiry);
    }
  }

  /**
   * Add tax exempt column to users list
   */
  public function add_exempt_column($columns) {
    $opts = Sails_Tax_Settings::get();
    if (($opts['exemptions_enabled'] ?? 'no') !== 'yes') {
      return $columns;
    }
    
    $columns['sails_tax_exempt'] = __('Tax Exempt', 'sails-tax');
    return $columns;
  }

  /**
   * Render tax exempt column content
   */
  public function render_exempt_column($output, $column_name, $user_id) {
    if ($column_name !== 'sails_tax_exempt') {
      return $output;
    }

    if (self::is_customer_exempt($user_id)) {
      $details = self::get_exemption_details($user_id);
      $label = '✓ ' . __('Exempt', 'sails-tax');
      if (!empty($details['reason'])) {
        $label .= ' (' . ucfirst($details['reason']) . ')';
      }
      return '<span style="color: #2e7d32;">' . esc_html($label) . '</span>';
    }

    return '—';
  }

  /**
   * Show exemption info on order admin page
   */
  public function render_order_exemption_info($order) {
    $user_id = $order->get_customer_id();
    if (!$user_id) {
      return;
    }

    // Check if this order had an exempt customer
    $was_exempt = $order->get_meta('_sails_tax_exempt_applied');
    
    if ($was_exempt === 'yes') {
      $cert_number = $order->get_meta('_sails_tax_exempt_cert');
      $reason = $order->get_meta('_sails_tax_exempt_reason');
      ?>
      <p class="form-field form-field-wide">
        <strong><?php esc_html_e('Tax Exemption Applied:', 'sails-tax'); ?></strong><br>
        <?php if ($reason) : ?>
          <?php echo esc_html(ucfirst($reason)); ?><br>
        <?php endif; ?>
        <?php if ($cert_number) : ?>
          <?php esc_html_e('Certificate:', 'sails-tax'); ?> <?php echo esc_html($cert_number); ?>
        <?php endif; ?>
      </p>
      <?php
    } elseif (self::is_customer_exempt($user_id)) {
      // Customer is currently exempt but this order wasn't marked
      ?>
      <p class="form-field form-field-wide">
        <em style="color: #2e7d32;">
          <?php esc_html_e('Note: This customer is now tax exempt (may not have been at time of order).', 'sails-tax'); ?>
        </em>
      </p>
      <?php
    }
  }

  /**
   * Get list of US states
   */
  private static function get_us_states() {
    return [
      'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
      'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
      'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
      'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
      'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
      'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
      'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
      'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
      'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
      'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
      'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
      'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
      'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
    ];
  }
}
