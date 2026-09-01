<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProfileStatsService;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(private readonly ProfileStatsService $stats) {}

    public function __invoke(Request $request)
    {
        return $this->stats->forUser($request->user());
    }
}
