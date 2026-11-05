<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Billable clinic services (X-ray, dressing, lab sample ...). Consultation fees live on the doctor profile. */
final class Service
{
    /** @return array<int,array<string,mixed>> */
    public static function all(bool $activeOnly = false): array
    {
        return Database::all('SELECT * FROM services' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY is_active DESC, name');
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM services WHERE id = :id', ['id' => $id]);
    }

    public static function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return Database::value('SELECT COUNT(*) FROM services WHERE name = :n AND id <> :id', ['n' => $name, 'id' => $exceptId ?? 0]) > 0;
    }

    public static function create(string $name, ?string $description, string $price): int
    {
        return Database::insert('INSERT INTO services (name, description, price) VALUES (:n, :d, :p)', ['n' => $name, 'd' => $description, 'p' => $price]);
    }

    public static function update(int $id, string $name, ?string $description, string $price): void
    {
        Database::run('UPDATE services SET name = :n, description = :d, price = :p WHERE id = :id', ['n' => $name, 'd' => $description, 'p' => $price, 'id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE services SET is_active = :a WHERE id = :id', ['a' => (int) $active, 'id' => $id]);
    }
}
