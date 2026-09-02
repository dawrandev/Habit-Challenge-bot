<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Clock;

/**
 * Ilovaning joriy kuni.
 *
 * Kun kalendar bo'yicha emas, `day_start_hour` bo'yicha almashadi (Clock).
 * Yarim tun o'tib, lekin kun almashmagan paytda UI buni OCHIQ aytishi kerak:
 * aks holda telefonda "2-sentabr" turadi-yu, isbot 1-sentabrga yoziladi va
 * foydalanuvchi buni xato deb o'ylaydi.
 */
class DayController extends Controller
{
    public function __invoke()
    {
        return [
            // Isbot AYNAN shu kunga yoziladi
            'date' => Clock::todayLocal()->toDateString(),
            'ends_at' => Clock::dayEndsAt()->toIso8601String(),
            'day_start_hour' => Clock::dayStartHour(),
            // true = yarim tun o'tgan, lekin hali kechagi kun davom etyapti
            'grace' => Clock::inGraceWindow(),
        ];
    }
}
