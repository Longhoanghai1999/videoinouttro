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
        try {
            $request->validate([
                'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-m4v,video/*|max:51200',
            ]);

            $filename = Str::random(40) . '.mp4';
            $filePath = 'uploads/' . $filename;

            $file = $request->file('video');

            $path = $file->storeAs('uploads', $filename, 'local');
            $fullPath = storage_path('app/' . $path);
            chmod($fullPath, 0664);
            if (!file_exists($fullPath)) {
                return response()->json(['error' => 'Failed to store uploaded file'], 500);
            }

            // Validate MP4 file
            $output = [];
            $exitCode = 0;
            exec("ffprobe -i " . escapeshellarg($fullPath) . " 2>&1", $output, $exitCode);
            if ($exitCode !== 0) {
                return response()->json(['error' => 'Invalid video file'], 422);
            }
            MergeVideoJob::dispatch($filename);

            return response()->json([
                'message' => 'Video is being processed...',
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to upload video: ' . $e->getMessage()], 500);
        }
    }
    public function download($filename)
    {
        //$path = public_path("videos/processed/{$filename}");
        $baseFilename = pathinfo($filename, PATHINFO_FILENAME);
        $path = public_path("videos/processed/{$baseFilename}_SV.mp4");


        if (!file_exists($path)) {
            Log::error("Download file not found: {$path}");
            abort(404);
        }

        return response()->file($path, [], function () use ($path) {
            try {
                unlink($path);
                Log::info("File deleted after download: {$path}");
            } catch (\Exception $e) {
                Log::error("Failed to delete file after download: {$path}, Error: " . $e->getMessage());
            }
        });

        // return response()->file($path);
    }
}
