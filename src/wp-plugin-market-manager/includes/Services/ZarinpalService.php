<?php
namespace WPLM\Services;

use WPLM\Options;

defined('ABSPATH') || exit;

final class ZarinpalService
{
    private const REQUEST_PROD = 'https://payment.zarinpal.com/pg/v4/payment/request.json';
    private const VERIFY_PROD = 'https://payment.zarinpal.com/pg/v4/payment/verify.json';
    private const START_PROD = 'https://payment.zarinpal.com/pg/StartPay/';
    private const REQUEST_SANDBOX = 'https://sandbox.zarinpal.com/pg/v4/payment/request.json';
    private const VERIFY_SANDBOX = 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json';
    private const START_SANDBOX = 'https://sandbox.zarinpal.com/pg/StartPay/';

    public static function amount_for_gateway(float $price): int
    {
        $settings = Options::get();
        $amount = (int) round($price);
        if ('toman' === $settings['price_unit']) {
            $amount *= 10;
        }
        return max(1000, $amount);
    }

    public static function request_payment(int $amount, string $callback_url, string $description, array $extra = []): array
    {
        $settings = Options::get();
        $merchant = trim((string) $settings['zarinpal_merchant']);
        if (!$merchant) {
            return ['success' => false, 'message' => 'مرچنت کد زرین‌پال تنظیم نشده است.'];
        }

        $sandbox = '1' === (string) $settings['zarinpal_sandbox'];
        $payload = array_filter([
            'merchant_id' => $merchant,
            'amount' => $amount,
            'callback_url' => $callback_url,
            'description' => $description,
            'metadata' => array_filter([
                'mobile' => $extra['mobile'] ?? '',
                'email' => $extra['email'] ?? '',
                'order_id' => isset($extra['order_id']) ? (string) $extra['order_id'] : '',
            ]),
        ]);

        $response = wp_remote_post($sandbox ? self::REQUEST_SANDBOX : self::REQUEST_PROD, [
            'timeout' => 25,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $data = $body['data'] ?? [];
        if (isset($data['code']) && 100 === (int) $data['code'] && !empty($data['authority'])) {
            return ['success' => true, 'authority' => (string) $data['authority'], 'track_id' => (string) $data['authority'], 'raw' => $body];
        }
        return ['success' => false, 'message' => $body['errors']['message'] ?? 'خطا در ایجاد تراکنش زرین‌پال.', 'raw' => $body];
    }

    public static function start_url(string $authority): string
    {
        $settings = Options::get();
        return ('1' === (string) $settings['zarinpal_sandbox'] ? self::START_SANDBOX : self::START_PROD) . rawurlencode($authority);
    }

    public static function verify(string $authority, int $amount): array
    {
        $settings = Options::get();
        $merchant = trim((string) $settings['zarinpal_merchant']);
        if (!$merchant) {
            return ['success' => false, 'message' => 'مرچنت کد زرین‌پال تنظیم نشده است.'];
        }
        $sandbox = '1' === (string) $settings['zarinpal_sandbox'];
        $response = wp_remote_post($sandbox ? self::VERIFY_SANDBOX : self::VERIFY_PROD, [
            'timeout' => 25,
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode(['merchant_id' => $merchant, 'amount' => $amount, 'authority' => $authority]),
        ]);
        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $data = $body['data'] ?? [];
        $code = isset($data['code']) ? (int) $data['code'] : 0;
        $ok = in_array($code, [100, 101], true);
        return [
            'success' => $ok,
            'message' => $ok ? 'پرداخت تایید شد.' : ($body['errors']['message'] ?? 'پرداخت تایید نشد.'),
            'raw' => $body,
            'ref_number' => isset($data['ref_id']) ? (string) $data['ref_id'] : '',
            'card_number' => isset($data['card_pan']) ? (string) $data['card_pan'] : '',
            'amount' => $amount,
        ];
    }
}
