# Testes Manuais - Passo 6 (Blindagem e Revalidação)

## 1) Lotar capacidade e tentar selecionar (deve bloquear)

- Preparação:
  - Oficina com `capacidade > 0`.
  - Reservas já confirmadas/pagas no mesmo `data_diaria` + `dia_semana` até atingir capacidade.
- Ação:
  - Tentar selecionar a mesma oficina em uma nova diária no mesmo dia.
- Esperado:
  - Seleção bloqueada.
  - Retorno com `reason = CAPACIDADE_ESGOTADA`.

## 2) Lotar após seleção, antes do checkout (revalidação no continuar)

- Preparação:
  - Usuário A seleciona oficina (RASCUNHO).
  - Enquanto isso, vagas acabam por confirmações pagas de outros usuários.
- Ação:
  - Usuário A clica em `Continuar para pagamento`.
- Esperado:
  - `revalidarGradeAntesDoCheckout` cancela a reserva lotada.
  - Slot é removido de `diaria_slots_travados`.
  - Mensagem exibida:
    - "Ops! Enquanto você montava a grade, essa Oficina Modular ficou sem vagas.
Sem stress 😊 escolha outra opção para esse horário e siga com a diária."

## 3) Lotar entre checkout e confirmação de pagamento (webhook)

- Preparação:
  - Diária com reservas em `RASCUNHO`.
  - Pagamento iniciado.
  - Antes do webhook confirmar, capacidade da oficina é consumida por outro pagamento.
- Ação:
  - Disparar webhook `PAYMENT_CONFIRMED` / `PAYMENT_RECEIVED`.
- Esperado:
  - `confirmarGradeNoPagamento` roda em transação.
  - Reserva lotada é cancelada e slot removido.
  - Auditoria registrada com `CANCELAMENTO_LOTACAO`.
  - Diária fica `status_pagamento = PAGO` e `grade_travada = true`.

## 4) Webhook duplicado (idempotência)

- Preparação:
  - Webhook de pagamento já processado com sucesso.
- Ação:
  - Reenviar o mesmo webhook para o mesmo `asaas_payment_id`.
- Esperado:
  - Fluxo não duplica confirmação da grade.
  - Função retorna estado idempotente.
  - Sem alterações indevidas em reservas/slots.

## 5) Pagamento confirmado sem reservas (ok)

- Preparação:
  - Diária com `grade_oficina_modular_ok = true`, porém sem reservas `RASCUNHO`.
- Ação:
  - Confirmar pagamento via webhook.
- Esperado:
  - Processo finaliza com `ok=true`.
  - Diária marcada como paga e travada.
  - Sem erro por ausência de reservas.

## 6) Oficina cancelada por quórum após grade

- Preparação:
  - Reserva em `RASCUNHO` para oficina depois marcada como `status_quorum = CANCELADA`.
- Ação:
  - Tentar `Continuar para pagamento` e/ou confirmar no webhook.
- Esperado:
  - Revalidação/confirmacão cancela a reserva.
  - Slot removido.
  - Auditoria registrada com motivo de oficina inválida/cancelada.
