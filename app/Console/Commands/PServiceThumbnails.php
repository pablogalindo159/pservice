<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Image;

class PServiceThumbnails extends Command
{
    protected $signature = 'pservice:thumbnails {--force : Regera mesmo se já existir}';

    protected $description = 'Gera preview (1600px) e miniatura (480px) a partir dos originais';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $done = 0;

        Photo::query()->orderBy('id')->chunkById(100, function ($photos) use ($disk, &$done) {
            foreach ($photos as $photo) {
                if (! $disk->exists($photo->original_path)) {
                    continue;
                }
                $base = preg_replace('#^photos/#', '', dirname($photo->original_path)).'/'.pathinfo($photo->original_path, PATHINFO_FILENAME).'.jpg';
                $preview = "previews/{$base}";
                $thumb = "thumbs/{$base}";

                if (! $this->option('force') && $photo->preview_path && $disk->exists($photo->preview_path)) {
                    continue;
                }

                $previewBytes = Image::fromPath($disk->path($photo->original_path))
                    ->orient()
                    ->scale(width: config('pservice.upload.preview_width'))
                    ->toJpeg()->quality(82)
                    ->toBytes();
                $disk->put($preview, $previewBytes);
                $size = config('pservice.upload.thumb_width');
                $disk->put($thumb, Image::fromBytes($previewBytes)->cover($size, $size)->toJpeg()->quality(78)->toBytes());

                if ($photo->thumbnail_path && $photo->thumbnail_path !== $thumb) {
                    $disk->delete($photo->thumbnail_path);
                }
                $photo->forceFill(['preview_path' => $preview, 'thumbnail_path' => $thumb])->saveQuietly();
                $done++;
            }
        });

        $this->info("{$done} foto(s) processada(s).");

        return self::SUCCESS;
    }
}
