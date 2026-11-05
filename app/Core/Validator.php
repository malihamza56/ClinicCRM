<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Server-side input validation.
 *
 *   $v = Validator::make($request->all(), [
 *       'first_name' => 'required|name|max:80',
 *       'email'      => 'email|max:190',          // optional: skipped when left empty
 *   ]);
 *   if ($v->fails()) { ... $v->errors() ... }  else { $data = $v->validated(); }
 *
 * A field without the "required" rule is optional: when empty it is skipped and returned as null.
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $clean = [];

    /**
     * @param array<string,mixed>  $input
     * @param array<string,string> $rules
     * @param array<string,string> $labels Friendly field names for messages
     */
    private function __construct(private array $input, private array $rules, private array $labels)
    {
    }

    public static function make(array $input, array $rules, array $labels = []): self
    {
        $validator = new self($input, $rules, $labels);
        $validator->run();

        return $validator;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string,string> field => first error message */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string,mixed> trimmed values; empty optional fields are null */
    public function validated(): array
    {
        return $this->clean;
    }

    /** Lets a service add a business-rule error that a simple rule cannot express. */
    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $raw = $this->input[$field] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $rules = explode('|', $ruleString);
            $isEmpty = $value === null || $value === '' || $value === [];

            if ($isEmpty) {
                if (in_array('required', $rules, true)) {
                    $this->errors[$field] = $this->label($field) . ' is required.';
                }
                $this->clean[$field] = null;
                continue;
            }

            if (!is_string($value)) {
                $this->errors[$field] = $this->label($field) . ' is not valid.';
                continue;
            }

            $this->clean[$field] = $value;
            foreach ($rules as $rule) {
                if ($rule === 'required' || $rule === 'nullable') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $message = $this->check($name, $param, $value, $field);
                if ($message !== null) {
                    $this->errors[$field] = $message;
                    break;
                }
            }
        }
    }

    private function check(string $rule, ?string $param, string $value, string $field): ?string
    {
        $label = $this->label($field);

        return match ($rule) {
            'max'   => mb_strlen($value) > (int) $param ? "$label must not be longer than $param characters." : null,
            'min'   => mb_strlen($value) < (int) $param ? "$label must be at least $param characters." : null,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) === false ? "$label must be a valid email address." : null,
            'phone' => preg_match('/^\+?[0-9][0-9 \-]{5,17}[0-9]$/', $value) !== 1 ? "$label must be a valid phone number (digits, spaces or dashes; may start with +)." : null,
            'name'  => preg_match('/^[\p{L}][\p{L}\p{M} .\'\-]*$/u', $value) !== 1 ? "$label may contain only letters, spaces, dots, hyphens and apostrophes." : null,
            'date'  => $this->isDate($value) ? null : "$label must be a valid date.",
            'time'  => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) !== 1 ? "$label must be a valid time (HH:MM)." : null,
            'in'    => in_array($value, explode(',', (string) $param), true) ? null : "$label has an invalid value.",
            'int'   => preg_match('/^\d+$/', $value) !== 1 ? "$label must be a whole number." : null,
            'number' => $this->checkNumber($value, (string) $param, $label),
            'money' => Money::parse($value) === null ? "$label must be an amount such as 1500 or 1500.50." : null,
            'money_positive' => (($m = Money::parse($value)) === null || $m <= 0) ? "$label must be an amount greater than zero." : null,
            'date_not_future' => $this->isDate($value) && $value > date('Y-m-d') ? "$label cannot be in the future." : null,
            'date_not_past'   => $this->isDate($value) && $value < date('Y-m-d') ? "$label cannot be in the past." : null,
            'same'  => ($this->input[(string) $param] ?? null) !== $value ? "$label does not match." : null,
            default => throw new \LogicException("Unknown validation rule: $rule"),
        };
    }

    /** "number:min,max" - a decimal number inside an inclusive range. */
    private function checkNumber(string $value, string $range, string $label): ?string
    {
        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            return "$label must be a number.";
        }
        [$min, $max] = array_map('floatval', explode(',', $range));
        $number = (float) $value;

        return ($number < $min || $number > $max) ? "$label must be between $min and $max." : null;
    }

    private function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
