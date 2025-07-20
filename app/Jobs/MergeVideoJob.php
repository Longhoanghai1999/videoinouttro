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
        $uploadDir = storage_path('app/uploads');
        $tmpDir = storage_path('app/tmp');

        foreach ([$processedStorageDir, $uploadDir, $tmpDir] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        $intro = public_path('videos/static/intro.mp4');
        $outro = public_path('videos/static/outro.mp4');
        $user = $uploadDir . '/' . $this->userFilename;

        // Validate input files
        foreach (['Intro video' => $intro, 'User video' => $user, 'Outro video' => $outro] as $label => $file) {
            if (!file_exists($file)) {
                Log::error("❌ {$label} not found at path: {$file}");
                return;
            }
        }

        $tempOutput = storage_path("app/processed/result_{$this->userFilename}");
        $listFile = $tmpDir . '/merge_list_' . Str::random(10) . '.txt';

        // Re-encode inputs to ensure compatibility
        $tempIntro = $tmpDir . '/temp_intro_' . Str::random(10) . '.mp4';
        $tempUser = $tmpDir . '/temp_user_' . Str::random(10) . '.mp4';
        $tempOutro = $tmpDir . '/temp_outro_' . Str::random(10) . '.mp4';

        $reencodeCmd = "ffmpeg -y -i %s -c:v libx264 -preset fast -c:a aac -ar 44100 -r 30 -s 1280x720 %s";

        foreach ([$intro => $tempIntro, $user => $tempUser, $outro => $tempOutro] as $input => $output) {
            $cmd = sprintf($reencodeCmd, escapeshellarg($input), escapeshellarg($output));
            exec($cmd, $outputLines, $exitCode);
            if ($exitCode !== 0) {
                Log::error("❌ FFmpeg re-encode failed for {$input}", ['exit_code' => $exitCode, 'output' => $outputLines]);
                return;
            }
        }

        // Create concat list
        $content = "file '" . addslashes($tempIntro) . "'\n";
        $content .= "file '" . addslashes($tempUser) . "'\n";
        $content .= "file '" . addslashes($tempOutro) . "'\n";
        file_put_contents($listFile, $content);

        Log::info("📄 FFmpeg merge list content:\n" . $content);

        // Concatenate videos
        $cmd = "ffmpeg -y -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c:v libx264 -c:a aac " . escapeshellarg($tempOutput);
        exec($cmd, $outputLines, $exitCode);

        // Clean up temporary files
        foreach ([$listFile, $tempIntro, $tempUser, $tempOutro] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if ($exitCode !== 0) {
            Log::error("❌ FFmpeg merge failed", ['exit_code' => $exitCode, 'output' => $outputLines, 'cmd' => $cmd]);
            return;
        }

        // Move result to public directory
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
