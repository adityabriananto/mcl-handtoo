<?php

namespace App\Http\Controllers;

use App\Models\DataUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DataUploadClearController extends Controller
{
    private const STATS_CACHE_TTL = 60; // seconds
    private const TABLE_CACHE_TTL = 30; // seconds

    /**
     * Display the Handover Data Uploads management page.
     * Stats are cached to avoid repeated COUNT(*) queries.
     */
    public function index()
    {
        $stats = $this->getCachedStats();

        return view('handover.data_uploads', [
            'oldRecordCount' => $stats['old_records'],
            'totalRecordCount' => $stats['total_records'],
            'oldFileCount' => $stats['old_files'],
            'cutoffDate' => $stats['cutoff_date'],
        ]);
    }

    /**
     * Clear data uploads older than 1 month.
     * Also cleans up old files in storage/app/uploads.
     */
    public function clear(Request $request)
    {
        $cutoffDate = now()->subMonth();

        // Count records to be deleted
        $recordCount = DataUpload::where('created_at', '<', $cutoffDate)->count();

        if ($recordCount === 0) {
            return back()->with('info', 'Tidak ada data upload yang lebih dari 1 bulan untuk dihapus.');
        }

        try {
            // Delete old DB records
            DataUpload::where('created_at', '<', $cutoffDate)->delete();

            // Clean up old files in storage/app/uploads (if any orphaned files exist)
            $deletedFiles = $this->cleanupOldUploadFiles($cutoffDate);

            $message = "Berhasil menghapus {$recordCount} record data upload yang lebih dari 1 bulan.";
            if ($deletedFiles > 0) {
                $message .= " {$deletedFiles} file lama juga dihapus dari storage.";
            }

            // Flush stats cache so next load shows updated numbers
            Cache::forget('data_upload_stats');
            Cache::forget('data_upload_table_page_1');

            Log::info('DataUpload auto-clear executed', [
                'records_deleted' => $recordCount,
                'files_deleted' => $deletedFiles,
                'cutoff_date' => $cutoffDate->toDateTimeString(),
            ]);

            return back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('DataUpload auto-clear failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus data: ' . $e->getMessage());
        }
    }

    /**
     * Get summary of old data uploads (JSON API for live refresh).
     * Results are cached to avoid hammering the DB.
     */
    public function summary()
    {
        $stats = $this->getCachedStats();
        return response()->json($stats);
    }

    /**
     * Get recent data uploads as JSON (paginated, cached).
     */
    public function recent(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $cacheKey = "data_upload_table_page_{$page}";

        $data = Cache::remember($cacheKey, self::TABLE_CACHE_TTL, function () use ($page) {
            $paginator = DataUpload::latest('created_at')
                ->select(['id', 'airwaybill', 'order_number', 'owner_name', 'qty', 'platform_name', 'created_at'])
                ->paginate(20, ['*'], 'page', $page);

            return [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Get cached stats (DB counts + file counts).
     */
    private function getCachedStats(): array
    {
        return Cache::remember('data_upload_stats', self::STATS_CACHE_TTL, function () {
            $cutoffDate = now()->subMonth();

            // Use query builder for raw performance (avoids Eloquent overhead)
            $oldRecordCount = \DB::table('data_uploads')
                ->where('created_at', '<', $cutoffDate)
                ->count();

            $totalRecordCount = \DB::table('data_uploads')->count();

            return [
                'old_records' => $oldRecordCount,
                'total_records' => $totalRecordCount,
                'old_files' => $this->countOldUploadFiles($cutoffDate),
                'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
            ];
        });
    }

    /**
     * Clean up old files in storage/app/uploads.
     */
    private function cleanupOldUploadFiles($cutoffDate): int
    {
        $deletedCount = 0;
        $uploadPath = storage_path('app/uploads');

        if (!is_dir($uploadPath)) {
            return 0;
        }

        $files = glob($uploadPath . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffDate->timestamp) {
                unlink($file);
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Count old files in storage/app/uploads.
     */
    private function countOldUploadFiles($cutoffDate): int
    {
        $count = 0;
        $uploadPath = storage_path('app/uploads');

        if (!is_dir($uploadPath)) {
            return 0;
        }

        $files = glob($uploadPath . '/*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffDate->timestamp) {
                $count++;
            }
        }

        return $count;
    }
}
