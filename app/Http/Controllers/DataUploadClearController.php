<?php

namespace App\Http\Controllers;

use App\Models\DataUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class DataUploadClearController extends Controller
{
    /**
     * Display the Handover Data Uploads management page.
     */
    public function index()
    {
        $cutoffDate = now()->subMonth();

        $oldRecordCount = DataUpload::where('created_at', '<', $cutoffDate)->count();
        $totalRecordCount = DataUpload::count();
        $oldFileCount = $this->countOldUploadFiles($cutoffDate);

        return view('handover.data_uploads', [
            'oldRecordCount' => $oldRecordCount,
            'totalRecordCount' => $totalRecordCount,
            'oldFileCount' => $oldFileCount,
            'cutoffDate' => $cutoffDate,
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
     * Get summary of old data uploads (for dashboard display).
     */
    public function summary()
    {
        $cutoffDate = now()->subMonth();

        $oldRecordCount = DataUpload::where('created_at', '<', $cutoffDate)->count();
        $totalRecordCount = DataUpload::count();
        $oldFileCount = $this->countOldUploadFiles($cutoffDate);

        return response()->json([
            'old_records' => $oldRecordCount,
            'total_records' => $totalRecordCount,
            'old_files' => $oldFileCount,
            'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
        ]);
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
