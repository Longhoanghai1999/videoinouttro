<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

        Log::info("Starting MergeVideoJob for filename: {$this->userFilename}");
        $user = storage_path('app/uploads/' . $this->userFilename);
        Log::info("Upload directory contents: " . json_encode(scandir(storage_path('app/uploads'))));
        Log::info("Checking user file: {$user}, exists: " . (file_exists($user) ? 'yes' : 'no'));

        $tempOutput = $processedStorageDir . '/result_' . $this->userFilename;
        $publicOutputDir = public_path('videos/processed');
        $logPath = storage_path('logs/ffmpeg_' . Str::random(8) . '.log');

        foreach (
            [
                'Intro video' => $intro,
                'User video' => $user,
                'Outro video' => $outro,
            ] as $label => $file
        ) {
            if (!file_exists($file)) {

                Log::error("{$label} not found: {$file}");
                return;
            }
        }

        Log::info("Starting video processing for: {$user}");

        $newIntro = $tmpDir . '/new_intro_' . Str::random(10) . '.mp4';
        $newUser = $tmpDir . '/new_user_' . Str::random(10) . '.mp4';
        $newOutro = $tmpDir . '/new_outro_' . Str::random(10) . '.mp4';

        $cmdIntro = "ffmpeg -y -i " . escapeshellarg($intro) . " -vf scale=1280:720,setsar=1 -r 30 -video_track_timescale 90000 -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 " . escapeshellarg($newIntro) . " > {$logPath}_intro 2>&1";
        exec($cmdIntro, $output, $exitCode);
        if ($exitCode !== 0) {
            Log::error("Failed to process intro: " . implode("\n", $output));
            return;
        }

        $cmdUser = "ffmpeg -y -i " . escapeshellarg($user) . " -f lavfi -i anullsrc=cl=stereo:r=44100 -vf scale=1280:720,setsar=1 -r 30 -video_track_timescale 90000 -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 -shortest " . escapeshellarg($newUser) . " > {$logPath}_user 2>&1";
        Log::info("Executing FFmpeg command: {$cmdUser}");
        exec($cmdUser, $output, $exitCode);
        Log::info("FFmpeg output: " . implode("\n", $output));

        if ($exitCode !== 0) {
            Log::error("Failed to process user video. FFmpeg exited with code {$exitCode}. Command: {$cmdUser}. Output:\n" . implode("\n", $output));
            return;
        }

        $cmdOutro = "ffmpeg -y -i " . escapeshellarg($outro) . " -vf scale=1280:720,setsar=1 -r 30 -video_track_timescale 90000 -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 " . escapeshellarg($newOutro) . " > {$logPath}_outro 2>&1";
        exec($cmdOutro, $output, $exitCode);
        if ($exitCode !== 0) {
            Log::error("Failed to process outro: " . implode("\n", $output));
            return;
        }

        $cmd = "ffmpeg -y -i " . escapeshellarg($newIntro) . " -i " . escapeshellarg($newUser) . " -i " . escapeshellarg($newOutro) .
            " -filter_complex \"[0:v][0:a][1:v][1:a][2:v][2:a]concat=n=3:v=1:a=1[outv][outa]\" -map \"[outv]\" -map \"[outa]\" -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 -movflags +faststart " .
            escapeshellarg($tempOutput) . " > " . escapeshellarg($logPath) . " 2>&1";

        exec($cmd, $outputLines, $exitCode);

        if ($exitCode !== 0) {
            Log::error("FFmpeg concat failed: " . implode("\n", $outputLines));
            return;
        }

        if (!file_exists($publicOutputDir)) {
            mkdir($publicOutputDir, 0777, true);
        }

        $finalOutput = $publicOutputDir . '/result_' . $this->userFilename;
        rename($tempOutput, $finalOutput);
        if (file_exists($finalOutput)) {
            @unlink($newIntro);
            @unlink($newUser);
            @unlink($newOutro);
            @unlink($user);
            Log::info("Cleaned up temporary files for: {$this->userFilename}");
        }
        Log::info("Video processed successfully: {$finalOutput}");
    }
}
