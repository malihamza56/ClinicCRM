<?php
declare(strict_types=1);

/*
 * Small global helper functions used mostly by view templates.
 * Keep this file short: anything with business rules belongs in a Service or Model.
 */

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Money;
use App\Core\Request;
use App\Core\Session;
use App\Models\Setting;

/** Escape text for safe output inside HTML. Use it on EVERY value printed in a template. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Builds an app URL such as url('/patients', ['q' => 'ali']). Works at a domain root or in a sub-folder. */
function url(string $path = '/', array $query = []): string
{
    $url = Request::basePath() . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');

    return $query === [] ? $url : $url . '?' . http_build_query($query);
}

/** URL of a file inside public/assets, with a version stamp so browsers reload changed files. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $version = is_file($file) ? '?v=' . filemtime($file) : '';

    return url('/assets/' . ltrim($path, '/')) . $version;
}

function csrf_field(): string
{
    return '<input type="hidden" name="' . Csrf::FIELD . '" value="' . e(Csrf::token()) . '">';
}

/** Value the user typed in the previous (failed) submission of a form. */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::flashed('old', []);

    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

function field_error(string $key): ?string
{
    $errors = Session::flashed('errors', []);

    return is_array($errors) ? ($errors[$key] ?? null) : null;
}

function can(string $permission): bool
{
    return Auth::can($permission);
}

function can_any(string ...$permissions): bool
{
    return Auth::canAny(...$permissions);
}

/** Bootstrap Icons glyph, e.g. icon('calendar-check'). Decorative, so hidden from screen readers. */
function icon(string $name, string $class = ''): string
{
    return '<i class="bi bi-' . e($name) . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true"></i>';
}

function setting(string $key, string $default = ''): string
{
    return Setting::get($key, $default);
}

/** Formats a DECIMAL string (or minor-unit int) from the database using the clinic's currency symbol. */
function money(string|int|float|null $decimal): string
{
    return Money::format(Money::fromDb($decimal === null ? null : (string) $decimal), setting('currency_symbol', 'Rs.'));
}

/** Formats an integer amount in minor units (paisa/cents), as returned by dashboard and report services. */
function money_minor(int $minor, bool $compact = false): string
{
    $text = Money::format($minor, setting('currency_symbol', 'Rs.'));

    // Compact form drops ".00" for whole amounts (used on big KPI cards, like the reference dashboard).
    return $compact && $minor % 100 === 0 ? substr($text, 0, -3) : $text;
}

function format_date(?string $date, string $format = 'd M Y'): string
{
    if ($date === null || $date === '' || str_starts_with($date, '0000')) {
        return '-';
    }

    return (new DateTimeImmutable($date))->format($format);
}

function format_time(?string $time): string
{
    if ($time === null || $time === '') {
        return '-';
    }

    return (new DateTimeImmutable('2000-01-01 ' . $time))->format('g:i A');
}

function format_datetime(?string $dateTime): string
{
    return $dateTime === null || $dateTime === '' ? '-' : (new DateTimeImmutable($dateTime))->format('d M Y, g:i A');
}

function age_from(?string $dateOfBirth): string
{
    if ($dateOfBirth === null || $dateOfBirth === '') {
        return '-';
    }
    $age = (new DateTimeImmutable($dateOfBirth))->diff(new DateTimeImmutable('today'));

    return $age->y >= 1 ? $age->y . ' yrs' : max(0, $age->m) . ' mos';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $letters !== '' ? $letters : '?';
}

/** Coloured status pill. $status is shown as text, so colour is never the only signal. */
function status_badge(string $status): string
{
    $tone = match ($status) {
        'Completed', 'Paid', 'Finalized', 'Active', 'Confirmed', 'Payment' => 'success',
        'Scheduled', 'Pending', 'Draft', 'Partial', 'Adjustment' => 'warning',
        'Waiting', 'In Progress', 'Walk-in' => 'info',
        'Cancelled', 'No Show', 'Void', 'Unpaid', 'Inactive', 'Archived', 'Overdue', 'Refund' => 'danger',
        default => 'neutral',
    };

    return '<span class="badge badge--' . $tone . '">' . e($status) . '</span>';
}

/** Is the sidebar link for $prefix the current page? */
function nav_active(string $prefix): string
{
    $path = (string) ($GLOBALS['current_path'] ?? '/');
    $match = $prefix === '/' ? $path === '/' : ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/'));

    return $match ? ' is-active' : '';
}

/** Current query string with some values replaced - used by filters and pagination links. */
function url_with(array $changes): string
{
    $query = array_merge($_GET, $changes);
    $path = (string) ($GLOBALS['current_path'] ?? '/');

    return url($path, $query);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/** Escapes a value for a data-* attribute holding JSON (used to pass chart data to JavaScript). */
function json_attr(array $data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/**
 * Renders one labelled form control with its old value and validation message.
 * Options: type (text|email|tel|date|time|number|password|textarea|select), value, required, help, options (select),
 * placeholder, rows, min, max, step, maxlength, autocomplete, class, id, attrs (extra attributes), disabled.
 */
function field(string $name, string $label, array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $id = $o['id'] ?? 'f_' . preg_replace('/\W+/', '_', $name);
    $value = old($name, $o['value'] ?? '');
    $error = field_error($name);
    $classes = 'input' . ($error ? ' is-invalid' : '') . (isset($o['class']) ? ' ' . $o['class'] : '');
    $attrs = ' id="' . e($id) . '" name="' . e($name) . '" class="' . e($classes) . '"';
    if (!empty($o['required'])) {
        $attrs .= ' required';
    }
    if (!empty($o['disabled'])) {
        $attrs .= ' disabled';
    }
    foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'autocomplete', 'rows', 'inputmode'] as $attr) {
        if (isset($o[$attr])) {
            $attrs .= ' ' . $attr . '="' . e($o[$attr]) . '"';
        }
    }
    foreach ($o['attrs'] ?? [] as $k => $v) {
        $attrs .= ' ' . e($k) . '="' . e($v) . '"';
    }
    if ($error) {
        $attrs .= ' aria-invalid="true" aria-describedby="' . e($id) . '_err"';
    } elseif (!empty($o['help'])) {
        $attrs .= ' aria-describedby="' . e($id) . '_help"';
    }

    if ($type === 'textarea') {
        $control = '<textarea' . $attrs . '>' . e($value) . '</textarea>';
    } elseif ($type === 'select') {
        $control = '<select' . $attrs . '>';
        if (!empty($o['blank'])) {
            $control .= '<option value="">' . e($o['blank']) . '</option>';
        }
        foreach ($o['options'] ?? [] as $optValue => $optLabel) {
            $control .= '<option value="' . e($optValue) . '"' . selected($value, $optValue) . '>' . e($optLabel) . '</option>';
        }
        $control .= '</select>';
    } else {
        $control = '<input type="' . e($type) . '" value="' . e($type === 'password' ? '' : $value) . '"' . $attrs . '>';
    }

    return '<div class="field' . (!empty($o['span2']) ? ' span-2' : '') . '"><label for="' . e($id) . '">' . e($label)
        . (!empty($o['required']) ? '<span class="req" aria-hidden="true">*</span>' : '') . '</label>' . $control
        . ($error ? '<div class="error-text" id="' . e($id) . '_err" role="alert">' . icon('exclamation-circle') . ' ' . e($error) . '</div>'
            : (!empty($o['help']) ? '<div class="help" id="' . e($id) . '_help">' . e($o['help']) . '</div>' : ''))
        . '</div>';
}

/** Standard Cancel / Save buttons row for forms. */
function form_actions(string $cancelUrl, string $saveLabel = 'Save'): string
{
    return '<div class="form-actions"><a class="btn btn-secondary" href="' . e($cancelUrl) . '">Cancel</a>'
        . '<button class="btn btn-primary" type="submit">' . icon('check2') . ' ' . e($saveLabel) . '</button></div>';
}

/** Hidden POST form with one button, used for small actions (activate, archive, check-in). */
function post_button(string $action, string $label, string $class = 'btn btn-secondary btn-sm', ?string $confirm = null, array $hidden = [], string $glyph = ''): string
{
    $html = '<form method="post" class="inline" action="' . e($action) . '">' . csrf_field();
    foreach ($hidden as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }

    return $html . '<button type="submit" class="' . e($class) . '"' . ($confirm !== null ? ' data-confirm="' . e($confirm) . '"' : '') . '>'
        . ($glyph !== '' ? icon($glyph) . ' ' : '') . e($label) . '</button></form>';
}
