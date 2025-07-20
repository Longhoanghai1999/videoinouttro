<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\MergeVideoJob;
use Illuminate\Support\Facades\Log;

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

        $uploadDir = storage_path('app/uploads');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = Str::random(40) . '.mp4';
        try {
            $request->file('video')->move($uploadDir, $filename);
            // Kiểm tra file có tồn tại sau khi di chuyển
            if (!file_exists($uploadDir . '/' . $filename)) {
                Log::error("Failed to move uploaded file to: {$uploadDir}/{$filename}");
                return response()->json(['error' => 'Failed to upload video'], 500);
            }
        } catch (\Exception $e) {
            Log::error("Upload error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to upload video'], 500);
        }

        MergeVideoJob::dispatch($filename);

        return response()->json([
            'message' => 'Video is being processed...',
            'filename' => $filename,
        ]);
    }

    public function download($filename)
    {
        $path = storage_path("app/processed/{$filename}");
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path);
    }
}
