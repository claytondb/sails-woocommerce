<?php

if (!defined('ABSPATH')) exit;

class Sails_Tax_Checkout {
  public function register() {
    add_action('woocommerce_cart_calculate_fees', [$this, 'apply_estimated_tax'], 20, 1);
    add_action('woocommerce_review_order_before_order_total', [$this, 'maybe_render_customer_note']);
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

    // Calculate taxable amount (subtotal + shipping). MVP: treat everything taxable.
    $amount = floatval($cart->get_subtotal() + $cart->get_shipping_total());
    if ($amount <= 0) return;

    $api = new Sails_Tax_API();
    $result = $api->calculate($amount, $toZip, $toState);

    if (is_wp_error($result)) {
      // Merchant-visible note in checkout (admin only) and order note later.
      $cart->add_fee('Sales Tax (Sails)', 0, false);
      $this->store_last_notice([
        'confidence' => 'error',
        'message' => $result->get_error_message(),
      ]);
      return;
    }

    $taxAmount = isset($result['taxAmount']) ? floatval($result['taxAmount']) : 0;
    $confidence = $result['confidence'] ?? 'state_only';
    $message = $result['message'] ?? '';

    // Add tax as a fee line item.
    $cart->add_fee('Sales Tax', $taxAmount, false);

    // Store disclaimer for rendering and for order meta.
    $this->store_last_notice([
      'confidence' => $confidence,
      'message' => $message,
    ]);
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
}
