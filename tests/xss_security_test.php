<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function xss_read(string $path, array &$failures): string
{
    $content = is_file($path) ? file_get_contents($path) : false;
    if (!is_string($content)) {
        $failures[] = 'Arquivo ausente ou ilegível: ' . $path;
        return '';
    }
    return $content;
}

function xss_contains(string $label, string $content, string $needle, array &$failures): void
{
    if (!str_contains($content, $needle)) {
        $failures[] = $label . ' deveria conter: ' . $needle;
    }
}

function xss_not_contains(string $label, string $content, string $needle, array &$failures): void
{
    if (str_contains($content, $needle)) {
        $failures[] = $label . ' não deveria conter: ' . $needle;
    }
}

$adminJs = xss_read($root . '/public/assets/js/admin-dashboard.js', $failures);
$adminDashboard = xss_read($root . '/public/admin/dashboard.php', $failures);
$firstAccessJs = xss_read($root . '/public/assets/js/app.js', $failures);
$firstAccessPage = xss_read($root . '/public/primeiro-acesso.php', $failures);
$bulkMail = xss_read($root . '/public/api/admin-bulk-email.php', $failures);
$paymentReturn = xss_read($root . '/public/pagamento-retorno.php', $failures);
$workshopGrade = xss_read($root . '/public/diaria-grade-oficina-modular.php', $failures);

xss_contains('primeiro acesso cria options pelo DOM', $firstAccessJs, 'studentCandidateSelect.replaceChildren()', $failures);
xss_contains('primeiro acesso usa textContent', $firstAccessJs, 'option.textContent = candidateLabel(candidate)', $failures);
xss_not_contains('primeiro acesso não interpola candidato em HTML', $firstAccessJs, '<option value="${idx}">${candidateLabel(candidate)}</option>', $failures);
xss_contains('cache do primeiro acesso avançou', $firstAccessPage, '/assets/js/app.js?v=7', $failures);

foreach (['JSON_HEX_TAG', 'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT'] as $flag) {
    xss_contains('bootstrap JSON administrativo seguro', $adminDashboard, $flag, $failures);
}
xss_contains('cache administrativo avançou', $adminDashboard, '/assets/js/admin-dashboard.js?v=81', $failures);

xss_contains('painel sanitiza HTML de e-mail', $adminJs, 'function sanitizeBulkMailHtml(value)', $failures);
xss_contains('painel remove manipuladores inline', $adminJs, "name.startsWith('on')", $failures);
xss_contains('painel remove elementos executáveis', $adminJs, "'script',", $failures);
xss_contains('prévia usa iframe isolado', $adminDashboard, 'sandbox="allow-same-origin"', $failures);
xss_contains('prévia aplica CSP sem scripts', $adminJs, '"default-src \'none\'; img-src https: data:', $failures);
xss_contains('painel sanitiza antes da prévia', $adminJs, 'bulkMailVisualInput.srcdoc = buildBulkMailPreviewHtml', $failures);
xss_contains('painel bloqueia colagem HTML', $adminJs, "event.clipboardData?.getData('text/plain')", $failures);
xss_not_contains('prévia não recebe HTML cru', $adminJs, 'bulkMailVisualInput.innerHTML = bulkMailHtmlInput.value', $failures);
xss_contains('nome do aluno é escapado', $adminJs, '<strong>Aluno: ${escapeHtml(studentName)}</strong>', $failures);
xss_contains('cliente Asaas é escapado', $adminJs, '<td>${escapeHtml(customer)}</td>', $failures);
xss_contains('URL Asaas exige HTTPS e domínio', $adminJs, 'function safeAsaasHttpsUrl(value)', $failures);
xss_contains('visão como usuário exige mesma origem', $adminJs, 'function safeSameOriginUrl(value, fallback = \'/dashboard.php\')', $failures);
xss_contains('redirecionamento administrativo é validado', $adminJs, "safeSameOriginUrl(data.url || '/dashboard.php')", $failures);

xss_contains('placeholders aceitam contexto HTML explícito', $bulkMail, 'bool $htmlContext = false', $failures);
xss_contains('placeholders HTML são escapados', $bulkMail, "htmlspecialchars(\$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')", $failures);
xss_contains('envio em massa usa contexto HTML', $bulkMail, 'replacePlaceholders($html, $context, true)', $failures);

xss_contains('retorno valida URL', $paymentReturn, 'FILTER_VALIDATE_URL', $failures);
xss_contains('retorno exige HTTPS', $paymentReturn, "\$invoiceScheme === 'https'", $failures);
xss_contains('retorno restringe domínio Asaas', $paymentReturn, "str_ends_with(\$invoiceHost, '.asaas.com')", $failures);

xss_contains('grade valida navegação local', $workshopGrade, 'function safeSameOriginPath(value)', $failures);
xss_contains('grade valida fatura Asaas', $workshopGrade, 'function safeAsaasInvoiceUrl(value)', $failures);
xss_not_contains('grade não navega para fatura crua', $workshopGrade, 'window.location.href = r.data.invoice_url', $failures);

$hostileJson = json_encode(
    [['name' => '</script><img src=x onerror=alert(1)>']],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($hostileJson) || str_contains($hostileJson, '</script>') || str_contains($hostileJson, '<img')) {
    $failures[] = 'Flags JSON seguras deveriam neutralizar fechamento de script.';
}

if ($failures !== []) {
    fwrite(STDERR, "Falhas na proteção contra XSS:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "OK: sinks XSS, URLs e HTML de e-mail validados.\n";
