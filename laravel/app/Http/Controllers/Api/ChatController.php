<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request, string $battle)
    {
        return $this->chat->messages($request->user(), (int) $battle);
    }

    public function store(SendMessageRequest $request, string $battle)
    {
        return $this->chat->post(
            $request->user(),
            (int) $battle,
            $request->string('text')->toString(),
        );
    }
}
