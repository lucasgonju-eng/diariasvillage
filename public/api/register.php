<?php
declare(strict_types=1);

// Tombstone intencional: o cadastro legado por nome do aluno não pode voltar
// a criar vínculos. O primeiro acesso canônico usa student_id e claims atômicos.
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode([
    'ok' => false,
    'error' => 'Endpoint indisponível.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
