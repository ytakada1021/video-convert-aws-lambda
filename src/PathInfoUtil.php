<?php

class PathInfoUtil
{
    public static function filenameOf(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    public static function extensionOf(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }
}
