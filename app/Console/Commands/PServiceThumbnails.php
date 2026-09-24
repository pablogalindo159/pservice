<?php

namespace App\Console\Commands;

use App\Models\Photo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

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

                $image = Image::read($disk->path($photo->original_path));
                $image->scaleDown(width: config('pservice.upload.preview_width'));
                $disk->put($preview, (string) $image->toJpeg(82));
                $size = config('pservice.upload.thumb_width');
                $image->cover($size, $size);
                $disk->put($thumb, (string) $image->toJpeg(78));

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
