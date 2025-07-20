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
        $filePath = $uploadDir . '/' . $filename;

        try {
            // Sử dụng storeAs thay vì move để đảm bảo lưu file
            $request->file('video')->storeAs('uploads', $filename, 'local');
            // Kiểm tra file có tồn tại sau khi lưu
            if (!file_exists($filePath)) {
                Log::error("Failed to store uploaded file to: {$filePath}");
                return response()->json(['error' => 'Failed to upload video'], 500);
            }
        } catch (\Exception $e) {
            Log::error("Upload error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to upload video: ' . $e->getMessage()], 500);
        }

        // Dispatch job đồng bộ để debug (có thể đổi lại dispatch bất đồng bộ sau khi ổn định)
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
            abort(404);
        }
        return response()->file($path);
    }
}
