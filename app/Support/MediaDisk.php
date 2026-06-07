<?php

namespace App\Support;

final class MediaDisk
{
    public static function portfolio(): string
    {
        return self::configured('filesystems.media.portfolio_disk', 'public');
    }

    public static function private(): string
    {
        return self::configured('filesystems.media.private_disk', 'local');
    }

    private static function configured(string $key, string $fallback): string
    {
        $disk = config($key);

        if (is_string($disk) && $disk !== '') {
            return $disk;
        }

        return $fallback;
    }
}
