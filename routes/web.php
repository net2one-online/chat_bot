<?php

use App\Http\Controllers\BitrixOAuthController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\ChatTesterController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmbedController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/locale/{lang}', function (string $lang) {
    session(['locale' => $lang]);

    return redirect()->back();
})->name('locale.change');

Route::get('/chat', [ChatTesterController::class, 'index'])->name('chat.index');
Route::post('/chat/send', [ChatTesterController::class, 'send'])->name('chat.send');
Route::get('/chat/reset', [ChatTesterController::class, 'reset'])->name('chat.reset');

Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
Route::post('/settings/gemini', [SettingsController::class, 'updateGemini'])->name('settings.gemini.update');
Route::post('/settings/test-gemini', [SettingsController::class, 'testGemini'])->name('settings.gemini.test');

Route::get('/auth/bitrix', [BitrixOAuthController::class, 'start'])->name('oauth.bitrix.start');
Route::match(['get', 'post'], '/auth/bitrix/callback', [BitrixOAuthController::class, 'callback'])->name('oauth.bitrix.callback');
Route::match(['get', 'post'], '/auth/bitrix/installation', [BitrixOAuthController::class, 'install'])->name('oauth.bitrix.installation');

Route::match(['get', 'post'], '/embed', [EmbedController::class, 'index'])->name('embed.index');

Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup/complete', [SetupController::class, 'complete'])->name('setup.complete');
Route::post('/setup/reset', [SetupController::class, 'reset'])->name('setup.reset');

Route::resource('bots', BotController::class);
Route::post('bots/{bot}/register', [BotController::class, 'register'])->name('bots.register');
Route::resource('conversations', ConversationController::class)->only(['index', 'show']);
Route::resource('knowledge', KnowledgeController::class);
