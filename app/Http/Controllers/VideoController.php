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


    // public function upload(Request $request)
    // {
    //     if (!$request->hasFile('video')) {
    //         return response()->json(['error' => 'No video file received.'], 400);
    //     }

    //     $file = $request->file('video');

    //     // Debug
    //     return response()->json([
    //         'original_name' => $file->getClientOriginalName(),
    //         'mime' => $file->getMimeType(),
    //         'size_kb' => $file->getSize() / 1024,
    //         'extension' => $file->getClientOriginalExtension(),
    //         'is_valid' => $file->isValid(),
    //     ]);
    // }

    public function upload(Request $request)
    {
        // $request->validate([
        //     'video' => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-m4v,video/*|max:51200',
        // ]);

        $uploadDir = storage_path('app/uploads');

        // Nếu thư mục chưa tồn tại thì tạo
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // ✅ XÓA TOÀN BỘ FILE VIDEO TRƯỚC ĐÓ TRONG THƯ MỤC uploads
        File::cleanDirectory($uploadDir); // Laravel helper to delete all files in a directory

        // Tiếp tục xử lý upload file
        $filename = Str::random(40) . '.mp4';
        $request->file('video')->move($uploadDir, $filename);

        // Gửi job xử lý merge
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
