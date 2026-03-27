# UI - Grade de Oficina Modular

## Visão Geral

A página `GET /diaria/{diariaId}/grade-oficina-modular` foi evoluída para uma UI funcional inspirada no Stitch, com:

- timetable do dia da diária;
- lista lateral de Oficinas Modulares em cards;
- preview de slot por hover;
- modal de detalhes da oficina;
- integração com seleção/remoção via endpoints backend.

## Estados Visuais

### Timetable

- **LIVRE**: slot sem oficina travada.
- **OCUPADO**: slot já travado para uma oficina.
- **PREVIEW**: slot destacado quando o usuário passa o mouse em um card de oficina disponível.
- **PREVIEW BLOQUEADO**: destaque de preview em modo conflito quando o slot já está ocupado por outra oficina.

### Cards de Oficina

- **DISPONIVEL**: possui encontro no dia e slot livre (botão `Selecionar`).
- **SELECIONADA**: oficina já travada no slot do dia (botão `Remover`).
- **CONFLITO**: possui encontro no dia, mas slot já ocupado por outra oficina (botão desabilitado `Conflito de horário`).
- **FORA_DO_DIA**: não possui encontro no dia da diária (botão desabilitado `Não disponível hoje` + mensagem comercial).

## Preview de Slot

Comportamento:

1. Usuário passa o mouse sobre o card de uma oficina.
2. A UI identifica o `slot_id` do encontro no dia da diária.
3. O timetable destaca o slot correspondente:
   - estilo normal de preview se seleção for possível;
   - estilo bloqueado se a oficina estiver em conflito.
4. Ao sair do hover, o destaque é removido.

## Upsell

### Fora do dia

Quando a oficina não ocorre no dia da diária, a UI mostra:

> "Essa Oficina Modular rola em outro dia da semana 😊  
> Se você quiser, dá pra adicionar mais uma diária nesse outro dia e garantir a participação completa."

CTA:

- `Adicionar outra diária` (redireciona para o fluxo de escolha de diária atual).

### Segundo encontro no modal

Quando a oficina possui 2º encontro em outro dia, o modal exibe:

> "Você garante o encontro de hoje aqui.  
> E se quiser completar a experiência, dá pra adicionar mais uma diária no dia do 2º encontro 😊"

Regra de negócio mantida:

- o 2º encontro **não** é travado automaticamente.

## Modal de Detalhes

Ao clicar em `Detalhes` no card:

- abre modal com:
  - título da oficina;
  - encontro do dia (quando existir);
  - 2º encontro (quando existir);
  - botão de ação dinâmico por estado:
    - `Selecionar Oficina Modular` (DISPONIVEL)
    - `Remover` (SELECIONADA)
    - desabilitado com texto de incentivo (FORA_DO_DIA / CONFLITO)
- fechamento por:
  - botão `×`
  - clique no backdrop.

## Integração Backend

A UI mantém os endpoints como fonte de verdade:

- `POST /api/diarias/{diariaId}/oficinas-modulares/{oficinaId}/selecionar`
- `POST /api/diarias/{diariaId}/oficinas-modulares/{oficinaId}/remover`

Após selecionar/remover:

- a tela atualiza de forma leve (re-render em memória da grade/cards sem reload completo);
- exibe mensagens de sucesso/erro em banner discreto;
- mostra `upsell_message` quando retornar do backend.
