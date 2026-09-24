<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Photo;
use App\Models\ServiceOrder;
use App\Support\Stages;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Intervention\Image\Laravel\Facades\Image;

class PhotoController extends Controller
{
    public function store(Request $request, ServiceOrder $os)
    {
        abort_unless($request->user()?->canTakePhotos(), 403);

        $data = $request->validate([
            'stage' => ['required', Rule::in(Stages::all())],
            'photos' => 'required|array|min:1|max:30',
            'photos.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:'.config('pservice.upload.max_kb'),
        ], [
            'photos.required' => 'Nenhuma foto recebida. Se enviou muitas fotos de uma vez, o limite do servidor pode ter sido excedido.',
        ]);

        $saved = [];
        foreach ($request->file('photos', []) as $file) {
            $saved[] = $this->storeOne($request, $os, $data['stage'], $file);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'photos' => collect($saved)->map(fn (Photo $p) => [
                    'id' => $p->id,
                    'thumb' => route('photos.file', [$p, 'size' => 'thumb']),
                    'preview' => route('photos.file', [$p, 'size' => 'preview']),
                ]),
            ]);
        }

        return back()->with('ok', count($saved).' foto(s) salva(s).');
    }

    private function storeOne(Request $request, ServiceOrder $os, string $stage, UploadedFile $file): Photo
    {
        $disk = Storage::disk('local');
        $now = now();
        $slug = Stages::slug($stage);

        // Extensão pelo conteúdo real do arquivo, não pelo nome enviado.
        $ext = $file->extension() ?: 'jpg';
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;

        $seq = $os->photos()->withTrashed()->where('stage', $stage)->count() + 1;
        do {
            $filename = sprintf('OS%s_%s_%s_%02d.%s', $os->number, $slug, $now->format('Y-m-d_H-i-s'), $seq, $ext);
            $original = "photos/{$os->number}/{$slug}/{$filename}";
            $seq++;
        } while ($disk->exists($original));

        $base = pathinfo($filename, PATHINFO_FILENAME);
        $preview = "previews/{$os->number}/{$slug}/{$base}.jpg";
        $thumb = "thumbs/{$os->number}/{$slug}/{$base}.jpg";

        $disk->putFileAs(dirname($original), $file, basename($original));

        // Uma leitura só: preview (1600px) e depois miniatura quadrada (480px).
        $image = Image::read($file->getRealPath());
        $image->scaleDown(width: config('pservice.upload.preview_width'));
        $disk->put($preview, (string) $image->toJpeg(82));
        $size = config('pservice.upload.thumb_width');
        $image->cover($size, $size);
        $disk->put($thumb, (string) $image->toJpeg(78));

        return DB::transaction(function () use ($request, $os, $stage, $original, $preview, $thumb, $file, $now, $filename) {
            $photo = Photo::create([
                'service_order_id' => $os->id,
                'user_id' => $request->user()->id,
                'stage' => $stage,
                'original_path' => $original,
                'preview_path' => $preview,
                'thumbnail_path' => $thumb,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'captured_at' => $now,
            ]);

            if ($os->status === 'aberta') {
                $os->update(['status' => 'em_andamento']);
            }

            AuditLog::record('photo.added', $os->id, $photo->id, ['stage' => $stage, 'filename' => $filename]);

            return $photo;
        });
    }

    public function destroy(Request $request, Photo $photo)
    {
        abort_unless($request->user()?->canDeletePhotos(), 403);

        AuditLog::record('photo.deleted', $photo->service_order_id, $photo->id, [
            'stage' => $photo->stage,
            'filename' => basename($photo->original_path),
            'author_id' => $photo->user_id,
        ]);

        // O original é mantido em disco (soft delete) para permitir recuperação
        // e continuar nos backups. Apenas os derivados são removidos.
        Storage::disk('local')->delete(array_filter([$photo->thumbnail_path, $photo->preview_path]));
        $photo->delete();

        return back()->with('ok', 'Foto excluída e registrada no histórico.');
    }

    public function file(Request $request, Photo $photo)
    {
        $size = $request->string('size')->toString() ?: ($request->boolean('thumb') ? 'thumb' : 'original');

        $path = match ($size) {
            'thumb' => $photo->thumbnail_path,
            'preview' => $photo->preview_path ?: $photo->original_path,
            default => $photo->original_path,
        };

        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        return response()->file($disk->path($path), ['Cache-Control' => 'private, max-age=604800']);
    }

    public function downloadStage(Request $request, ServiceOrder $os)
    {
        abort_unless($request->user()?->canDownload(), 403);
        $stage = $request->string('stage')->toString();
        abort_unless(in_array($stage, Stages::all(), true), 422);

        return $this->makeZip($os, $os->photos()->where('stage', $stage)->orderBy('captured_at')->get(), $stage);
    }

    public function downloadAll(Request $request, ServiceOrder $os)
    {
        abort_unless($request->user()?->canDownload(), 403);

        return $this->makeZip($os, $os->photos()->orderBy('captured_at')->get(), 'todas');
    }

    private function makeZip(ServiceOrder $os, Collection $photos, string $label)
    {
        if ($photos->isEmpty()) {
            return back()->with('ok', 'Nenhuma foto para baixar nesta seleção.');
        }

        @set_time_limit(600);
        $disk = Storage::disk('local');
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $zipPath = $dir.'/os_'.$os->id.'_'.Str::uuid().'.zip';
        $zip = new \ZipArchive;
        abort_unless($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500);

        foreach ($photos as $photo) {
            if ($disk->exists($photo->original_path)) {
                $entry = 'OS'.$os->number.'/'.Stages::slug($photo->stage).'/'.basename($photo->original_path);
                $zip->addFile($disk->path($photo->original_path), $entry);
                $zip->setCompressionName($entry, \ZipArchive::CM_STORE); // JPG já é comprimido
            }
        }
        $zip->close();

        AuditLog::record('photo.download', $os->id, null, ['scope' => $label, 'count' => $photos->count()]);

        return response()->download($zipPath, 'OS'.$os->number.'_'.Str::slug($label).'.zip')->deleteFileAfterSend(true);
    }
}
