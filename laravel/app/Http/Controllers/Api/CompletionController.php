<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitCompletionRequest;
use App\Http\Requests\VerifyRequest;
use App\Services\CompletionService;
use App\Services\DisputeService;
use App\Services\VerificationService;
use Illuminate\Http\Request;

class CompletionController extends Controller
{
    public function __construct(
        private readonly CompletionService $completions,
        private readonly VerificationService $verifications,
        private readonly DisputeService $disputes,
    ) {}

    public function store(SubmitCompletionRequest $request)
    {
        return $this->completions->submit(
            user: $request->user(),
            challengeId: $request->integer('challenge_id'),
            contents: $request->file('file')->get(),
            filename: 'proof.jpg',
            note: $request->filled('note') ? trim((string) $request->input('note')) : null,
        );
    }

    public function verify(VerifyRequest $request, string $completion)
    {
        $result = $this->verifications->verify(
            $request->user(),
            (int) $completion,
            $request->boolean('approve'),
        );

        return ['ok' => true, 'status' => $result->status->value];
    }

    public function dispute(Request $request, string $completion)
    {
        $this->disputes->open($request->user(), (int) $completion);

        return ['ok' => true];
    }

    public function queue(Request $request)
    {
        return $this->verifications->queueFor($request->user());
    }
}
