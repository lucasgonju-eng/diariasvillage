<?php
$bootstrapCandidates = [
    __DIR__ . '/src/Bootstrap.php',
    dirname(__DIR__) . '/src/Bootstrap.php',
];
foreach ($bootstrapCandidates as $bootstrapFile) {
    if (is_file($bootstrapFile)) {
        require_once $bootstrapFile;
        break;
    }
}
date_default_timezone_set('America/Sao_Paulo');

use App\Helpers;
use App\HttpClient;
use App\SupabaseClient;

$user = Helpers::requireAuthWeb();
$client = new SupabaseClient(new HttpClient());
$studentIdsScope = [];
$sessionStudentId = trim((string) ($user['student_id'] ?? ''));
if ($sessionStudentId !== '') {
    $studentIdsScope[$sessionStudentId] = true;
}
$documentDigits = preg_replace('/\D+/', '', (string) ($user['parent_document'] ?? '')) ?? '';

$userId = trim((string) ($user['id'] ?? ''));
if ($userId !== '') {
    $guardianCurrent = $client->select(
        'guardians',
        'select=student_id,parent_document&id=eq.' . urlencode($userId) . '&limit=1'
    );
    if (($guardianCurrent['ok'] ?? false) && !empty($guardianCurrent['data'][0])) {
        $current = $guardianCurrent['data'][0];
        $sid = trim((string) ($current['student_id'] ?? ''));
        if ($sid !== '') {
            $studentIdsScope[$sid] = true;
        }
        if ($documentDigits === '') {
            $documentDigits = preg_replace('/\D+/', '', (string) ($current['parent_document'] ?? '')) ?? '';
        }
    }
}

$userEmail = trim((string) ($user['email'] ?? ''));
if ($userEmail !== '') {
    $guardiansByEmail = $client->select(
        'guardians',
        'select=student_id&email=eq.' . urlencode($userEmail) . '&limit=200'
    );
    if (($guardiansByEmail['ok'] ?? false) && !empty($guardiansByEmail['data'])) {
        foreach ($guardiansByEmail['data'] as $row) {
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIdsScope[$sid] = true;
            }
        }
    }
}

if ($documentDigits !== '') {
    $maskCpf = static function (string $digits): string {
        if (strlen($digits) !== 11) {
            return $digits;
        }
        return substr($digits, 0, 3) . '.'
            . substr($digits, 3, 3) . '.'
            . substr($digits, 6, 3) . '-'
            . substr($digits, 9, 2);
    };
    $cpfAttempts = [
        'parent_document=eq.' . urlencode($documentDigits) . '&select=student_id&limit=500',
        'parent_document=eq.' . urlencode($maskCpf($documentDigits)) . '&select=student_id&limit=500',
        'parent_document=ilike.' . urlencode('*' . $documentDigits . '*') . '&select=student_id&limit=500',
    ];
    foreach ($cpfAttempts as $query) {
        $guardiansByCpf = $client->select('guardians', $query);
        if (!($guardiansByCpf['ok'] ?? false) || empty($guardiansByCpf['data'])) {
            continue;
        }
        foreach ($guardiansByCpf['data'] as $row) {
            $sid = trim((string) ($row['student_id'] ?? ''));
            if ($sid !== '') {
                $studentIdsScope[$sid] = true;
            }
        }
    }
}

$studentRows = [];
$studentIds = array_keys($studentIdsScope);
if (!empty($studentIds)) {
    if (count($studentIds) === 1) {
        $studentResult = $client->select(
            'students',
            'select=id,name,enrollment,grade,class_name,class&id=eq.' . urlencode($studentIds[0]) . '&limit=1'
        );
    } else {
        $quotedStudentIds = array_map(static fn($id) => '"' . str_replace('"', '', $id) . '"', $studentIds);
        $studentResult = $client->select(
            'students',
            'select=id,name,enrollment,grade,class_name,class&id=in.(' . implode(',', $quotedStudentIds) . ')&order=name.asc&limit=20'
        );
    }
    if (($studentResult['ok'] ?? false) && !empty($studentResult['data']) && is_array($studentResult['data'])) {
        $studentRows = $studentResult['data'];
    }
}

$studentRow = [];
if (!empty($studentRows)) {
    if ($sessionStudentId !== '') {
        foreach ($studentRows as $candidate) {
            if (trim((string) ($candidate['id'] ?? '')) === $sessionStudentId) {
                $studentRow = $candidate;
                break;
            }
        }
    }
    if (empty($studentRow)) {
        $studentRow = $studentRows[0];
    }
}
$studentName = trim((string) ($studentRow['name'] ?? 'aluno(a)'));
$nowDt = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$nextBusinessDay = static function (DateTimeImmutable $date): DateTimeImmutable {
    $candidate = $date;
    while (in_array((int) $candidate->format('N'), [6, 7], true)) {
        $candidate = $candidate->modify('+1 day');
    }
    return $candidate;
};
$candidateDt = ((int) $nowDt->format('H') >= 16) ? $nowDt->modify('+1 day') : $nowDt;
$minDate = $nextBusinessDay($candidateDt)->format('Y-m-d');
$minDateBr = (DateTimeImmutable::createFromFormat('Y-m-d', $minDate) ?: $nowDt)->format('d/m/Y');
$dashboardError = isset($_SESSION['dashboard_error']) ? (string) $_SESSION['dashboard_error'] : '';
unset($_SESSION['dashboard_error']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Diárias Village</title>
  <meta name="description" content="Escolha a diária do dia com praticidade e gere o pagamento." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/style.css?v=5">
</head>
<body>
  <header class="hero" id="top">
    <div class="container">
      <div class="topbar">
        <div class="brand">
          <span class="brand-mark" aria-hidden="true"></span>
          <div class="brand-text">
            <div class="brand-title">DIÁRIAS VILLAGE</div>
            <div class="brand-sub">Painel do responsável</div>
          </div>
        </div>

        <div class="cta">
          <a class="btn btn-ghost btn-sm" href="/financeiro.php">Financeiro</a>
          <a class="btn btn-ghost btn-sm" href="/profile.php">Perfil</a>
          <a class="btn btn-ghost btn-sm" href="/logout.php">Sair</a>
        </div>
      </div>

      <div class="hero-grid">
        <div class="hero-left">
          <div class="pill">Dashboard</div>
          <h1>Bem-vindo(a)!</h1>
          <p class="lead" style="margin-top:-6px;font-weight:700;color:#FFE7A6;">
            Pode entrar, <?php echo htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8'); ?>, a casa é sua!
          </p>
          <p class="lead">Escolha a diária do dia com praticidade.</p>

          <div class="microchips" role="list">
            <span class="microchip" role="listitem">Pagamento via PIX</span>
            <span class="microchip" role="listitem">Liberação automática</span>
            <span class="microchip" role="listitem">Confirmação por e-mail</span>
          </div>
        </div>

        <aside class="hero-card" aria-label="Formulário de início da diária">
          <h3>Montar grade da diária</h3>
          <p class="muted">Escolha a data e siga para a etapa de Grade de Oficina Modular.</p>
          <div class="info-note" id="planned-countdown" data-now="<?php echo date('c'); ?>">
            Carregando contagem regressiva da diária planejada...
          </div>

          <form id="payment-form" method="get" action="/api/diaria-iniciar.php">
            <div class="grid-2">
              <div class="form-group">
                <label>Data</label>
                <input type="text" id="payment-date-br" value="<?php echo htmlspecialchars($minDateBr, ENT_QUOTES, 'UTF-8'); ?>" inputmode="numeric" autocomplete="off" placeholder="dd/mm/aaaa" required />
                <input type="hidden" id="payment-date" name="date" value="<?php echo $minDate; ?>" data-min-iso="<?php echo $minDate; ?>" />
                <div class="small">Após 16h, somente datas futuras.</div>
              </div>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Ir para Grade de Oficina Modular</button>
            <div id="payment-message"></div>
            <?php if ($dashboardError !== ''): ?>
              <div class="error" style="margin-top:8px;"><?php echo htmlspecialchars($dashboardError, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
          </form>
        </aside>
      </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0,64 C240,120 480,120 720,72 C960,24 1200,24 1440,72 L1440,120 L0,120 Z"></path>
    </svg>
  </header>

  <main>
    <section class="section section-alt" id="info-diarias">
      <div class="container">
        <div class="section-head">
          <h2>Informações das diárias</h2>
          <p class="muted">Regras aplicadas automaticamente conforme o horário do pedido.</p>
        </div>

        <div class="info-cards">
          <div class="info-card">
            <h3>Diária Planejada</h3>
            <p>Antes das 10h do day-use (R$ 77,00).</p>
          </div>
          <div class="info-card">
            <h3>Diária Emergencial</h3>
            <p>Após as 10h do day-use (R$ 97,00).</p>
          </div>
        </div>

        <div class="info-note">
          Para datas futuras, a diária é planejada automaticamente. Após 16h, a compra para o dia atual é encerrada.
          Finalizando o pagamento, você recebe por e-mail o número de confirmação do day-use.
        </div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="container">
      Desenvolvido por Lucas Gonçalves Junior - 2026
      <a class="tinyLink" href="/admin/" aria-label="Acesso administrativo">Admin</a>
    </div>
  </footer>

  <script src="/assets/js/dashboard.js?v=20260219-1"></script>
  <script>
    (function setupBrazilianDateInput() {
      var form = document.getElementById('payment-form');
      var dateBr = document.getElementById('payment-date-br');
      var dateIso = document.getElementById('payment-date');
      var paymentMessage = document.getElementById('payment-message');
      if (!form || !dateBr || !dateIso) return;

      function parseBrToIso(raw) {
        var m = String(raw || '').trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!m) return null;
        var dd = Number(m[1]);
        var mm = Number(m[2]);
        var yyyy = Number(m[3]);
        var date = new Date(yyyy, mm - 1, dd);
        if (
          date.getFullYear() !== yyyy ||
          date.getMonth() !== (mm - 1) ||
          date.getDate() !== dd
        ) {
          return null;
        }
        var d = String(dd).padStart(2, '0');
        var m2 = String(mm).padStart(2, '0');
        return String(yyyy) + '-' + m2 + '-' + d;
      }

      function normalizeBrInput() {
        var digits = dateBr.value.replace(/\D+/g, '').slice(0, 8);
        if (digits.length > 4) {
          dateBr.value = digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
        } else if (digits.length > 2) {
          dateBr.value = digits.slice(0, 2) + '/' + digits.slice(2);
        } else {
          dateBr.value = digits;
        }
      }

      dateBr.addEventListener('input', normalizeBrInput);
      dateBr.addEventListener('blur', function () {
        var iso = parseBrToIso(dateBr.value);
        if (iso) {
          var parts = iso.split('-');
          dateBr.value = parts[2] + '/' + parts[1] + '/' + parts[0];
        }
      });

      form.addEventListener('submit', function (e) {
        var iso = parseBrToIso(dateBr.value);
        var minIso = dateIso.dataset.minIso || '';
        if (!iso) {
          e.preventDefault();
          if (paymentMessage) paymentMessage.innerHTML = '<div class="error">Informe a data no formato DD/MM/AAAA.</div>';
          return;
        }
        if (minIso && iso < minIso) {
          e.preventDefault();
          if (paymentMessage) paymentMessage.innerHTML = '<div class="error">Selecione a próxima data útil disponível.</div>';
          return;
        }
        dateIso.value = iso;
      });
    })();
  </script>
  <script>
    (async function checkPendingPaymentAfterReturn() {
      let paymentId = '';
      try {
        paymentId = sessionStorage.getItem('pendingPaymentId') || '';
      } catch (e) {
        return;
      }
      if (!paymentId) return;

      try {
        const res = await fetch('/api/payment-status.php?paymentId=' + encodeURIComponent(paymentId), {
          method: 'GET',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json' },
        });
        const data = await res.json().catch(() => ({}));
        if (res.ok && data.ok && data.payment && data.payment.status === 'paid') {
          const successUrl = (typeof data.redirect_to === 'string' && data.redirect_to)
            ? data.redirect_to
            : '/pagamento-sucesso.php?paymentId=' + encodeURIComponent(paymentId);
          try {
            sessionStorage.removeItem('pendingPaymentId');
            sessionStorage.removeItem('pendingPaymentSuccessUrl');
          } catch (e) {
            // Sem impacto no fluxo.
          }
          window.location.href = successUrl;
          return;
        }
      } catch (e) {
        // Não interrompe o uso normal do dashboard.
      }
    })();
  </script>
</body>
</html>
