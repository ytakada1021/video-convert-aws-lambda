<?php

class PathInfoUtil
{
    public static function dirnameOf(string $path): string
    {
        return pathinfo($path, PATHINFO_DIRNAME);
    }

    public static function filenameOf(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function extensionOf(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function pathWithoutExtensionOf(string $path): string
    {
        return sprintf('%s/%s', self::dirnameOf($path), self::filenameOf($path));
    }
}
