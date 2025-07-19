<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

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
        // Các file intro/outro đặt trong public/videos/static/
        $intro = public_path('videos/static/intro.mp4');
        $outro = public_path('videos/static/outro.mp4'); // sửa đường dẫn bị dư dấu /
        $user  = storage_path("app/uploads/{$this->userFilename}");

        $output = storage_path("app/processed/result_{$this->userFilename}");
        $tmpDir = storage_path('app/tmp');

        if (!file_exists($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $listFile = $tmpDir . '/merge_list_' . Str::random(10) . '.txt';

        // Đảm bảo escape dấu cách bằng cách bao đường dẫn trong dấu nháy đơn
        $content = "file '" . addslashes($intro) . "'\n";
        $content .= "file '" . addslashes($user) . "'\n";
        $content .= "file '" . addslashes($outro) . "'\n";

        file_put_contents($listFile, $content);

        // Gọi ffmpeg
        $cmd = "ffmpeg -y -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($output);
        exec($cmd, $outputLines, $exitCode);

        unlink($listFile); // Xoá file danh sách sau khi xử lý

        if ($exitCode !== 0) {
            Log::error("❌ Merge failed for {$this->userFilename}", [
                'exit_code' => $exitCode,
                'output' => $outputLines,
                'cmd' => $cmd,
            ]);
        } else {
            Log::info("✅ Merge success: {$output}");
        }
    }
}
