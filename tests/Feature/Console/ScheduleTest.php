<?php

namespace Tests\Feature\Console;

use App\Domain\Enums\TitleType;
use App\Jobs\FetchPopularTitlesJob;
use App\Jobs\FetchTrendingTitlesJob;
use App\Jobs\ImportTmdbProvidersJob;
use App\Jobs\MaterializeTitleSimilaritiesBatchJob;
use App\Jobs\SyncTitlePopularityJob;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    private const string TIMEZONE = 'Asia/Seoul';

    /**
     * @return array<int, CarbonImmutable>
     */
    private function runsWithin(Event $event, int $days = 7): array
    {
        $start = CarbonImmutable::parse('2026-10-12 00:00', self::TIMEZONE);

        return collect((new CronExpression($event->expression))->getMultipleRunDates(200, $start, allowCurrentDate: true, timeZone: $event->timezone))
            ->map(fn (\DateTime $run) => CarbonImmutable::instance($run)->setTimezone(self::TIMEZONE))
            ->filter(fn (CarbonImmutable $run) => $run->lessThan($start->addDays($days)))
            ->values()
            ->all();
    }

    private function isServerUp(CarbonImmutable $time): bool
    {
        $minuteOfDay = $time->hour * 60 + $time->minute;

        return $time->isWeekend() || $minuteOfDay >= 17 * 60 + 30 || $minuteOfDay < 2 * 60 + 30;
    }

    public function test_schedule_runs_in_korea_time(): void
    {
        foreach (app(Schedule::class)->events() as $event) {
            $this->assertSame(self::TIMEZONE, $event->timezone, $event->getSummaryForDisplay());
        }
    }

    public function test_every_event_runs_while_the_server_is_up(): void
    {
        foreach (app(Schedule::class)->events() as $event) {
            $runs = $this->runsWithin($event) ?: $this->runsWithin($event, days: 31);
            $runsWhileUp = array_filter($runs, $this->isServerUp(...));

            $this->assertNotEmpty($runsWhileUp, "{$event->getSummaryForDisplay()} never runs while the server is up");

            if (count($runs) <= 7) {
                $this->assertCount(count($runs), $runsWhileUp, "{$event->getSummaryForDisplay()} has runs while the server is down");
            }
        }
    }

    public function test_trending_fetches_run_daily_and_weekly(): void
    {
        $runsPerWeek = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => $event->description === FetchTrendingTitlesJob::class)
            ->map(fn (Event $event) => count($this->runsWithin($event)))
            ->sort()
            ->values()
            ->all();

        $this->assertSame([1, 1, 7, 7], $runsPerWeek);
    }

    public function test_similarities_are_refreshed_hourly(): void
    {
        $runsPerWeek = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => $event->description === MaterializeTitleSimilaritiesBatchJob::class)
            ->map(fn (Event $event) => count($this->runsWithin($event)))
            ->values()
            ->all();

        $this->assertSame([7 * 24], $runsPerWeek);
    }

    public function test_providers_are_imported_from_tmdb_monthly(): void
    {
        $runsPerMonth = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => $event->description === ImportTmdbProvidersJob::class)
            ->map(fn (Event $event) => count($this->runsWithin($event, days: 31)))
            ->values()
            ->all();

        $this->assertSame([1], $runsPerMonth);
    }

    public function test_popular_fetches_run_daily_after_the_tmdb_export_and_chain_a_popularity_sync(): void
    {
        Queue::fake();

        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $event) => $event->description === FetchPopularTitlesJob::class);

        foreach ($events as $event) {
            $runs = $this->runsWithin($event);

            $this->assertCount(7, $runs);
            $this->assertGreaterThanOrEqual(17 * 60 + 30, $runs[0]->hour * 60 + $runs[0]->minute);
            $event->run(app());
        }

        $this->assertCount(2, $events);
        foreach (TitleType::cases() as $type) {
            Queue::assertPushedWithChain(
                FetchPopularTitlesJob::class,
                [new SyncTitlePopularityJob($type)],
                fn (FetchPopularTitlesJob $job) => (new \ReflectionProperty($job, 'titleType'))->getValue($job) === $type,
            );
        }
    }
}
