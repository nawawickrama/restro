<?php

namespace App\Services;

use App\Models\CateringCatalogue;
use App\Models\CateringCatalogueImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
     * Add pages to the end of a catalogue.
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
                'sort_order' => ++$position,
            ]);

            $added++;
        }

        return $added;
    }

    /** Remove one page, and the file behind it. */
    public function remove(CateringCatalogueImage $image): void
    {
        $this->deleteFile($image->path);

        $image->delete();
    }

    /** Remove every page, used when the whole category goes. */
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
