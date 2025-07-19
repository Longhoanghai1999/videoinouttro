<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Str;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

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
        $intro = storage_path('app/templates/intro.mp4');
        $user  = storage_path("app/uploads/{$this->userFilename}");
        $outro = storage_path('app/templates/outtro.mp4');

        $listFile = storage_path("app/tmp/merge_list_" . Str::random(10) . ".txt");
        $output   = storage_path("app/processed/result_{$this->userFilename}");

        if (!file_exists(dirname($listFile))) {
            mkdir(dirname($listFile), 0777, true);
        }

        file_put_contents($listFile, "file '{$intro}'\nfile '{$user}'\nfile '{$outro}'");

        $cmd = "ffmpeg -f concat -safe 0 -i {$listFile} -c copy {$output}";

        exec($cmd, $outputLines, $exitCode);
        unlink($listFile);

        if ($exitCode !== 0) {
            \Log::error("Merge failed for {$this->userFilename}", $outputLines);
        }
    }
}
