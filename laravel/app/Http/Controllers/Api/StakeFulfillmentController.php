<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\StakeKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\FulfillPromiseRequest;
use App\Models\Battle;
use App\Services\StakeObligationService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Va'da bajarildi — foto/video isbot (SPEC §17).
 * Javob — yangilangan va'dalar ro'yxati: UI uni to'g'ridan-to'g'ri chizadi.
 */
class StakeFulfillmentController extends Controller
{
    public function __construct(private readonly StakeObligationService $obligations) {}

    public function store(FulfillPromiseRequest $request, Battle $battle, string $kind)
    {
        $this->obligations->fulfill(
            $request->user(),
            $battle,
            StakeKind::tryFrom($kind) ?? throw new NotFoundHttpException('Garov turi topilmadi'),
            $request->file('file'),
            $request->filled('note') ? trim((string) $request->input('note')) : null,
        );

        return ['obligations' => $this->obligations->forBattle($battle->refresh(), $request->user()->id)];
    }
}
