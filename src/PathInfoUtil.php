<?php

class PathInfoUtil
{
    public static function filenameOf(string $path): string
    {
        return pathinfo($path)['filename'];
    }
}
