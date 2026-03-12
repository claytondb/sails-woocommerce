<?php
/**
 * Sails Tax Nexus Management
 *
 * Manages tax nexus state configuration. Merchants can specify which US states
 * they have economic nexus in, and Sails Tax will only collect tax for those
 * states — avoiding unnecessary tax collection in states where you have no
 * legal obligation to collect.
 *
 * @package SailsTax
 * @since 1.1.0
 */

if (!defined('ABSPATH')) exit;

class Sails_Tax_Nexus {

  /**
   * All US states as code => name
   */
  const US_STATES = [
    'AL' => 'Alabama',       'AK' => 'Alaska',        'AZ' => 'Arizona',
    'AR' => 'Arkansas',      'CA' => 'California',     'CO' => 'Colorado',
    'CT' => 'Connecticut',   'DE' => 'Delaware',       'FL' => 'Florida',
    'GA' => 'Georgia',       'HI' => 'Hawaii',         'ID' => 'Idaho',
    'IL' => 'Illinois',      'IN' => 'Indiana',        'IA' => 'Iowa',
    'KS' => 'Kansas',        'KY' => 'Kentucky',       'LA' => 'Louisiana',
    'ME' => 'Maine',         'MD' => 'Maryland',       'MA' => 'Massachusetts',
    'MI' => 'Michigan',      'MN' => 'Minnesota',      'MS' => 'Mississippi',
    'MO' => 'Missouri',      'MT' => 'Montana',        'NE' => 'Nebraska',
    'NV' => 'Nevada',        'NH' => 'New Hampshire',  'NJ' => 'New Jersey',
    'NM' => 'New Mexico',    'NY' => 'New York',       'NC' => 'North Carolina',
    'ND' => 'North Dakota',  'OH' => 'Ohio',           'OK' => 'Oklahoma',
    'OR' => 'Oregon',        'PA' => 'Pennsylvania',   'RI' => 'Rhode Island',
    'SC' => 'South Carolina','SD' => 'South Dakota',   'TN' => 'Tennessee',
    'TX' => 'Texas',         'UT' => 'Utah',           'VT' => 'Vermont',
    'VA' => 'Virginia',      'WA' => 'Washington',     'WV' => 'West Virginia',
    'WI' => 'Wisconsin',     'WY' => 'Wyoming',        'DC' => 'District of Columbia',
  ];

  /**
   * Check if nexus-only mode is enabled.
   *
   * When disabled (default), Sails Tax calculates tax for all states.
   * When enabled, only states in the nexus list will have tax calculated.
   *
   * @return bool
   */
  public static function is_nexus_mode_enabled(): bool {
    $opts = Sails_Tax_Settings::get();
    return ($opts['nexus_mode'] ?? 'all') === 'nexus_only';
  }

  /**
   * Get the list of configured nexus states.
   *
   * @return string[] Array of 2-letter state codes e.g. ['CA', 'NY', 'TX']
   */
  public static function get_nexus_states(): array {
    $opts = Sails_Tax_Settings::get();
    $raw = $opts['nexus_states'] ?? '';
    if (empty($raw)) return [];
    $states = array_map('trim', explode(',', $raw));
    return array_filter($states, fn($s) => strlen($s) === 2);
  }

  /**
   * Check whether a given state is a nexus state for this merchant.
   *
   * Always returns true when nexus mode is "all" (disabled).
   * Returns true when nexus mode is "nexus_only" and the state is in the list.
   *
   * @param string $state_code 2-letter US state code
   * @return bool True if tax should be collected in this state
   */
  public static function is_nexus_state(string $state_code): bool {
    if (!self::is_nexus_mode_enabled()) {
      return true; // Collect tax in all states
    }
    $nexus_states = self::get_nexus_states();
    if (empty($nexus_states)) {
      return true; // No states configured — don't block anything
    }
    return in_array(strtoupper($state_code), array_map('strtoupper', $nexus_states), true);
  }

  /**
   * Register the Nexus Management admin page under Sails Tax settings.
   */
  public function register(): void {
    add_action('admin_menu', [$this, 'add_nexus_page']);
    add_action('admin_post_sails_tax_save_nexus', [$this, 'handle_save_nexus']);
    add_action('wp_ajax_sails_tax_get_nexus_states', [$this, 'ajax_get_nexus_states']);
  }

  /**
   * Add the Nexus submenu page.
   */
  public function add_nexus_page(): void {
    add_submenu_page(
      'woocommerce',
      __('Sails Tax Nexus', 'sails-tax'),
      __('Tax Nexus', 'sails-tax'),
      'manage_woocommerce',
      'sails-tax-nexus',
      [$this, 'render_nexus_page']
    );
  }

  /**
   * Handle the nexus settings form submission.
   */
  public function handle_save_nexus(): void {
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'sails_save_nexus')) {
      wp_die(__('Security check failed.', 'sails-tax'));
    }
    if (!current_user_can('manage_woocommerce')) {
      wp_die(__('Unauthorized.', 'sails-tax'));
    }

    $opts = Sails_Tax_Settings::get();

    // Save nexus mode
    $opts['nexus_mode'] = (isset($_POST['nexus_mode']) && $_POST['nexus_mode'] === 'nexus_only')
      ? 'nexus_only'
      : 'all';

    // Save nexus states — validate each is a real 2-letter code
    $submitted_states = isset($_POST['nexus_states']) && is_array($_POST['nexus_states'])
      ? $_POST['nexus_states']
      : [];

    $valid_states = [];
    foreach ($submitted_states as $state) {
      $state = strtoupper(sanitize_text_field($state));
      if (isset(self::US_STATES[$state])) {
        $valid_states[] = $state;
      }
    }
    sort($valid_states);
    $opts['nexus_states'] = implode(',', $valid_states);

    update_option(Sails_Tax_Settings::OPTION_NAME, $opts);
    set_transient('sails_nexus_saved', true, 30);

    wp_redirect(admin_url('admin.php?page=sails-tax-nexus'));
    exit;
  }

  /**
   * AJAX: return current nexus states as JSON (for external JS use).
   */
  public function ajax_get_nexus_states(): void {
    check_ajax_referer('sails_tax_test', '_wpnonce');
    wp_send_json_success([
      'nexus_mode'   => self::is_nexus_mode_enabled() ? 'nexus_only' : 'all',
      'nexus_states' => self::get_nexus_states(),
    ]);
  }

  /**
   * Render the Nexus Management page.
   */
  public function render_nexus_page(): void {
    $opts       = Sails_Tax_Settings::get();
    $nexus_mode = $opts['nexus_mode'] ?? 'all';
    $saved_raw  = $opts['nexus_states'] ?? '';
    $saved_list = $saved_raw ? array_map('trim', explode(',', $saved_raw)) : [];

    $saved = get_transient('sails_nexus_saved');
    if ($saved) delete_transient('sails_nexus_saved');

    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Tax Nexus Management', 'sails-tax'); ?></h1>

      <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible">
          <p><?php esc_html_e('Nexus settings saved.', 'sails-tax'); ?></p>
        </div>
      <?php endif; ?>

      <div style="max-width: 800px;">
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px 16px; margin-bottom: 20px; border-radius: 4px;">
          <strong><?php esc_html_e('What is tax nexus?', 'sails-tax'); ?></strong><br>
          <?php esc_html_e(
            'You\'re legally required to collect sales tax only in states where you have "nexus" — ' .
            'a significant business presence such as a physical office, warehouse, employees, or ' .
            'exceeding an economic threshold (typically $100K in sales or 200 transactions). ' .
            'Use Nexus-Only mode to avoid collecting tax in states where you have no obligation.',
            'sails-tax'
          ); ?>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
          <?php wp_nonce_field('sails_save_nexus'); ?>
          <input type="hidden" name="action" value="sails_tax_save_nexus">

          <table class="form-table" role="presentation">
            <tr>
              <th scope="row">
                <label for="nexus_mode"><?php esc_html_e('Collection Mode', 'sails-tax'); ?></label>
              </th>
              <td>
                <fieldset>
                  <label style="display: block; margin-bottom: 8px;">
                    <input type="radio" name="nexus_mode" value="all"
                      <?php checked($nexus_mode, 'all'); ?>>
                    <strong><?php esc_html_e('All States (default)', 'sails-tax'); ?></strong><br>
                    <span style="color: #666; margin-left: 20px;">
                      <?php esc_html_e('Collect tax in every US state. Good for large merchants with broad nexus.', 'sails-tax'); ?>
                    </span>
                  </label>
                  <label style="display: block; margin-top: 12px;">
                    <input type="radio" name="nexus_mode" value="nexus_only"
                      id="nexus_only_radio" <?php checked($nexus_mode, 'nexus_only'); ?>>
                    <strong><?php esc_html_e('Nexus States Only', 'sails-tax'); ?></strong><br>
                    <span style="color: #666; margin-left: 20px;">
                      <?php esc_html_e('Only collect tax in states you\'ve selected below. Tax is skipped (shown as $0.00) for all other states.', 'sails-tax'); ?>
                    </span>
                  </label>
                </fieldset>
              </td>
            </tr>
            <tr id="nexus-states-row" style="<?php echo $nexus_mode === 'nexus_only' ? '' : 'display:none;'; ?>">
              <th scope="row">
                <?php esc_html_e('Nexus States', 'sails-tax'); ?>
              </th>
              <td>
                <p class="description" style="margin-bottom: 12px;">
                  <?php esc_html_e('Select every state where you have nexus. Tax will be collected in these states; all others will show $0 tax.', 'sails-tax'); ?>
                </p>

                <!-- Quick actions -->
                <div style="margin-bottom: 10px; display: flex; gap: 8px; flex-wrap: wrap;">
                  <button type="button" class="button button-small" id="sails-select-none">
                    <?php esc_html_e('Clear All', 'sails-tax'); ?>
                  </button>
                  <button type="button" class="button button-small" id="sails-select-common">
                    <?php esc_html_e('Common Nexus States', 'sails-tax'); ?>
                  </button>
                </div>

                <!-- State grid -->
                <div id="sails-state-grid" style="
                  display: grid;
                  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                  gap: 6px;
                  max-height: 400px;
                  overflow-y: auto;
                  border: 1px solid #ddd;
                  border-radius: 4px;
                  padding: 12px;
                  background: #fafafa;
                ">
                  <?php foreach (self::US_STATES as $code => $name): ?>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 4px 6px; border-radius: 3px; transition: background 0.15s;" 
                           class="sails-state-label <?php echo in_array($code, $saved_list) ? 'is-checked' : ''; ?>">
                      <input
                        type="checkbox"
                        name="nexus_states[]"
                        value="<?php echo esc_attr($code); ?>"
                        <?php echo in_array($code, $saved_list) ? 'checked' : ''; ?>
                        class="sails-state-cb"
                      >
                      <span class="sails-state-code" style="font-weight: 600; font-family: monospace; color: #23282d; min-width: 28px;">
                        <?php echo esc_html($code); ?>
                      </span>
                      <span style="color: #555; font-size: 13px;"><?php echo esc_html($name); ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>

                <p style="margin-top: 8px; color: #666; font-size: 12px;">
                  <span id="sails-selected-count"><?php echo count($saved_list); ?></span>
                  <?php esc_html_e('states selected', 'sails-tax'); ?>
                </p>
              </td>
            </tr>
          </table>

          <?php submit_button(__('Save Nexus Settings', 'sails-tax')); ?>
        </form>

        <!-- Current nexus summary -->
        <?php if ($nexus_mode === 'nexus_only' && !empty($saved_list)): ?>
          <hr>
          <h2><?php esc_html_e('Current Nexus Configuration', 'sails-tax'); ?></h2>
          <p><?php printf(
            /* translators: number of nexus states */
            esc_html__('Tax is being collected in %d state(s):', 'sails-tax'),
            count($saved_list)
          ); ?></p>
          <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;">
            <?php foreach ($saved_list as $code): ?>
              <span style="
                display: inline-flex;
                align-items: center;
                gap: 4px;
                background: #e8f5e9;
                border: 1px solid #4caf50;
                color: #2e7d32;
                padding: 4px 10px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 600;
              ">
                ✓ <?php echo esc_html($code); ?>
                <?php if (isset(self::US_STATES[$code])): ?>
                  <span style="font-weight: 400; color: #388e3c;"><?php echo esc_html(self::US_STATES[$code]); ?></span>
                <?php endif; ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php elseif ($nexus_mode === 'all'): ?>
          <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px 16px; margin-top: 20px; border-radius: 4px;">
            <?php esc_html_e('Currently collecting tax in all US states. Switch to Nexus-Only mode to restrict collection to specific states.', 'sails-tax'); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <style>
      .sails-state-label:hover { background: #f0f0f0; }
      .sails-state-label.is-checked { background: #e8f5e9; }
    </style>

    <script type="text/javascript">
    (function() {
      // Toggle state grid visibility when mode changes
      var radios = document.querySelectorAll('[name="nexus_mode"]');
      var statesRow = document.getElementById('nexus-states-row');

      radios.forEach(function(radio) {
        radio.addEventListener('change', function() {
          statesRow.style.display = (this.value === 'nexus_only') ? '' : 'none';
        });
      });

      // Update selected count & highlight
      var counter = document.getElementById('sails-selected-count');
      var checkboxes = document.querySelectorAll('.sails-state-cb');

      function updateCount() {
        var count = document.querySelectorAll('.sails-state-cb:checked').length;
        if (counter) counter.textContent = count;
        checkboxes.forEach(function(cb) {
          var label = cb.closest('.sails-state-label');
          if (label) label.classList.toggle('is-checked', cb.checked);
        });
      }

      checkboxes.forEach(function(cb) {
        cb.addEventListener('change', updateCount);
      });

      // Clear all
      var clearBtn = document.getElementById('sails-select-none');
      if (clearBtn) {
        clearBtn.addEventListener('click', function() {
          checkboxes.forEach(function(cb) { cb.checked = false; });
          updateCount();
        });
      }

      // Select common nexus states (the "big 5" plus common economic nexus states)
      var commonStates = ['CA','TX','NY','FL','WA','IL','PA','OH','GA','NC','VA','NJ','CO','AZ','MA'];
      var commonBtn = document.getElementById('sails-select-common');
      if (commonBtn) {
        commonBtn.addEventListener('click', function() {
          checkboxes.forEach(function(cb) {
            cb.checked = commonStates.includes(cb.value);
          });
          updateCount();
          // Auto-switch to nexus_only mode
          var nexusRadio = document.getElementById('nexus_only_radio');
          if (nexusRadio) {
            nexusRadio.checked = true;
            statesRow.style.display = '';
          }
        });
      }
    })();
    </script>
    <?php
  }
}
