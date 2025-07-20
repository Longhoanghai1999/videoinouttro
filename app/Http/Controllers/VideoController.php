<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\MergeVideoJob;
use Illuminate\Support\Facades\File;

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

        // Validate video file with FFmpeg
        $file = $request->file('video');
        $tempPath = $file->getPathname();
        $cmd = "ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 " . escapeshellarg($tempPath);
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || empty($output)) {
            return response()->json(['errors' => ['video' => ['Invalid or corrupted video file.']]], 422);
        }

        // Clean upload directory (consider user-specific directories to avoid race conditions)
        File::cleanDirectory($uploadDir);

        $filename = Str::random(40) . '.mp4';
        $file->move($uploadDir, $filename);

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
