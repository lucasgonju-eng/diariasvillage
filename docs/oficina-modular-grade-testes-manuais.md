# Testes Manuais - Grade de Oficina Modular

## Objetivo

Validar manualmente o fluxo backend de seleção e remoção de Oficina Modular no dia da diária, incluindo conflito de slot e upsell de segundo encontro.

## Pré-requisitos

- Usuário autenticado (sessão válida).
- Registros de `diaria`, `oficina_modular`, `oficina_modular_horarios` e `grade_slots` existentes.
- Rotas disponíveis:
  - `POST /api/diarias/{diariaId}/oficinas-modulares/{oficinaId}/selecionar`
  - `POST /api/diarias/{diariaId}/oficinas-modulares/{oficinaId}/remover`

## Cenário 1 - Selecionar oficina válida no dia

- Preparação:
  - diária com data em `dia_semana = X`.
  - oficina ativa com encontro em `dia_semana = X`.
- Ação:
  - chamar endpoint `selecionar`.
- Esperado:
  - `ok = true`.
  - `slot_travado` preenchido no formato `SEG_14:00`, `TER_15:40` etc.
  - registro em `diaria_slots_travados`.
  - registro em `diaria_oficina_modular_reserva` com `status = RASCUNHO`.

## Cenário 2 - Selecionar com conflito

- Preparação:
  - já existir em `diaria_slots_travados` o mesmo `(diaria_id, slot_id)` para outra oficina.
- Ação:
  - chamar endpoint `selecionar` para oficina que gere o mesmo `slot_id`.
- Esperado:
  - `ok = false`.
  - `allowed = false`.
  - `reason = CONFLITO_SLOT`.
  - status HTTP `409`.

## Cenário 3 - Selecionar oficina que não acontece no dia

- Preparação:
  - diária em `dia_semana = X`.
  - oficina com encontros apenas em dias diferentes de `X`.
- Ação:
  - chamar endpoint `selecionar`.
- Esperado:
  - `ok = false`.
  - `allowed = false`.
  - `reason = OFICINA_FORA_DO_DIA`.
  - mensagem:
    - "Essa Oficina Modular acontece em outro dia da semana 😊
Para participar dela por completo, é só adicionar mais uma diária nesse outro dia. Quer que eu te leve pra escolher agora?"

## Cenário 4 - Selecionar oficina com 2º encontro (ver upsell)

- Preparação:
  - oficina com encontro no dia da diária e outro encontro em dia diferente.
- Ação:
  - chamar endpoint `selecionar`.
- Esperado:
  - `ok = true`.
  - `possui_segundo_encontro = true`.
  - `segundo_dia_semana` preenchido.
  - `upsell_message` preenchida.
  - somente o `slot_travado` do dia da diária deve ser criado (sem travar o segundo dia).

## Cenário 5 - Remover oficina

- Preparação:
  - oficina previamente selecionada para a diária.
- Ação:
  - chamar endpoint `remover`.
- Esperado:
  - `ok = true`.
  - `slot_liberado` preenchido com o slot do dia.
  - remoção do slot em `diaria_slots_travados`.
  - reserva em `diaria_oficina_modular_reserva` com `status = CANCELADA`.

## Cenário 6 - Remover idempotente

- Preparação:
  - não existir reserva para `(diaria_id, oficina_id)`.
- Ação:
  - chamar endpoint `remover`.
- Esperado:
  - `ok = true`.
  - sem erro.
  - sem alterações indevidas em banco.
