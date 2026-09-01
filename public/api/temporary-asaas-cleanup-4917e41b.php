<?php

http_response_code(404);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Not found.']);
