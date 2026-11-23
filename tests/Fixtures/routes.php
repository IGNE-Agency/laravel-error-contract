<?php

declare(strict_types=1);

use Igne\ProblemDetails\Tests\Fixtures\ArticleController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/fixtures')->group(function (): void {
    Route::get('articles', [ArticleController::class, 'index'])->name('api.fixtures.articles.index');
    Route::post('articles', [ArticleController::class, 'store']);
    Route::get('articles/{article}', [ArticleController::class, 'show']);
    Route::get('problems', [ArticleController::class, 'problems']);
    Route::post('articles/{article}/purchase', [ArticleController::class, 'purchase']);
    Route::post('articles/{article}/publish', [ArticleController::class, 'publish']);
    Route::post('articles/export', [ArticleController::class, 'export']);
});
