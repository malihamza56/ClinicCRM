<?php
declare(strict_types=1);

namespace App\Config;

/** Constants shared by the billing code and views. */
final class Billing
{
    public const STATUS_UNPAID  = 'Unpaid';
    public const STATUS_PARTIAL = 'Partial';
    public const STATUS_PAID    = 'Paid';
    public const STATUS_VOID    = 'Void';

    public const TYPE_PAYMENT    = 'Payment';
    public const TYPE_REFUND     = 'Refund';
    public const TYPE_ADJUSTMENT = 'Adjustment';

    public const METHODS = ['Cash', 'Card', 'Bank Transfer', 'Mobile Wallet'];

    public const ITEM_CONSULTATION = 'Consultation';
    public const ITEM_SERVICE      = 'Service';
}
