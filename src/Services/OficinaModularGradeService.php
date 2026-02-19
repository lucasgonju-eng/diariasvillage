<?php

namespace App\Services;

use App\HttpClient;
use App\SupabaseClient;

final class OficinaModularGradeService
{
    private const MSG_FORA_DO_DIA = "Essa Oficina Modular acontece em outro dia da semana 😊\nPara participar dela por completo, é só adicionar mais uma diária nesse outro dia. Quer que eu te leve pra escolher agora?";
    private const MSG_UPSELL_SEGUNDO_ENCONTRO = "Perfeito! Você garantiu o encontro de hoje 😊\nEssa Oficina Modular também acontece em outro dia da semana. Se quiser completar a experiência, é só adicionar mais uma diária nesse outro dia.";
    private const MSG_REVALIDACAO_LOTACAO = "Ops! Enquanto você montava a grade, essa Oficina Modular ficou sem vagas.\nSem stress 😊 escolha outra opção para esse horário e siga com a diária.";
    private const MSG_POS_PAGAMENTO_AJUSTE = "Sua diária está confirmada ✅\nUma Oficina Modular ficou sem vagas nesse horário, então ajustamos sua grade automaticamente. Se quiser, você pode escolher outra opção disponível.";

    private SupabaseClient $client;

    public function __construct(?SupabaseClient $client = null)
    {
        $this->client = $client ?? new SupabaseClient(new HttpClient());
    }

    public function selecionarOficinaModular(string $diariaId, string $oficinaModularId, ?string $slotIdPreferido = null): array
    {
        $diaria = $this->buscarDiaria($diariaId);
        if ($diaria === null) {
            return ['ok' => false, 'error' => 'Diária não encontrada.'];
        }

        $diaDiaria = $this->calcularDiaSemanaDiaria($diaria);
        if ($diaDiaria === null) {
            return ['ok' => false, 'error' => 'Não foi possível determinar o dia da diária.'];
        }

        $oficina = $this->buscarOficina($oficinaModularId);
        if ($oficina === null) {
            return ['ok' => false, 'error' => 'Oficina Modular não encontrada.'];
        }

        if (!$this->toBool($oficina['ativa'] ?? false)) {
            return ['ok' => false, 'error' => 'Oficina Modular inativa.', 'reason' => 'OFICINA_INATIVA'];
        }

        if (($oficina['status_quorum'] ?? null) === 'CANCELADA') {
            return ['ok' => false, 'error' => 'Oficina Modular cancelada.', 'reason' => 'OFICINA_CANCELADA'];
        }

        if (($oficina['tipo'] ?? null) === 'OCASIONAL_30D' && !$this->validarJanelaOcasional30d($oficina)) {
            return ['ok' => false, 'error' => 'Oficina fora da janela de validade.', 'reason' => 'FORA_DA_VALIDADE_30D'];
        }

        // Limite de vagas temporariamente desativado para todas as oficinas.

        $encontros = $this->buscarEncontros($oficinaModularId);
        if ($encontros === []) {
            return ['ok' => false, 'error' => 'Oficina sem encontros cadastrados.'];
        }

        $slotPreferidoNormalizado = null;
        if (is_string($slotIdPreferido) && trim($slotIdPreferido) !== '') {
            $slotPreferidoNormalizado = strtoupper(trim($slotIdPreferido));
        }

        $encontroDia = null;
        if ($slotPreferidoNormalizado !== null) {
            foreach ($encontros as $encontro) {
                if ((int) ($encontro['dia_semana'] ?? 0) !== $diaDiaria) {
                    continue;
                }
                $slotEncontro = $this->gerarSlotId($diaDiaria, (string) ($encontro['hora_inicio'] ?? ''));
                if ($slotEncontro !== null && strtoupper($slotEncontro) === $slotPreferidoNormalizado) {
                    $encontroDia = $encontro;
                    break;
                }
            }
        } else {
            foreach ($encontros as $encontro) {
                if ((int) ($encontro['dia_semana'] ?? 0) === $diaDiaria) {
                    $encontroDia = $encontro;
                    break;
                }
            }
        }

        if ($encontroDia === null) {
            return [
                'ok' => false,
                'allowed' => false,
                'reason' => 'OFICINA_FORA_DO_DIA',
                'message' => self::MSG_FORA_DO_DIA,
            ];
        }

        $slotId = $this->gerarSlotId($diaDiaria, (string) ($encontroDia['hora_inicio'] ?? ''));
        if ($slotId === null) {
            return ['ok' => false, 'error' => 'Horário do encontro inválido.'];
        }

        $segundoDiaSemana = null;
        foreach ($encontros as $encontro) {
            $dia = (int) ($encontro['dia_semana'] ?? 0);
            if ($dia > 0 && $dia !== $diaDiaria) {
                $segundoDiaSemana = $dia;
                break;
            }
        }

        $possuiSegundoEncontro = $segundoDiaSemana !== null;

        // Evita conflito falso em clique repetido da mesma oficina no mesmo slot.
        $slotTravadoExistente = $this->client->select(
            'diaria_slots_travados',
            'select=oficina_modular_id'
            . '&diaria_id=eq.' . rawurlencode($diariaId)
            . '&slot_id=eq.' . rawurlencode($slotId)
            . '&limit=1'
        );
        if (($slotTravadoExistente['ok'] ?? false) && !empty($slotTravadoExistente['data'][0])) {
            $oficinaTravada = (string) ($slotTravadoExistente['data'][0]['oficina_modular_id'] ?? '');
            if ($oficinaTravada === $oficinaModularId) {
                return [
                    'ok' => true,
                    'diaria_id' => $diariaId,
                    'oficina_modular_id' => $oficinaModularId,
                    'slot_travado' => $slotId,
                    'possui_segundo_encontro' => $possuiSegundoEncontro,
                    'segundo_dia_semana' => $segundoDiaSemana,
                    'upsell_message' => $possuiSegundoEncontro ? self::MSG_UPSELL_SEGUNDO_ENCONTRO : null,
                    'already_selected' => true,
                ];
            }
            return [
                'ok' => false,
                'allowed' => false,
                'reason' => 'CONFLITO_SLOT',
                'slot_id' => $slotId,
            ];
        }

        // Transação no banco encapsulada na função RPC.
        $tx = $this->client->rpc('oficina_modular_grade_travar_e_reservar', [
            'p_diaria_id' => $diariaId,
            'p_oficina_modular_id' => $oficinaModularId,
            'p_dia_semana' => $diaDiaria,
            'p_slot_id' => $slotId,
            'p_possui_segundo_encontro' => $possuiSegundoEncontro,
            'p_segundo_dia_semana' => $segundoDiaSemana,
        ]);

        if (!$tx['ok']) {
            return ['ok' => false, 'error' => 'Erro ao salvar seleção de oficina.'];
        }

        $txData = $this->normalizarRetornoRpc($tx['data'] ?? null);
        if (!is_array($txData)) {
            return ['ok' => false, 'error' => 'Resposta inválida da transação de seleção.'];
        }

        if (($txData['ok'] ?? false) === false && ($txData['reason'] ?? '') === 'CONFLITO_SLOT') {
            return [
                'ok' => false,
                'allowed' => false,
                'reason' => 'CONFLITO_SLOT',
                'slot_id' => $slotId,
            ];
        }

        if (($txData['ok'] ?? false) !== true) {
            return ['ok' => false, 'error' => 'Falha ao concluir seleção de oficina.'];
        }

        return [
            'ok' => true,
            'diaria_id' => $diariaId,
            'oficina_modular_id' => $oficinaModularId,
            'slot_travado' => $slotId,
            'possui_segundo_encontro' => $possuiSegundoEncontro,
            'segundo_dia_semana' => $segundoDiaSemana,
            'upsell_message' => $possuiSegundoEncontro ? self::MSG_UPSELL_SEGUNDO_ENCONTRO : null,
        ];
    }

    public function removerOficinaModular(string $diariaId, string $oficinaModularId, ?string $slotIdPreferido = null): array
    {
        $diaria = $this->buscarDiaria($diariaId);
        if ($diaria === null) {
            return ['ok' => false, 'error' => 'Diária não encontrada.'];
        }

        $diaDiaria = $this->calcularDiaSemanaDiaria($diaria);
        if ($diaDiaria === null) {
            return ['ok' => false, 'error' => 'Não foi possível determinar o dia da diária.'];
        }

        $reserva = $this->buscarReserva($diariaId, $oficinaModularId);
        if ($reserva === null) {
            return ['ok' => true];
        }

        $encontros = $this->buscarEncontros($oficinaModularId);
        $encontroDia = null;
        foreach ($encontros as $encontro) {
            if ((int) ($encontro['dia_semana'] ?? 0) === $diaDiaria) {
                $encontroDia = $encontro;
                break;
            }
        }

        $slotId = null;
        if (is_string($slotIdPreferido) && trim($slotIdPreferido) !== '') {
            $slotId = trim($slotIdPreferido);
        } elseif ($encontroDia !== null) {
            $slotId = $this->gerarSlotId($diaDiaria, (string) ($encontroDia['hora_inicio'] ?? ''));
        }

        // Transação no banco encapsulada na função RPC.
        $tx = $this->client->rpc('oficina_modular_grade_liberar_e_cancelar', [
            'p_diaria_id' => $diariaId,
            'p_oficina_modular_id' => $oficinaModularId,
            'p_slot_id' => $slotId,
            'p_marcar_cancelada' => true,
        ]);

        if (!$tx['ok']) {
            return ['ok' => false, 'error' => 'Erro ao remover seleção de oficina.'];
        }

        return [
            'ok' => true,
            'slot_liberado' => $slotId,
        ];
    }

    public function buildSlotIdFromDayAndTime(int $diaSemana, string $horaInicio): ?string
    {
        return $this->gerarSlotId($diaSemana, $horaInicio);
    }

    public function getOcupacaoAtual(string $oficinaModularId, string $dataDiaria, int $diaSemana): array
    {
        // Limite de vagas temporariamente desativado para todas as oficinas.
        return [
            'total_confirmadas' => 0,
            'capacidade' => 0,
            'vagas_restantes' => 999999,
        ];
    }

    public function revalidarGradeAntesDoCheckout(string $diariaId): array
    {
        // Limite de vagas temporariamente desativado para todas as oficinas.
        return ['ok' => true];
    }

    public function confirmarGradeNoPagamento(string $diariaId): array
    {
        $diaria = $this->buscarDiaria($diariaId);
        if ($diaria === null) {
            return ['ok' => false, 'error' => 'Diária não encontrada.'];
        }

        if ((string) ($diaria['status_pagamento'] ?? 'PENDENTE') === 'PAGO' || $this->toBool($diaria['grade_travada'] ?? false)) {
            return ['ok' => true, 'idempotent' => true, 'confirmadas' => 0, 'canceladas' => 0];
        }

        $this->client->update('diaria', 'id=eq.' . rawurlencode($diariaId), [
            'status_pagamento' => 'PAGO',
            'grade_travada' => true,
            'updated_at' => date('c'),
        ]);

        $this->client->update(
            'diaria_oficina_modular_reserva',
            'diaria_id=eq.' . rawurlencode($diariaId) . '&status=neq.CANCELADA',
            ['status' => 'CONFIRMADA', 'updated_at' => date('c')]
        );

        return ['ok' => true, 'idempotent' => false, 'confirmadas' => 0, 'canceladas' => 0];
    }

    public function criarUpsellSegundoEncontro(string $diariaId, string $oficinaModularId, string $guardianId): array
    {
        $diaria = $this->buscarDiaria($diariaId);
        if ($diaria === null) {
            return ['ok' => false, 'error' => 'Diária não encontrada.'];
        }
        if ((string) ($diaria['guardian_id'] ?? '') !== $guardianId) {
            return ['ok' => false, 'error' => 'Diária não pertence ao responsável atual.'];
        }
        if ((string) ($diaria['status_pagamento'] ?? 'PENDENTE') === 'PAGO' || $this->toBool($diaria['grade_travada'] ?? false)) {
            return ['ok' => false, 'error' => 'Essa diária já está paga/travada.'];
        }

        $reserva = $this->buscarReserva($diariaId, $oficinaModularId);
        if ($reserva === null || (string) ($reserva['status'] ?? '') === 'CANCELADA') {
            return ['ok' => false, 'error' => 'Não existe reserva ativa dessa Oficina Modular na diária atual.'];
        }

        $possuiSegundo = $this->toBool($reserva['possui_segundo_encontro'] ?? false);
        $segundoDia = (int) ($reserva['segundo_dia_semana'] ?? 0);
        if (!$possuiSegundo || $segundoDia < 1 || $segundoDia > 7) {
            return ['ok' => false, 'error' => 'Essa Oficina Modular não possui segundo encontro elegível para upsell.'];
        }

        $baseDate = (string) ($diaria['data_diaria'] ?? '');
        $newDate = $this->calcularProximaDataParaDiaSemana($segundoDia, $baseDate);
        if ($newDate === null) {
            return ['ok' => false, 'error' => 'Não foi possível calcular a data do segundo encontro.'];
        }

        $studentId = (string) ($diaria['student_id'] ?? '');
        if ($studentId === '') {
            return ['ok' => false, 'error' => 'Diária sem aluno vinculado.'];
        }

        $destino = $this->criarOuReutilizarDiariaPendente($guardianId, $studentId, $newDate);
        if (($destino['ok'] ?? false) !== true) {
            return $destino;
        }

        $newDiariaId = (string) ($destino['diaria_id'] ?? '');
        $this->registrarUpsellLog($diariaId, $newDiariaId, $oficinaModularId, $segundoDia);

        return [
            'ok' => true,
            'new_diaria_id' => $newDiariaId,
            'new_diaria_date' => $newDate,
            'redirect_url' => '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($newDiariaId)
                . '&preselect_oficina_modular_id=' . rawurlencode($oficinaModularId)
                . '&from_upsell=1',
        ];
    }

    public function criarDiariaParaEncontro(
        string $oficinaModularId,
        string $guardianId,
        string $studentId,
        ?int $targetDiaSemana = null
    ): array {
        $oficina = $this->buscarOficina($oficinaModularId);
        if ($oficina === null || !$this->toBool($oficina['ativa'] ?? false)) {
            return ['ok' => false, 'error' => 'Oficina Modular não encontrada ou inativa.'];
        }

        $encontros = $this->buscarEncontros($oficinaModularId);
        if ($encontros === []) {
            return ['ok' => false, 'error' => 'Oficina sem encontros cadastrados.'];
        }

        $diasDisponiveis = [];
        foreach ($encontros as $encontro) {
            $dia = (int) ($encontro['dia_semana'] ?? 0);
            if ($dia >= 1 && $dia <= 7 && !in_array($dia, $diasDisponiveis, true)) {
                $diasDisponiveis[] = $dia;
            }
        }
        sort($diasDisponiveis);

        if ($targetDiaSemana === null) {
            if (count($diasDisponiveis) !== 1) {
                return [
                    'ok' => false,
                    'error' => 'Escolha qual dia do encontro deseja para criar a diária.',
                    'reason' => 'TARGET_DIA_SEMANA_REQUIRED',
                    'dias_disponiveis' => $diasDisponiveis,
                ];
            }
            $targetDiaSemana = $diasDisponiveis[0];
        }

        if (!in_array($targetDiaSemana, $diasDisponiveis, true)) {
            return ['ok' => false, 'error' => 'Dia escolhido não pertence aos encontros da Oficina Modular.'];
        }

        $newDate = $this->calcularProximaDataParaDiaSemana($targetDiaSemana, date('Y-m-d'));
        if ($newDate === null) {
            return ['ok' => false, 'error' => 'Não foi possível calcular a data para o encontro selecionado.'];
        }

        $destino = $this->criarOuReutilizarDiariaPendente($guardianId, $studentId, $newDate);
        if (($destino['ok'] ?? false) !== true) {
            return $destino;
        }

        $newDiariaId = (string) ($destino['diaria_id'] ?? '');
        $this->registrarUpsellLog(null, $newDiariaId, $oficinaModularId, $targetDiaSemana);

        return [
            'ok' => true,
            'new_diaria_id' => $newDiariaId,
            'new_diaria_date' => $newDate,
            'redirect_url' => '/diaria-grade-oficina-modular.php?diariaId=' . rawurlencode($newDiariaId)
                . '&preselect_oficina_modular_id=' . rawurlencode($oficinaModularId)
                . '&from_upsell=1',
            'target_dia_semana' => $targetDiaSemana,
        ];
    }

    private function buscarDiaria(string $diariaId): ?array
    {
        $result = $this->client->select('diaria', 'select=*&id=eq.' . rawurlencode($diariaId) . '&limit=1');
        if (!$result['ok'] || empty($result['data']) || !is_array($result['data'][0])) {
            return null;
        }
        return $result['data'][0];
    }

    private function buscarOficina(string $oficinaModularId): ?array
    {
        $result = $this->client->select('oficina_modular', 'select=*&id=eq.' . rawurlencode($oficinaModularId) . '&limit=1');
        if (!$result['ok'] || empty($result['data']) || !is_array($result['data'][0])) {
            return null;
        }
        return $result['data'][0];
    }

    private function buscarEncontros(string $oficinaModularId): array
    {
        $query = 'select=id,oficina_modular_id,dia_semana,hora_inicio,hora_fim'
            . '&oficina_modular_id=eq.' . rawurlencode($oficinaModularId)
            . '&order=dia_semana.asc,hora_inicio.asc';
        $result = $this->client->select('oficina_modular_horarios', $query);
        if (!$result['ok'] || !is_array($result['data'])) {
            return [];
        }
        return $result['data'];
    }

    private function buscarReserva(string $diariaId, string $oficinaModularId): ?array
    {
        $query = 'select=*'
            . '&diaria_id=eq.' . rawurlencode($diariaId)
            . '&oficina_modular_id=eq.' . rawurlencode($oficinaModularId)
            . '&limit=1';
        $result = $this->client->select('diaria_oficina_modular_reserva', $query);
        if (!$result['ok'] || empty($result['data']) || !is_array($result['data'][0])) {
            return null;
        }
        return $result['data'][0];
    }

    private function criarOuReutilizarDiariaPendente(string $guardianId, string $studentId, string $dataDiaria): array
    {
        $query = 'select=id,data_diaria,status_pagamento,grade_travada'
            . '&guardian_id=eq.' . rawurlencode($guardianId)
            . '&student_id=eq.' . rawurlencode($studentId)
            . '&data_diaria=eq.' . rawurlencode($dataDiaria)
            . '&status_pagamento=eq.PENDENTE'
            . '&order=created_at.desc'
            . '&limit=1';
        $existing = $this->client->select('diaria', $query);
        if ($existing['ok'] && !empty($existing['data'][0]) && is_array($existing['data'][0])) {
            return [
                'ok' => true,
                'diaria_id' => (string) $existing['data'][0]['id'],
                'reused' => true,
            ];
        }

        $insert = $this->client->insert('diaria', [[
            'guardian_id' => $guardianId,
            'student_id' => $studentId,
            'data_diaria' => $dataDiaria,
            'grade_oficina_modular_ok' => false,
            'status_pagamento' => 'PENDENTE',
            'grade_travada' => false,
        ]]);
        if (!$insert['ok'] || empty($insert['data'][0]) || !is_array($insert['data'][0])) {
            return ['ok' => false, 'error' => 'Não foi possível criar a diária de upsell.'];
        }

        return [
            'ok' => true,
            'diaria_id' => (string) $insert['data'][0]['id'],
            'reused' => false,
        ];
    }

    private function calcularProximaDataParaDiaSemana(int $targetDiaSemana, string $baseDate): ?string
    {
        if ($targetDiaSemana < 1 || $targetDiaSemana > 7) {
            return null;
        }
        $base = \DateTimeImmutable::createFromFormat('Y-m-d', substr($baseDate, 0, 10));
        if (!$base instanceof \DateTimeImmutable) {
            return null;
        }

        $baseDia = (int) $base->format('N');
        $delta = ($targetDiaSemana - $baseDia + 7) % 7;
        if ($delta === 0) {
            $delta = 7;
        }

        return $base->modify('+' . $delta . ' day')->format('Y-m-d');
    }

    private function registrarUpsellLog(?string $diariaOrigemId, string $diariaDestinoId, string $oficinaModularId, int $segundoDiaSemana): void
    {
        $payload = [[
            'diaria_origem_id' => $diariaOrigemId,
            'diaria_destino_id' => $diariaDestinoId,
            'oficina_modular_id' => $oficinaModularId,
            'segundo_dia_semana' => $segundoDiaSemana,
        ]];
        $this->client->insert('oficina_modular_upsell_log', $payload);
    }

    private function calcularDiaSemanaDiaria(array $diaria): ?int
    {
        $dateCandidates = [
            $diaria['data_diaria'] ?? null,
            $diaria['data'] ?? null,
            $diaria['date'] ?? null,
            $diaria['payment_date'] ?? null,
        ];

        $dateString = null;
        foreach ($dateCandidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                $dateString = substr(trim($candidate), 0, 10);
                break;
            }
        }

        if ($dateString === null) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateString);
        if (!$dt instanceof \DateTimeImmutable) {
            return null;
        }

        return (int) $dt->format('N'); // 1=segunda ... 7=domingo
    }

    private function validarJanelaOcasional30d(array $oficina): bool
    {
        $hoje = date('Y-m-d');
        $inicio = isset($oficina['data_inicio_validade']) ? (string) $oficina['data_inicio_validade'] : '';
        $fim = isset($oficina['data_fim_validade']) ? (string) $oficina['data_fim_validade'] : '';

        if ($inicio === '' || $fim === '') {
            return false;
        }

        return $hoje >= $inicio && $hoje <= $fim;
    }

    private function gerarSlotId(int $diaSemana, string $horaInicio): ?string
    {
        $prefixo = $this->prefixoDiaSemana($diaSemana);
        if ($prefixo === null) {
            return null;
        }

        $hora = trim($horaInicio);
        if ($hora === '') {
            return null;
        }

        $horaNormalizada = substr($hora, 0, 5);
        if (!preg_match('/^\d{2}:\d{2}$/', $horaNormalizada)) {
            return null;
        }

        return $prefixo . '_' . $horaNormalizada;
    }

    private function prefixoDiaSemana(int $diaSemana): ?string
    {
        $map = [
            1 => 'SEG',
            2 => 'TER',
            3 => 'QUA',
            4 => 'QUI',
            5 => 'SEX',
            6 => 'SAB',
            7 => 'DOM',
        ];

        return $map[$diaSemana] ?? null;
    }

    private function normalizarRetornoRpc($rpcData): ?array
    {
        if (is_array($rpcData)) {
            if (array_key_exists('ok', $rpcData)) {
                return $rpcData;
            }

            if (isset($rpcData[0]) && is_array($rpcData[0])) {
                return $rpcData[0];
            }

            // Alguns RPCs retornam objeto JSON direto, sem campo "ok".
            return $rpcData;
        }

        return null;
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 't', 'yes', 'y'], true);
        }
        return (bool) $value;
    }
}
