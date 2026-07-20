<?php

Route::get('/titles/{title}/graph', [TitleGraphController::class, 'show']);
