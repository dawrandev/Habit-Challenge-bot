<?php

declare(strict_types=1);

use App\Enums\BattleStatus;
use App\Enums\StakeKind;
use App\Models\Battle;
use App\Models\BattleStakeVote;
use App\Services\BattleStakeService;
use Illuminate\Database\Migrations\Migration;

/*
 * Yutuq endi tanlanmaydi — har o'yinchi o'z yutug'ini aytadi (SPEC §17).
 *
 * Kelishuvdagi duellar uchun (sxema o'zgarmaydi, faqat ma'lumot):
 *   - yutuq bo'yicha berilgan ovozlar endi ma'nosiz — o'chiriladi;
 *   - jazosi allaqachon kelishilgan, faqat yutuqni kutayotgan duel endi
 *     boshlanishi kerak — aks holda uni hech narsa boshlamaydi.
 *
 * Yutug'i eski qoida bilan tanlangan duellar o'zgarmaydi (prizeFor shuni o'qiydi).
 */
return new class extends Migration
{
    public function up(): void
    {
        $negotiating = Battle::where('status', BattleStatus::Negotiating->value)->get();

        BattleStakeVote::whereIn('battle_id', $negotiating->pluck('id'))
            ->where('kind', StakeKind::Prize->value)
            ->delete();

        $stakes = app(BattleStakeService::class);
        foreach ($negotiating as $battle) {
            $stakes->startIfSettled($battle);
        }
    }

    public function down(): void
    {
        // Ma'lumot ko'chirish — orqaga qaytarib bo'lmaydi (va kerak ham emas)
    }
};
