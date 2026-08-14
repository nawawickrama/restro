<?php

namespace App\Services;

use App\Models\MenuItem;
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

    /** What we would allow if PHP itself were not the tighter constraint. */
    private const PREFERRED_MAX_KB = 4096;

    /** Room inside post_max_size for the rest of the form fields. */
    private const FORM_OVERHEAD_KB = 128;

    /**
     * The largest photo this server can actually accept, in kilobytes.
     *
     * PHP rejects an oversized body before Laravel sees the request, so a
     * validation rule looser than php.ini produces a raw 413 instead of a
     * form error. Deriving the rule from the real limits keeps the two from
     * ever disagreeing — raise `upload_max_filesize` and `post_max_size` in
     * php.ini and the form follows automatically.
     */
    public static function maxUploadKilobytes(): int
    {
        $limits = array_filter([
            self::iniBytes('upload_max_filesize'),
            self::iniBytes('post_max_size'),
        ]);

        if ($limits === []) {
            return self::PREFERRED_MAX_KB;
        }

        $ceiling = (int) floor(min($limits) / 1024) - self::FORM_OVERHEAD_KB;

        return max(256, min(self::PREFERRED_MAX_KB, $ceiling));
    }

    /** "1.9MB" — the limit as a person would say it. */
    public static function maxUploadLabel(): string
    {
        $kilobytes = self::maxUploadKilobytes();

        return $kilobytes >= 1024
            ? rtrim(rtrim(number_format($kilobytes / 1024, 1), '0'), '.').'MB'
            : $kilobytes.'KB';
    }

    /** Parse a php.ini shorthand size ("2M", "512K") into bytes. */
    private static function iniBytes(string $directive): ?int
    {
        $value = trim((string) ini_get($directive));

        // 0 and -1 both mean "no limit" for these directives.
        if ($value === '' || (int) $value <= 0) {
            return null;
        }

        $bytes = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $bytes * 1024 ** 3,
            'm' => $bytes * 1024 ** 2,
            'k' => $bytes * 1024,
            default => $bytes,
        };
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
