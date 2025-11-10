<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\HistoryController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// --- Handover Station Routes ---
Route::get('/', [HandoverController::class, 'index'])->name('handover.index');
Route::post('/handover/set-batch', [HandoverController::class, 'setBatch'])->name('handover.set-batch');
Route::post('/handover/clear-batch', [HandoverController::class, 'clearBatch'])->name('handover.clear-batch');
Route::post('/handover/set-3pl', [HandoverController::class, 'setThreePl'])->name('handover.set-3pl');
Route::post('/handover/scan', [HandoverController::class, 'scan'])->name('handover.scan');
Route::post('/handover/remove', [HandoverController::class, 'remove'])->name('handover.remove');
Route::post('/handover/finalize', [HandoverController::class, 'finalize'])->name('handover.finalize');

// --- History Dashboard Routes ---
Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
// Route detail, export, dan upload (simulasi)
Route::get('/history/export', [HistoryController::class, 'exportCsv'])->name('history.export-csv');
Route::get('/history/{handoverId}/download', [HistoryController::class, 'downloadManifest'])->name('history.download-manifest');
Route::post('/history/{handoverId}/upload', [HistoryController::class, 'uploadManifest'])->name('history.upload-manifest');

require __DIR__.'/auth.php';
