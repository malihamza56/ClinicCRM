<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Page;

final class AuditLog
{
    public static function write(?int $userId, string $action, string $entityType, ?int $entityId, string $summary, ?array $details, ?string $ip): void
    {
        Database::run(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, summary, details, ip_address)
             VALUES (:u, :a, :t, :i, :s, :d, :ip)',
            [
                'u'  => $userId,
                'a'  => $action,
                't'  => $entityType,
                'i'  => $entityId,
                's'  => mb_substr($summary, 0, 255),
                'd'  => $details === null ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'ip' => $ip,
            ]
        );
    }

    /** @param array{from?:string,to?:string,action?:string,user_id?:int,entity_type?:string} $filters */
    public static function paginate(array $filters, int $page, int $perPage): Page
    {
        $where = ['1 = 1'];
        $params = [];
        if (!empty($filters['from'])) {
            $where[] = 'DATE(l.created_at) >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'DATE(l.created_at) <= :to';
            $params['to'] = $filters['to'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'l.action = :action';
            $params['action'] = $filters['action'];
        }
        if (!empty($filters['entity_type'])) {
            $where[] = 'l.entity_type = :etype';
            $params['etype'] = $filters['entity_type'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'l.user_id = :user';
            $params['user'] = (int) $filters['user_id'];
        }
        $sqlWhere = implode(' AND ', $where);

        $total = (int) Database::value("SELECT COUNT(*) FROM audit_logs l WHERE $sqlWhere", $params);
        $items = Database::all(
            "SELECT l.*, u.full_name AS user_name FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id
             WHERE $sqlWhere ORDER BY l.id DESC LIMIT " . (int) $perPage . ' OFFSET ' . Page::offset($page, $perPage),
            $params
        );

        return new Page($items, $total, $page, $perPage);
    }

    /** @return string[] */
    public static function distinct(string $column): array
    {
        if (!in_array($column, ['action', 'entity_type'], true)) {
            throw new \InvalidArgumentException('Unsupported column');
        }

        return array_column(Database::all("SELECT DISTINCT $column AS v FROM audit_logs ORDER BY v"), 'v');
    }
}
