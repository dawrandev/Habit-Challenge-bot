<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddChallengeRequest;
use App\Http\Requests\CreateBattleRequest;
use App\Models\Battle;
use App\Services\BattleAccess;
use App\Services\BattleService;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BattleController extends Controller
{
    public function __construct(private readonly BattleService $battles) {}

    public function index(Request $request)
    {
        return $this->battles->listForUser($request->user());
    }

    public function store(CreateBattleRequest $request)
    {
        $battle = $this->battles->create(
            user: $request->user(),
            title: $request->string('title')->toString(),
            period: DateRange::fromStrings(
                $request->string('start_date')->toString(),
                $request->string('end_date')->toString(),
            ),
            challenges: $request->array('challenges'),
        );

        return ['battle' => $battle, 'invite_token' => $battle->invite_token];
    }

    public function invite(Request $request, string $token)
    {
        return $this->battles->invitePreview($request->user(), $token);
    }

    public function accept(Request $request, string $token)
    {
        $battle = $this->battles->acceptByToken($request->user(), $token);

        return ['ok' => true, 'battle_id' => $battle->id];
    }

    public function show(Request $request, Battle $battle, BattleAccess $access)
    {
        $this->assertParticipant($access, $battle, $request);

        return $this->battles->detail($battle, $request->user());
    }

    public function today(Request $request, Battle $battle, BattleAccess $access)
    {
        $this->assertParticipant($access, $battle, $request);

        return $this->battles->todayTasks($battle, $request->user());
    }

    /**
     * Begona duelning hisobi/challenge'lari ID orqali o'qilmasin.
     */
    private function assertParticipant(BattleAccess $access, Battle $battle, Request $request): void
    {
        if (! $access->isParticipant($battle->id, $request->user()->id)) {
            throw new AccessDeniedHttpException("Ruxsat yo'q");
        }
    }

    public function addChallenge(AddChallengeRequest $request, Battle $battle, BattleAccess $access)
    {
        if (! $access->isParticipant($battle->id, $request->user()->id)) {
            throw new AccessDeniedHttpException('Ruxsat yo\'q');
        }

        return $this->battles->addChallenge($request->user(), $battle, $request->validated());
    }

    public function update(Request $request, Battle $battle)
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:120']]);

        return $this->battles->updateBattle($request->user(), $battle, $data['title']);
    }

    public function destroy(Request $request, Battle $battle)
    {
        $this->battles->deleteBattle($request->user(), $battle);

        return ['ok' => true];
    }

    public function updateChallenge(AddChallengeRequest $request, Battle $battle, string $challenge)
    {
        return $this->battles->updateChallenge(
            $request->user(),
            $battle,
            (int) $challenge,
            $request->validated(),
        );
    }

    public function destroyChallenge(Request $request, Battle $battle, string $challenge)
    {
        $this->battles->deleteChallenge($request->user(), $battle, (int) $challenge);

        return ['ok' => true];
    }

    public function acceptChallenge(Request $request, Battle $battle, string $challenge)
    {
        return $this->battles->acceptChallenge($request->user(), $battle, (int) $challenge);
    }

    public function rejectChallenge(Request $request, Battle $battle, string $challenge)
    {
        $this->battles->rejectChallenge($request->user(), $battle, (int) $challenge);

        return ['ok' => true];
    }
}
