<?php

if (!defined('ABSPATH')) exit;

class Sails_Tax_Checkout {
  // Cache key prefix for transient storage
  const CACHE_PREFIX = 'sails_tax_';
  const CACHE_TTL = 300; // 5 minutes

  public function register() {
    add_action('woocommerce_cart_calculate_fees', [$this, 'apply_estimated_tax'], 20, 1);
    add_action('woocommerce_review_order_before_order_total', [$this, 'maybe_render_customer_note']);
    // Store tax metadata on order completion
    add_action('woocommerce_checkout_order_processed', [$this, 'store_order_meta'], 10, 3);
    add_action('woocommerce_store_api_checkout_order_processed', [$this, 'store_order_meta_block'], 10, 1);
  }

  public function apply_estimated_tax($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $opts = Sails_Tax_Settings::get();
    if (($opts['enabled'] ?? 'no') !== 'yes') return;

    // Get destination info from customer
    $customer = WC()->customer;
    if (!$customer) return;

    // Prefer shipping address, but fall back to billing (Block Checkout often only provides billing).
    $toZip = $customer->get_shipping_postcode();
    $toState = $customer->get_shipping_state();

    if (!$toZip) {
      $toZip = $customer->get_billing_postcode();
    }
    if (!$toState) {
      $toState = $customer->get_billing_state();
    }

    if (!$toZip || !$toState) {
      return; // wait until address is present
    }

    // Check if customer is tax exempt
    $user_id = get_current_user_id();
    if ($user_id && class_exists('Sails_Tax_Exemptions') && Sails_Tax_Exemptions::is_customer_exempt($user_id, $toState)) {
      // Customer is exempt - apply $0 tax
      $cart->add_fee(__('Sales Tax', 'sails-tax'), 0, false);
      $exemption_details = Sails_Tax_Exemptions::get_exemption_details($user_id);
      $this->store_last_notice([
        'confidence' => 'exempt',
        'message' => __('Tax exempt customer', 'sails-tax'),
        'taxAmount' => 0,
        'rate' => 0,
        'exempt' => true,
        'exempt_reason' => $exemption_details['reason'] ?? '',
        'exempt_cert' => $exemption_details['cert_number'] ?? '',
      ]);
      return;
    }

    // Check for product category exemptions
    $product_exempt_amount = 0;
    $product_exempt_items = [];
    
    if (class_exists('Sails_Tax_Product_Exemptions') && Sails_Tax_Product_Exemptions::is_enabled()) {
      $exemption_breakdown = Sails_Tax_Product_Exemptions::get_cart_exemption_breakdown($cart, $toState);
      $product_exempt_amount = $exemption_breakdown['exempt_amount'];
      $product_exempt_items = $exemption_breakdown['exempt_items'];
      
      // If entire cart is exempt, apply $0 tax
      if ($exemption_breakdown['taxable_amount'] <= 0 && $product_exempt_amount > 0) {
        $cart->add_fee(__('Sales Tax', 'sails-tax'), 0, false);
        $this->store_last_notice([
          'confidence' => 'product_exempt',
          'message' => __('All items in cart are tax-exempt', 'sails-tax'),
          'taxAmount' => 0,
          'rate' => 0,
          'product_exempt' => true,
          'exempt_items' => $product_exempt_items,
        ]);
        return;
      }
    }

    // Calculate taxable amount (taxable subtotal + shipping)
    $subtotal = floatval($cart->get_subtotal());
    $taxable_subtotal = $subtotal - $product_exempt_amount;
    $amount = floatval($taxable_subtotal + $cart->get_shipping_total());
    if ($amount <= 0) return;

    // Check cache first to avoid redundant API calls
    $cache_key = $this->get_cache_key($amount, $toZip, $toState);
    $cached = get_transient($cache_key);
    
    if ($cached !== false) {
      $this->apply_tax_result($cart, $cached, $product_exempt_amount, $product_exempt_items);
      return;
    }

    $api = new Sails_Tax_API();
    $result = $api->calculate($amount, $toZip, $toState);

    if (is_wp_error($result)) {
      // Merchant-visible note in checkout (admin only) and order note later.
      $cart->add_fee(__('Sales Tax (Sails)', 'sails-tax'), 0, false);
      $this->store_last_notice([
        'confidence' => 'error',
        'message' => $result->get_error_message(),
        'amount' => $amount,
        'toZip' => $toZip,
        'toState' => $toState,
      ]);
      return;
    }

    // Cache successful result
    set_transient($cache_key, $result, self::CACHE_TTL);
    $this->apply_tax_result($cart, $result, $product_exempt_amount, $product_exempt_items);
  }

  private function apply_tax_result($cart, $result, $product_exempt_amount = 0, $product_exempt_items = []) {
    $taxAmount = isset($result['taxAmount']) ? floatval($result['taxAmount']) : 0;
    $confidence = $result['confidence'] ?? 'state_only';
    $message = $result['message'] ?? '';
    $rate = $result['rate'] ?? null;

    // Add tax as a fee line item.
    $cart->add_fee(__('Sales Tax', 'sails-tax'), $taxAmount, false);

    // Store disclaimer for rendering and for order meta.
    $notice_data = [
      'confidence' => $confidence,
      'message' => $message,
      'taxAmount' => $taxAmount,
      'rate' => $rate,
    ];

    // Include product exemption info if applicable
    if ($product_exempt_amount > 0) {
      $notice_data['product_exempt_amount'] = $product_exempt_amount;
      $notice_data['product_exempt_items'] = $product_exempt_items;
      $notice_data['has_partial_exemption'] = true;
    }

    $this->store_last_notice($notice_data);
  }

  private function get_cache_key($amount, $zip, $state) {
    // Round amount to avoid cache misses on tiny price changes
    $rounded = round($amount, 2);
    return self::CACHE_PREFIX . md5($rounded . '|' . $zip . '|' . $state);
  }

  private function store_last_notice($data) {
    // Store in WC session for checkout display.
    if (WC()->session) {
      WC()->session->set('sails_tax_last', $data);
    }
  }

  public function maybe_render_customer_note() {
    $opts = Sails_Tax_Settings::get();
    if (($opts['customer_disclaimer'] ?? 'no') !== 'yes') return;

    $data = WC()->session ? WC()->session->get('sails_tax_last') : null;
    if (!$data || empty($data['message'])) return;

    $confidence = $data['confidence'] ?? '';
    if ($confidence === 'exact_zip') return; // only show when not exact

    echo '<tr class="sails-tax-estimate-note"><td colspan="2">';
    echo '<small style="color:#6b7280;">' . esc_html($data['message']) . '</small>';
    echo '</td></tr>';
  }

  /**
   * Store tax calculation metadata on order (classic checkout)
   */
  public function store_order_meta($order_id, $posted_data, $order) {
    $this->save_tax_meta_to_order($order);
  }

  /**
   * Store tax calculation metadata on order (block checkout)
   */
  public function store_order_meta_block($order) {
    $this->save_tax_meta_to_order($order);
  }

  private function save_tax_meta_to_order($order) {
    if (!$order) return;

    $data = WC()->session ? WC()->session->get('sails_tax_last') : null;
    if (!$data) return;

    // Store confidence level for reporting/auditing
    $order->update_meta_data('_sails_tax_confidence', $data['confidence'] ?? 'unknown');
    
    if (isset($data['taxAmount'])) {
      $order->update_meta_data('_sails_tax_amount', $data['taxAmount']);
    }
    if (isset($data['rate'])) {
      $order->update_meta_data('_sails_tax_rate', $data['rate']);
    }
    if (!empty($data['message'])) {
      $order->update_meta_data('_sails_tax_message', $data['message']);
    }

    // Store exemption details if applicable
    if (!empty($data['exempt'])) {
      $order->update_meta_data('_sails_tax_exempt_applied', 'yes');
      if (!empty($data['exempt_reason'])) {
        $order->update_meta_data('_sails_tax_exempt_reason', $data['exempt_reason']);
      }
      if (!empty($data['exempt_cert'])) {
        $order->update_meta_data('_sails_tax_exempt_cert', $data['exempt_cert']);
      }
    }

    // Store product exemption details if applicable
    if (!empty($data['product_exempt']) || !empty($data['has_partial_exemption'])) {
      $order->update_meta_data('_sails_tax_product_exempt', 'yes');
      if (!empty($data['product_exempt_amount'])) {
        $order->update_meta_data('_sails_tax_product_exempt_amount', $data['product_exempt_amount']);
      }
    }

    $order->save();

    // Add order note for admin visibility
    $confidence = $data['confidence'] ?? 'unknown';
    if ($confidence === 'exempt') {
      $note = __('Sails Tax: Customer is tax exempt.', 'sails-tax');
      if (!empty($data['exempt_reason'])) {
        $note .= ' ' . sprintf(__('Reason: %s.', 'sails-tax'), ucfirst($data['exempt_reason']));
      }
      if (!empty($data['exempt_cert'])) {
        $note .= ' ' . sprintf(__('Certificate: %s', 'sails-tax'), $data['exempt_cert']);
      }
      $order->add_order_note($note, false);
    } elseif ($confidence === 'product_exempt') {
      $note = __('Sails Tax: All products in this order are tax-exempt.', 'sails-tax');
      $order->add_order_note($note, false);
    } elseif (!empty($data['has_partial_exemption'])) {
      $exempt_amount = wc_price($data['product_exempt_amount'] ?? 0);
      $note = sprintf(
        /* translators: %s: exempt amount */
        __('Sails Tax: %s of this order was exempt from tax (product category exemption).', 'sails-tax'),
        $exempt_amount
      );
      $order->add_order_note($note, false);
    } elseif ($confidence !== 'exact_zip') {
      $note = sprintf(
        /* translators: 1: confidence level 2: additional message */
        __('Sails Tax: %1$s confidence. %2$s', 'sails-tax'),
        ucfirst(str_replace('_', ' ', $confidence)),
        $data['message'] ?? ''
      );
      $order->add_order_note($note, false);
    }
  }
}
