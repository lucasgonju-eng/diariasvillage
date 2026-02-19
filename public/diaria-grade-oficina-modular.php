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
use App\Services\OficinaModularGradeService;
use App\SupabaseClient;

$user = Helpers::requireAuthWeb();
$diariaId = isset($_GET['diariaId']) ? trim((string) $_GET['diariaId']) : '';
if ($diariaId === '') {
    http_response_code(404);
    echo 'Diária não informada.';
    exit;
}

$client = new SupabaseClient(new HttpClient());
$guardianResult = $client->select('guardians', 'select=*&id=eq.' . rawurlencode((string) $user['id']) . '&limit=1');
if (!$guardianResult['ok'] || empty($guardianResult['data'][0])) {
    http_response_code(403);
    echo 'Acesso não autorizado.';
    exit;
}

$guardian = $guardianResult['data'][0];
$guardianId = (string) ($guardian['id'] ?? '');

$diariaResult = $client->select(
    'diaria',
    'select=*'
    . '&id=eq.' . rawurlencode($diariaId)
    . '&guardian_id=eq.' . rawurlencode($guardianId)
    . '&limit=1'
);
if (!$diariaResult['ok'] || empty($diariaResult['data'][0])) {
    http_response_code(404);
    echo 'Diária não encontrada.';
    exit;
}

$diaria = $diariaResult['data'][0];
$dataDiaria = (string) ($diaria['data_diaria'] ?? '');
$dtDiaria = \DateTimeImmutable::createFromFormat('Y-m-d', $dataDiaria);
if (!$dtDiaria instanceof \DateTimeImmutable) {
    http_response_code(422);
    echo 'Data da diária inválida.';
    exit;
}

$diaSemana = (int) $dtDiaria->format('N');
$diasNomes = [
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado',
    7 => 'Domingo',
];
$diaNome = $diasNomes[$diaSemana] ?? 'Dia inválido';
$dataFormatada = $dtDiaria->format('d/m/Y');
$isDiaUtil = $diaSemana >= 1 && $diaSemana <= 5;

$message = isset($_GET['m']) ? trim((string) $_GET['m']) : '';
$error = isset($_GET['e']) ? trim((string) $_GET['e']) : '';
$proximaSemana = isset($_GET['proxima_semana']) && (string) $_GET['proxima_semana'] === '1';
$fromUpsell = (isset($_GET['from_upsell']) && (string) $_GET['from_upsell'] === '1');
$preselectOficinaId = isset($_GET['preselect_oficina_modular_id']) ? trim((string) $_GET['preselect_oficina_modular_id']) : '';

$service = new OficinaModularGradeService($client);
if ($fromUpsell && $preselectOficinaId !== '') {
    $auto = $service->selecionarOficinaModular($diariaId, $preselectOficinaId);
    if (($auto['ok'] ?? false) === true) {
        $message = "Pronto! Já reservei o próximo encontro 😊 Agora é só confirmar e seguir para o pagamento.";
    } else {
        $reason = (string) ($auto['reason'] ?? '');
        if ($reason === 'CONFLITO_SLOT' || $reason === 'CAPACIDADE_ESGOTADA') {
            $error = "Quase lá 😊\nNesse horário a Oficina Modular não está disponível. Mas você já está no dia certo — escolha outra opção na grade ou selecione outro horário.";
        } elseif ($reason === 'OFICINA_FORA_DO_DIA') {
            $error = "Quase lá 😊\nNão foi possível pré-selecionar automaticamente. Você já está no dia certo para montar sua grade.";
        } else {
            $error = (string) ($auto['message'] ?? $auto['error'] ?? 'Não foi possível pré-selecionar a Oficina Modular.');
        }
    }
}

// Grade fixa: exibimos sempre 2 horarios uteis (sem sabado/domingo), mesmo sem dados no banco.
$slots = [];
if ($isDiaUtil) {
    $slotsFixos = [
        ['hora_inicio' => '14:00', 'hora_fim' => '15:00'],
        ['hora_inicio' => '15:40', 'hora_fim' => '16:40'],
    ];
    foreach ($slotsFixos as $slotFixo) {
        $slotId = $service->buildSlotIdFromDayAndTime($diaSemana, $slotFixo['hora_inicio']);
        if ($slotId === null) {
            continue;
        }
        $slots[] = [
            'slot_id' => $slotId,
            'dia_semana' => $diaSemana,
            'hora_inicio' => $slotFixo['hora_inicio'],
            'hora_fim' => $slotFixo['hora_fim'],
        ];
    }
}

$travadosResult = $client->select(
    'diaria_slots_travados',
    'select=id,slot_id,oficina_modular_id,oficina_modular:oficina_modular_id(id,nome)'
    . '&diaria_id=eq.' . rawurlencode($diariaId)
);
$travados = ($travadosResult['ok'] && is_array($travadosResult['data'])) ? $travadosResult['data'] : [];

$ocupacaoPorSlot = [];
foreach ($travados as $travado) {
    $slot = (string) ($travado['slot_id'] ?? '');
    if ($slot === '') {
        continue;
    }
    $oficinaJoin = is_array($travado['oficina_modular'] ?? null) ? $travado['oficina_modular'] : [];
    $ocupacaoPorSlot[$slot] = [
        'oficina_id' => (string) ($travado['oficina_modular_id'] ?? ''),
        'nome' => (string) ($oficinaJoin['nome'] ?? 'Oficina'),
    ];
}

$oficinasResult = $client->select(
    'oficina_modular',
    'select=id,nome,ativa,status_quorum,tipo,capacidade,data_inicio_validade,data_fim_validade'
    . '&ativa=eq.true'
    . '&order=nome.asc'
);
$oficinas = ($oficinasResult['ok'] && is_array($oficinasResult['data'])) ? $oficinasResult['data'] : [];

$horariosResult = $client->select(
    'oficina_modular_horarios',
    'select=id,oficina_modular_id,dia_semana,hora_inicio,hora_fim'
    . '&order=oficina_modular_id.asc,dia_semana.asc,hora_inicio.asc'
);
$horarios = ($horariosResult['ok'] && is_array($horariosResult['data'])) ? $horariosResult['data'] : [];
$horariosPorOficina = [];
foreach ($horarios as $h) {
    $ofId = (string) ($h['oficina_modular_id'] ?? '');
    if ($ofId === '') {
        continue;
    }
    if (!isset($horariosPorOficina[$ofId])) {
        $horariosPorOficina[$ofId] = [];
    }
    $horariosPorOficina[$ofId][] = $h;
}

$mensagemForaDia = "Essa Oficina Modular rola em outro dia da semana 😊\nSe você quiser, dá pra adicionar mais uma diária nesse outro dia e garantir a participação completa.";
$oficinasUi = [];
$mapaPasso6Excel = [
    1 => 'OM1 - Futsal',
    2 => 'OM2 - Criação em Arte e Design',
    3 => 'OM3 - Arte Além do Papel',
    4 => 'OM4 - Teatro',
    5 => 'OM5 - Einstein English as a Foreign Language',
    6 => 'OM6 - Divertindo-se na Queimada',
    7 => 'OM7 - Voleibol',
    8 => 'OM8 - Arte Circense',
    9 => 'OM9 - EinsteinChef - Sabores Saudáveis',
    10 => 'OM10 - Banda Einstein Village',
    11 => 'OM11 - Oficina de Xadrez',
    12 => 'OM12 - Desenho em Foco: da Imaginação à Realidade',
];
$normalizarOficinaExcel = static function (string $nomeOriginal) use ($mapaPasso6Excel): string {
    $nomeTrim = trim($nomeOriginal);
    if (preg_match('/^teste\s+passo6\b/i', $nomeTrim)) {
        return '';
    }

    if (stripos($nomeOriginal, 'trilha do conhecimento') !== false || stripos($nomeOriginal, 'trilhas do conhecimento') !== false) {
        return 'Trilhas do Conhecimento';
    }

    // Padroniza nomes OM já existentes para manter acentuação oficial no card/modal.
    if (preg_match('/\bom\s*0*(\d{1,2})\b/i', $nomeTrim, $omMatch)) {
        $omCode = (int) ($omMatch[1] ?? 0);
        if ($omCode >= 1 && $omCode <= 12 && isset($mapaPasso6Excel[$omCode])) {
            return $mapaPasso6Excel[$omCode];
        }
    }
    if (stripos($nomeTrim, 'einsteinchef') !== false) {
        return $mapaPasso6Excel[9];
    }
    if (stripos($nomeTrim, 'english as a foreign language') !== false) {
        return $mapaPasso6Excel[5];
    }

    // Normaliza apenas legado real "Passo6 ...", sem capturar nomes de teste.
    if (!preg_match('/^passo6\b/i', $nomeTrim)) {
        return $nomeOriginal;
    }

    if (preg_match('/S(\d+)/i', $nomeOriginal, $m)) {
        $turma = (int) ($m[1] ?? 1);
        return $mapaPasso6Excel[$turma] ?? ('Oficina S' . $turma);
    }

    return $mapaPasso6Excel[1];
};

foreach ($oficinas as $oficina) {
    $ofId = (string) ($oficina['id'] ?? '');
    $nomeOriginal = (string) ($oficina['nome'] ?? 'Oficina');
    $nomeNormalizado = $normalizarOficinaExcel($nomeOriginal);
    if ($nomeNormalizado === '') {
        continue;
    }
    $listaHorarios = $horariosPorOficina[$ofId] ?? [];
    $encontrosHoje = [];
    $segundoDia = null;
    $segundoHorario = null;
    $encontrosFormatados = [];

    foreach ($listaHorarios as $h) {
        $d = (int) ($h['dia_semana'] ?? 0);
        $horaIniFmt = substr((string) ($h['hora_inicio'] ?? ''), 0, 5);
        $horaFimFmt = substr((string) ($h['hora_fim'] ?? ''), 0, 5);
        $encontrosFormatados[] = [
            'dia_semana' => $d,
            'dia_nome' => $diasNomes[$d] ?? ('Dia ' . $d),
            'hora_inicio' => $horaIniFmt,
            'hora_fim' => $horaFimFmt,
        ];
        if ($d === $diaSemana) {
            $encontrosHoje[] = $h;
        }
        if ($d !== $diaSemana && $d >= 1 && $d <= 7 && $segundoDia === null) {
            $segundoDia = $d;
            $segundoHorario = $horaIniFmt . '–' . $horaFimFmt;
        }
    }

    // Limite de vagas temporariamente desativado para todas as oficinas.
    $capacidade = 0;
    if (!empty($encontrosHoje)) {
        foreach ($encontrosHoje as $encontroDia) {
            $statusUi = 'DISPONIVEL';
            $slotIdDia = null;
            $horarioDiaLabel = null;
            $isSelecionada = false;
            $vagasRestantes = null;
            $totalConfirmadas = 0;

            $horaIni = substr((string) ($encontroDia['hora_inicio'] ?? ''), 0, 5);
            $horaFim = substr((string) ($encontroDia['hora_fim'] ?? ''), 0, 5);
            $slotIdDia = $service->buildSlotIdFromDayAndTime($diaSemana, $horaIni);
            $horarioDiaLabel = $horaIni . '–' . $horaFim;

            if ($slotIdDia !== null && isset($ocupacaoPorSlot[$slotIdDia])) {
                $isSelecionada = (($ocupacaoPorSlot[$slotIdDia]['oficina_id'] ?? '') === $ofId);
                $statusUi = $isSelecionada ? 'SELECIONADA' : 'CONFLITO';
            } else {
                $statusUi = 'DISPONIVEL';
            }

            $ocupacaoAtual = $service->getOcupacaoAtual($ofId, $dataDiaria, $diaSemana);
            $totalConfirmadas = (int) ($ocupacaoAtual['total_confirmadas'] ?? 0);
            if ($capacidade > 0) {
                $vagasRestantes = (int) ($ocupacaoAtual['vagas_restantes'] ?? max($capacidade - $totalConfirmadas, 0));
            }

            $oficinasUi[] = [
                'id' => $ofId,
                'nome' => (string) $nomeNormalizado,
                'status_ui' => $statusUi,
                'slot_id_dia' => $slotIdDia,
                'horario_dia_label' => $horarioDiaLabel,
                'is_selecionada' => $isSelecionada,
                'encontros' => $encontrosFormatados,
                'capacidade' => $capacidade,
                'total_confirmadas' => $totalConfirmadas,
                'vagas_restantes' => $vagasRestantes,
                'possui_segundo_encontro' => $segundoDia !== null,
                'segundo_dia_semana' => $segundoDia,
                'segundo_horario_label' => $segundoHorario,
                'fora_do_dia_message' => null,
                'fora_do_dia_cta' => null,
            ];
        }
    } else {
        $oficinasUi[] = [
            'id' => $ofId,
            'nome' => (string) $nomeNormalizado,
            'status_ui' => 'FORA_DO_DIA',
            'slot_id_dia' => null,
            'horario_dia_label' => null,
            'is_selecionada' => false,
            'encontros' => $encontrosFormatados,
            'capacidade' => $capacidade,
            'total_confirmadas' => 0,
            'vagas_restantes' => null,
            'possui_segundo_encontro' => $segundoDia !== null,
            'segundo_dia_semana' => $segundoDia,
            'segundo_horario_label' => $segundoHorario,
            'fora_do_dia_message' => $mensagemForaDia,
            'fora_do_dia_cta' => 'Adicionar diária nesse dia',
        ];
    }
}

// Deduplica cards por nome normalizado para evitar repeticao visual.
if (!empty($oficinasUi)) {
    $dedupe = [];
    foreach ($oficinasUi as $item) {
        $key = strtolower(trim((string) ($item['nome'] ?? '')));
        $key .= '|' . strtolower(trim((string) ($item['horario_dia_label'] ?? '')));
        if ($key === '') {
            $key = (string) ($item['id'] ?? uniqid('of_', true));
        }

        if (!isset($dedupe[$key])) {
            $dedupe[$key] = $item;
            continue;
        }

        $atual = $dedupe[$key];
        $itemSelecionada = (bool) ($item['is_selecionada'] ?? false);
        $atualSelecionada = (bool) ($atual['is_selecionada'] ?? false);
        if ($itemSelecionada && !$atualSelecionada) {
            $dedupe[$key] = $item;
            continue;
        }

        $atualDisponivel = (($atual['status_ui'] ?? '') === 'DISPONIVEL');
        $itemDisponivel = (($item['status_ui'] ?? '') === 'DISPONIVEL');
        if ($itemDisponivel && !$atualDisponivel) {
            $dedupe[$key] = $item;
        }
    }
    $oficinasUi = array_values($dedupe);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Grade de Oficina Modular</title>
  <link rel="stylesheet" href="/assets/style.css?v=5">
  <style>
    :root{
      --brand-blue:#0a1b4d;
      --brand-blue-2:#163a7a;
      --brand-yellow:#d6b25e;
      --brand-yellow-2:#f2d98a;
      --bg-soft:#f3f6fc;
      --card-border:#d9e2f3;
    }
    body{background:var(--bg-soft);color:#101a2d}
    .grade-wrap{max-width:1280px;margin:24px auto;padding:0 16px}
    .grade-title{margin:0 0 4px 0}
    .grade-dayline{display:block;margin:6px 0 8px 0;font-size:0.5em;font-weight:700;line-height:1.15;color:#24385f}
    .grade-sub{margin:0 0 18px 0;color:#3f4f67}
    .board{display:grid;grid-template-columns:1.2fr .8fr;gap:18px;align-items:start}
    .card{border:1px solid var(--card-border);border-radius:16px;padding:16px;background:#fff;box-shadow:0 8px 22px rgba(10,27,77,.08)}
    .small-muted{font-size:12px;color:#435777}
    .row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .btn-remove{
      background:#fef2f2 !important;
      border:1px solid #f5c2c7 !important;
      color:#b42318 !important;
      font-weight:700;
    }
    .btn-remove:hover{
      background:#fee4e2 !important;
      border-color:#fda29b !important;
      color:#912018 !important;
    }
    .msg-ok{background:#ecf8f1;border:1px solid #bde7d0;padding:10px;border-radius:8px;color:#165f2d;margin-bottom:12px;white-space:pre-line}
    .msg-err{background:#fff2f2;border:1px solid #f2c4c4;padding:10px;border-radius:8px;color:#7e1f1f;margin-bottom:12px;white-space:pre-line}
    .msg-alert-nextweek{background:#fff1f1;border:1px solid #ef9a9a;padding:10px;border-radius:8px;color:#b00020;margin-bottom:12px;font-weight:700;text-decoration:underline}
    .timetable-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
    .day-chip{border-radius:999px;padding:6px 10px;font-size:12px;background:var(--brand-blue);color:#fff}
    .weekbar{display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px 0}
    .weekchip{font-size:11px;padding:5px 9px;border-radius:999px;border:1px solid #c7d4ee;color:var(--brand-blue);background:#f6f9ff;cursor:pointer}
    .weekchip.is-current{background:var(--brand-yellow);border-color:#c99f2f;color:var(--brand-blue);font-weight:700}
    .timetable-grid{display:grid;gap:10px}
    .slot-cell{border:1px solid #dbe5f8;border-radius:12px;padding:12px;background:#f8fbff;transition:all .2s ease}
    .slot-cell.is-preview{border-color:var(--brand-blue);background:#e9f0ff;box-shadow:0 0 0 2px rgba(10,27,77,.12)}
    .slot-cell.is-preview-blocked{border-color:#d08a00;background:#fff7e8}
    .slot-cell.is-occupied{border-color:#3e5f9f;background:#eef4ff}
    .slot-time{font-weight:700}
    .slot-state{font-size:13px;color:#3e5270}
    .slots-summary{margin-top:10px;display:flex;gap:8px;flex-wrap:wrap}
    .chip{font-size:12px;padding:5px 9px;border-radius:999px;background:#ecf2ff;color:#1d325d}
    .of-list{display:grid;gap:10px}
    .of-card.is-trilha{border-left:5px solid var(--brand-blue);background:linear-gradient(180deg,#f7fbff 0%,#eef4ff 100%);box-shadow:0 8px 20px rgba(10,27,77,.12)}
    .of-columns{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .of-col{display:grid;gap:8px;align-content:start}
    .of-col-head{display:flex;align-items:center;gap:8px;font-weight:700;color:var(--brand-blue);font-size:13px}
    .of-col-chip{display:inline-block;font-size:11px;padding:4px 8px;border-radius:999px;background:var(--brand-yellow);color:var(--brand-blue);font-weight:700}
    .of-empty{font-size:12px;color:#60708d;padding:8px 2px}
    .of-card{border:1px solid #dbe5f8;border-radius:12px;padding:12px;background:#fff;transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease}
    .of-card:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(10,27,77,.10)}
    .of-card.is-hover{border-color:var(--brand-blue)}
    .of-card-title{display:flex;justify-content:space-between;align-items:flex-start;gap:8px}
    .of-card-title button{padding:0;border:0;background:none;color:var(--brand-blue);cursor:pointer;font-size:12px;text-decoration:underline}
    .warn{font-size:12px;color:#7a4a00;background:#fff7e6;border:1px solid #ffdf9f;padding:8px;border-radius:8px;margin-top:8px;white-space:pre-line}
    .toast{position:sticky;top:12px;z-index:12}
    .checkout{margin-top:16px}
    .checkout-actions{display:flex;gap:10px;flex-wrap:wrap}
    .modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;padding:16px;z-index:50;opacity:0;transition:opacity .2s ease}
    .modal-bg.is-open{display:flex;opacity:1}
    .modal{width:min(620px,100%);background:#fff;border-radius:16px;border:1px solid var(--card-border);box-shadow:0 18px 44px rgba(0,0,0,.22);padding:16px;transform:scale(.96);transition:transform .2s ease}
    .modal-bg.is-open .modal{transform:scale(1)}
    .modal-head{display:flex;justify-content:space-between;gap:8px;align-items:flex-start}
    .modal-head button{border:0;background:none;cursor:pointer;font-size:18px;line-height:1}
    .modal-block{margin-top:12px;padding-top:12px;border-top:1px solid #ebeff6}
    .enc-list{display:grid;gap:8px}
    .enc-item{font-size:13px;padding:8px;border:1px solid #e7ecf4;border-radius:8px;background:#fafbfd}
    .modal-desc{font-size:13px;line-height:1.55;color:#283f67;background:#f8fbff;border:1px solid #dde8fb;border-radius:10px;padding:10px;white-space:pre-line}
    .modal-skills{font-size:13px;line-height:1.55;color:#1f3a60;background:#f2f7ff;border:1px solid #cfe0ff;border-radius:10px;padding:10px;white-space:pre-line}
    .day-picker{margin-top:12px;display:none;border-top:1px solid #ebeff6;padding-top:12px}
    .day-picker h4{margin:0 0 8px 0;font-size:14px}
    .day-picker-options{display:flex;gap:8px;flex-wrap:wrap}
    .day-picker-options button{border:1px solid #cdd7e8;background:#fff;border-radius:8px;padding:6px 10px;cursor:pointer}
    @media (max-width:1024px){.board{grid-template-columns:1fr}}
    @media (max-width:900px){.of-columns{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="grade-wrap">
    <h1 class="grade-title">
      Grade de Oficina Modular
      <span class="grade-dayline"><?php echo htmlspecialchars($diaNome, ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($dataFormatada, ENT_QUOTES, 'UTF-8'); ?>)</span>
    </h1>
    <p class="grade-sub">Monte sua grade do dia e siga para o pagamento.</p>

    <?php if ($message !== ''): ?>
      <div class="msg-ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="msg-err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($proximaSemana): ?>
      <div class="msg-alert-nextweek">Você está comprando uma diária para a próxima semana</div>
    <?php endif; ?>

    <div class="board">
      <section class="card">
        <div class="timetable-head">
          <h3>Timetable do dia</h3>
          <span class="day-chip"><?php echo htmlspecialchars($diaNome, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <div class="weekbar" aria-label="Dias úteis">
          <?php foreach ([1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira'] as $diaN => $diaLbl): ?>
            <button type="button"
              class="weekchip js-day-switch <?php echo $diaN === $diaSemana ? 'is-current' : ''; ?>"
              data-day="<?php echo (int) $diaN; ?>"
              aria-label="Ir para <?php echo htmlspecialchars($diaLbl, ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo $diaLbl; ?>
            </button>
          <?php endforeach; ?>
        </div>
        <div id="timetable-grid" class="timetable-grid"></div>
      </section>

      <section class="card">
        <h3>Oficinas Modulares</h3>
        <p class="small-muted">Passe o mouse para preview do slot e clique em detalhes para abrir o modal.</p>
        <div class="of-columns">
          <div class="of-col">
            <div class="of-col-head"><span class="of-col-chip">14h</span> Horário 14:00–15:00</div>
            <div id="oficinas-list-1400" class="of-list"></div>
          </div>
          <div class="of-col">
            <div class="of-col-head"><span class="of-col-chip">15h40</span> Horário 15:40–16:40</div>
            <div id="oficinas-list-1540" class="of-list"></div>
          </div>
        </div>
      </section>
    </div>

    <section class="card checkout">
      <h3>Continuar para pagamento</h3>
      <p class="small-muted">Finalize as seleções e confirme para avançar.</p>
      <div id="selecoes-resumo" class="slots-summary"></div>
      <form id="checkout-form">
        <div class="row">
          <div>
            <label for="billing-type">Forma de pagamento</label><br>
            <select id="billing-type">
              <option value="PIX">PIX</option>
            </select>
          </div>
          <div>
            <label for="billing-document">CPF/CNPJ do responsável</label><br>
            <input type="text" id="billing-document" value="<?php echo htmlspecialchars((string) ($guardian['parent_document'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" />
          </div>
        </div>
        <div class="checkout-actions" style="margin-top:10px">
          <button class="btn btn-primary" type="submit">Continuar para pagamento</button>
          <a class="btn btn-ghost" href="/dashboard.php">Voltar e trocar a diária</a>
        </div>
        <div id="checkout-message" class="small-muted" style="margin-top:8px"></div>
      </form>
    </section>
  </div>

  <div id="oficina-modal-bg" class="modal-bg" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-label="Detalhes da Oficina Modular">
      <div class="modal-head">
        <div>
          <h3 id="modal-title" style="margin:0 0 4px 0;"></h3>
          <div id="modal-sub" class="small-muted"></div>
        </div>
        <button id="modal-close" type="button" aria-label="Fechar">×</button>
      </div>
      <div class="modal-block">
        <strong>Professor(a)</strong>
        <div id="modal-professor" class="modal-desc" style="margin-top:8px"></div>
      </div>
      <div class="modal-block">
        <strong>Encontros</strong>
        <div id="modal-encontros" class="enc-list" style="margin-top:8px"></div>
      </div>
      <div class="modal-block">
        <strong>Descrição da oficina</strong>
        <div id="modal-descricao" class="modal-desc" style="margin-top:8px"></div>
      </div>
      <div class="modal-block">
        <strong>Desenvolvimento do estudante</strong>
        <div id="modal-habilidades" class="modal-skills" style="margin-top:8px"></div>
      </div>
      <div class="modal-block">
        <div class="row">
          <button id="modal-action" class="btn btn-primary" type="button"></button>
          <a class="btn btn-ghost" href="/dashboard.php">Adicionar outra diária</a>
        </div>
      </div>
    </div>
  </div>

  <div id="day-picker-modal-bg" class="modal-bg" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-label="Escolha o dia do encontro">
      <div class="modal-head">
        <div>
          <h3 style="margin:0 0 4px 0;">Qual dia você prefere?</h3>
          <div class="small-muted">Selecione o encontro para criar a diária com pré-seleção.</div>
        </div>
        <button id="day-picker-close" type="button" aria-label="Fechar">×</button>
      </div>
      <div id="day-picker" class="day-picker" style="display:block">
        <h4>Encontros disponíveis</h4>
        <div id="day-picker-options" class="day-picker-options"></div>
      </div>
    </div>
  </div>

  <script>
    const diariaId = <?php echo json_encode($diariaId, JSON_UNESCAPED_UNICODE); ?>;
    const diaSemana = <?php echo json_encode($diaSemana, JSON_UNESCAPED_UNICODE); ?>;
    const initialSlots = <?php echo json_encode($slots, JSON_UNESCAPED_UNICODE); ?> || [];
    const initialOcupacao = <?php echo json_encode($ocupacaoPorSlot, JSON_UNESCAPED_UNICODE); ?> || {};
    const initialOficinas = <?php echo json_encode($oficinasUi, JSON_UNESCAPED_UNICODE); ?> || [];
    const existingMessage = <?php echo json_encode($message, JSON_UNESCAPED_UNICODE); ?>;
    const existingError = <?php echo json_encode($error, JSON_UNESCAPED_UNICODE); ?>;
    const fromUpsell = <?php echo $fromUpsell ? 'true' : 'false'; ?>;
    const preselectOficinaId = <?php echo json_encode($preselectOficinaId, JSON_UNESCAPED_UNICODE); ?>;
    const isDiaUtil = <?php echo $isDiaUtil ? 'true' : 'false'; ?>;
    const ORIENTADORA_LABEL = 'A Oficina Modular deve ser escolhida pela Orientadora';

    const state = {
      slots: initialSlots.map((s) => ({
        slot_id: s.slot_id,
        hora_inicio: String(s.hora_inicio || '').slice(0, 5),
        hora_fim: String(s.hora_fim || '').slice(0, 5),
      })),
      ocupacao: { ...initialOcupacao },
      oficinas: initialOficinas.map((o) => ({ ...o })),
      orientadoraSlots: {},
      previewSlotId: null,
      previewBlocked: false,
      checkoutToken: null,
    };

    const descricoesPorCodigoOm = {
      1: 'Desenvolve fundamentos do futsal com foco técnico e estratégico.\nTrabalha disciplina, respeito, conduta esportiva e colaboração em equipe.',
      2: 'Na Criação em Arte e Design, os alunos assumem protagonismo em projetos autorais.\nA oficina amplia repertório artístico e estimula criatividade, planejamento e expressão coletiva.',
      3: 'Oficina artística com experimentação de técnicas visuais e linguagem criativa.\nFortalece expressão individual, percepção estética e confiança no processo de criação.',
      4: 'Oficina de teatro com jogos, improvisações e encenações para desenvolver comunicação.\nFortalece autoconfiança, empatia, expressão corporal e convivência em grupo.',
      5: 'Aprendizagem de inglês em abordagem interdisciplinar, com cultura e geografia.\nConteúdos adaptados por nível, com prática aplicada para ampliar comunicação.',
      6: 'Treinamento lúdico e estratégico para aprimorar arremesso, posicionamento e tomada de decisão.\nPromove espírito esportivo, cooperação e desenvolvimento físico.',
      7: 'Vivência dinâmica do voleibol com fundamentos técnicos e noções táticas.\nDesenvolve disciplina, concentração, trabalho em equipe e confiança.',
      8: 'Atividade corporal e criativa para coordenação, ritmo, equilíbrio e expressão.\nIncentiva autonomia, colaboração e segurança na execução de movimentos.',
      9: 'Oficina culinária com foco em hábitos saudáveis e aprendizagem prática.\nIntegra matemática, ciências, cultura e sustentabilidade em experiências de cozinha.',
      10: 'Oficina musical com prática de instrumentos, ritmo e criação coletiva.\nEstimula percepção sonora, criatividade e integração dos alunos em apresentações e dinâmicas.',
      11: 'Atividade estratégica para desenvolver raciocínio lógico e planejamento.\nAprimora concentração, resolução de problemas e pensamento crítico.',
      12: 'Oficina de desenho com técnicas variadas para ampliar percepção e repertório artístico.\nEstimula criatividade, expressão visual e evolução técnica com prática guiada.',
    };
    const descricaoTrilhaConhecimento = 'As Trilhas do Conhecimento acontecem diariamente nos dois horários.\nSão voltadas ao reforço de conteúdos, tira-dúvidas e preparação para avaliações com apoio dos professores.';
    const professoresPorCodigoOm = {
      1: 'Professor Kayo',
      2: 'Professora Juliana Imítria',
      3: 'Professora Amanda',
      4: 'Professora Juliana',
      5: 'Professor Enzo',
      6: 'Professor Kayo',
      7: 'Professor Kayo',
      8: 'Professora Dalila',
      9: 'Professora Clara',
      10: 'Professor Enzo',
      11: 'Professor Alexandre',
      12: 'Professora Amanda',
    };
    const professoresTrilhasConhecimento = 'Professora Ângela, Professora Gyovanna e Professora Regina';
    const habilidadesPorCodigoOm = {
      1: 'Competências-chave: coordenação motora ampla, leitura tática e tomada de decisão sob pressão.\nGanhos pedagógicos: disciplina, autocontrole, cooperação e responsabilidade em equipe.',
      2: 'Competências-chave: pensamento criativo, repertório visual e execução de projetos artísticos.\nGanhos pedagógicos: planejamento, protagonismo, comunicação de ideias e resolução de problemas.',
      3: 'Competências-chave: expressão artística, observação estética e domínio progressivo de técnicas.\nGanhos pedagógicos: autoestima, autonomia criativa e consistência no processo de produção.',
      4: 'Competências-chave: comunicação verbal/não verbal, improvisação e presença cênica.\nGanhos pedagógicos: empatia, inteligência emocional, autoconfiança e trabalho colaborativo.',
      5: 'Competências-chave: escuta ativa, vocabulário funcional e comunicação em contexto real.\nGanhos pedagógicos: fluência progressiva, compreensão intercultural e segurança para se expressar.',
      6: 'Competências-chave: coordenação, agilidade, estratégia e tomada de decisão em jogo.\nGanhos pedagógicos: espírito esportivo, colaboração, persistência e liderança situacional.',
      7: 'Competências-chave: fundamentos técnicos do voleibol, leitura de jogo e posicionamento.\nGanhos pedagógicos: foco, disciplina, cooperação e gestão de desafio.',
      8: 'Competências-chave: ritmo, equilíbrio, coordenação fina e consciência corporal.\nGanhos pedagógicos: confiança, expressão corporal e interação positiva em grupo.',
      9: 'Competências-chave: organização de etapas, execução culinária e noções de alimentação saudável.\nGanhos pedagógicos: autonomia, raciocínio aplicado, colaboração e responsabilidade.',
      10: 'Competências-chave: percepção auditiva, ritmo, técnica instrumental e performance coletiva.\nGanhos pedagógicos: concentração, criatividade, escuta qualificada e trabalho em conjunto.',
      11: 'Competências-chave: raciocínio lógico, antecipação de cenários e planejamento estratégico.\nGanhos pedagógicos: pensamento crítico, atenção sustentada e tomada de decisão estruturada.',
      12: 'Competências-chave: técnicas de desenho, percepção visual e construção de repertório gráfico.\nGanhos pedagógicos: precisão, expressão autoral, constância e evolução técnica.',
    };
    const habilidadesTrilhaConhecimento = 'Competências-chave: consolidação de conteúdos, autorregulação dos estudos e clareza de dúvidas.\nGanhos pedagógicos: autonomia acadêmica, confiança para avaliações e melhora consistente de desempenho.';

    const modalBg = document.querySelector('#oficina-modal-bg');
    const modalTitle = document.querySelector('#modal-title');
    const modalSub = document.querySelector('#modal-sub');
    const modalEncontros = document.querySelector('#modal-encontros');
    const modalProfessor = document.querySelector('#modal-professor');
    const modalDescricao = document.querySelector('#modal-descricao');
    const modalHabilidades = document.querySelector('#modal-habilidades');
    const modalAction = document.querySelector('#modal-action');
    const modalClose = document.querySelector('#modal-close');
    const dayPickerModalBg = document.querySelector('#day-picker-modal-bg');
    const dayPickerClose = document.querySelector('#day-picker-close');
    const dayPickerOptions = document.querySelector('#day-picker-options');

    function escapeHtml(text) {
      return String(text || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function showToast(kind, message) {
      const old = document.querySelector('.toast');
      if (old) old.remove();
      const klass = kind === 'error' ? 'msg-err' : 'msg-ok';
      document.querySelector('.grade-wrap').insertAdjacentHTML('afterbegin', `<div class="${klass} toast">${escapeHtml(message)}</div>`);
    }

    function getOficinaById(id, slotId = '') {
      if (slotId) {
        const bySlot = state.oficinas.find((o) => o.id === id && String(o.slot_id_dia || '') === String(slotId));
        if (bySlot) return bySlot;
      }
      return state.oficinas.find((o) => o.id === id) || null;
    }

    function slotIdPorColuna(coluna) {
      if (coluna === '1400') {
        const slot = state.slots.find((s) => String(s.hora_inicio || '') === '14:00');
        return slot ? String(slot.slot_id || '') : '';
      }
      if (coluna === '1540') {
        const slot = state.slots.find((s) => String(s.hora_inicio || '') === '15:40');
        return slot ? String(slot.slot_id || '') : '';
      }
      return '';
    }

    function slotEstaCompleto(slotId) {
      if (!slotId) return false;
      if (state.ocupacao[slotId]) return true;
      return !!state.orientadoraSlots[slotId];
    }

    function payloadOrientadoraSlots() {
      return Object.keys(state.orientadoraSlots || {}).filter((slotId) => !!state.orientadoraSlots[slotId]);
    }

    function normalizeText(value) {
      return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
    }

    function extrairCodigoOm(nome) {
      const normalized = normalizeText(nome);
      const m = normalized.match(/\bom\s*0*(\d{1,2})\b/);
      if (!m) return null;
      const code = Number(m[1]);
      return Number.isFinite(code) ? code : null;
    }

    function getDescricaoOmByNome(nome) {
      const normalized = normalizeText(nome);
      if (normalized.includes('trilha do conhecimento') || normalized.includes('trilhas do conhecimento')) {
        return descricaoTrilhaConhecimento;
      }

      const codigo = extrairCodigoOm(nome);
      if (codigo !== null && descricoesPorCodigoOm[codigo]) return descricoesPorCodigoOm[codigo];

      // Fallback por texto quando não há código OM no título.
      if (normalized.includes('english as a foreign language') || normalized.includes('ingles')) return descricoesPorCodigoOm[5];
      if (normalized.includes('einsteinchef')) return descricoesPorCodigoOm[9];
      if (normalized.includes('teatro')) return descricoesPorCodigoOm[4];
      if (normalized.includes('xadrez')) return descricoesPorCodigoOm[11];
      if (normalized.includes('futsal')) return descricoesPorCodigoOm[1];

      return 'Oficina Modular com atividades práticas e acompanhamento pedagógico.\nConsulte a coordenação para mais detalhes da proposta deste mês.';
    }

    function getProfessorByNome(nome) {
      const normalized = normalizeText(nome);
      if (normalized.includes('trilha do conhecimento') || normalized.includes('trilhas do conhecimento')) {
        return professoresTrilhasConhecimento;
      }

      const codigo = extrairCodigoOm(nome);
      if (codigo === 5) {
        if (normalized.includes('- 3') || normalized.includes('nivel 3') || normalized.includes('nível 3')) {
          return 'Professor Allan';
        }
        if (normalized.includes('- 2') || normalized.includes('nivel 2') || normalized.includes('nível 2')) {
          return 'Professor Enzo';
        }
      }
      if (codigo !== null && professoresPorCodigoOm[codigo]) return professoresPorCodigoOm[codigo];
      return 'Professor(a) responsável informado(a) pela coordenação';
    }

    function getHabilidadesByNome(nome) {
      const normalized = normalizeText(nome);
      if (normalized.includes('trilha do conhecimento') || normalized.includes('trilhas do conhecimento')) {
        return habilidadesTrilhaConhecimento;
      }
      const codigo = extrairCodigoOm(nome);
      if (codigo !== null && habilidadesPorCodigoOm[codigo]) return habilidadesPorCodigoOm[codigo];
      if (normalized.includes('english as a foreign language') || normalized.includes('ingles')) return habilidadesPorCodigoOm[5];
      if (normalized.includes('einsteinchef')) return habilidadesPorCodigoOm[9];
      if (normalized.includes('teatro')) return habilidadesPorCodigoOm[4];
      if (normalized.includes('xadrez')) return habilidadesPorCodigoOm[11];
      if (normalized.includes('futsal')) return habilidadesPorCodigoOm[1];
      return 'Desenvolvimento integral com foco em competências emocionais, cognitivas e práticas aplicadas ao contexto da oficina.';
    }

    function recalcStatuses() {
      state.oficinas = state.oficinas.map((of) => {
        if (!of.slot_id_dia) {
          return { ...of, status_ui: 'FORA_DO_DIA', is_selecionada: false };
        }
        const ocup = state.ocupacao[of.slot_id_dia];
        if (!ocup) {
          return { ...of, status_ui: 'DISPONIVEL', is_selecionada: false };
        }
        if ((ocup.oficina_id || '') === of.id) {
          return { ...of, status_ui: 'SELECIONADA', is_selecionada: true };
        }
        return { ...of, status_ui: 'CONFLITO', is_selecionada: false };
      });
    }

    function limparParamsUpsellUrl() {
      if (!fromUpsell && !preselectOficinaId) return;
      const url = new URL(window.location.href);
      url.searchParams.delete('from_upsell');
      url.searchParams.delete('preselect_oficina_modular_id');
      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState({}, '', url.toString());
      }
    }

    function renderTimetable() {
      const root = document.querySelector('#timetable-grid');
      root.innerHTML = '';

      if (!state.slots.length) {
        root.innerHTML = isDiaUtil
          ? '<p class="small-muted">Sem seleção ainda para os horários 14:00-15:00 e 15:40-16:40.</p>'
          : '<p class="small-muted">A grade é exibida apenas de segunda a sexta.</p>';
        return;
      }

      state.slots.forEach((slot) => {
        const ocup = state.ocupacao[slot.slot_id] || null;
        const isPreview = state.previewSlotId && state.previewSlotId === slot.slot_id;
        const classes = ['slot-cell'];
        if (ocup) classes.push('is-occupied');
        if (isPreview) classes.push(state.previewBlocked ? 'is-preview-blocked' : 'is-preview');

        root.insertAdjacentHTML('beforeend', `
          <div class="${classes.join(' ')}">
            <div class="slot-time">${escapeHtml(slot.hora_inicio)}–${escapeHtml(slot.hora_fim)}</div>
            <div class="slot-state">${ocup ? escapeHtml(ocup.nome || 'Oficina selecionada') : (state.orientadoraSlots[slot.slot_id] ? escapeHtml(ORIENTADORA_LABEL) : 'Livre')}</div>
            <div class="row" style="margin-top:8px">
              ${ocup
                ? `<button class="btn btn-sm btn-remove js-remover" data-oficina="${escapeHtml(ocup.oficina_id || '')}" data-slot="${escapeHtml(slot.slot_id || '')}" type="button">Remover</button>`
                : (state.orientadoraSlots[slot.slot_id]
                    ? `<button class="btn btn-sm btn-remove js-remover-orientadora" data-slot="${escapeHtml(slot.slot_id || '')}" type="button">Remover</button>`
                    : '<span class="chip">Livre</span>')}
            </div>
          </div>
        `);
      });

      root.querySelectorAll('.js-remover').forEach((btn) => {
        btn.addEventListener('click', async () => {
          await removerOficina(btn.dataset.oficina || '', btn.dataset.slot || '');
        });
      });
      root.querySelectorAll('.js-remover-orientadora').forEach((btn) => {
        btn.addEventListener('click', () => {
          const slotId = String(btn.dataset.slot || '');
          if (!slotId) return;
          delete state.orientadoraSlots[slotId];
          renderAll();
          showToast('success', 'Opção da Orientadora removida.');
        });
      });
    }

    function buttonByStatus(of) {
      if (of.is_orientadora === true) {
        if (of.status_ui === 'SELECIONADA') {
          return '<button class="btn btn-sm btn-remove js-remover-orientadora-card" type="button">Remover</button>';
        }
        if (of.status_ui === 'CONFLITO') {
          return '<button class="btn btn-ghost btn-sm" disabled type="button">Horário já preenchido</button>';
        }
        return '<button class="btn btn-primary btn-sm js-selecionar-orientadora" type="button">Escolher opção da Orientadora</button>';
      }
      if (of.status_ui === 'DISPONIVEL') return '<button class="btn btn-primary btn-sm js-selecionar" type="button">Selecionar</button>';
      if (of.status_ui === 'SELECIONADA') return '<button class="btn btn-sm btn-remove js-remover-card" type="button">Remover</button>';
      if (of.status_ui === 'CONFLITO') return '<button class="btn btn-ghost btn-sm" disabled type="button">Conflito de horário</button>';
      return '<button class="btn btn-ghost btn-sm" disabled type="button">Não disponível hoje</button>';
    }

    function colunaOficina(of) {
      if (String(of.status_ui || '') === 'FORA_DO_DIA') return null;
      const label = String(of.horario_dia_label || '').trim();
      if (label.startsWith('14:00')) return '1400';
      if (label.startsWith('15:40')) return '1540';
      const slot = String(of.slot_id_dia || '');
      if (slot.endsWith('_14:00')) return '1400';
      if (slot.endsWith('_15:40')) return '1540';
      return null;
    }

    function renderCardsOficinas(root, lista) {
      root.innerHTML = '';
      if (!lista.length) {
        root.innerHTML = '<div class="of-empty">Sem oficinas para este horário.</div>';
        return;
      }

      lista.forEach((of) => {
        const trilhaClass = of.is_trilha ? ' is-trilha' : '';
        root.insertAdjacentHTML('beforeend', `
          <article class="of-card${trilhaClass}" data-oficina="${escapeHtml(of.id)}" data-slot="${escapeHtml(of.slot_id_dia || '')}">
            <div class="of-card-title">
              <strong>${escapeHtml(of.nome)}</strong>
              <button type="button" class="js-detalhes" aria-label="Ver detalhes da oficina ${escapeHtml(of.nome)}">Detalhes</button>
            </div>
            <div class="small-muted" style="margin-top:6px">
              ${of.horario_dia_label ? `Hoje: ${escapeHtml(of.horario_dia_label)}` : 'Acontece em outro dia'}
            </div>
            <div class="small-muted">${of.is_orientadora ? 'Orientação da escola' : `Professor(a): <strong>${escapeHtml(getProfessorByNome(of.nome || ''))}</strong>`}</div>
            ${Number(of.capacidade || 0) > 0 ? `<div class="small-muted">Vagas restantes: <strong>${escapeHtml(of.vagas_restantes)}</strong></div>` : ''}
            <div class="row" style="margin-top:8px">${buttonByStatus(of)}</div>
            ${of.status_ui === 'FORA_DO_DIA' ? `<div class="warn">${escapeHtml(of.fora_do_dia_message || '')}</div><div class="small-muted" style="margin-top:6px"><button type="button" class="btn btn-ghost btn-sm js-upsell-fora-dia">${escapeHtml(of.fora_do_dia_cta || 'Adicionar diária nesse dia')}</button></div>` : ''}
          </article>
        `);
      });
    }

    function bindOficinaCardEvents(root) {
      root.querySelectorAll('.of-card').forEach((card) => {
        const ofId = card.getAttribute('data-oficina') || '';
        const slotId = card.getAttribute('data-slot') || '';
        card.addEventListener('mouseenter', () => {
          if (!slotId) return;
          const of = getOficinaById(ofId);
          state.previewSlotId = slotId;
          state.previewBlocked = !!(of && of.status_ui === 'CONFLITO');
          card.classList.add('is-hover');
          renderTimetable();
        });
        card.addEventListener('mouseleave', () => {
          state.previewSlotId = null;
          state.previewBlocked = false;
          card.classList.remove('is-hover');
          renderTimetable();
        });
      });

      root.querySelectorAll('.js-selecionar').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
          const card = e.target.closest('.of-card');
          const ofId = card ? (card.getAttribute('data-oficina') || '') : '';
          const slotId = card ? (card.getAttribute('data-slot') || '') : '';
          await selecionarOficina(ofId, slotId);
        });
      });

      root.querySelectorAll('.js-remover-card').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
          const card = e.target.closest('.of-card');
          const ofId = card ? (card.getAttribute('data-oficina') || '') : '';
          const slotId = card ? (card.getAttribute('data-slot') || '') : '';
          await removerOficina(ofId, slotId);
        });
      });

      root.querySelectorAll('.js-detalhes').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const card = e.target.closest('.of-card');
          const ofId = card ? (card.getAttribute('data-oficina') || '') : '';
          const slotId = card ? (card.getAttribute('data-slot') || '') : '';
          openModal(ofId, slotId);
        });
      });

      root.querySelectorAll('.js-selecionar-orientadora').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          const card = e.target.closest('.of-card');
          const slotId = card ? (card.getAttribute('data-slot') || '') : '';
          if (!slotId) return;
          if (state.ocupacao[slotId]) {
            showToast('error', 'Esse horário já possui uma oficina selecionada.');
            return;
          }
          state.orientadoraSlots[slotId] = true;
          renderAll();
          showToast('success', 'Horário marcado para escolha pela Orientadora.');
        });
      });

      root.querySelectorAll('.js-remover-orientadora-card').forEach((btn) => {
        btn.addEventListener('click', (e) => {
          const card = e.target.closest('.of-card');
          const slotId = card ? (card.getAttribute('data-slot') || '') : '';
          if (!slotId) return;
          delete state.orientadoraSlots[slotId];
          renderAll();
          showToast('success', 'Opção da Orientadora removida.');
        });
      });

      root.querySelectorAll('.js-upsell-fora-dia').forEach((btn) => {
        btn.addEventListener('click', async (e) => {
          const card = e.target.closest('.of-card');
          const ofId = card ? (card.getAttribute('data-oficina') || '') : '';
          await acionarUpsellForaDoDia(ofId);
        });
      });
    }

    function renderOficinas() {
      const root1400 = document.querySelector('#oficinas-list-1400');
      const root1540 = document.querySelector('#oficinas-list-1540');
      const lista1400 = [];
      const lista1540 = [];

      state.oficinas.forEach((of) => {
        const col = colunaOficina(of);
        if (col === '1400') {
          lista1400.push(of);
        } else if (col === '1540') {
          lista1540.push(of);
        }
      });
      const trilhaFirst = (a, b) => {
        const aNome = normalizeText(a.nome || '');
        const bNome = normalizeText(b.nome || '');
        const aTrilha = aNome.includes('trilha do conhecimento') || aNome.includes('trilhas do conhecimento');
        const bTrilha = bNome.includes('trilha do conhecimento') || bNome.includes('trilhas do conhecimento');
        if (aTrilha && !bTrilha) return -1;
        if (!aTrilha && bTrilha) return 1;
        return String(a.nome || '').localeCompare(String(b.nome || ''), 'pt-BR');
      };
      const forceTrilhasTop = (lista) => {
        const trilhas = [];
        const demais = [];
        lista.forEach((item) => {
          const n = normalizeText(item.nome || '');
          if (n.includes('trilha do conhecimento') || n.includes('trilhas do conhecimento')) {
            trilhas.push(item);
          } else {
            demais.push(item);
          }
        });
        // Se houver mais de um registro de trilhas no mesmo horario, mantém apenas o primeiro.
        const trilhaUnica = trilhas.length > 0 ? [trilhas[0]] : [];
        return [...trilhaUnica, ...demais];
      };
      const dedupePorNomeHorario = (lista) => {
        const map = new Map();
        lista.forEach((item) => {
          const nomeNorm = normalizeText(item.nome || '').replace(/\s+/g, ' ').trim();
          const codigoOm = extrairCodigoOm(item.nome || '');
          const horarioNorm = normalizeText(item.horario_dia_label || '').replace(/\s+/g, ' ').trim();
          const slotNorm = normalizeText(item.slot_id_dia || '').replace(/\s+/g, ' ').trim();
          const baseKey = codigoOm !== null ? `om${codigoOm}` : nomeNorm;
          const key = `${baseKey}|${horarioNorm || slotNorm}`;
          if (!map.has(key)) {
            map.set(key, item);
            return;
          }
          const atual = map.get(key);
          const rank = (x) => {
            const st = String(x.status_ui || '');
            if (st === 'SELECIONADA') return 3;
            if (st === 'DISPONIVEL') return 2;
            if (st === 'CONFLITO') return 1;
            return 0;
          };
          const itemTemUpsell = !!item.possui_segundo_encontro;
          const atualTemUpsell = !!atual.possui_segundo_encontro;
          if (itemTemUpsell && !atualTemUpsell) {
            map.set(key, item);
            return;
          }
          const itemEncontros = Array.isArray(item.encontros) ? item.encontros.length : 0;
          const atualEncontros = Array.isArray(atual.encontros) ? atual.encontros.length : 0;
          if (itemEncontros > atualEncontros) {
            map.set(key, item);
            return;
          }
          if (rank(item) > rank(atual)) map.set(key, item);
        });
        return Array.from(map.values());
      };

      const limpa1400 = dedupePorNomeHorario(lista1400);
      const limpa1540 = dedupePorNomeHorario(lista1540);
      limpa1400.sort(trilhaFirst);
      limpa1540.sort(trilhaFirst);
      const final1400 = forceTrilhasTop(limpa1400);
      const final1540 = forceTrilhasTop(limpa1540);

      const slot1400 = slotIdPorColuna('1400');
      const slot1540 = slotIdPorColuna('1540');
      const orientadora1400 = {
        id: '__orientadora_1400__',
        nome: ORIENTADORA_LABEL,
        status_ui: !slot1400 ? 'FORA_DO_DIA' : (slotEstaCompleto(slot1400) ? (state.orientadoraSlots[slot1400] ? 'SELECIONADA' : 'CONFLITO') : 'DISPONIVEL'),
        slot_id_dia: slot1400,
        horario_dia_label: '14:00–15:00',
        is_selecionada: !!state.orientadoraSlots[slot1400],
        is_orientadora: true,
        encontros: [],
        capacidade: 0,
      };
      const orientadora1540 = {
        id: '__orientadora_1540__',
        nome: ORIENTADORA_LABEL,
        status_ui: !slot1540 ? 'FORA_DO_DIA' : (slotEstaCompleto(slot1540) ? (state.orientadoraSlots[slot1540] ? 'SELECIONADA' : 'CONFLITO') : 'DISPONIVEL'),
        slot_id_dia: slot1540,
        horario_dia_label: '15:40–16:40',
        is_selecionada: !!state.orientadoraSlots[slot1540],
        is_orientadora: true,
        encontros: [],
        capacidade: 0,
      };
      const finalComOrientadora1400 = [orientadora1400, ...final1400];
      const finalComOrientadora1540 = [orientadora1540, ...final1540];

      renderCardsOficinas(root1400, finalComOrientadora1400);
      renderCardsOficinas(root1540, finalComOrientadora1540);
      bindOficinaCardEvents(root1400);
      bindOficinaCardEvents(root1540);
    }

    function renderResumo() {
      const root = document.querySelector('#selecoes-resumo');
      const selecionadas = Object.values(state.ocupacao || {});
      const orientadoraSelecoes = payloadOrientadoraSlots().map((slotId) => {
        const slot = state.slots.find((s) => String(s.slot_id || '') === slotId);
        const horario = slot ? `${slot.hora_inicio}–${slot.hora_fim}` : 'horário';
        return { nome: `${ORIENTADORA_LABEL} (${horario})` };
      });
      const tudoSelecionado = [...selecionadas, ...orientadoraSelecoes];
      if (!tudoSelecionado.length) {
        root.innerHTML = '<span class="chip">Nenhuma oficina selecionada</span>';
        return;
      }
      root.innerHTML = tudoSelecionado.map((s) => `<span class="chip">${escapeHtml(s.nome || 'Oficina')}</span>`).join('');
    }

    function renderAll() {
      recalcStatuses();
      renderTimetable();
      renderOficinas();
      renderResumo();
    }

    async function postJson(url, body) {
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body || {})
      });
      let data = {};
      try { data = await res.json(); } catch (_) {}
      return { ok: res.ok, status: res.status, data };
    }

    function bindDaySwitch() {
      document.querySelectorAll('.js-day-switch').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const target = Number(btn.dataset.day || 0);
          if (!target || target === Number(diaSemana)) return;
          btn.disabled = true;
          const r = await postJson('/api/diaria-navegar-dia.php', {
            diaria_id: diariaId,
            target_dia_semana: target,
          });
          btn.disabled = false;
          if (!r.ok || !r.data.ok) {
            showToast('error', r.data.error || 'Não foi possível alternar o dia da grade.');
            return;
          }
          if (r.data.redirect_url) {
            window.location.href = r.data.redirect_url;
          }
        });
      });
    }

    async function selecionarOficina(oficinaId, slotId = '') {
      if (!oficinaId) return;
      const url = `/api/oficina-modular-grade-selecionar.php?diariaId=${encodeURIComponent(diariaId)}&oficinaId=${encodeURIComponent(oficinaId)}&slotId=${encodeURIComponent(slotId || '')}`;
      const r = await postJson(url, {});
      if (!r.ok || !r.data.ok) {
        if ((r.data.reason || '') === 'CONFLITO_SLOT') {
          showToast('error', 'Conflito de horário neste slot. Remova primeiro a oficina já escolhida nesse horário.');
        } else {
          showToast('error', r.data.message || r.data.error || 'Não foi possível selecionar a oficina.');
        }
        return;
      }

      const slot = r.data.slot_travado || '';
      if (slot) {
        delete state.orientadoraSlots[slot];
        const of = getOficinaById(oficinaId);
        state.ocupacao[slot] = {
          oficina_id: oficinaId,
          nome: of ? of.nome : 'Oficina'
        };
      }

      renderAll();
      showToast('success', r.data.upsell_message || 'Oficina selecionada com sucesso.');
    }

    async function removerOficina(oficinaId, slotId = '') {
      if (!oficinaId) return;
      const url = `/api/oficina-modular-grade-remover.php?diariaId=${encodeURIComponent(diariaId)}&oficinaId=${encodeURIComponent(oficinaId)}&slotId=${encodeURIComponent(slotId || '')}`;
      const r = await postJson(url, {});
      if (!r.ok || !r.data.ok) {
        showToast('error', r.data.error || 'Não foi possível remover a oficina.');
        return;
      }

      const slot = r.data.slot_liberado || '';
      if (slot && state.ocupacao[slot] && (state.ocupacao[slot].oficina_id || '') === oficinaId) {
        delete state.ocupacao[slot];
      } else {
        Object.keys(state.ocupacao).forEach((key) => {
          if ((state.ocupacao[key].oficina_id || '') === oficinaId) {
            delete state.ocupacao[key];
          }
        });
      }

      renderAll();
      closeModal();
      showToast('success', 'Oficina removida.');
    }

    function abrirDayPicker(oficina, encontros) {
      dayPickerOptions.innerHTML = '';
      encontros.forEach((enc) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = `${enc.dia_nome} ${enc.hora_inicio}-${enc.hora_fim}`;
        btn.addEventListener('click', async () => {
          await criarDiariaParaEncontro(oficina.id, enc.dia_semana);
        });
        dayPickerOptions.appendChild(btn);
      });
      dayPickerModalBg.classList.add('is-open');
      dayPickerModalBg.setAttribute('aria-hidden', 'false');
    }

    function fecharDayPicker() {
      dayPickerModalBg.classList.remove('is-open');
      dayPickerModalBg.setAttribute('aria-hidden', 'true');
    }

    async function criarDiariaParaEncontro(oficinaId, targetDiaSemana) {
      const payload = {};
      if (typeof targetDiaSemana === 'number') payload.target_dia_semana = targetDiaSemana;
      const url = `/api/oficina-modular-criar-diaria-encontro.php?oficinaId=${encodeURIComponent(oficinaId)}`;
      const r = await postJson(url, payload);
      if (!r.ok || !r.data.ok) {
        if ((r.data.reason || '') === 'TARGET_DIA_SEMANA_REQUIRED' && Array.isArray(r.data.dias_disponiveis)) {
          const of = getOficinaById(oficinaId);
          const encontros = (of && Array.isArray(of.encontros)) ? of.encontros : [];
          const filtrados = encontros.filter((e) => r.data.dias_disponiveis.includes(Number(e.dia_semana)));
          abrirDayPicker(of, filtrados);
          return;
        }
        showToast('error', r.data.error || 'Não foi possível criar diária para esse encontro.');
        return;
      }
      if (r.data.redirect_url) {
        window.location.href = r.data.redirect_url;
      }
    }

    async function acionarUpsellForaDoDia(oficinaId) {
      const of = getOficinaById(oficinaId);
      if (!of) return;
      const encontros = Array.isArray(of.encontros) ? of.encontros : [];
      const unicos = [];
      encontros.forEach((e) => {
        if (!unicos.some((u) => Number(u.dia_semana) === Number(e.dia_semana))) {
          unicos.push(e);
        }
      });
      if (unicos.length <= 1) {
        await criarDiariaParaEncontro(oficinaId, unicos[0] ? Number(unicos[0].dia_semana) : null);
        return;
      }
      abrirDayPicker(of, unicos);
    }

    function openModal(oficinaId, slotId = '') {
      const of = getOficinaById(oficinaId, slotId);
      if (!of) return;
      if (of.is_orientadora === true) {
        modalTitle.textContent = of.nome || ORIENTADORA_LABEL;
        modalSub.textContent = of.horario_dia_label ? `Horário: ${of.horario_dia_label}` : 'Horário da diária';
        if (modalProfessor) {
          modalProfessor.textContent = 'Escolha feita pela Orientadora da unidade.';
        }
        modalEncontros.innerHTML = `<div class="enc-item"><strong>Escolha assistida</strong><br>A equipe pedagógica define a melhor oficina para este horário.</div>`;
        modalDescricao.textContent = 'Ao escolher esta opção, você autoriza a Orientadora a definir a Oficina Modular mais adequada para o estudante neste horário.';
        modalHabilidades.textContent = 'A escolha considera perfil, objetivos de aprendizagem, equilíbrio de rotina e aproveitamento pedagógico da diária.';
        modalAction.disabled = false;
        if (of.status_ui === 'SELECIONADA') {
          modalAction.textContent = 'Remover';
          modalAction.className = 'btn btn-remove';
          modalAction.onclick = () => {
            const slotId = String(of.slot_id_dia || '');
            if (!slotId) return;
            delete state.orientadoraSlots[slotId];
            renderAll();
            closeModal();
            showToast('success', 'Opção da Orientadora removida.');
          };
        } else if (of.status_ui === 'DISPONIVEL') {
          modalAction.textContent = 'Escolher opção da Orientadora';
          modalAction.className = 'btn btn-primary';
          modalAction.onclick = () => {
            const slotId = String(of.slot_id_dia || '');
            if (!slotId) return;
            if (state.ocupacao[slotId]) {
              showToast('error', 'Esse horário já possui uma oficina selecionada.');
              return;
            }
            state.orientadoraSlots[slotId] = true;
            renderAll();
            closeModal();
            showToast('success', 'Horário marcado para escolha pela Orientadora.');
          };
        } else {
          modalAction.textContent = 'Horário já preenchido';
          modalAction.className = 'btn btn-ghost';
          modalAction.disabled = true;
          modalAction.onclick = null;
        }
        modalBg.classList.add('is-open');
        modalBg.setAttribute('aria-hidden', 'false');
        return;
      }
      const nomeNormalizado = normalizeText(of.nome || '');
      const isTrilhasConhecimento = nomeNormalizado.includes('trilha do conhecimento') || nomeNormalizado.includes('trilhas do conhecimento');
      const professorDaOm = getProfessorByNome(of.nome || '');
      modalTitle.textContent = of.nome || 'Oficina';
      modalSub.textContent = of.horario_dia_label ? `Hoje: ${of.horario_dia_label}` : 'Sem encontro no dia da diária';
      if (Number(of.capacidade || 0) > 0) {
        modalSub.textContent += ` • Vagas restantes: ${of.vagas_restantes}`;
      }
      if (modalProfessor) {
        modalProfessor.textContent = professorDaOm;
      }

      const encontros = Array.isArray(of.encontros) ? of.encontros : [];
      if (isTrilhasConhecimento) {
        modalEncontros.innerHTML = `
          <div class="enc-item"><strong>1º horário</strong><br>Segunda a sexta • 14:00–15:00</div>
          <div class="enc-item"><strong>2º horário</strong><br>Segunda a sexta • 15:40–16:40</div>
        `;
      } else {
        const vistos = new Set();
        const encontrosUnicos = encontros.filter((enc) => {
          const key = `${Number(enc.dia_semana || 0)}|${String(enc.hora_inicio || '')}|${String(enc.hora_fim || '')}`;
          if (vistos.has(key)) return false;
          vistos.add(key);
          return true;
        });
        modalEncontros.innerHTML = encontrosUnicos.map((enc) => {
          const isHoje = Number(enc.dia_semana) === Number(diaSemana);
          const prefix = isHoje ? 'Encontro do dia' : '2º encontro';
          return `<div class="enc-item"><strong>${prefix}</strong><br>${escapeHtml(enc.dia_nome || '')} ${escapeHtml(enc.hora_inicio || '')}–${escapeHtml(enc.hora_fim || '')}</div>`;
        }).join('') || '<div class="enc-item">Sem encontros cadastrados.</div>';
      }
      modalDescricao.textContent = `Professor(a): ${professorDaOm}\n\n${getDescricaoOmByNome(of.nome || '')}`;
      modalHabilidades.textContent = getHabilidadesByNome(of.nome || '');

      modalAction.disabled = false;
      if (of.status_ui === 'DISPONIVEL') {
        modalAction.textContent = 'Selecionar Oficina Modular';
        modalAction.className = 'btn btn-primary';
        modalAction.onclick = () => selecionarOficina(of.id, of.slot_id_dia || '');
      } else if (of.status_ui === 'SELECIONADA') {
        modalAction.textContent = 'Remover';
        modalAction.className = 'btn btn-remove';
        modalAction.onclick = () => removerOficina(of.id, of.slot_id_dia || '');
      } else if (of.status_ui === 'FORA_DO_DIA') {
        modalAction.textContent = 'Não disponível hoje';
        modalAction.className = 'btn btn-ghost';
        modalAction.disabled = true;
        modalAction.onclick = null;
      } else {
        modalAction.textContent = 'Conflito de horário';
        modalAction.className = 'btn btn-ghost';
        modalAction.disabled = true;
        modalAction.onclick = null;
      }

      modalBg.classList.add('is-open');
      modalBg.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
      modalBg.classList.remove('is-open');
      modalBg.setAttribute('aria-hidden', 'true');
    }

    modalClose.addEventListener('click', closeModal);
    modalBg.addEventListener('click', (e) => {
      if (e.target === modalBg) closeModal();
    });
    dayPickerClose.addEventListener('click', fecharDayPicker);
    dayPickerModalBg.addEventListener('click', (e) => {
      if (e.target === dayPickerModalBg) fecharDayPicker();
    });

    renderAll();
    bindDaySwitch();
    limparParamsUpsellUrl();
    if (existingMessage) showToast('success', existingMessage);
    if (existingError) showToast('error', existingError);

    const checkoutForm = document.querySelector('#checkout-form');
    const checkoutMessage = document.querySelector('#checkout-message');
    checkoutForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const slot1400 = slotIdPorColuna('1400');
      const slot1540 = slotIdPorColuna('1540');
      const falta1400 = slot1400 && !slotEstaCompleto(slot1400);
      const falta1540 = slot1540 && !slotEstaCompleto(slot1540);
      if (falta1400 || falta1540) {
        checkoutMessage.textContent = 'Escolha as 2 opções da diária (14:00 e 15:40). Se preferir, use "A Oficina Modular deve ser escolhida pela Orientadora".';
        return;
      }
      checkoutMessage.textContent = 'Concluindo etapa da grade...';
      const concluir = await postJson('/api/diaria-grade-concluir.php', {
        diaria_id: diariaId,
        orientadora_slots: payloadOrientadoraSlots(),
      });
      if (!concluir.ok || !concluir.data.ok) {
        checkoutMessage.textContent = concluir.data.error || 'Não foi possível concluir a etapa da grade.';
        return;
      }
      state.checkoutToken = concluir.data.checkout_token || null;

      checkoutMessage.textContent = 'Criando pagamento...';
      const payload = {
        diaria_id: diariaId,
        billing_type: document.querySelector('#billing-type').value,
        document: document.querySelector('#billing-document').value.trim(),
        orientadora_slots: payloadOrientadoraSlots(),
        checkout_token: state.checkoutToken,
      };
      const r = await postJson('/api/create-payment.php', payload);
      if (!r.ok || !r.data.ok) {
        if (r.data.redirect_to) {
          window.location.href = r.data.redirect_to;
          return;
        }
        checkoutMessage.textContent = r.data.error || 'Falha ao criar pagamento.';
        return;
      }
      checkoutMessage.textContent = 'Pagamento criado. Redirecionando...';
      if (r.data.invoice_url && r.data.invoice_url !== '#') {
        try {
          if (r.data.payment_id) {
            sessionStorage.setItem('pendingPaymentId', String(r.data.payment_id));
          }
        } catch (e) {
          // Segue normalmente mesmo sem storage.
        }
        // iPhone/Safari: mantém fluxo estável abrindo o Asaas na mesma aba.
        window.location.href = r.data.invoice_url;
        return;
      }
      checkoutMessage.textContent = 'Pagamento criado, mas não foi possível abrir o link do Asaas.';
    });
  </script>
</body>
</html>
