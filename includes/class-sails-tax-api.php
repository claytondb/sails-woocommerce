<?php

if (!defined('ABSPATH')) exit;

class Sails_Tax_API {
  /**
   * Maximum retry attempts for rate-limited requests
   */
  const MAX_RETRIES = 3;

  /**
   * Base delay in milliseconds for exponential backoff
   */
  const BASE_DELAY_MS = 500;

  /**
   * Transient key for tracking rate limit state
   */
  const RATE_LIMIT_TRANSIENT = 'sails_tax_rate_limited';

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
      return new WP_Error('sails_tax_no_key', __('Sails API key is not configured.', 'sails-tax'));
    }

    // Check if we're currently rate limited
    $rate_limit_until = get_transient(self::RATE_LIMIT_TRANSIENT);
    if ($rate_limit_until && time() < $rate_limit_until) {
      $wait_seconds = $rate_limit_until - time();
      $this->log(sprintf('Rate limited, waiting %d seconds before retry', $wait_seconds), 'warning', $debug);
      return new WP_Error('sails_tax_rate_limited', sprintf(
        __('API rate limited. Please try again in %d seconds.', 'sails-tax'),
        $wait_seconds
      ));
    }

    $url = $base . '/api/v1/tax/calculate';

    $payload = [
      'amount' => floatval($amount),
      'toZip' => sanitize_text_field($toZip),
      'toState' => strtoupper(sanitize_text_field($toState)),
    ];

    $this->log(sprintf('Calculating tax: $%.2f to %s, %s', $amount, $toZip, $toState), 'info', $debug);

    return $this->request_with_retry($url, $key, $payload, $debug);
  }

  /**
   * Make API request with exponential backoff retry for rate limits
   */
  private function request_with_retry($url, $key, $payload, $debug, $attempt = 1) {
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
    $headers = wp_remote_retrieve_headers($res);

    // Handle rate limiting (HTTP 429)
    if ($code === 429) {
      return $this->handle_rate_limit($url, $key, $payload, $debug, $attempt, $headers);
    }

    // Handle server errors with retry (5xx)
    if ($code >= 500 && $code < 600 && $attempt < self::MAX_RETRIES) {
      $delay = $this->calculate_backoff_delay($attempt);
      $this->log(sprintf(
        'Server error (HTTP %d), attempt %d/%d, retrying in %dms',
        $code, $attempt, self::MAX_RETRIES, $delay
      ), 'warning', $debug);
      
      usleep($delay * 1000);
      return $this->request_with_retry($url, $key, $payload, $debug, $attempt + 1);
    }

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
      'Tax calculated: $%.2f at %.2f%% (%s confidence) in %dms%s',
      $taxAmount, $rate, $confidence, $elapsed,
      $attempt > 1 ? sprintf(' (attempt %d)', $attempt) : ''
    ), 'info', $debug);

    return $json['data'];
  }

  /**
   * Handle rate limit response with backoff
   */
  private function handle_rate_limit($url, $key, $payload, $debug, $attempt, $headers) {
    // Check for Retry-After header
    $retry_after = null;
    if (isset($headers['retry-after'])) {
      $retry_after = intval($headers['retry-after']);
    } elseif (isset($headers['Retry-After'])) {
      $retry_after = intval($headers['Retry-After']);
    }

    // Use Retry-After if provided, otherwise calculate exponential backoff
    if ($retry_after && $retry_after > 0) {
      $delay_seconds = min($retry_after, 60); // Cap at 60 seconds
    } else {
      $delay_seconds = min(pow(2, $attempt) * 2, 60); // Exponential: 4s, 8s, 16s, max 60s
    }

    $this->log(sprintf(
      'Rate limited (429), attempt %d/%d, waiting %d seconds',
      $attempt, self::MAX_RETRIES, $delay_seconds
    ), 'warning', $debug);

    // If we've exhausted retries, set a transient to prevent immediate retries
    if ($attempt >= self::MAX_RETRIES) {
      set_transient(self::RATE_LIMIT_TRANSIENT, time() + $delay_seconds, $delay_seconds);
      return new WP_Error('sails_tax_rate_limited', sprintf(
        __('API rate limit exceeded. Please try again in %d seconds.', 'sails-tax'),
        $delay_seconds
      ));
    }

    // Wait and retry
    sleep($delay_seconds);
    return $this->request_with_retry($url, $key, $payload, $debug, $attempt + 1);
  }

  /**
   * Calculate exponential backoff delay in milliseconds
   */
  private function calculate_backoff_delay($attempt) {
    // Exponential backoff: 500ms, 1000ms, 2000ms, etc.
    $delay = self::BASE_DELAY_MS * pow(2, $attempt - 1);
    
    // Add jitter (±25%) to prevent thundering herd
    $jitter = $delay * 0.25;
    $delay = $delay + rand(-$jitter, $jitter);
    
    // Cap at 10 seconds
    return min($delay, 10000);
  }

  /**
   * Clear rate limit state (useful for admin/testing)
   */
  public static function clear_rate_limit() {
    delete_transient(self::RATE_LIMIT_TRANSIENT);
  }

  /**
   * Check if currently rate limited
   */
  public static function is_rate_limited() {
    $rate_limit_until = get_transient(self::RATE_LIMIT_TRANSIENT);
    return $rate_limit_until && time() < $rate_limit_until;
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
