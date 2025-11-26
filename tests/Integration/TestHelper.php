<?php
namespace OpenTimestamps\Tests\Integration;

class TestHelper
{
    public static function createTempFile(string $content, string $prefix = 'ots'): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), $prefix);
        file_put_contents($tmpFile, $content);
        return $tmpFile;
    }

    public static function deleteFiles(array $files): void
    {
        foreach ($files as $f) {
            if (file_exists($f)) unlink($f);
        }
    }
}
