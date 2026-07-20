<?php

use App\Domain\Enums\TitleType;
use App\Jobs\EnrichTitleKnowledgeGraphsJob;
use App\Jobs\EnrichTitleProvidersBatchJob;
use App\Jobs\FetchTrendingTitlesJob;
use App\Utils\Enum\TimeWindow;

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

Schedule::job(new EnrichTitleProvidersBatchJob)
    ->hourly()->withoutOverlapping();

Schedule::job(new EnrichTitleKnowledgeGraphsJob(100))
    ->everyThreeHours()->withoutOverlapping();
