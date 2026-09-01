<?php
declare(strict_types=1);

namespace App\Support;

class Flash
{
    private const KEY = '_flash';

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('error', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    private static function add(string $type, string $message): void
    {
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type:string, message:string}> */
    public static function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return $messages;
    }
}
