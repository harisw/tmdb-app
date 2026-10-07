<?php

use App\Domain\Enums\TitleType;
use App\Jobs\EnrichTitleCreditsBatchJob;
use App\Jobs\EnrichTitleKnowledgeGraphsJob;
use App\Jobs\EnrichTitleProvidersBatchJob;
use App\Jobs\FetchPopularTitlesJob;
use App\Jobs\FetchTrendingTitlesJob;
use App\Jobs\FetchUpcomingTitlesJob;
use App\Utils\Enum\FeatureFlag;
use App\Utils\Enum\TimeWindow;
use Laravel\Pennant\Feature;

Schedule::command('pulse:check')->everyMinute()->withoutOverlapping();

Schedule::command('db:seed --class=CountsSeeder --force')
    ->everyThreeHours(1)->withoutOverlapping();

Schedule::command('titles:refresh-searchable --force')
    ->everySixHours()->withoutOverlapping();

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::DAY, TitleType::MOVIE, 30))
    ->dailyAt('02:00');

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::DAY, TitleType::TV, 30))
    ->dailyAt('04:00');

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::WEEK, TitleType::MOVIE))
    ->fridays();

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::WEEK, TitleType::TV))
    ->saturdays();

Schedule::job(new FetchPopularTitlesJob(TitleType::MOVIE))
    ->dailyAt('03:00');

Schedule::job(new FetchPopularTitlesJob(TitleType::TV))
    ->dailyAt('05:00');

Schedule::job(new FetchUpcomingTitlesJob(TitleType::MOVIE))
    ->dailyAt('07:00');

Schedule::job(new FetchUpcomingTitlesJob(TitleType::TV))
    ->dailyAt('09:00');

Schedule::job(new EnrichTitleProvidersBatchJob)
    ->hourly()->withoutOverlapping();

Schedule::job(new EnrichTitleCreditsBatchJob)
    ->hourlyAt(30)->withoutOverlapping();

Schedule::job(new EnrichTitleKnowledgeGraphsJob(100))
    ->everyThreeHours()->withoutOverlapping()
    ->when(fn () => Feature::for('global')->active(FeatureFlag::KnowledgeGraphEnrichment));

Schedule::job(new EnrichTitleProvidersBatchJob)
    ->thursdays()
    ->at('08:00');

Schedule::job(new EnrichTitleProvidersBatchJob)
    ->wednesdays()
    ->at('08:00');
