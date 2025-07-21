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
                mkdir($dir, 0775, true);
                chmod($dir, 0775);
            }
        }


        $intro = public_path('videos/static/intro.mp4');
        $outro = public_path('videos/static/outro.mp4');

        $user = storage_path('app/uploads/' . $this->userFilename);

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
                return;
            }
        }

        $newIntro = $tmpDir . '/new_intro_' . Str::random(10) . '.mp4';
        $newUser = $tmpDir . '/new_user_' . Str::random(10) . '.mp4';
        $newOutro = $tmpDir . '/new_outro_' . Str::random(10) . '.mp4';

        $cmdIntro = "ffmpeg -y -i " . escapeshellarg($intro) . " -vf scale=720:1280,setsar=1 -r 30 -video_track_timescale 90000 -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 " . escapeshellarg($newIntro) . " > {$logPath}_intro 2>&1";
        exec($cmdIntro, $output, $exitCode);
        if ($exitCode !== 0) {
            return;
        }

        $cmdUser = "ffmpeg -y -i " . escapeshellarg($user) . " -f lavfi -i anullsrc=cl=stereo:r=44100 -vf scale=720:1280,setsar=1 -r 30 -video_track_timescale 90000 -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 -shortest " . escapeshellarg($newUser) . " > {$logPath}_user 2>&1";

        exec($cmdUser, $output, $exitCode);
        if ($exitCode !== 0) {
            return;
        }

        $cmdOutro = "ffmpeg -y -i " . escapeshellarg($outro) . " -vf scale=720:1280,setsar=1 -r 30 -video_track_timescale 90000 -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 " . escapeshellarg($newOutro) . " > {$logPath}_outro 2>&1";
        exec($cmdOutro, $output, $exitCode);
        if ($exitCode !== 0) {
            return;
        }

        // $cmd = "ffmpeg -y -i " . escapeshellarg($newIntro) . " -i " . escapeshellarg($newUser) . " -i " . escapeshellarg($newOutro) .
        //     " -filter_complex \"[0:v][0:a][1:v][1:a][2:v][2:a]concat=n=3:v=1:a=1[outv][outa]\" -map \"[outv]\" -map \"[outa]\" -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 -movflags +faststart " .
        //     escapeshellarg($tempOutput) . " > " . escapeshellarg($logPath) . " 2>&1";

        $cmd = "ffmpeg -y -i " . escapeshellarg($newUser) . " -i " . escapeshellarg($newIntro) .
            " -filter_complex \"[0:v][0:a][1:v][1:a]concat=n=2:v=1:a=1[outv][outa]\" -map \"[outv]\" -map \"[outa]\" -c:v libx264 -preset fast -crf 23 -c:a aac -ar 44100 -ac 2 -movflags +faststart " .
            escapeshellarg($tempOutput) . " > " . escapeshellarg($logPath) . " 2>&1";

        exec($cmd, $outputLines, $exitCode);

        if ($exitCode !== 0) {
            return;
        }

        if (!file_exists($publicOutputDir)) {
            mkdir($publicOutputDir, 0777, true);
        }

        // $finalOutput = $publicOutputDir . '/result_' . $this->userFilename;
        $baseFilename = pathinfo($this->userFilename, PATHINFO_FILENAME);
        $finalOutput = $publicOutputDir . '/' . $baseFilename . '_SV.mp4';
        rename($tempOutput, $finalOutput);
        if (file_exists($finalOutput)) {
            @unlink($newIntro);
            @unlink($newUser);
            @unlink($newOutro);
            @unlink($user);
        }
    }
}
