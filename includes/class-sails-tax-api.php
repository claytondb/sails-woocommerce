<?php

if (!defined('ABSPATH')) exit;

class Sails_Tax_API {
  /**
   * Calculate tax for a given amount and destination
   */
  public function calculate($amount, $toZip, $toState) {
    $opts = Sails_Tax_Settings::get();
    $base = isset($opts['api_base_url']) ? rtrim($opts['api_base_url'], '/') : 'https://sails.tax';
    $key  = isset($opts['api_key']) ? $opts['api_key'] : '';
    $debug = ($opts['debug_logging'] ?? 'no') === 'yes';

    if (!$key) {
      $this->log('API key not configured', 'error', $debug);
      return new WP_Error('sails_tax_no_key', 'Sails API key is not configured.');
    }

    $url = $base . '/api/v1/tax/calculate';

    $payload = [
      'amount' => floatval($amount),
      'toZip' => sanitize_text_field($toZip),
      'toState' => strtoupper(sanitize_text_field($toState)),
    ];

    $this->log(sprintf('Calculating tax: $%.2f to %s, %s', $amount, $toZip, $toState), 'info', $debug);

    $start_time = microtime(true);
    
    $res = wp_remote_post($url, [
      'timeout' => 10,
      'headers' => [
        'Authorization' => 'Bearer ' . $key,
        'Content-Type' => 'application/json',
        'User-Agent' => 'SailsTax-WooCommerce/' . SAILS_TAX_VERSION,
      ],
      'body' => wp_json_encode($payload),
    ]);

    $elapsed = round((microtime(true) - $start_time) * 1000);

    if (is_wp_error($res)) {
      $this->log('API request failed: ' . $res->get_error_message(), 'error', $debug);
      return $res;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
      $msg = isset($json['error']) ? $json['error'] : ('HTTP ' . $code);
      $this->log(sprintf('API error (HTTP %d): %s', $code, $msg), 'error', $debug);
      return new WP_Error('sails_tax_http_error', $msg);
    }

    if (!isset($json['success']) || !$json['success']) {
      $msg = isset($json['error']) ? $json['error'] : 'Unknown error';
      $this->log('API returned error: ' . $msg, 'error', $debug);
      return new WP_Error('sails_tax_api_error', $msg);
    }

    $rate = isset($json['data']['rate']) ? ($json['data']['rate'] * 100) : 0;
    $taxAmount = isset($json['data']['taxAmount']) ? $json['data']['taxAmount'] : 0;
    $confidence = isset($json['data']['confidence']) ? $json['data']['confidence'] : 'unknown';
    
    $this->log(sprintf(
      'Tax calculated: $%.2f at %.2f%% (%s confidence) in %dms',
      $taxAmount, $rate, $confidence, $elapsed
    ), 'info', $debug);

    return $json['data'];
  }

  /**
   * Log message to WooCommerce logs
   */
  private function log($message, $level = 'info', $enabled = true) {
    if (!$enabled) return;
    
    if (function_exists('wc_get_logger')) {
      $logger = wc_get_logger();
      $context = ['source' => 'sails-tax'];
      
      switch ($level) {
        case 'error':
          $logger->error($message, $context);
          break;
        case 'warning':
          $logger->warning($message, $context);
          break;
        default:
          $logger->info($message, $context);
      }
    }
  }
}
