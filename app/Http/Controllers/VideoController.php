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
        Log::info("Upload request received", [
            'method' => $request->method(),
            'url' => $request->url(),
            'has_file' => $request->hasFile('video'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        try {
            Log::info("Starting validation");
            $request->validate([
                'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-m4v,video/*|max:51200',
            ]);
            Log::info("Validation passed");

            $filename = Str::random(40) . '.mp4';
            $filePath = 'uploads/' . $filename;

            $file = $request->file('video');
            Log::info("Before storage", [
                'tmp_name' => $file->getPathname(),
                'tmp_exists' => file_exists($file->getPathname()) ? 'yes' : 'no',
            ]);
            Log::info("File details", [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
                'tmp_name' => $file->getPathname(),
                'tmp_exists' => file_exists($file->getPathname()) ? 'yes' : 'no',
            ]);

            Log::info("Attempting to store file at: storage/app/{$filePath}");
            $path = $file->storeAs('uploads', $filename, 'local');
            $fullPath = storage_path('app/' . $path);
            Log::info("File uploaded successfully: {$fullPath}");
            Log::info("File permissions: " . substr(sprintf('%o', fileperms($fullPath)), -4));
            Log::info("Storage attempt completed", ['path' => $fullPath]);
            chmod($fullPath, 0664);
            if (!file_exists($fullPath)) {
                Log::error("Failed to store uploaded file to: {$fullPath}");
                return response()->json(['error' => 'Failed to store uploaded file'], 500);
            }
            Log::info("File uploaded successfully: {$fullPath}");

            // Validate MP4 file
            $output = [];
            $exitCode = 0;
            exec("ffprobe -i " . escapeshellarg($fullPath) . " 2>&1", $output, $exitCode);
            if ($exitCode !== 0) {
                Log::error("Invalid video file: " . implode("\n", $output));
                return response()->json(['error' => 'Invalid video file'], 422);
            }

            Log::info("Dispatching MergeVideoJob");
            MergeVideoJob::dispatch($filename);

            return response()->json([
                'message' => 'Video is being processed...',
                'filename' => $filename,
            ]);
        } catch (\Exception $e) {
            Log::error("Upload error: " . $e->getMessage() . "\nStack trace: " . $e->getTraceAsString());
            return response()->json(['error' => 'Failed to upload video: ' . $e->getMessage()], 500);
        }
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
