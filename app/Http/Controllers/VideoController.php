<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Jobs\MergeVideoJob;


class VideoController extends Controller
{
    public function index()
    {
        return view('video.upload');
    }


    public function upload(Request $request)
    {
        $request->validate([
            'video' => 'required|file|mimetypes:video/mp4|max:102400', // max 100MB
        ]);

        $path = $request->file('video')->store('uploads');
        $filename = basename($path);

        MergeVideoJob::dispatch($filename);

        return response()->json([
            'message' => 'Video is being processed...',
            'filename' => $filename
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
