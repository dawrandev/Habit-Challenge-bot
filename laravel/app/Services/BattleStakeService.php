<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scoring\PenaltyRule;
use App\Enums\BattleStatus;
use App\Enums\StakeDecision;
use App\Enums\StakeKind;
use App\Models\Battle;
use App\Models\BattleStake;
use App\Models\BattleStakeVote;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use App\Support\Clock;
use App\Support\Coin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Garov — yutuq va jazo kelishuvi (SPEC §17).
 *
 * Har o'yinchi har garov turi bo'yicha bittadan variant aytadi. Keyin har
 * kim ikki variantdan birini tanlaydi: ovozlar mos kelsa — kelishildi;
 * har xil bo'lsa — chatda kelishadi yoki tanga tashlanadi. Ikkala garov
 * hal bo'lgach duel o'zi boshlanadi.
 */
class BattleStakeService
{
    public function __construct(
        private readonly BattleAccess $access,
        private readonly NotificationService $notifications,
        private readonly Coin $coin,
    ) {}

    /**
     * O'yinchining variantlarini yozadi (yaratishda va taklifni qabul qilishda).
     *
     * @param  array{prize: string, penalty: string}  $texts
     */
    public function propose(Battle $battle, User $user, array $texts): void
    {
        foreach (StakeKind::cases() as $kind) {
            BattleStake::updateOrCreate(
                ['battle_id' => $battle->id, 'kind' => $kind->value, 'proposed_by' => $user->id],
                ['text' => trim($texts[$kind->value])],
            );
        }

        $battle->unsetRelation('stakes');
    }

    /**
     * O'z variantini o'zgartiradi — garov hal bo'lmaguncha.
     *
     * O'sha tur bo'yicha OVOZLAR bekor qilinadi: eski matnga berilgan ovoz
     * jimgina yangisiga o'tib ketmasin.
     */
    public function edit(Battle $battle, User $user, StakeKind $kind, string $text): BattleStake
    {
        $this->assertParticipant($battle, $user);

        if (! in_array($battle->status, [BattleStatus::Pending, BattleStatus::Negotiating], true)) {
            throw new ConflictHttpException('Battle boshlangan — garov endi o\'zgarmaydi');
        }
        if ($this->chosen($battle, $kind) !== null) {
            throw new ConflictHttpException('Bu garov allaqachon kelishilgan');
        }

        $stake = $battle->stakes()
            ->where('kind', $kind->value)
            ->where('proposed_by', $user->id)
            ->first() ?? throw new NotFoundHttpException('Variant topilmadi');

        DB::transaction(function () use ($battle, $kind, $stake, $text) {
            $stake->update(['text' => trim($text)]);
            BattleStakeVote::where('battle_id', $battle->id)->where('kind', $kind->value)->delete();
        });

        $this->notifyOthers(
            $battle,
            $user,
            "✏️ <b>{$user->first_name}</b> {$this->kindLabel($kind)} variantini o'zgartirdi:\n"
                ."«{$stake->text}»\n\nQaytadan tanlang 👇",
        );

        return $stake;
    }

    /**
     * Ikki variantdan birini tanlaydi. Ikkalasi bir xilini tanlasa — kelishildi.
     */
    public function vote(Battle $battle, User $user, int $stakeId): BattleStake
    {
        $this->assertParticipant($battle, $user);
        $this->assertNegotiating($battle);

        $stake = $battle->stakes()->find($stakeId)
            ?? throw new NotFoundHttpException('Variant topilmadi');

        if ($this->chosen($battle, $stake->kind) !== null) {
            throw new ConflictHttpException('Bu garov allaqachon kelishilgan');
        }

        BattleStakeVote::updateOrCreate(
            ['battle_id' => $battle->id, 'user_id' => $user->id, 'kind' => $stake->kind->value],
            ['stake_id' => $stake->id],
        );

        $votes = $this->votes($battle, $stake->kind);
        $agreed = $votes->count() === 2 && $votes->pluck('stake_id')->unique()->count() === 1;

        if ($agreed) {
            $stake->update(['chosen_via' => StakeDecision::Agreement]);
            $this->notifyAll($battle, "🤝 Kelishildi! {$this->kindIcon($stake->kind)} "
                .ucfirst($this->kindLabel($stake->kind)).": <b>{$stake->text}</b>");
            $this->startIfSettled($battle);
        } else {
            $this->notifyOthers(
                $battle,
                $user,
                "🗳 <b>{$user->first_name}</b> {$this->kindLabel($stake->kind)} bo'yicha tanlov qildi:\n"
                    ."«{$stake->text}»\n\nSen ham tanla 👇",
            );
        }

        return $stake->fresh();
    }

    /**
     * Ovozlar ikkiga bo'linganda — tanga tashlaydi (50/50).
     */
    public function flip(Battle $battle, User $user, StakeKind $kind): BattleStake
    {
        $this->assertParticipant($battle, $user);
        $this->assertNegotiating($battle);

        if ($this->chosen($battle, $kind) !== null) {
            throw new ConflictHttpException('Bu garov allaqachon kelishilgan');
        }

        $stakeIds = $this->votes($battle, $kind)->pluck('stake_id')->unique()->values();
        if ($stakeIds->count() !== 2) {
            throw new ConflictHttpException('Tanga faqat ikkalangiz har xil variant tanlaganda tashlanadi');
        }

        /** @var BattleStake $winner */
        $winner = $this->coin->flip([
            $battle->stakes()->findOrFail($stakeIds[0]),
            $battle->stakes()->findOrFail($stakeIds[1]),
        ]);
        $winner->update(['chosen_via' => StakeDecision::Coin]);

        $this->notifyAll($battle, "🎲 <b>{$user->first_name}</b> tanga tashladi!\n"
            ."{$this->kindIcon($kind)} ".ucfirst($this->kindLabel($kind)).": <b>{$winner->text}</b>");
        $this->startIfSettled($battle);

        return $winner->fresh();
    }

    /**
     * Battle sahifasi uchun garov holati. Garovsiz duelda — null.
     *
     * @return array<string, mixed>|null
     */
    public function summary(Battle $battle, int $meId): ?array
    {
        $stakes = $battle->stakes()->get();
        if ($stakes->isEmpty()) {
            return null;
        }

        $votes = BattleStakeVote::where('battle_id', $battle->id)->get();
        $out = ['threshold' => $battle->penalty_threshold];

        foreach (StakeKind::cases() as $kind) {
            $options = $stakes->where('kind', $kind)->values();
            $chosen = $options->first(fn (BattleStake $s) => $s->isChosen());
            $kindVotes = $votes->where('kind', $kind);

            $out[$kind->value] = [
                // O'zingniki doim birinchi — UI'da tartib sakramasin
                'options' => $options
                    ->sortByDesc(fn (BattleStake $s) => $s->proposed_by === $meId)
                    ->map(fn (BattleStake $s) => [
                        'id' => $s->id,
                        'text' => $s->text,
                        'proposed_by' => $s->proposed_by,
                        'is_mine' => $s->proposed_by === $meId,
                    ])
                    ->values()
                    ->all(),
                'my_vote' => $kindVotes->firstWhere('user_id', $meId)?->stake_id,
                'rival_vote' => $kindVotes->first(fn (BattleStakeVote $v) => $v->user_id !== $meId)?->stake_id,
                'chosen' => $chosen ? [
                    'id' => $chosen->id,
                    'text' => $chosen->text,
                    'proposed_by' => $chosen->proposed_by,
                    'via' => $chosen->chosen_via->value,
                ] : null,
            ];
        }

        return $out;
    }

    /** Taklif sahifasi uchun: yaratuvchining variantlari. */
    public function creatorProposals(Battle $battle): ?array
    {
        $stakes = $battle->stakes()->where('proposed_by', $battle->created_by)->get();
        if ($stakes->isEmpty()) {
            return null;
        }

        return [
            'threshold' => $battle->penalty_threshold,
            'prize' => $stakes->firstWhere('kind', StakeKind::Prize)?->text,
            'penalty' => $stakes->firstWhere('kind', StakeKind::Penalty)?->text,
        ];
    }

    /** Kelishilgan variant matni (natija ekrani va bot xabari uchun). */
    public function chosenText(Battle $battle, StakeKind $kind): ?string
    {
        return $this->chosen($battle, $kind)?->text;
    }

    public function rule(Battle $battle): PenaltyRule
    {
        return new PenaltyRule($battle->penalty_threshold);
    }

    // --- ichki --------------------------------------------------------------

    private function chosen(Battle $battle, StakeKind $kind): ?BattleStake
    {
        return $battle->stakes()
            ->where('kind', $kind->value)
            ->whereNotNull('chosen_via')
            ->first();
    }

    /**
     * @return Collection<int, BattleStakeVote>
     */
    private function votes(Battle $battle, StakeKind $kind)
    {
        return BattleStakeVote::where('battle_id', $battle->id)
            ->where('kind', $kind->value)
            ->get();
    }

    /**
     * Ikkala garov hal bo'lsa — duel boshlanadi.
     *
     * Kelishuv rejadagi boshlanish sanasidan keyin tugagan bo'lsa, davr
     * uzunligini saqlab sanalar bugunga suriladi: o'tib ketgan kunlar
     * hech kim aybdor bo'lmagan jarimaga aylanmasin.
     */
    private function startIfSettled(Battle $battle): void
    {
        foreach (StakeKind::cases() as $kind) {
            if ($this->chosen($battle, $kind) === null) {
                return;
            }
        }

        DB::transaction(function () use ($battle) {
            $today = Clock::todayLocal();
            // Bir mintaqada o'qiymiz — aks holda kunlar farqi kasr chiqib, bir kun yo'qolardi
            $start = CarbonImmutable::parse($battle->start_date->toDateString(), $today->getTimezone());

            if ($start->lessThan($today)) {
                $shift = (int) $start->diffInDays($today);
                $newStart = $today->toDateString();

                $battle->start_date = $newStart;
                $battle->end_date = CarbonImmutable::parse($battle->end_date->toDateString(), $today->getTimezone())
                    ->addDays($shift)
                    ->toDateString();

                $battle->challenges()
                    ->whereDate('start_date', '<', $newStart)
                    ->update(['start_date' => $newStart]);
            }

            $battle->status = BattleStatus::Active;
            $battle->save();
        });

        $this->notifyAll($battle, "🔥 <b>{$battle->title}</b> boshlandi!\n"
            ."🏆 Yutuq: {$this->chosenText($battle, StakeKind::Prize)}\n"
            ."⚡ Jazo ({$battle->penalty_threshold}% va kam bajarganga): {$this->chosenText($battle, StakeKind::Penalty)}\n\n"
            .'Omad! 💪');
    }

    private function assertParticipant(Battle $battle, User $user): void
    {
        if (! $this->access->isParticipant($battle->id, $user->id)) {
            throw new AccessDeniedHttpException("Ruxsat yo'q");
        }
    }

    private function assertNegotiating(Battle $battle): void
    {
        if ($battle->status !== BattleStatus::Negotiating) {
            throw new ConflictHttpException('Kelishuv bosqichi emas');
        }
    }

    private function notifyOthers(Battle $battle, User $user, string $text): void
    {
        $this->notifications->notify($this->access->otherTelegramIds($battle->id, $user->id), $text);
    }

    private function notifyAll(Battle $battle, string $text): void
    {
        $this->notifications->notify($this->access->otherTelegramIds($battle->id, 0), $text);
    }

    private function kindLabel(StakeKind $kind): string
    {
        return $kind === StakeKind::Prize ? 'yutuq' : 'jazo';
    }

    private function kindIcon(StakeKind $kind): string
    {
        return $kind === StakeKind::Prize ? '🏆' : '⚡';
    }
}
