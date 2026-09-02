<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Ilova vaqti — Toshkent mintaqasi bo'yicha (SPEC §3).
 *
 * ⚠️ MUHIM: ilovaning "kuni" KALENDAR kuni emas.
 *
 * Kun `day_start_hour` da (default 04:00) almashadi, yarim tunda emas. Sabab
 * hayotiy: odam odatini kechqurun bajaradi va 23:59 da bitta isbot yuborib
 * ulguradi, qolganini yuborayotganda soat 00:00 bo'lib qoladi — kalendar
 * bo'yicha bu "keyingi kun", lekin odam uchun bu O'SHA kechaning davomi.
 * Kalendarga qat'iy amal qilish odamni bajargan ishi uchun jazolardi.
 *
 * Ya'ni "1-sentabr" kuni 1-sentabr 04:00 dan 2-sentabr 04:00 gacha davom
 * etadi. Retroaktiv yuborish YO'Q — bu faqat kechani cho'zish, o'tgan kunni
 * qayta ochish emas (aks holda jonli kamera isbotining ma'nosi qolmasdi).
 */
final class Clock
{
    /** Haqiqiy devor soati — sanoq va qolgan vaqt uchun. */
    public static function nowLocal(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    /**
     * Ilovaning JORIY KUNI (00:00 da normallashtirilgan sana).
     *
     * Yarim tundan keyin, lekin `day_start_hour` dan oldin bo'lsa — hali
     * kechagi kun qaytadi. Butun ilova shu funksiyaga tayanadi, shuning
     * uchun scoring, "bugun kutilyapti", kunlik yopish va statistika —
     * hammasi avtomatik bir xil kunni ko'radi.
     */
    public static function todayLocal(): CarbonImmutable
    {
        $now = self::nowLocal();

        return $now->hour < self::dayStartHour()
            ? $now->subDay()->startOfDay()
            : $now->startOfDay();
    }

    /** Kun necha soatda almashadi (0–23). */
    public static function dayStartHour(): int
    {
        $hour = (int) config('telegram.day_start_hour', 0);

        return max(0, min(23, $hour));
    }

    /**
     * Berilgan ilova kuni QACHON tugaydi (aniq lahza).
     *
     * D kuni D+1 ning `day_start_hour` ida tugaydi: 1-sen kuni
     * 2-sen 04:00 da yopiladi.
     */
    public static function dayEndsAt(?CarbonImmutable $day = null): CarbonImmutable
    {
        $day ??= self::todayLocal();

        return $day->startOfDay()->addDay()->setTime(self::dayStartHour(), 0);
    }

    /**
     * Hozir "cho'zilgan kecha"damizmi — yarim tun o'tgan, lekin kun hali
     * almashmagan. UI shuni ochiq aytishi kerak, aks holda foydalanuvchi
     * sanani ko'rib chalkashadi.
     */
    public static function inGraceWindow(): bool
    {
        $hour = self::dayStartHour();

        return $hour > 0 && self::nowLocal()->hour < $hour;
    }

    private static function timezone(): string
    {
        return (string) config('telegram.timezone');
    }
}
