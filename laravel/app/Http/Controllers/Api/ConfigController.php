<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Mini App uchun ish vaqti konfiguratsiyasi.
 *
 * Bot username ILGARI frontend'ga build vaqtida `VITE_BOT_USERNAME` orqali
 * qotirilardi. Natijada `npm run build` ni flagsiz bajarish barcha taklif
 * havolalarini jimgina `t.me/YourBot` ga yo'naltirib qo'yardi — havola
 * ishlamasdi, lekin hech qayerda xato ko'rinmasdi.
 *
 * Endi qiymat .env dan ish vaqtida keladi: rebuild shart emas.
 */
class ConfigController extends Controller
{
    public function __invoke()
    {
        return [
            'bot_username' => (string) config('telegram.bot_username'),
        ];
    }
}
