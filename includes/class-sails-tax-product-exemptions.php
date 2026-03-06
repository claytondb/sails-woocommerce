<?php
/**
 * Product Category Tax Exemptions
 * 
 * Allows stores to mark certain product categories as tax-exempt.
 * Supports state-specific exemptions (e.g., groceries exempt in some states only).
 * 
 * @package Sails_Tax
 * @since 0.6.0
 */

if (!defined('ABSPATH')) exit;

class Sails_Tax_Product_Exemptions {
  const OPTION_NAME = 'sails_tax_product_exemptions';
  
  /**
   * Common exemption types with default state applicability
   */
  const EXEMPTION_TYPES = [
    'groceries' => [
      'label' => 'Groceries / Food',
      'description' => 'Unprepared food items',
      'common_states' => ['AZ', 'CA', 'CO', 'FL', 'GA', 'LA', 'MI', 'NV', 'NY', 'NC', 'OH', 'PA', 'TX', 'VA', 'WA'],
    ],
    'clothing' => [
      'label' => 'Clothing',
      'description' => 'Apparel under threshold',
      'common_states' => ['NJ', 'NY', 'PA', 'MA', 'MN', 'RI', 'VT'],
    ],
    'medicine' => [
      'label' => 'Medicine / OTC Drugs',
      'description' => 'Over-the-counter medications',
      'common_states' => ['CT', 'FL', 'MD', 'MA', 'MN', 'NJ', 'NY', 'PA', 'TX', 'VT', 'VA'],
    ],
    'digital' => [
      'label' => 'Digital Products',
      'description' => 'Software, ebooks, digital downloads',
      'common_states' => ['CA', 'FL', 'GA', 'MT', 'NH', 'OR'],
    ],
    'custom' => [
      'label' => 'Custom Exemption',
      'description' => 'Define your own exempt categories',
      'common_states' => [],
    ],
  ];

  /**
   * US States list for multi-select
   */
  const US_STATES = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
    'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
    'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
    'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
    'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
    'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
    'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
    'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
    'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
    'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
    'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
  ];

  public function register() {
    // Add submenu page for product exemptions
    add_action('admin_menu', [$this, 'add_menu'], 20);
    add_action('admin_init', [$this, 'register_settings']);
    
    // Enqueue admin styles
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
  }

  public function enqueue_admin_styles($hook) {
    if (strpos($hook, 'sails-tax-product-exemptions') === false) {
      return;
    }
    
    // Enqueue Select2 for multi-select dropdowns
    wp_enqueue_style('woocommerce_admin_styles');
    wp_enqueue_script('select2');
    wp_enqueue_style('select2');
    
    // Inline styles for the page
    wp_add_inline_style('woocommerce_admin_styles', '
      .sails-exemption-rule {
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
      }
      .sails-exemption-rule h3 {
        margin-top: 0;
        color: #1f2937;
      }
      .sails-exemption-rule .description {
        color: #6b7280;
        font-style: italic;
      }
      .sails-rule-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 15px;
      }
      .sails-rule-field label {
        display: block;
        font-weight: 600;
        margin-bottom: 5px;
        color: #374151;
      }
      .sails-rule-field select,
      .sails-rule-field .select2-container {
        width: 100% !important;
      }
      .sails-add-rule {
        margin-top: 20px;
      }
      .sails-remove-rule {
        float: right;
        color: #dc3232;
        cursor: pointer;
        text-decoration: none;
      }
      .sails-remove-rule:hover {
        color: #a00;
      }
      .sails-info-box {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 20px;
      }
      .sails-info-box h4 {
        margin: 0 0 10px 0;
        color: #1e40af;
      }
      .sails-info-box p {
        margin: 0;
        color: #1e3a8a;
      }
    ');
  }

  public function add_menu() {
    add_submenu_page(
      'woocommerce',
      __('Product Tax Exemptions', 'sails-tax'),
      __('Product Exemptions', 'sails-tax'),
      'manage_woocommerce',
      'sails-tax-product-exemptions',
      [$this, 'render_page']
    );
  }

  public function register_settings() {
    register_setting(self::OPTION_NAME, self::OPTION_NAME, [
      'type' => 'array',
      'sanitize_callback' => [$this, 'sanitize'],
      'default' => [
        'enabled' => 'no',
        'rules' => [],
      ],
    ]);
  }

  public function sanitize($input) {
    $out = [
      'enabled' => (isset($input['enabled']) && $input['enabled'] === 'yes') ? 'yes' : 'no',
      'rules' => [],
    ];

    if (isset($input['rules']) && is_array($input['rules'])) {
      foreach ($input['rules'] as $rule) {
        if (empty($rule['categories'])) continue;
        
        $out['rules'][] = [
          'exemption_type' => sanitize_text_field($rule['exemption_type'] ?? 'custom'),
          'categories' => array_map('intval', (array) $rule['categories']),
          'states' => isset($rule['states']) ? array_map('sanitize_text_field', (array) $rule['states']) : ['ALL'],
          'all_states' => isset($rule['all_states']) && $rule['all_states'] === 'yes' ? 'yes' : 'no',
        ];
      }
    }

    return $out;
  }

  public static function get_settings() {
    return get_option(self::OPTION_NAME, [
      'enabled' => 'no',
      'rules' => [],
    ]);
  }

  /**
   * Check if product exemptions are enabled
   */
  public static function is_enabled() {
    $settings = self::get_settings();
    return ($settings['enabled'] ?? 'no') === 'yes';
  }

  /**
   * Get exempt amount from cart for a specific state
   * 
   * @param WC_Cart $cart
   * @param string $state Two-letter state code
   * @return array ['exempt_amount' => float, 'taxable_amount' => float, 'exempt_items' => array]
   */
  public static function get_cart_exemption_breakdown($cart, $state) {
    $settings = self::get_settings();
    
    if (($settings['enabled'] ?? 'no') !== 'yes' || empty($settings['rules'])) {
      $subtotal = floatval($cart->get_subtotal());
      return [
        'exempt_amount' => 0,
        'taxable_amount' => $subtotal,
        'exempt_items' => [],
        'has_exemptions' => false,
      ];
    }

    $exempt_amount = 0;
    $taxable_amount = 0;
    $exempt_items = [];

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
      $product = $cart_item['data'];
      $line_total = floatval($cart_item['line_subtotal']);
      
      $is_exempt = self::is_product_exempt($product, $state, $settings['rules']);
      
      if ($is_exempt) {
        $exempt_amount += $line_total;
        $exempt_items[] = [
          'key' => $cart_item_key,
          'name' => $product->get_name(),
          'amount' => $line_total,
        ];
      } else {
        $taxable_amount += $line_total;
      }
    }

    return [
      'exempt_amount' => $exempt_amount,
      'taxable_amount' => $taxable_amount,
      'exempt_items' => $exempt_items,
      'has_exemptions' => count($exempt_items) > 0,
    ];
  }

  /**
   * Check if a specific product is exempt based on rules
   * 
   * @param WC_Product $product
   * @param string $state
   * @param array $rules
   * @return bool
   */
  public static function is_product_exempt($product, $state, $rules = null) {
    if ($rules === null) {
      $settings = self::get_settings();
      $rules = $settings['rules'] ?? [];
    }

    if (empty($rules)) {
      return false;
    }

    // Get all category IDs for this product (including parent categories)
    $product_categories = self::get_product_category_ids($product);

    foreach ($rules as $rule) {
      // Check if product is in any of the exempt categories
      $category_match = array_intersect($product_categories, $rule['categories']);
      
      if (empty($category_match)) {
        continue;
      }

      // Check state applicability
      if (($rule['all_states'] ?? 'no') === 'yes') {
        return true;
      }

      $rule_states = $rule['states'] ?? [];
      if (in_array('ALL', $rule_states) || in_array($state, $rule_states)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Get all category IDs for a product (including parent categories)
   */
  private static function get_product_category_ids($product) {
    $category_ids = [];
    
    // Get direct category IDs
    $terms = get_the_terms($product->get_id(), 'product_cat');
    
    if ($terms && !is_wp_error($terms)) {
      foreach ($terms as $term) {
        $category_ids[] = $term->term_id;
        
        // Include parent categories
        $parents = get_ancestors($term->term_id, 'product_cat', 'taxonomy');
        $category_ids = array_merge($category_ids, $parents);
      }
    }

    // For variations, also check parent product
    if ($product->is_type('variation')) {
      $parent_id = $product->get_parent_id();
      $parent_terms = get_the_terms($parent_id, 'product_cat');
      
      if ($parent_terms && !is_wp_error($parent_terms)) {
        foreach ($parent_terms as $term) {
          $category_ids[] = $term->term_id;
          $parents = get_ancestors($term->term_id, 'product_cat', 'taxonomy');
          $category_ids = array_merge($category_ids, $parents);
        }
      }
    }

    return array_unique($category_ids);
  }

  public function render_page() {
    $settings = self::get_settings();
    $rules = $settings['rules'] ?? [];
    
    // Get all product categories
    $categories = get_terms([
      'taxonomy' => 'product_cat',
      'hide_empty' => false,
      'orderby' => 'name',
    ]);
    
    ?>
    <div class="wrap">
      <h1><?php esc_html_e('Product Tax Exemptions', 'sails-tax'); ?></h1>
      
      <div class="sails-info-box">
        <h4><?php esc_html_e('📋 About Product Exemptions', 'sails-tax'); ?></h4>
        <p>
          <?php esc_html_e('Some products are tax-exempt in certain states (e.g., groceries, clothing, medicine). Configure rules below to automatically exclude these products from tax calculations.', 'sails-tax'); ?>
        </p>
      </div>
      
      <form method="post" action="options.php">
        <?php settings_fields(self::OPTION_NAME); ?>
        
        <table class="form-table">
          <tr>
            <th scope="row"><?php esc_html_e('Enable Product Exemptions', 'sails-tax'); ?></th>
            <td>
              <select name="<?php echo self::OPTION_NAME; ?>[enabled]">
                <option value="yes" <?php selected($settings['enabled'] ?? 'no', 'yes'); ?>><?php esc_html_e('Yes', 'sails-tax'); ?></option>
                <option value="no" <?php selected($settings['enabled'] ?? 'no', 'no'); ?>><?php esc_html_e('No', 'sails-tax'); ?></option>
              </select>
              <p class="description">
                <?php esc_html_e('When enabled, products in exempt categories will have their portion excluded from tax calculations.', 'sails-tax'); ?>
              </p>
            </td>
          </tr>
        </table>
        
        <h2><?php esc_html_e('Exemption Rules', 'sails-tax'); ?></h2>
        
        <div id="sails-exemption-rules">
          <?php 
          if (empty($rules)) {
            $rules = [['exemption_type' => 'custom', 'categories' => [], 'states' => [], 'all_states' => 'no']];
          }
          
          foreach ($rules as $index => $rule): 
          ?>
          <div class="sails-exemption-rule" data-index="<?php echo $index; ?>">
            <a href="#" class="sails-remove-rule" onclick="return sailsRemoveRule(this);">✕ <?php esc_html_e('Remove', 'sails-tax'); ?></a>
            
            <h3><?php esc_html_e('Exemption Rule', 'sails-tax'); ?> #<?php echo $index + 1; ?></h3>
            
            <div class="sails-rule-grid">
              <div class="sails-rule-field">
                <label><?php esc_html_e('Exemption Type', 'sails-tax'); ?></label>
                <select name="<?php echo self::OPTION_NAME; ?>[rules][<?php echo $index; ?>][exemption_type]" class="sails-exemption-type">
                  <?php foreach (self::EXEMPTION_TYPES as $type_key => $type_info): ?>
                  <option value="<?php echo esc_attr($type_key); ?>" <?php selected($rule['exemption_type'] ?? '', $type_key); ?>>
                    <?php echo esc_html($type_info['label']); ?>
                  </option>
                  <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Select a preset or use Custom for your own categories.', 'sails-tax'); ?></p>
              </div>
              
              <div class="sails-rule-field">
                <label><?php esc_html_e('Product Categories', 'sails-tax'); ?></label>
                <select name="<?php echo self::OPTION_NAME; ?>[rules][<?php echo $index; ?>][categories][]" 
                        class="sails-category-select" 
                        multiple="multiple" 
                        style="width: 100%;">
                  <?php foreach ($categories as $cat): ?>
                  <option value="<?php echo esc_attr($cat->term_id); ?>" 
                          <?php echo in_array($cat->term_id, $rule['categories'] ?? []) ? 'selected' : ''; ?>>
                    <?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Products in these categories will be exempt.', 'sails-tax'); ?></p>
              </div>
              
              <div class="sails-rule-field">
                <label><?php esc_html_e('Apply to All States?', 'sails-tax'); ?></label>
                <select name="<?php echo self::OPTION_NAME; ?>[rules][<?php echo $index; ?>][all_states]" class="sails-all-states">
                  <option value="yes" <?php selected($rule['all_states'] ?? 'no', 'yes'); ?>><?php esc_html_e('Yes - Exempt in all states', 'sails-tax'); ?></option>
                  <option value="no" <?php selected($rule['all_states'] ?? 'no', 'no'); ?>><?php esc_html_e('No - Only specific states', 'sails-tax'); ?></option>
                </select>
              </div>
              
              <div class="sails-rule-field sails-states-field" style="<?php echo ($rule['all_states'] ?? 'no') === 'yes' ? 'display:none;' : ''; ?>">
                <label><?php esc_html_e('Exempt States', 'sails-tax'); ?></label>
                <select name="<?php echo self::OPTION_NAME; ?>[rules][<?php echo $index; ?>][states][]" 
                        class="sails-states-select" 
                        multiple="multiple" 
                        style="width: 100%;">
                  <?php foreach (self::US_STATES as $code => $name): ?>
                  <option value="<?php echo esc_attr($code); ?>" 
                          <?php echo in_array($code, $rule['states'] ?? []) ? 'selected' : ''; ?>>
                    <?php echo esc_html($name); ?> (<?php echo $code; ?>)
                  </option>
                  <?php endforeach; ?>
                </select>
                <p class="description"><?php esc_html_e('Exemption only applies when shipping to these states.', 'sails-tax'); ?></p>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <button type="button" class="button sails-add-rule" onclick="sailsAddRule();">
          + <?php esc_html_e('Add Another Rule', 'sails-tax'); ?>
        </button>
        
        <?php submit_button(); ?>
      </form>
    </div>
    
    <script type="text/javascript">
    jQuery(document).ready(function($) {
      // Initialize Select2 on existing selects
      $('.sails-category-select, .sails-states-select').select2({
        placeholder: '<?php esc_html_e('Select options...', 'sails-tax'); ?>',
        allowClear: true
      });
      
      // Toggle state select visibility based on all_states selection
      $(document).on('change', '.sails-all-states', function() {
        var statesField = $(this).closest('.sails-exemption-rule').find('.sails-states-field');
        if ($(this).val() === 'yes') {
          statesField.hide();
        } else {
          statesField.show();
        }
      });
      
      // Pre-fill states when exemption type changes
      var exemptionStates = <?php echo json_encode(array_map(function($t) { return $t['common_states']; }, self::EXEMPTION_TYPES)); ?>;
      
      $(document).on('change', '.sails-exemption-type', function() {
        var type = $(this).val();
        var statesSelect = $(this).closest('.sails-exemption-rule').find('.sails-states-select');
        var allStatesSelect = $(this).closest('.sails-exemption-rule').find('.sails-all-states');
        
        if (type !== 'custom' && exemptionStates[type] && exemptionStates[type].length > 0) {
          statesSelect.val(exemptionStates[type]).trigger('change');
          allStatesSelect.val('no').trigger('change');
        }
      });
    });
    
    var ruleIndex = <?php echo count($rules); ?>;
    
    function sailsAddRule() {
      var template = `
        <div class="sails-exemption-rule" data-index="${ruleIndex}">
          <a href="#" class="sails-remove-rule" onclick="return sailsRemoveRule(this);">✕ <?php esc_html_e('Remove', 'sails-tax'); ?></a>
          <h3><?php esc_html_e('Exemption Rule', 'sails-tax'); ?> #${ruleIndex + 1}</h3>
          <div class="sails-rule-grid">
            <div class="sails-rule-field">
              <label><?php esc_html_e('Exemption Type', 'sails-tax'); ?></label>
              <select name="<?php echo self::OPTION_NAME; ?>[rules][${ruleIndex}][exemption_type]" class="sails-exemption-type">
                <?php foreach (self::EXEMPTION_TYPES as $type_key => $type_info): ?>
                <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_info['label']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sails-rule-field">
              <label><?php esc_html_e('Product Categories', 'sails-tax'); ?></label>
              <select name="<?php echo self::OPTION_NAME; ?>[rules][${ruleIndex}][categories][]" class="sails-category-select" multiple="multiple" style="width: 100%;">
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo esc_attr($cat->term_id); ?>"><?php echo esc_html($cat->name); ?> (<?php echo $cat->count; ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sails-rule-field">
              <label><?php esc_html_e('Apply to All States?', 'sails-tax'); ?></label>
              <select name="<?php echo self::OPTION_NAME; ?>[rules][${ruleIndex}][all_states]" class="sails-all-states">
                <option value="yes"><?php esc_html_e('Yes - Exempt in all states', 'sails-tax'); ?></option>
                <option value="no" selected><?php esc_html_e('No - Only specific states', 'sails-tax'); ?></option>
              </select>
            </div>
            <div class="sails-rule-field sails-states-field">
              <label><?php esc_html_e('Exempt States', 'sails-tax'); ?></label>
              <select name="<?php echo self::OPTION_NAME; ?>[rules][${ruleIndex}][states][]" class="sails-states-select" multiple="multiple" style="width: 100%;">
                <?php foreach (self::US_STATES as $code => $name): ?>
                <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?> (<?php echo $code; ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      `;
      
      jQuery('#sails-exemption-rules').append(template);
      
      // Initialize Select2 on new selects
      jQuery('.sails-exemption-rule[data-index="' + ruleIndex + '"] .sails-category-select, .sails-exemption-rule[data-index="' + ruleIndex + '"] .sails-states-select').select2({
        placeholder: '<?php esc_html_e('Select options...', 'sails-tax'); ?>',
        allowClear: true
      });
      
      ruleIndex++;
    }
    
    function sailsRemoveRule(el) {
      if (jQuery('.sails-exemption-rule').length > 1) {
        jQuery(el).closest('.sails-exemption-rule').remove();
      } else {
        alert('<?php esc_html_e('You need at least one rule. Disable the feature instead.', 'sails-tax'); ?>');
      }
      return false;
    }
    </script>
    <?php
  }
}
