<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class MergeVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filename;
    protected $uniqueDir;

    public function __construct($filename, $uniqueDir)
    {
        $this->filename = $filename;
        $this->uniqueDir = $uniqueDir;
    }

    public function handle()
    {
        // Define directories
        $uploadDir = storage_path("app/uploads/{$this->uniqueDir}");
        $processedDir = storage_path('app/processed');
        $tmpDir = storage_path('app/tmp');
        $publicDir = public_path('videos/processed');

        foreach ([$uploadDir, $processedDir, $tmpDir, $publicDir] as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // Define input and output paths
        $intro = public_path('videos/static/intro.mp4');
        $outro = public_path('videos/static/outro.mp4');
        $userVideo = "{$uploadDir}/{$this->filename}";
        $tempOutput = "{$processedDir}/result_{$this->filename}";
        $finalOutput = "{$publicDir}/result_{$this->filename}";

        // Validate input files
        foreach (
            [
                'Intro video' => $intro,
                'User video' => $userVideo,
                'Outro video' => $outro,
            ] as $label => $file
        ) {
            if (!file_exists($file)) {
                Log::error("❌ {$label} not found at path: {$file}");
                $this->fail(new \Exception("Missing required video file: {$label}"));
                return;
            }
        }

        // Re-encode inputs to ensure compatibility
        $tempIntro = "{$tmpDir}/temp_intro_" . Str::random(10) . '.mp4';
        $tempUser = "{$tmpDir}/temp_user_" . Str::random(10) . '.mp4';
        $tempOutro = "{$tmpDir}/temp_outro_" . Str::random(10) . '.mp4';

        $reencodeCmd = "ffmpeg -y -i %s -c:v libx264 -preset veryfast -c:a aac -ar 44100 -r 30 -s 854x480 -pix_fmt yuv420p %s";
        foreach (
            [
                $intro => $tempIntro,
                $userVideo => $tempUser,
                $outro => $tempOutro,
            ] as $input => $output
        ) {
            Log::info("📄 Re-encoding: {$input} to {$output}");
            $cmd = sprintf($reencodeCmd, escapeshellarg($input), escapeshellarg($output));
            exec($cmd, $outputLines, $exitCode);
            if ($exitCode !== 0) {
                Log::error("❌ FFmpeg re-encode failed for {$input}", [
                    'exit_code' => $exitCode,
                    'output' => $outputLines,
                    'cmd' => $cmd,
                ]);
                $this->fail(new \Exception("Re-encoding failed for {$input}"));
                return;
            }
        }

        // Create concat list
        $listFile = "{$tmpDir}/merge_list_" . Str::random(10) . '.txt';
        $content = "file '" . addslashes($tempIntro) . "'\n";
        $content .= "file '" . addslashes($tempUser) . "'\n";
        $content .= "file '" . addslashes($tempOutro) . "'\n";
        file_put_contents($listFile, $content);

        Log::info("📄 FFmpeg merge list created: {$listFile}, content:\n{$content}");

        // Concatenate videos
        $concatCmd = "ffmpeg -y -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c:v libx264 -c:a aac -ar 44100 -pix_fmt yuv420p " . escapeshellarg($tempOutput);
        exec($concatCmd, $outputLines, $exitCode);

        // Clean up temporary files
        foreach ([$listFile, $tempIntro, $tempUser, $tempOutro] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if ($exitCode !== 0) {
            Log::error("❌ FFmpeg merge failed", [
                'exit_code' => $exitCode,
                'output' => $outputLines,
                'cmd' => $concatCmd,
            ]);
            $this->fail(new \Exception("FFmpeg merge failed"));
            return;
        }

        // Verify temp output exists
        if (!file_exists($tempOutput)) {
            Log::error("❌ Temp output file not created: {$tempOutput}");
            $this->fail(new \Exception("Temp output file not created"));
            return;
        }

        // Move result to public directory
        if (!rename($tempOutput, $finalOutput)) {
            Log::error("❌ Failed to move file from {$tempOutput} to {$finalOutput}", [
                'error' => error_get_last(),
            ]);
            $this->fail(new \Exception("Failed to move merged video"));
            return;
        }

        Log::info("✅ Video merged and moved to public: {$finalOutput}");

        // Clean up user upload directory
        if (file_exists($uploadDir)) {
            File::deleteDirectory($uploadDir);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error("❌ MergeVideoJob failed for {$this->filename}", [
            'exception' => $exception->getMessage(),
        ]);
        // Clean up user upload directory on failure
        $uploadDir = storage_path("app/uploads/{$this->uniqueDir}");
        if (file_exists($uploadDir)) {
            File::deleteDirectory($uploadDir);
        }
        // Optionally notify user via frontend polling or other mechanism
    }
}
