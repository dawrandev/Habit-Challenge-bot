<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BattleStatus;
use App\Enums\StakeKind;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\StakeFulfillment;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use App\Services\Telegram\PhotoService;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Duel tugagach kim kimga nima qarzdor — va uning bajarilishi (SPEC §17).
 *
 *   Yutuq: yutqazgan g'olibning o'zi aytgan yutug'ini bajaradi.
 *          Durang — ikkalasi ham bir-birining yutug'ini bajaradi.
 *   Jazo:  jazoga tortilgan (rejaning chegaradan kami) kelishilgan jazoni bajaradi.
 *
 * Qarz natijadan hisoblanadi (saqlanmaydi); bajarilgani foto/video bilan
 * `stake_fulfillments` ga yoziladi — keyin tarix sifatida ko'rinadi.
 */
class StakeObligationService
{
    public function __construct(
        private readonly BattleStakeService $stakes,
        private readonly PhotoService $media,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Battle sahifasi uchun va'dalar ro'yxati (faqat tugagan garovli duel).
     *
     * @return list<array<string, mixed>>
     */
    public function forBattle(Battle $battle, int $meId): array
    {
        $fulfillments = StakeFulfillment::where('battle_id', $battle->id)->get();

        return array_map(function (array $o) use ($fulfillments, $meId) {
            $done = $fulfillments->first(
                fn (StakeFulfillment $f) => $f->kind === $o['kind'] && $f->debtor_id === $o['debtor']->id,
            );

            return [
                'kind' => $o['kind']->value,
                'debtor' => $o['debtor'],
                'creditor' => $o['creditor'],
                'text' => $o['text'],
                'is_mine' => $o['debtor']->id === $meId,
                'for_me' => $o['creditor']?->id === $meId,
                'fulfillment' => $done ? [
                    'file_id' => $done->file_id,
                    'media_type' => $done->media_type,
                    'note' => $done->note,
                    'created_at' => $done->created_at,
                ] : null,
            ];
        }, $this->obligations($battle));
    }

    /**
     * Va'da bajarilganini foto/video bilan tasdiqlaydi. Qayta yuklash —
     * eskisini almashtiradi (masalan, yaxshiroq video).
     */
    public function fulfill(User $user, Battle $battle, StakeKind $kind, UploadedFile $file, ?string $note): StakeFulfillment
    {
        $obligation = collect($this->obligations($battle))->first(
            fn (array $o) => $o['kind'] === $kind && $o['debtor']->id === $user->id,
        );
        if ($obligation === null) {
            throw $battle->status === BattleStatus::Finished
                ? new AccessDeniedHttpException('Bu va\'da senga tegishli emas')
                : new ConflictHttpException('Duel hali tugamagan');
        }

        $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
        $contents = $file->get();
        $fileId = $isVideo
            ? $this->media->storeVideo($contents, 'promise.'.($file->guessExtension() ?: 'mp4'))
            : $this->media->store($contents, 'promise.jpg');

        if ($fileId === '') {
            throw new ConflictHttpException('Faylni saqlab bo\'lmadi — qayta urinib ko\'r');
        }

        $fulfillment = StakeFulfillment::updateOrCreate(
            ['battle_id' => $battle->id, 'kind' => $kind->value, 'debtor_id' => $user->id],
            [
                'file_id' => $fileId,
                'media_type' => $isVideo ? StakeFulfillment::VIDEO : StakeFulfillment::PHOTO,
                'note' => $note,
            ],
        );

        $this->notifyFulfilled($battle, $user, $obligation, $isVideo, $fulfillment->wasRecentlyCreated);

        return $fulfillment;
    }

    /**
     * Duel yakunidagi qarzlar.
     *
     * @return list<array{kind: StakeKind, debtor: User, creditor: ?User, text: string}>
     */
    public function obligations(Battle $battle): array
    {
        if ($battle->status !== BattleStatus::Finished || ! $battle->hasStakes()) {
            return [];
        }

        $battle->loadMissing('participants.user');
        $players = $battle->participants->filter(fn (BattleParticipant $p) => $p->user !== null)->values();
        if ($players->count() !== 2) {
            return [];
        }

        $out = [];

        // Yutuq: yutqazgan → g'olibga; durang — ikkalasi bir-biriga
        foreach ($players as $creditor) {
            $debtor = $players->first(fn (BattleParticipant $p) => $p->user_id !== $creditor->user_id);
            $creditorWon = $battle->winner_id === $creditor->user_id;
            if (! $creditorWon && $battle->winner_id !== null) {
                continue;
            }

            $text = $this->stakes->prizeFor($battle, $creditor->user_id);
            if ($text !== null) {
                $out[] = ['kind' => StakeKind::Prize, 'debtor' => $debtor->user, 'creditor' => $creditor->user, 'text' => $text];
            }
        }

        // Jazo: jazoga tortilgan har kim
        $penalty = $this->stakes->chosenText($battle, StakeKind::Penalty);
        if ($penalty !== null) {
            foreach ($players as $p) {
                if ($p->penalized === true) {
                    $out[] = ['kind' => StakeKind::Penalty, 'debtor' => $p->user, 'creditor' => null, 'text' => $penalty];
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{kind: StakeKind, debtor: User, creditor: ?User, text: string}  $obligation
     */
    private function notifyFulfilled(Battle $battle, User $user, array $obligation, bool $isVideo, bool $first): void
    {
        $rival = $battle->participants->first(fn (BattleParticipant $p) => $p->user_id !== $user->id)?->user;
        if ($rival?->telegram_id === null) {
            return;
        }

        $name = e($user->first_name);
        $what = e($obligation['text']);
        $media = $isVideo ? '🎬 video' : '📸 rasm';

        $text = $obligation['kind'] === StakeKind::Prize
            ? "✅ <b>{$name}</b> va'dasini bajardi!\n🎁 Sen uchun: <b>{$what}</b>"
            : "✅ <b>{$name}</b> jazoni bajardi!\n⚡ <b>{$what}</b>";
        $text .= $first ? "\n\nIsboti — {$media}. Ko'rib qo'y 👇" : "\n\nIsbotini yangiladi ({$media}) 👇";

        $this->notifications->notifyWithApp((int) $rival->telegram_id, $text, '👀 Ko\'rish', "/battle/{$battle->id}");
    }
}
