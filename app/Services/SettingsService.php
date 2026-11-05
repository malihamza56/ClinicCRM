<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Money;
use App\Core\Validator;
use App\Models\Setting;

final class SettingsService
{
    public static function validate(array $input): Validator
    {
        $v = Validator::make($input, [
            'clinic_name'     => 'required|max:150',
            'clinic_address'  => 'max:255',
            'clinic_phone'    => 'phone',
            'clinic_email'    => 'email|max:190',
            'currency_symbol' => 'required|max:6',
            'currency_code'   => 'required|max:3|min:3',
            'opening_time'    => 'required|time',
            'closing_time'    => 'required|time',
            'tax_rate_percent' => 'required|number:0,100',
            'invoice_footer'  => 'max:300',
        ], ['clinic_name' => 'Clinic name', 'currency_symbol' => 'Currency symbol', 'currency_code' => 'Currency code', 'opening_time' => 'Opening time', 'closing_time' => 'Closing time', 'tax_rate_percent' => 'Tax rate']);

        $d = $v->validated();
        if (!$v->fails()) {
            if ($d['closing_time'] <= $d['opening_time']) {
                $v->addError('closing_time', 'Closing time must be after opening time.');
            }
            if (preg_match('/^[A-Za-z]{3}$/', (string) $d['currency_code']) !== 1) {
                $v->addError('currency_code', 'Currency code must be three letters, for example PKR.');
            }
            if (Money::parse((string) $d['tax_rate_percent']) === null) {
                $v->addError('tax_rate_percent', 'Tax rate can have at most two decimals.');
            }
        }

        return $v;
    }

    public static function save(array $data): void
    {
        $data['currency_code'] = strtoupper((string) $data['currency_code']);
        $data['tax_rate_percent'] = Money::toDecimal((int) Money::parse((string) $data['tax_rate_percent']));
        foreach ($data as $key => $value) {
            Setting::set($key, (string) ($value ?? ''));
        }
        AuditService::log('settings.updated', 'settings', null, 'Changed clinic settings');
    }
}
