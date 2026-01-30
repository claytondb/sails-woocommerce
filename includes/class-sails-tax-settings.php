<?php

if (!defined('ABSPATH')) exit;

class Sails_Tax_Settings {
  const OPTION_GROUP = 'sails_tax_options';
  const OPTION_NAME  = 'sails_tax_settings';

  public function register() {
    add_action('admin_menu', [$this, 'add_menu']);
    add_action('admin_init', [$this, 'register_settings']);
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
  }

  public function sanitize($input) {
    $out = [];
    $out['enabled'] = (isset($input['enabled']) && $input['enabled'] === 'yes') ? 'yes' : 'no';
    $out['api_base_url'] = isset($input['api_base_url']) ? esc_url_raw(trim($input['api_base_url'])) : 'https://sails.tax';
    $out['api_key'] = isset($input['api_key']) ? sanitize_text_field(trim($input['api_key'])) : '';
    $out['customer_disclaimer'] = (isset($input['customer_disclaimer']) && $input['customer_disclaimer'] === 'yes') ? 'yes' : 'no';
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
    echo '<form method="post" action="options.php">';
    settings_fields(self::OPTION_GROUP);
    do_settings_sections('sails-tax');
    submit_button();
    echo '</form>';
    echo '</div>';
  }
}
