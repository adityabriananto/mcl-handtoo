<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientApiController;
use App\Http\Controllers\DataHandoverUploadController;
use App\Http\Controllers\InboundOrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\TplPrefixController;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Route untuk Logout (Harus Login)
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::prefix('admin/client-api')->group(function () {
    Route::get('/', [ClientApiController::class, 'index'])->name('client_api.index');
    Route::get('/create', [ClientApiController::class, 'create'])->name('client_api.create');
    Route::post('/store', [ClientApiController::class, 'store'])->name('client_api.store');
    Route::get('/{id}/edit', [ClientApiController::class, 'edit'])->name('client_api.edit');
    Route::put('/{id}', [ClientApiController::class, 'update'])->name('client_api.update');
    Route::delete('/{id}', [ClientApiController::class, 'destroy'])->name('client_api.destroy');
    Route::post('/{id}/refresh-token', [ClientApiController::class, 'refreshToken'])->name('client_api.refresh');
});

// --- Inbound Routes ---
Route::prefix('inbound')->group(function () {
    // 1. Dashboard & List (Halaman Utama)
    Route::match(['get', 'post'], '/', [InboundOrderController::class, 'index'])->name('inbound.index');

    // 2. Export Excel (PENTING: Letakkan sebelum rute {id} agar kata 'export' tidak terbaca sebagai ID)
    Route::get('/export', [InboundOrderController::class, 'export'])->name('inbound.export');

    // 3. Create & Store (Manual Entry jika diperlukan)
    Route::get('/create', [InboundOrderController::class, 'create'])->name('inbound.create');
    Route::post('/store', [InboundOrderController::class, 'store'])->name('inbound.store');
    Route::post('/upload', [InboundOrderController::class, 'uploadIoNumber'])->name('inbound.upload');
    Route::get('/inbound/template', [InboundOrderController::class, 'downloadTemplate'])->name('inbound.template');

    // 4. Detail (Melihat List SKU di dalam Inbound)
    Route::get('/{id}', [InboundOrderController::class, 'show'])->name('inbound.show');

    // 5. Edit, Update & Delete
    Route::get('/{id}/edit', [InboundOrderController::class, 'edit'])->name('inbound.edit');
    Route::put('/{id}', [InboundOrderController::class, 'update'])->name('inbound.update');
    Route::delete('/{id}', [InboundOrderController::class, 'destroy'])->name('inbound.destroy');

    // 6. Split Data
    Route::post('/inbound/{id}/split', [InboundOrderController::class, 'split'])->name('inbound.split');

    // 7. Export
    Route::get('/export/{id}', [InboundOrderController::class, 'export'])->name('export');
    Route::get('/inbound/{inbound}/export-children', [InboundOrderController::class, 'exportChildren'])
    ->name('inbound.export.children');

    // 7. Update status
    Route::post('/inbound/status-complete/{id}', [InboundOrderController::class, 'updateStatus'])->name('inbound.complete');
});
});

// --- Handover Station Routes ---
Route::get('/', [HandoverController::class, 'index'])->name('handover.index');
Route::post('/handover/set-batch', [HandoverController::class, 'setBatch'])->name('handover.set-batch');
Route::post('/handover/clear-batch', [HandoverController::class, 'clearBatch'])->name('handover.clear-batch');
Route::post('/handover/set-3pl', [HandoverController::class, 'setThreePl'])->name('handover.set-3pl');
Route::post('/handover/scan', [HandoverController::class, 'scan'])->name('handover.scan');
Route::post('/handover/remove', [HandoverController::class, 'remove'])->name('handover.remove');
Route::post('/handover/finalize', [HandoverController::class, 'finalize'])->name('handover.finalize');
Route::get('/handover/check-count', [HandoverController::class, 'checkCount'])->name('handover.check-count');
Route::get('/handover/table-fragment', [HandoverController::class, 'getTableFragment'])->name('handover.table-fragment');

// --- History Dashboard Routes ---
Route::get('/history', [HistoryController::class, 'index'])->name('history.index');
// Route detail, export, dan upload (simulasi)
Route::get('/history/export', [HistoryController::class, 'exportCsv'])->name('history.export-csv');
Route::get('/history/{handoverId}/download', [HistoryController::class, 'downloadManifest'])->name('history.download-manifest');
Route::post('/history/{handoverId}/upload', [HistoryController::class, 'uploadManifest'])->name('history.upload-manifest');

// --- Tpl Prefix Routes ---
Route::resource('tpl/config', TplPrefixController::class)->names([
    'index' => 'tpl.config.index',
    'create' => 'tpl.config.create',
    'store' => 'tpl.config.store',
    'edit' => 'tpl.config.edit',
    'update' => 'tpl.config.update',
    'destroy' => 'tpl.config.destroy',
]);

// --- Handover Upload Data Routes ---
Route::resource('handover/upload', DataHandoverUploadController::class)->names([
    'index' => 'handover.upload.index',
    'create' => 'handover.upload.create',
    'store' => 'handover.upload.store',
    'edit' => 'handover.upload.edit',
    'update' => 'handover.upload.update',
    'destroy' => 'handover.upload.destroy',
]);

// --- Client API Routes ---

require __DIR__.'/auth.php';
