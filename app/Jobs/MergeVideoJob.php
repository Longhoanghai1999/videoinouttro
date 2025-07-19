<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class MergeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userFilename;

    public function __construct($userFilename)
    {
        $this->userFilename = $userFilename;
    }

    public function handle()
    {
        $processedStorageDir = storage_path('app/processed');
        if (!file_exists($processedStorageDir)) {
            mkdir($processedStorageDir, 0777, true);
        }

        $uploadDir = storage_path('app/uploads');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $intro = public_path('videos/static/intro.mp4');
        $outro = public_path('videos/static/outro.mp4');
        $user  = $uploadDir . '/' . $this->userFilename;

        // Kiểm tra sự tồn tại của các file
        foreach (
            [
                'Intro video' => $intro,
                'User video' => $user,
                'Outro video' => $outro,
            ] as $label => $file
        ) {
            if (!file_exists($file)) {
                Log::error("❌ {$label} not found at path: {$file}");
                return;
            }
        }

        $tempOutput = storage_path("app/processed/result_{$this->userFilename}");
        $tmpDir = storage_path('app/tmp');
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $listFile = $tmpDir . '/merge_list_' . Str::random(10) . '.txt';

        // Tạo file danh sách để concat
        $content = "file '" . addslashes($intro) . "'\n";
        $content .= "file '" . addslashes($user) . "'\n";
        $content .= "file '" . addslashes($outro) . "'\n";
        file_put_contents($listFile, $content);

        Log::info("📄 FFmpeg merge list content:\n" . $content);

        // Gọi ffmpeg
        $cmd = "ffmpeg -y -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($tempOutput);
        exec($cmd, $outputLines, $exitCode);

        // Xóa file tạm danh sách
        unlink($listFile);

        if ($exitCode !== 0) {
            Log::error("❌ FFmpeg merge failed", [
                'exit_code' => $exitCode,
                'output' => $outputLines,
                'cmd' => $cmd,
            ]);
            return;
        }

        // Di chuyển kết quả sang public
        $publicOutputDir = public_path('videos/processed');
        if (!file_exists($publicOutputDir)) {
            mkdir($publicOutputDir, 0777, true);
        }

        $finalOutput = $publicOutputDir . '/result_' . $this->userFilename;

        if (!rename($tempOutput, $finalOutput)) {
            Log::error("❌ Failed to move merged video to public folder: {$finalOutput}");
        } else {
            Log::info("✅ Merge success and moved to public: {$finalOutput}");
        }
    }
}
