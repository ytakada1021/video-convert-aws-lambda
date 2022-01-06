<?php

declare(strict_types=1);

class Assert {
    public static function isNotNull($mixed): void
    {
        assert(!is_null($mixed));
    }

    public static function isNotEmpty(?string $str): void
    {
        assert(!is_null($str));
        assert($str !== '');
    }

    public static function isTrue(bool $boolean): void
    {
        assert($boolean);
    }
}
