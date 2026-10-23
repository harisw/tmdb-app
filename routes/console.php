<?php

use App\Domain\Enums\TitleType;
use App\Jobs\EnrichSearchEmbeddingsBatchJob;
use App\Jobs\EnrichTitleCreditsBatchJob;
use App\Jobs\EnrichTitleEmbeddingsBatchJob;
use App\Jobs\EnrichTitleProvidersBatchJob;
use App\Jobs\FetchPopularTitlesJob;
use App\Jobs\FetchTrendingTitlesJob;
use App\Jobs\FetchUpcomingTitlesJob;
use App\Jobs\MaterializeTitleSimilaritiesBatchJob;
use App\Utils\Enum\FeatureFlag;
use App\Utils\Enum\TimeWindow;
use Laravel\Pennant\Feature;

Schedule::command('pulse:check')->everyMinute()->withoutOverlapping();

Schedule::command('db:seed --class=CountsSeeder --force')
    ->everySixHours()->withoutOverlapping();

Schedule::command('titles:refresh-searchable --force')
    ->everySixHours()->withoutOverlapping();

Schedule::command('db:export')
    ->cron('0 23 */3 * *')->withoutOverlapping();

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::DAY, TitleType::MOVIE, 30))
    ->dailyAt('18:00');

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::DAY, TitleType::TV, 30))
    ->dailyAt('20:00');

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::WEEK, TitleType::MOVIE))
    ->fridays()
    ->at('19:30');

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::WEEK, TitleType::TV))
    ->saturdays()
    ->at('19:30');

Schedule::job(new FetchPopularTitlesJob(TitleType::MOVIE))
    ->dailyAt('19:00');

Schedule::job(new FetchPopularTitlesJob(TitleType::TV))
    ->dailyAt('21:00');

Schedule::job(new FetchUpcomingTitlesJob(TitleType::MOVIE))
    ->dailyAt('22:00');

Schedule::job(new FetchUpcomingTitlesJob(TitleType::TV))
    ->dailyAt('23:00');

Schedule::job(new EnrichTitleProvidersBatchJob)
    ->hourly()->withoutOverlapping();

Schedule::job(new EnrichTitleCreditsBatchJob)
    ->hourlyAt(30)->withoutOverlapping();

Schedule::job(new EnrichTitleEmbeddingsBatchJob(100))
    ->everyThreeHours()->withoutOverlapping()
    ->when(fn () => Feature::for('global')->active(FeatureFlag::KnowledgeGraphEnrichment));

Schedule::job(new MaterializeTitleSimilaritiesBatchJob(1000))
    ->hourlyAt(45)->withoutOverlapping()
    ->when(fn () => Feature::for('global')->active(FeatureFlag::KnowledgeGraphEnrichment));

Schedule::job(new EnrichTitleProvidersBatchJob)
    ->thursdays()
    ->at('18:30');

Schedule::job(new EnrichTitleProvidersBatchJob)
    ->wednesdays()
    ->at('18:30');

Schedule::job(new EnrichSearchEmbeddingsBatchJob(500))->hourly()->withoutOverlapping();
