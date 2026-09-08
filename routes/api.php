<?php

use App\Http\Controllers\BitrixWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/bitrix/webhook', [BitrixWebhookController::class, 'webhook'])
    ->middleware('throttle:60,1')
    ->name('bitrix.webhook');
