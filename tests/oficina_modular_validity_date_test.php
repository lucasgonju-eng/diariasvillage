<?php
declare(strict_types=1);

$pagePath = dirname(__DIR__) . '/public/diaria-grade-oficina-modular.php';
$page = file_get_contents($pagePath);

if (!is_string($page)) {
    fwrite(STDERR, "Falha ao ler a página da grade de oficinas modulares.\n");
    exit(1);
}

$expectedFilter = '!($dataDiaria >= $inicioValidade && $dataDiaria <= $fimValidade)';
if (!str_contains($page, $expectedFilter)) {
    fwrite(STDERR, "A validade das oficinas deve ser comparada com a data da diária.\n");
    exit(1);
}

if (str_contains($page, "\$hoje = date('Y-m-d');")) {
    fwrite(STDERR, "A grade não deve filtrar oficinas pela data atual do servidor.\n");
    exit(1);
}

echo "OK: validade das oficinas usa a data da diária.\n";
