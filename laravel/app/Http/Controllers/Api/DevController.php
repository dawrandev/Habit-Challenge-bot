<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\BattleStatus;
use App\Enums\Cadence;
use App\Enums\CompletionStatus;
use App\Http\Controllers\Controller;
use App\Models\Battle;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * DEV: demo ma'lumot (faqat dev rejimda).
 */
class DevController extends Controller
{
    public function seed(Request $request)
    {
        if (config('telegram.bot_token') !== '' && ! config('telegram.allow_dev_auth')) {
            abort(403, 'Seed faqat dev rejimda');
        }

        $user = $request->user();
        $rival = User::firstOrCreate(
            ['telegram_id' => 999],
            ['first_name' => 'Ali', 'username' => 'ali'],
        );

        $today = Clock::todayLocal();
        $start = $today->subDays(6);

        $battle = Battle::create([
            'title' => 'Iyul dueli',
            'status' => BattleStatus::Active,
            'period_days' => 7,
            'start_date' => $start->toDateString(),
            'end_date' => $today->addDay()->toDateString(),
            'timezone' => config('telegram.timezone'),
            'created_by' => $user->id,
            'invite_token' => Str::random(12),
        ]);

        $battle->participants()->createMany([
            ['user_id' => $user->id, 'accepted' => true],
            ['user_id' => $rival->id, 'accepted' => true],
        ]);

        $defs = [
            ['read', '📖', Cadence::Daily, []],
            ['sport', '🏃', Cadence::Daily, []],
            ['earlyRise', '🌅', Cadence::WeeklyDays, [0, 2, 4]],
        ];

        $challenges = collect($defs)->map(fn ($d) => $battle->challenges()->create([
            'template_key' => $d[0],
            'icon' => $d[1],
            'cadence' => $d[2],
            'weekdays' => $d[3],
            'start_date' => $start->toDateString(),
        ]));

        $approve = function (Challenge $ch, int $userId, array $offsets) use ($start, $today): void {
            foreach ($offsets as $off) {
                $day = $start->addDays($off);
                if ($day->greaterThan($today) || ! $ch->cadence->isDue($day, $ch->weekdaysList())) {
                    continue;
                }
                Completion::create([
                    'challenge_id' => $ch->id,
                    'user_id' => $userId,
                    'day' => $day->toDateString(),
                    'status' => CompletionStatus::Approved,
                    'submitted_at' => now(),
                    'resolved_at' => now(),
                    'file_id' => 'dev-seed',
                ]);
            }
        };

        $approve($challenges[0], $user->id, [0, 1, 2, 3, 4, 5, 6]);
        $approve($challenges[0], $rival->id, [0, 1, 2, 4, 6]);
        $approve($challenges[1], $user->id, [0, 1, 3, 4, 5]);
        $approve($challenges[1], $rival->id, [0, 2, 4]);
        $approve($challenges[2], $user->id, [0, 2, 4]);
        $approve($challenges[2], $rival->id, [0, 2, 4]);

        return ['battle_id' => $battle->id, 'title' => $battle->title];
    }
}
