<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddChallengeRequest;
use App\Http\Requests\CreateQuestRequest;
use App\Http\Requests\UpdateQuestRequest;
use App\Models\Quest;
use App\Services\QuestService;
use App\Support\DateRange;
use Illuminate\Http\Request;

/**
 * Missiya — yakka odat yo'li, guvoh bilan (asimmetrik rejim).
 *
 * Isbot yuborish/tekshirish/nizo duel bilan UMUMIY endpoint'lardan boradi
 * (/completions) — ruxsatni ProofContext hal qiladi.
 */
class QuestController extends Controller
{
    public function __construct(private readonly QuestService $quests) {}

    public function index(Request $request)
    {
        return $this->quests->listForUser($request->user());
    }

    public function store(CreateQuestRequest $request)
    {
        $quest = $this->quests->create(
            owner: $request->user(),
            title: $request->string('title')->toString(),
            period: DateRange::fromStrings(
                $request->string('start_date')->toString(),
                $request->string('end_date')->toString(),
            ),
            goalPercent: $request->integer('goal_percent'),
            challenges: $request->array('challenges'),
        );

        return ['quest' => $quest, 'invite_token' => $quest->invite_token];
    }

    public function invite(Request $request, string $token)
    {
        return $this->quests->invitePreview($request->user(), $token);
    }

    public function accept(Request $request, string $token)
    {
        $quest = $this->quests->acceptWitness($request->user(), $token);

        return ['ok' => true, 'quest_id' => $quest->id];
    }

    public function show(Request $request, Quest $quest)
    {
        return $this->quests->detail($quest, $request->user());
    }

    public function today(Request $request, Quest $quest)
    {
        return $this->quests->todayTasks($quest, $request->user());
    }

    public function update(UpdateQuestRequest $request, Quest $quest)
    {
        return $this->quests->update(
            $request->user(),
            $quest,
            $request->string('title')->toString(),
            $request->has('goal_percent') ? $request->integer('goal_percent') : null,
        );
    }

    public function destroy(Request $request, Quest $quest)
    {
        $this->quests->delete($request->user(), $quest);

        return ['ok' => true];
    }

    public function abandon(Request $request, Quest $quest)
    {
        return $this->quests->abandon($request->user(), $quest);
    }

    public function addChallenge(AddChallengeRequest $request, Quest $quest)
    {
        return $this->quests->addChallenge($request->user(), $quest, $request->validated());
    }

    public function updateChallenge(AddChallengeRequest $request, Quest $quest, string $challenge)
    {
        return $this->quests->updateChallenge(
            $request->user(),
            $quest,
            (int) $challenge,
            $request->validated(),
        );
    }

    public function destroyChallenge(Request $request, Quest $quest, string $challenge)
    {
        $this->quests->deleteChallenge($request->user(), $quest, (int) $challenge);

        return ['ok' => true];
    }
}
