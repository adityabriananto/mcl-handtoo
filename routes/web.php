<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ClientApiController;
use App\Http\Controllers\DataHandoverUploadController;
use App\Http\Controllers\InboundOrderController;
use App\Http\Controllers\MbOrderUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HandoverController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\TplPrefixController;
use App\Http\Controllers\MbMasterController;
use App\Http\Controllers\MbCheckerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public & Guest Routes
|--------------------------------------------------------------------------
*/
Route::get('/', [HandoverController::class, 'index'])->name('handover.index');

// --- History Dashboard ---
Route::prefix('history')->name('history.')->group(function () {
    Route::get('/', [HistoryController::class, 'index'])->name('index');
    Route::get('/export', [HistoryController::class, 'exportCsv'])->name('export-csv');
    Route::get('/{handoverId}/download', [HistoryController::class, 'downloadManifest'])->name('download-manifest');
    Route::post('/{handoverId}/upload', [HistoryController::class, 'uploadManifest'])->name('upload-manifest');
});

Route::prefix('mb-checker')->name('mb-checker.')->group(function () {
    Route::get('/', [MbCheckerController::class, 'index'])->name('index');
    Route::get('/verify', [MbCheckerController::class, 'verify'])->name('verify');
    Route::get('/export', [MbCheckerController::class, 'exportBrandCheck'])->name('export');
});

// --- Handover Station Operations ---
Route::prefix('handover')->name('handover.')->group(function () {
    Route::post('/set-batch', [HandoverController::class, 'setBatch'])->name('set-batch');
    Route::post('/clear-batch', [HandoverController::class, 'clearBatch'])->name('clear-batch');
    Route::post('/set-3pl', [HandoverController::class, 'setThreePl'])->name('set-3pl');
    Route::post('/scan', [HandoverController::class, 'scan'])->name('scan');
    Route::post('/remove', [HandoverController::class, 'remove'])->name('remove');
    Route::post('/finalize', [HandoverController::class, 'finalize'])->name('finalize');
    Route::get('/check-count', [HandoverController::class, 'checkCount'])->name('check-count');
    Route::get('/table-fragment', [HandoverController::class, 'getTableFragment'])->name('table-fragment');
});

// TPL Config
Route::resource('tpl-config', TplPrefixController::class)->names('tpl.config');

// Handover Data Upload
Route::resource('handover-upload', DataHandoverUploadController::class)->names('handover.upload');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware([])->prefix('mb-master/orders')->name('mb-orders.')->group(function () {
    Route::get('/', [MbOrderUploadController::class, 'index'])->name('index');
    Route::post('/import', [MbOrderUploadController::class, 'store'])->name('import');
    Route::post('/clean', [MbOrderUploadController::class, 'clean'])->name('clean');
    Route::get('/mb-orders/logs', [MbOrderUploadController::class, 'logs'])->name('logs');
});

Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/ops/inbound', [InboundOrderController::class, 'opsIndex'])->name('ops.inbound.index');
// Route POST untuk handle filter/search di menu Ops
Route::post('/ops/inbound', [InboundOrderController::class, 'opsIndex'])->name('ops.inbound.filter');

Route::post('/inbound/upload-actual', [InboundOrderController::class, 'uploadActualQuantity'])->name('inbound.upload_actual');
/*
|--------------------------------------------------------------------------
| Authenticated Routes (Semua Menu Master & Admin)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');

    // --- Profile ---
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // --- MB Master (Brand Data) ---
    Route::prefix('mb-master')->name('mb-master.')->group(function () {
        // View & Data
        Route::get('/', [MbMasterController::class, 'index'])->name('index');
        Route::get('/import-status', [MbMasterController::class, 'checkImportStatus'])->name('import-status');

        // Actions
        Route::post('/', [MbMasterController::class, 'store'])->name('store');
        Route::post('/import', [MbMasterController::class, 'importCsv'])->name('import');

        // PERBAIKAN DI SINI: Hilangkan prefix berlebih dan sesuaikan parameter
        Route::patch('/{mbMaster}', [MbMasterController::class, 'update'])->name('update');

        Route::delete('/{id}', [MbMasterController::class, 'destroy'])->name('destroy');
    });

    // --- Inbound Routes ---
    Route::prefix('inbound')->name('inbound.')->group(function () {
        Route::match(['get', 'post'], '/', [InboundOrderController::class, 'index'])->name('index');
        Route::get('/export/{id}', [InboundOrderController::class, 'export'])->name('export');
        Route::get('/template', [InboundOrderController::class, 'downloadTemplate'])->name('template');
        Route::post('/upload', [InboundOrderController::class, 'uploadIoNumber'])->name('upload');
        Route::post('/status-complete/{id}', [InboundOrderController::class, 'updateStatus'])->name('complete');

        // Resource-like routes
        Route::get('/create', [InboundOrderController::class, 'create'])->name('create');
        Route::post('/store', [InboundOrderController::class, 'store'])->name('store');
        Route::get('/{id}', [InboundOrderController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [InboundOrderController::class, 'edit'])->name('edit');
        Route::put('/{id}', [InboundOrderController::class, 'update'])->name('update');
        Route::delete('/{id}', [InboundOrderController::class, 'destroy'])->name('destroy');

        // Specialized
        Route::post('/{id}/split', [InboundOrderController::class, 'split'])->name('split');
        Route::get('/{inbound}/export-children', [InboundOrderController::class, 'exportChildren'])->name('export.children');
    });

    // --- Configuration & Admin Section ---
    Route::prefix('admin')->group(function () {
        // Client API
        Route::resource('client-api', ClientApiController::class)->names('client_api');
        Route::post('client-api/{id}/refresh-token', [ClientApiController::class, 'refreshToken'])->name('client_api.refresh');

        // TPL Config
        // Route::resource('tpl-config', TplPrefixController::class)->names('tpl.config');

        // Handover Data Upload
        // Route::resource('handover-upload', DataHandoverUploadController::class)->names('handover.upload');
    });

    // --- History Dashboard ---
    // Route::prefix('history')->name('history.')->group(function () {
    //     Route::get('/', [HistoryController::class, 'index'])->name('index');
    //     Route::get('/export', [HistoryController::class, 'exportCsv'])->name('export-csv');
    //     Route::get('/{handoverId}/download', [HistoryController::class, 'downloadManifest'])->name('download-manifest');
    //     Route::post('/{handoverId}/upload', [HistoryController::class, 'uploadManifest'])->name('upload-manifest');
    // });

    // --- Handover Station Operations ---
    // Route::prefix('handover')->name('handover.')->group(function () {
    //     Route::post('/set-batch', [HandoverController::class, 'setBatch'])->name('set-batch');
    //     Route::post('/clear-batch', [HandoverController::class, 'clearBatch'])->name('clear-batch');
    //     Route::post('/set-3pl', [HandoverController::class, 'setThreePl'])->name('set-3pl');
    //     Route::post('/scan', [HandoverController::class, 'scan'])->name('scan');
    //     Route::post('/remove', [HandoverController::class, 'remove'])->name('remove');
    //     Route::post('/finalize', [HandoverController::class, 'finalize'])->name('finalize');
    //     Route::get('/check-count', [HandoverController::class, 'checkCount'])->name('check-count');
    //     Route::get('/table-fragment', [HandoverController::class, 'getTableFragment'])->name('table-fragment');
    // });
});

require __DIR__.'/auth.php';
