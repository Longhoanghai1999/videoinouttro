<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\MergeVideoJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoController extends Controller
{
    public function index()
    {
        return view('video.upload');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-m4v,video/*|max:51200',
        ]);

        $filename = Str::random(40) . '.mp4';
        $filePath = 'uploads/' . $filename;

        try {
            // Lưu file bằng Storage facade
            $path = $request->file('video')->storeAs('uploads', $filename, 'local');
            // Kiểm tra file có tồn tại
            $fullPath = storage_path('app/' . $path);
            if (!file_exists($fullPath)) {
                Log::error("Failed to store uploaded file to: {$fullPath}");
                return response()->json(['error' => 'Failed to upload video'], 500);
            }
            Log::info("File uploaded successfully: {$fullPath}");
        } catch (\Exception $e) {
            Log::error("Upload error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to upload video: ' . $e->getMessage()], 500);
        }

        // Dispatch đồng bộ để debug
        MergeVideoJob::dispatchSync($filename);

        return response()->json([
            'message' => 'Video is being processed...',
            'filename' => $filename,
        ]);
    }

    public function download($filename)
    {
        $path = public_path("videos/processed/{$filename}");
        if (!file_exists($path)) {
            Log::error("Download file not found: {$path}");
            abort(404);
        }
        return response()->file($path);
    }
}
