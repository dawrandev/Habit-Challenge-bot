<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Scoring\ScoringEngine;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoring engine config'dan (points/penalty/floor) — SPEC §4
        $this->app->singleton(ScoringEngine::class, fn () => ScoringEngine::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}
