<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\StakeKind;
use App\Http\Controllers\Controller;
use App\Models\Battle;
use App\Services\BattleStakeService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Garov — yutuq va jazo kelishuvi (SPEC §17).
 * Javob — yangilangan garov holati: UI uni to'g'ridan-to'g'ri chizadi.
 */
class BattleStakeController extends Controller
{
    public function __construct(private readonly BattleStakeService $stakes) {}

    public function update(Request $request, Battle $battle, string $kind)
    {
        $data = $request->validate(['text' => ['required', 'string', 'min:2', 'max:200']]);

        $this->stakes->edit($battle, $request->user(), $this->kind($kind), $data['text']);

        return $this->state($request, $battle);
    }

    public function vote(Request $request, Battle $battle, string $stake)
    {
        $this->stakes->vote($battle, $request->user(), (int) $stake);

        return $this->state($request, $battle);
    }

    public function coin(Request $request, Battle $battle, string $kind)
    {
        $chosen = $this->stakes->flip($battle, $request->user(), $this->kind($kind));

        return [...$this->state($request, $battle), 'flipped' => $chosen->id];
    }

    /**
     * @return array<string, mixed>
     */
    private function state(Request $request, Battle $battle): array
    {
        $battle->refresh();

        return [
            'status' => $battle->status->value,
            'stakes' => $this->stakes->summary($battle, $request->user()->id),
        ];
    }

    private function kind(string $kind): StakeKind
    {
        return StakeKind::tryFrom($kind) ?? throw new NotFoundHttpException('Garov turi topilmadi');
    }
}
