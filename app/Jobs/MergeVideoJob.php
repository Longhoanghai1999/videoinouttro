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
        Log::info("📄 Starting MergeVideoJob for filename: {$this->filename}, uniqueDir: {$this->uniqueDir}");

        // Define directories
        $uploadDir = storage_path("app/uploads/{$this->uniqueDir}");
        $processedDir = storage_path('app/processed');
        $tmpDir = storage_path('app/tmp');
        $publicDir = public_path('videos/processed');

        foreach ([$uploadDir, $processedDir, $tmpDir, $publicDir] as $dir) {
            if (!file_exists($dir)) {
                Log::info("📁 Creating directory: {$dir}");
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

        // Optimize: Skip re-encoding intro/outro if pre-encoded
        $tempIntro = $intro; // Assume pre-encoded
        $tempOutro = $outro; // Assume pre-encoded
        $tempUser = "{$tmpDir}/temp_user_" . Str::random(10) . '.mp4';

        // Re-encode user video
        $reencodeCmd = "ffmpeg -y -i %s -c:v libx264 -preset veryfast -c:a aac -ar 44100 -r 30 -s 640x360 -pix_fmt yuv420p %s 2>&1";
        Log::info("📄 Re-encoding user video: {$userVideo} to {$tempUser}");
        $cmd = sprintf($reencodeCmd, escapeshellarg($userVideo), escapeshellarg($tempUser));

        // Use proc_open for better error capture
        $descriptors = [
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];
        $process = proc_open($cmd, $descriptors, $pipes);
        $output = '';
        $errorOutput = '';

        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $errorOutput = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        } else {
            Log::error("❌ Failed to start FFmpeg re-encode for {$userVideo}");
            $this->fail(new \Exception("Failed to start FFmpeg re-encode"));
            return;
        }

        if ($exitCode !== 0) {
            Log::error("❌ FFmpeg re-encode failed for {$userVideo}", [
                'exit_code' => $exitCode,
                'output' => $output,
                'error' => $errorOutput,
                'cmd' => $cmd,
            ]);
            $this->fail(new \Exception("Re-encoding failed for user video"));
            return;
        }

        // Create concat list
        $listFile = "{$tmpDir}/merge_list_" . Str::random(10) . '.txt';
        $content = "file '" . addslashes($tempIntro) . "'\n";
        $content .= "file '" . addslashes($tempUser) . "'\n";
        $content .= "file '" . addslashes($tempOutro) . "'\n";
        file_put_contents($listFile, $content);

        Log::info("📄 FFmpeg merge list created: {$listFile}, content:\n{$content}");

        // Concatenate videos
        $concatCmd = "ffmpeg -y -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c:v libx264 -c:a aac -ar 44100 -pix_fmt yuv420p " . escapeshellarg($tempOutput) . " 2>&1";
        Log::info("📄 Running FFmpeg concat: {$concatCmd}");
        $process = proc_open($concatCmd, $descriptors, $pipes);
        $output = '';
        $errorOutput = '';

        if (is_resource($process)) {
            $output = stream_get_contents($pipes[1]);
            $errorOutput = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
        } else {
            Log::error("❌ Failed to start FFmpeg concat");
            $this->fail(new \Exception("Failed to start FFmpeg concat"));
            return;
        }

        // Clean up temporary files
        foreach ([$listFile, $tempUser] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        if ($exitCode !== 0) {
            Log::error("❌ FFmpeg merge failed", [
                'exit_code' => $exitCode,
                'output' => $output,
                'error' => $errorOutput,
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
        Log::info("📄 Attempting to move file from {$tempOutput} to {$finalOutput}");
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
    }
}
