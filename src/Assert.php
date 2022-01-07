<?php

declare(strict_types=1);

class Assert
{
    public static function notNull($mixed, string $message): void
    {
        if (is_null($mixed)) {
            throw new InvalidArgumentException($message);
        }
    }

    public static function notEmpty(string $str, string $message): void
    {
        if ($str === '') {
            throw new InvalidArgumentException($message);
        }
    }

    public static function isTrue(bool $boolean, string $message): void
    {
        if (! $boolean) {
            throw new InvalidArgumentException($message);
        }
    }
}
