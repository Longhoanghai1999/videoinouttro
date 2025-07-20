<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\MergeVideoJob;
use Illuminate\Support\Facades\File;
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
        File::cleanDirectory($uploadDir);
        $filename = Str::random(40) . '.mp4';
        $request->file('video')->move($uploadDir, $filename);
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
