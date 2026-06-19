<?php

use App\Domain\Enums\TitleType;
use App\Jobs\FetchTrendingTitlesJob;
use App\Utils\Enum\TimeWindow;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('db:seed --class=CountsSeeder --force')
    ->everyThreeHours(1);

Schedule::command('titles:refresh-searchable --force')
    ->everySixHours();

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::DAY, TitleType::MOVIE, 30))
    ->dailyAt('02:00');

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::DAY, TitleType::TV, 30))
    ->dailyAt('04:00');

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::WEEK, TitleType::MOVIE))
    ->fridays();

Schedule::job(new FetchTrendingTitlesJob(TimeWindow::WEEK, TitleType::TV))
    ->saturdays();
