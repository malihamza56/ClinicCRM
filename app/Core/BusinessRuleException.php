<?php
declare(strict_types=1);

namespace App\Core;

/**
 * A rule of the clinic was broken (slot taken, overpayment, invalid status change ...).
 * The message is written for staff and is safe to show on screen; controllers turn it into an error banner.
 */
final class BusinessRuleException extends \RuntimeException
{
    /** @param array<string,mixed> $context Extra facts a controller may use (e.g. the id of an existing duplicate record). */
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
