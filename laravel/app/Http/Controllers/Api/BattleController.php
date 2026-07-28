<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateBattleRequest;
use App\Models\Battle;
use App\Services\BattleService;
use Illuminate\Http\Request;

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
            periodDays: $request->integer('period_days'),
            startTomorrow: $request->boolean('start_tomorrow', true),
            challenges: $request->array('challenges'),
        );

        return ['battle' => $battle, 'invite_token' => $battle->invite_token];
    }

    public function accept(Request $request, string $token)
    {
        $battle = $this->battles->acceptByToken($request->user(), $token);

        return ['ok' => true, 'battle_id' => $battle->id];
    }

    public function show(Request $request, Battle $battle)
    {
        return $this->battles->detail($battle, $request->user());
    }

    public function today(Request $request, Battle $battle)
    {
        return $this->battles->todayTasks($battle, $request->user());
    }
}
