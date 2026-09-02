<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Telegram\TelegramClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Telegram bilan aloqa uzilganda ilova o'zini qanday tutadi.
 *
 * Ikki talab:
 *   1. Tarmoq uzilishi ISTISNO EMAS — server DNS'siz qolishi mumkin.
 *      Diagnostika buyrug'i shunday paytda XABAR QILISHI kerak, qulamasligi.
 *   2. Xato matnida BOT TOKENI bo'lmasligi kerak. Guzzle xatosi to'liq URL'ni
 *      qaytaradi, unda esa token bor — u ekranga yoki log'ga tushsa oshkor
 *      bo'ladi (haqiqatan shunday bo'lgan).
 */
class TelegramResilienceTest extends TestCase
{
    // `battle:doctor` DB'ni ham tekshiradi
    use RefreshDatabase;

    private const TOKEN = '8986779258:AAEVSNbD8kuw6Ug1Hm48m';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telegram.bot_token', self::TOKEN);
        config()->set('telegram.api_base', 'https://api.telegram.org');
    }

    /** Guzzle uzilish xatosi — ichida to'liq URL va token bo'ladi. */
    private function failWithTokenInMessage(): void
    {
        Http::fake(function () {
            throw new ConnectionException(
                'cURL error 6: Could not resolve host: api.telegram.org for '
                    .'https://api.telegram.org/bot'.self::TOKEN.'/getMe',
            );
        });
    }

    public function test_get_me_does_not_throw_when_the_network_is_down(): void
    {
        $this->failWithTokenInMessage();

        $result = app(TelegramClient::class)->getMe();

        $this->assertFalse($result['ok'] ?? true, 'uzilish ok=false qaytarishi kerak');
    }

    public function test_the_bot_token_never_leaks_into_the_error(): void
    {
        $this->failWithTokenInMessage();

        $error = (string) (app(TelegramClient::class)->getMe()['error'] ?? '');

        $this->assertStringNotContainsString(self::TOKEN, $error, 'token oshkor bo`lmasligi kerak');
        $this->assertStringContainsString('resolve host', $error, 'sabab ko`rinib turishi kerak');
    }

    public function test_webhook_info_survives_a_connection_failure(): void
    {
        $this->failWithTokenInMessage();

        $this->assertSame([], app(TelegramClient::class)->getWebhookInfo());
    }

    public function test_doctor_reports_the_outage_instead_of_crashing(): void
    {
        $this->failWithTokenInMessage();
        config()->set('telegram.webapp_url', 'https://example.test');

        $this->artisan('battle:doctor')
            ->expectsOutputToContain('DNS')
            ->assertExitCode(1);   // muammo bor — lekin qulamadi
    }

    public function test_doctor_output_never_contains_the_token(): void
    {
        $this->failWithTokenInMessage();

        $this->artisan('battle:doctor')->doesntExpectOutputToContain(self::TOKEN);
    }

    public function test_set_webhook_reports_failure_rather_than_throwing(): void
    {
        $this->failWithTokenInMessage();

        $result = app(TelegramClient::class)->setWebhook('https://example.test/api/telegram/webhook');

        $this->assertFalse($result['ok'] ?? true);
    }

    public function test_sending_a_message_stays_silent_on_failure(): void
    {
        // Bildirishnoma yuborilmasligi foydalanuvchi amalini to'xtatmasligi kerak
        $this->failWithTokenInMessage();

        app(TelegramClient::class)->sendMessage(123, 'salom');

        $this->assertTrue(true, 'istisno chiqmadi');
    }
}
