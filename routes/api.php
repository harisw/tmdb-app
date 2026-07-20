<?php

use App\Http\Controllers\TitleGraphController;

Route::get('/titles/{title}/graph', [TitleGraphController::class, 'show']);
