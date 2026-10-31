<?php
namespace WPLM\Services;

use WPLM\Options;

defined('ABSPATH') || exit;

final class ZibalService
{
    private const REQUEST_URL = 'https://gateway.zibal.ir/v1/request';
    private const VERIFY_URL = 'https://gateway.zibal.ir/v1/verify';
    private const START_URL = 'https://gateway.zibal.ir/start/';

    public static function merchant(): string
    {
        $settings = Options::get();
        if ('1' === $settings['zibal_test_mode']) {
            return 'zibal';
        }
        return trim((string) $settings['zibal_merchant']);
    }

    public static function start_url(string $track_id): string
    {
        return self::START_URL . rawurlencode($track_id);
    }

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
        $merchant = self::merchant();
        if (!$merchant) {
            return ['success' => false, 'message' => 'مرچنت کد زیبال تنظیم نشده است.'];
        }

        $payload = array_filter([
            'merchant' => $merchant,
            'amount' => $amount,
            'callbackUrl' => $callback_url,
            'description' => $description,
            'orderId' => $extra['order_id'] ?? '',
            'mobile' => $extra['mobile'] ?? '',
        ], static fn($value) => '' !== $value && null !== $value);

        $response = wp_remote_post(self::REQUEST_URL, [
            'timeout' => 25,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return ['success' => false, 'message' => 'پاسخ نامعتبر از درگاه زیبال دریافت شد.'];
        }

        if (isset($body['result']) && 100 === (int) $body['result'] && !empty($body['trackId'])) {
            return ['success' => true, 'track_id' => (string) $body['trackId'], 'raw' => $body];
        }

        return ['success' => false, 'message' => $body['message'] ?? $body['statusMessage'] ?? 'خطا در ایجاد تراکنش زیبال.', 'raw' => $body];
    }

    public static function verify(string $track_id): array
    {
        $merchant = self::merchant();
        if (!$merchant) {
            return ['success' => false, 'message' => 'مرچنت کد زیبال تنظیم نشده است.'];
        }

        $response = wp_remote_post(self::VERIFY_URL, [
            'timeout' => 25,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode([
                'merchant' => $merchant,
                'trackId' => $track_id,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body)) {
            return ['success' => false, 'message' => 'پاسخ تایید پرداخت نامعتبر است.'];
        }

        $ok = isset($body['result']) && 100 === (int) $body['result'];
        return [
            'success' => $ok,
            'message' => $body['message'] ?? $body['statusMessage'] ?? ($ok ? 'پرداخت تایید شد.' : 'پرداخت تایید نشد.'),
            'raw' => $body,
            'ref_number' => isset($body['refNumber']) ? (string) $body['refNumber'] : '',
            'card_number' => isset($body['cardNumber']) ? (string) $body['cardNumber'] : '',
            'paid_at' => isset($body['paidAt']) ? (string) $body['paidAt'] : '',
            'amount' => isset($body['amount']) ? (int) $body['amount'] : 0,
        ];
    }
}
