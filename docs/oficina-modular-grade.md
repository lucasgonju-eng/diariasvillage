# Especificação do Módulo: Grade de Oficina Modular

## Objetivo

Definir o comportamento funcional e técnico do módulo **Grade de Oficina Modular** no SaaS Diárias Village, garantindo que a escolha de oficinas ocorra somente para o dia da diária selecionada e que o fluxo de upsell do segundo encontro seja aplicado de forma consistente.

## Escopo

- Inclui a montagem da grade antes do pagamento da diária.
- Inclui validações de dia da semana, conflito de horário e capacidade/quórum.
- Inclui regra comercial de sugestão para compra de segunda diária quando houver segundo encontro em outro dia.
- Não inclui layout visual, implementação front-end ou desenvolvimento de código nesta etapa.

## Contexto de Produto

No Diárias Village, o responsável compra uma diária específica para o aluno.

Antes de finalizar o pagamento da diária, o responsável deve montar a Grade de Oficina Modular correspondente **exclusivamente** ao dia da diária selecionada.

## Regra Principal (Obrigatória)

A seleção de Oficina Modular só pode ocorrer para o dia da diária escolhida.

Exemplo:

- Se a diária selecionada for terça-feira, o responsável só pode inserir na grade oficinas que tenham encontro na terça-feira.

## Regra dos 2 Encontros (Importante)

Cada Oficina Modular possui 2 encontros semanais de 60 minutos.

Exemplo:

- Futsal: segunda 14:00 e quinta 14:00.

Regra adaptada para o módulo:

1. Se o responsável selecionar a Oficina Modular para o dia da diária, o sistema deve travar apenas o slot daquele dia.
2. O sistema deve identificar que existe um segundo encontro em outro dia.
3. O sistema não deve travar automaticamente o segundo encontro.
4. O sistema deve exibir um aviso comercial convidando o responsável a comprar outra diária para completar a experiência.

## Mensagem de Upsell (Texto Padrão)

Quando existir o segundo encontro em outro dia, exibir:

> “Essa Oficina Modular acontece em outro dia da semana também 😊  
> Para garantir a participação no encontro completo, é só adicionar mais uma diária para esse outro dia.”

Complemento opcional:

> “Quer que eu te leve pra escolher a próxima diária agora?”

## Fluxo do Usuário

1. Usuário escolhe a data da diária.
2. Antes do pagamento, é redirecionado para:
   - `/diaria/{id}/grade-oficina-modular`
3. O usuário visualiza a grade apenas do dia selecionado.
4. O usuário seleciona as Oficinas Modulares disponíveis naquele dia.
5. O sistema valida conflitos de horário em tempo real e bloqueia seleção conflitante.
6. O sistema exibe aviso de segundo encontro (upsell) quando aplicável.
7. Usuário confirma a grade.
8. Usuário segue para pagamento.

## Estados da Interface

### 1) Slot livre

- Slot elegível para seleção.
- Oficina ocorre no dia da diária.
- Sem conflito com slots já selecionados.

### 2) Slot selecionado

- Slot escolhido pelo responsável para a diária atual.
- Estado temporário (rascunho) até pagamento.
- Após pagamento, passa a estado confirmado no backend.

### 3) Slot em conflito

- Slot cujo horário colide com outro slot já selecionado para a mesma diária.
- Seleção deve ser impedida até resolução do conflito.

### 4) Oficina indisponível (não ocorre no dia da diária)

- Oficina que não possui encontro no dia da semana da diária escolhida.
- Deve aparecer como indisponível para seleção naquele contexto.

### 5) Oficina com 2º encontro em outro dia (estado "upsell")

- Oficina selecionável no dia da diária, mas com segundo encontro em outro dia.
- Permite seleção normal do encontro do dia.
- Exibe aviso comercial de compra de diária adicional para o outro dia.
- Não trava o segundo encontro automaticamente.

### 6) Oficina cheia

- Oficina atingiu limite de vagas para o slot do dia.
- Seleção deve ser bloqueada.

### 7) Oficina em quórum

- Oficina ainda não atingiu número mínimo de participantes.
- Seleção permitida, mas com indicação de status "em quórum".
- Regras de confirmação final da oficina devem seguir política operacional do produto.

## Regras de Negócio

1. Só é possível travar slots do dia da diária.
2. Conflito de horário impede seleção.
3. O segundo encontro nunca trava automaticamente.
4. O sistema deve sugerir compra adicional quando houver segundo encontro em outro dia.
5. Se a diária não for paga, a grade permanece como rascunho.
6. Após pagamento, os slots selecionados para aquele dia ficam confirmados.

## Requisitos Funcionais

### RF-01 - Filtragem por dia da diária

- O sistema deve listar apenas oficinas com ocorrência no dia da semana da diária selecionada.

### RF-02 - Seleção de slots

- O responsável pode selecionar um ou mais slots válidos do dia.

### RF-03 - Prevenção de conflito

- O sistema deve detectar sobreposição de horários e impedir seleção conflitante.

### RF-04 - Tratamento de capacidade

- O sistema deve impedir seleção de oficina cheia.

### RF-05 - Tratamento de quórum

- O sistema deve permitir seleção de oficina em quórum e sinalizar o status.

### RF-06 - Upsell de segundo encontro

- Ao selecionar oficina com segundo encontro em outro dia, o sistema deve exibir mensagem de upsell com o texto padrão definido.

### RF-07 - Persistência como rascunho

- Enquanto a diária estiver sem pagamento, seleções da grade devem ser salvas como rascunho editável.

### RF-08 - Confirmação pós-pagamento

- Após confirmação de pagamento da diária, as seleções do dia devem ser convertidas para estado confirmado.

## Requisitos Não Funcionais

- **Consistência transacional:** seleção e remoção de slots devem ser atômicas.
- **Integridade de dados:** constraints de conflito e dia da semana devem ser garantidas no backend.
- **Desempenho:** carregamento da grade deve ser otimizado para consulta por `diaria_id` e `dia_semana`.
- **Auditabilidade:** eventos de seleção, remoção e confirmação devem ser rastreáveis.

## Modelo Conceitual de Dados (Visão de Alto Nível)

Entidades principais:

- `diaria`
  - identifica a compra do dia pelo responsável.
- `oficina_modular`
  - cadastro da oficina (nome, capacidade padrão, regras de quórum).
- `oficina_modular_encontro`
  - definição dos encontros semanais (dia da semana, horário, duração).
- `diaria_grade_oficina`
  - seleção de slots da grade para a diária (status rascunho/confirmado).

Relacionamentos:

- `diaria` 1:N `diaria_grade_oficina`
- `oficina_modular` 1:N `oficina_modular_encontro`
- `oficina_modular_encontro` 1:N `diaria_grade_oficina`

## Regras de Validação (Backend)

1. **Validação de dia da semana**
   - `diaria_grade_oficina.encontro_id` deve referenciar encontro cujo `dia_semana` seja igual ao dia da diária.
2. **Validação de conflito de horário**
   - para mesma `diaria_id`, não pode haver dois encontros com intervalo de horário sobreposto.
3. **Validação de capacidade**
   - impedir inclusão se vagas esgotadas no encontro.
4. **Validação de status da diária**
   - diária paga não pode retornar para rascunho.
5. **Validação do segundo encontro**
   - identificar encontro complementar para upsell, sem auto-reserva.

## API (Proposta de Contrato)

### Endpoint de seleção

- `POST /api/diarias/{diariaId}/grade-oficina-modular`
- Objetivo: adicionar seleção de slot para a diária.
- Entrada mínima:
  - `encontro_id`
- Saída:
  - grade atualizada do dia
  - sinalização de upsell quando houver segundo encontro em outro dia
  - mensagens de validação (conflito, lotação, dia inválido)

### Endpoint de remoção

- `DELETE /api/diarias/{diariaId}/grade-oficina-modular/{selecaoId}`
- Objetivo: remover slot previamente selecionado na grade da diária.
- Saída:
  - grade atualizada do dia
  - recalculo de conflitos e status de upsell

### Endpoint de consulta da grade

- `GET /api/diarias/{diariaId}/grade-oficina-modular`
- Objetivo: carregar slots elegíveis e estado atual das seleções.
- Saída:
  - lista de slots do dia
  - status de cada slot/interface
  - informações de capacidade/quórum

## Regras de Persistência por Estado

- **Rascunho**
  - criado/atualizado enquanto pagamento não foi concluído.
  - editável (adicionar/remover slots).
- **Confirmado**
  - transição após pagamento aprovado.
  - congela seleções do dia da diária.

## Comportamento de Upsell (Segundo Encontro)

Ao selecionar uma oficina do dia da diária:

1. buscar se existe segundo encontro da mesma oficina em outro dia da semana;
2. caso exista, retornar metadado `upsell_available = true`;
3. incluir mensagem padrão obrigatória e complemento opcional;
4. disponibilizar ação para navegação para escolha de nova diária (sem seleção automática de slot no outro dia).

## Critérios de Aceite (Alto Nível)

1. Não é possível selecionar oficina que não ocorre no dia da diária.
2. Não é possível selecionar dois slots conflitantes na mesma diária.
3. Seleção de oficina com segundo encontro não cria reserva automática no outro dia.
4. Mensagem de upsell aparece quando aplicável, com o texto definido.
5. Grade permanece em rascunho antes do pagamento.
6. Após pagamento, slots do dia ficam confirmados.

## Checklist Técnico

- Estrutura de tabelas necessárias.
- Relacionamento diária ↔ oficina modular.
- Estrutura de slots por dia.
- Constraint para evitar conflito.
- Endpoint de seleção.
- Endpoint de remoção.
- Validação de dia da semana.
- Lógica de upsell do segundo encontro.

## Fora de Escopo Nesta Etapa

- Criação de layout.
- Implementação de front-end.
- Implementação de backend.
- Migração de dados e automações operacionais.
