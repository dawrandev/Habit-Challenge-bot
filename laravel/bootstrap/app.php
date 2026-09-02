<?php

use App\Console\Commands\AutoApproveCommand;
use App\Console\Commands\CloseDayCommand;
use App\Http\Middleware\AuthenticateTelegram;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tg.auth' => AuthenticateTelegram::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Kunlik yopish ilova kuni almashgandan KEYIN yugurishi SHART.
        // Kun 04:00 da almashsa-yu cron 00:05 da yugursa, o'sha paytda
        // "bugun" hali kechagi kun bo'ladi va yopilish bir kunga kechikardi.
        // Manba — App\Support\Clock::dayStartHour().
        $closeAt = sprintf('%02d:05', max(0, min(23, (int) config('telegram.day_start_hour', 0))));

        $schedule->command(CloseDayCommand::class)
            ->dailyAt($closeAt)
            ->timezone(config('telegram.timezone'));

        // Har soatda — 24s o'tgan tekshiruvlarni auto-tasdiq (SPEC §11)
        $schedule->command(AutoApproveCommand::class)->hourly();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
