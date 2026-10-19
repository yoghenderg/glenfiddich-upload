<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Support\MediaThumbnail;
use Illuminate\Console\Command;

class GenerateMediaThumbnails extends Command
{
    protected $signature = 'media:thumbnails {--memory=512M : Memory ceiling for serial image decoding}';
    protected $description = 'Generate missing gallery previews without modifying originals';

    public function handle(): int
    {
        $memory = (string) $this->option('memory');
        if (! preg_match('/^[1-9][0-9]*[KMG]?$/i', $memory)) {
            $this->error('Use a bounded memory ceiling such as 512M.');
            return self::FAILURE;
        }
        ini_set('memory_limit', $memory);
        $generated = 0;
        $skipped = 0;
        Media::where('type', 'image')->chunkById(50, function ($items) use (&$generated, &$skipped): void {
            foreach ($items as $media) {
                try {
                    MediaThumbnail::generate($media) ? $generated++ : $skipped++;
                } catch (\Throwable $error) {
                    report($error);
                    $skipped++;
                }
            }
        });
        $this->info("Previews ready: {$generated}; unavailable: {$skipped}.");
        return self::SUCCESS;
    }
}
