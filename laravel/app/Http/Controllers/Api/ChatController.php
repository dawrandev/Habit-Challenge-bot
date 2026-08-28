<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Battle;
use App\Models\Quest;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $request, Battle $battle)
    {
        return $this->chat->messages($request->user(), $battle);
    }

    public function store(SendMessageRequest $request, Battle $battle)
    {
        return $this->chat->post(
            $request->user(),
            $battle,
            $request->string('text')->toString(),
        );
    }

    public function questIndex(Request $request, Quest $quest)
    {
        return $this->chat->messages($request->user(), $quest);
    }

    public function questStore(SendMessageRequest $request, Quest $quest)
    {
        return $this->chat->post(
            $request->user(),
            $quest,
            $request->string('text')->toString(),
        );
    }
}
