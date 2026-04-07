<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (empty($_SESSION['user'])) {
    json_response(['error' => 'Unauthorized'], 401);
}

$cid = company_id() ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $structSt = $pdo->prepare(
            "SELECT ss.id, ss.name, ss.description, ss.created_at,
                    COUNT(sc.id) AS component_count
             FROM salary_structures ss
             LEFT JOIN salary_components sc ON sc.salary_structure_id = ss.id
             WHERE ss.company_id = ?
             GROUP BY ss.id, ss.name, ss.description, ss.created_at
             ORDER BY ss.created_at DESC"
        );
        $structSt->execute([$cid]);
        $structures = $structSt->fetchAll() ?: [];

        $compSt = $pdo->prepare(
            "SELECT component_type, COUNT(*) AS cnt
             FROM salary_components
             WHERE company_id = ?
             GROUP BY component_type"
        );
        $compSt->execute([$cid]);
        $componentRows = $compSt->fetchAll() ?: [];

        $componentCounts = ['earning' => 0, 'deduction' => 0];
        foreach ($componentRows as $row) {
            $key = (string)$row['component_type'];
            if (array_key_exists($key, $componentCounts)) {
                $componentCounts[$key] = (int)$row['cnt'];
            }
        }

        json_response([
            'structures' => array_map(function ($row) {
                return [
                    'id' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'description' => (string)($row['description'] ?? ''),
                    'componentCount' => (int)$row['component_count'],
                    'createdAt' => (string)$row['created_at'],
                ];
            }, $structures),
            'componentCounts' => $componentCounts,
        ]);
    } catch (Exception $e) {
        error_log('Compensation API error: ' . $e->getMessage());
        json_response(['error' => 'Unable to load compensation data.'], 500);
    }
}

json_response(['error' => 'Method not allowed'], 405);
