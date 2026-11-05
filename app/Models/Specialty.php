<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Specialty
{
    /** @return array<int,array<string,mixed>> */
    public static function all(bool $activeOnly = false): array
    {
        return Database::all(
            'SELECT s.*, (SELECT COUNT(*) FROM doctors d WHERE d.specialty_id = s.id) AS doctor_count
             FROM specialties s' . ($activeOnly ? ' WHERE s.is_active = 1' : '') . ' ORDER BY s.is_active DESC, s.name'
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one('SELECT * FROM specialties WHERE id = :id', ['id' => $id]);
    }

    public static function nameTaken(string $name, ?int $exceptId = null): bool
    {
        return Database::value('SELECT COUNT(*) FROM specialties WHERE name = :n AND id <> :id', ['n' => $name, 'id' => $exceptId ?? 0]) > 0;
    }

    public static function create(string $name, ?string $description): int
    {
        return Database::insert('INSERT INTO specialties (name, description) VALUES (:n, :d)', ['n' => $name, 'd' => $description]);
    }

    public static function update(int $id, string $name, ?string $description): void
    {
        Database::run('UPDATE specialties SET name = :n, description = :d WHERE id = :id', ['n' => $name, 'd' => $description, 'id' => $id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::run('UPDATE specialties SET is_active = :a WHERE id = :id', ['a' => (int) $active, 'id' => $id]);
    }
}
