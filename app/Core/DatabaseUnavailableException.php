<?php
declare(strict_types=1);

namespace App\Core;

/** Thrown when the database server cannot be reached, so the app can show a friendly "service unavailable" page. */
final class DatabaseUnavailableException extends \RuntimeException
{
}
