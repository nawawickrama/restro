<?php

namespace App\Services;

use App\Models\CateringCatalogue;
use App\Models\CateringCatalogueImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The artwork pages of a catering catalogue.
 *
 * Kept out of the controller so that a page removed from the brochure takes
 * its file with it — an orphaned upload is easy to leave behind and
 * impossible to find later.
 */
class CateringCatalogueImageService
{
    private const DIRECTORY = 'catering-catalogue';

    /**
     * Add artworks to the end of a category.
     *
     * Each upload is a package in its own right, so it is named from the file
     * it arrived as — a designer sending "Package 2.jpg" gets "Package 2"
     * without anybody retyping it. The name is editable afterwards.
     *
     * @param  list<UploadedFile>  $files
     */
    public function add(CateringCatalogue $catalogue, array $files): int
    {
        $position = (int) $catalogue->images()->max('sort_order');
        $added = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $catalogue->images()->create([
                'path' => $file->store(self::DIRECTORY, 'public'),
                'title' => $this->titleFrom($file),
                'sort_order' => ++$position,
            ]);

            $added++;
        }

        return $added;
    }

    /** "Package 2.jpg" becomes "Package 2"; a camera filename becomes nothing. */
    private function titleFrom(UploadedFile $file): ?string
    {
        $name = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME);
        $name = trim(preg_replace('/[_-]+/', ' ', $name));

        // Names like IMG_2381 or DSC00194 say nothing; leave those blank so the
        // page falls back to its position.
        if ($name === '' || preg_match('/^(img|dsc|photo|image|screenshot)\b/i', $name)) {
            return null;
        }

        return Str::limit(Str::title($name), 120, '');
    }

    /** Remove one artwork, and the file behind it. */
    public function remove(CateringCatalogueImage $image): void
    {
        $this->deleteFile($image->path);

        $image->delete();
    }

    /** Remove every artwork, used when the whole category goes. */
    public function removeAll(CateringCatalogue $catalogue): void
    {
        $catalogue->images->each(fn (CateringCatalogueImage $image) => $this->deleteFile($image->path));

        $catalogue->images()->delete();
    }

    private function deleteFile(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
