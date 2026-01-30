<?php

if (!defined('ABSPATH')) exit;

class Sails_Tax_API {
  public function calculate($amount, $toZip, $toState) {
    $opts = Sails_Tax_Settings::get();
    $base = isset($opts['api_base_url']) ? rtrim($opts['api_base_url'], '/') : 'https://sails.tax';
    $key  = isset($opts['api_key']) ? $opts['api_key'] : '';

    if (!$key) {
      return new WP_Error('sails_tax_no_key', 'Sails API key is not configured.');
    }

    $url = $base . '/api/v1/tax/calculate';

    $payload = [
      'amount' => floatval($amount),
      'toZip' => sanitize_text_field($toZip),
      'toState' => strtoupper(sanitize_text_field($toState)),
    ];

    $res = wp_remote_post($url, [
      'timeout' => 10,
      'headers' => [
        'Authorization' => 'Bearer ' . $key,
        'Content-Type' => 'application/json',
      ],
      'body' => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) return $res;

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    $json = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
      $msg = isset($json['error']) ? $json['error'] : ('HTTP ' . $code);
      return new WP_Error('sails_tax_http_error', $msg);
    }

    if (!isset($json['success']) || !$json['success']) {
      $msg = isset($json['error']) ? $json['error'] : 'Unknown error';
      return new WP_Error('sails_tax_api_error', $msg);
    }

    return $json['data'];
  }
}
