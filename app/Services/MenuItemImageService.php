<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Support\UploadLimit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Food photos for menu items.
 *
 * Kept out of the controller so that replacing and deleting an item's picture
 * always cleans up the old file — an orphaned upload is easy to leave behind
 * and impossible to find later.
 */
class MenuItemImageService
{
    private const DIRECTORY = 'menu-items';

    /**
     * The largest photo this server can actually accept.
     *
     * Delegates to {@see UploadLimit}, which every upload in the application
     * shares so no form can promise more than php.ini will take.
     */
    public static function maxUploadKilobytes(): int
    {
        return UploadLimit::kilobytes();
    }

    public static function maxUploadLabel(): string
    {
        return UploadLimit::label();
    }

    /** Store a new photo, discarding whatever the item had before. */
    public function store(MenuItem $item, UploadedFile $file): void
    {
        $this->deleteFile($item->image_path);

        $item->update(['image_path' => $file->store(self::DIRECTORY, 'public')]);
    }

    /** Remove the photo and fall back to a name-only tile in the POS. */
    public function remove(MenuItem $item): void
    {
        $this->deleteFile($item->image_path);

        $item->update(['image_path' => null]);
    }

    private function deleteFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
