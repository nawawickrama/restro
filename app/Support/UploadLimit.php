<?php

namespace App\Support;

/**
 * The largest file this server will actually accept.
 *
 * PHP discards an oversized request body before Laravel ever runs, so a
 * validation rule looser than php.ini hands the user a raw 413 instead of a
 * form error. Every upload in the application sizes its rule from here, so the
 * two can never disagree: raise `upload_max_filesize` and `post_max_size` and
 * the forms follow on their own.
 */
final class UploadLimit
{
    /** What we would allow if PHP itself were not the tighter constraint. */
    private const PREFERRED_MAX_KB = 8192;

    /** Room inside post_max_size for the rest of the form fields. */
    private const FORM_OVERHEAD_KB = 128;

    public static function kilobytes(): int
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
    public static function label(): string
    {
        $kilobytes = self::kilobytes();

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
}
