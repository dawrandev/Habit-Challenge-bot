<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BattleStatus;
use App\Enums\CompletionStatus;
use App\Enums\QuestOutcome;
use App\Enums\QuestStatus;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use App\Models\Verification;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Profil statistikasi — foydalanuvchining butun tarixi bo'yicha yig'ma.
 *
 * Duel (g'olib/yutqazgan) va missiya (maqsadga yetdi/yetmadi) alohida
 * sanaladi: ular boshqa-boshqa narsa, bitta "g'alaba" ustuniga qo'shilmaydi.
 */
class ProfileStatsService
{
    private const ACTIVITY_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        return [
            'battles' => $this->battles($user),
            'quests' => $this->quests($user),
            'proofs' => $this->proofs($user),
            'verification' => $this->verification($user),
            'activity' => $this->activity($user),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function battles(User $user): array
    {
        $battleIds = BattleParticipant::where('user_id', $user->id)->pluck('battle_id');

        $battles = Battle::whereIn('id', $battleIds)->get(['id', 'status', 'winner_id']);
        $finished = $battles->where('status', BattleStatus::Finished);

        return [
            'total' => $battles->count(),
            'active' => $battles->where('status', BattleStatus::Active)->count(),
            'finished' => $finished->count(),
            'won' => $finished->where('winner_id', $user->id)->count(),
            'draw' => $finished->whereNull('winner_id')->count(),
            'lost' => $finished->filter(
                fn (Battle $b) => $b->winner_id !== null && $b->winner_id !== $user->id,
            )->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function quests(User $user): array
    {
        // Faqat O'ZI bajargan missiyalar — guvohlik qilganlari bu yerga kirmaydi
        $quests = Quest::where('owner_id', $user->id)->get(['id', 'status', 'outcome']);
        $finished = $quests->where('status', QuestStatus::Finished);

        return [
            'total' => $quests->count(),
            'active' => $quests->where('status', QuestStatus::Active)->count(),
            'achieved' => $finished->where('outcome', QuestOutcome::Achieved)->count(),
            'missed' => $finished->where('outcome', QuestOutcome::Missed)->count(),
            'witnessing' => Quest::where('witness_id', $user->id)
                ->where('status', QuestStatus::Active->value)
                ->count(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function proofs(User $user): array
    {
        $counts = Completion::where('user_id', $user->id)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $approved = (int) ($counts[CompletionStatus::Approved->value] ?? 0)
            + (int) ($counts[CompletionStatus::AutoApproved->value] ?? 0);

        return [
            'approved' => $approved,
            'rejected' => (int) ($counts[CompletionStatus::Rejected->value] ?? 0),
            'pending' => (int) ($counts[CompletionStatus::Pending->value] ?? 0),
        ];
    }

    /**
     * Tekshiruvchi sifatidagi ishonchlilik.
     *
     * Sherigingning isbotini ko'rib chiqdingmi, yoki 24 soat o'tib avtomatik
     * tasdiqlanib ketdimi — o'zaro javobgarlik ilovasida eng gapiruvchi raqam.
     *
     * @return array<string, int>
     */
    private function verification(User $user): array
    {
        $answered = Verification::where('verifier_id', $user->id)->count();

        // Foydalanuvchi tekshiruvchi bo'lgan, lekin javob bermagan holatlar
        $battleIds = BattleParticipant::where('user_id', $user->id)->pluck('battle_id');
        $questIds = Quest::where('witness_id', $user->id)->pluck('id');

        $expired = 0;
        if ($battleIds->isNotEmpty() || $questIds->isNotEmpty()) {
            $expired = Completion::query()
                ->where('status', CompletionStatus::AutoApproved->value)
                ->where('user_id', '!=', $user->id)
                ->whereHas('challenge', fn ($q) => $q
                    ->whereIn('battle_id', $battleIds)
                    ->orWhereIn('quest_id', $questIds))
                ->count();
        }

        return [
            'answered' => $answered,
            'expired' => $expired,
        ];
    }

    /**
     * Oxirgi 30 kun — kuniga nechta isbot tasdiqlangan (ustunli chart uchun).
     *
     * Bo'sh kunlar ham qaytariladi: chartda uzilish ko'rinishi kerak,
     * yo'q kunni tashlab ketish tarixni yolg'on tekis qilib ko'rsatardi.
     *
     * @return array<int, array{date: string, done: int}>
     */
    private function activity(User $user): array
    {
        $today = Clock::todayLocal();
        $start = $today->subDays(self::ACTIVITY_DAYS - 1);

        $rows = Completion::where('user_id', $user->id)
            ->whereIn('status', [
                CompletionStatus::Approved->value,
                CompletionStatus::AutoApproved->value,
            ])
            ->whereDate('day', '>=', $start->toDateString())
            ->select('day', DB::raw('COUNT(*) as total'))
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn ($row) => [
                CarbonImmutable::parse($row->day)->format('Y-m-d') => (int) $row->total,
            ]);

        $out = [];
        for ($day = $start; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $out[] = ['date' => $key, 'done' => $rows[$key] ?? 0];
        }

        return $out;
    }
}
