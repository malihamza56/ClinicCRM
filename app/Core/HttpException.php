<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown to stop a request with an HTTP error (403, 404, 419 ...). The error handler turns it into a page. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            403 => 'You do not have permission to do that.',
            404 => 'The page you asked for does not exist.',
            419 => 'Your form expired. Please go back, refresh the page and try again.',
            default => 'Something went wrong.',
        };
    }
}
