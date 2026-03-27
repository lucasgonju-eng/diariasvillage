# Testes Manuais - Upsell do 2º Encontro (Passo 7)

## 1) Seleciona OM com 2º encontro → upsell cria nova diária → pré-seleciona com sucesso

- Selecionar uma OM que tenha `possui_segundo_encontro=true`.
- Clicar em `Adicionar diária do 2º encontro`.
- Esperado:
  - endpoint de upsell retorna `redirect_url`;
  - abre nova grade em `/diaria/{newId}/grade-oficina-modular?preselect_oficina_modular_id=...&from_upsell=1`;
  - pré-seleção automática da OM ocorre;
  - banner: `Pronto! Já reservei o próximo encontro...`.

## 2) Pré-seleção falha por conflito

- Garantir slot ocupado na diária destino.
- Executar upsell para essa OM.
- Esperado:
  - grade de destino abre;
  - pré-seleção não conclui;
  - mensagem amigável de conflito;
  - usuário consegue escolher outra opção.

## 3) Pré-seleção falha por capacidade

- Lotar a capacidade da OM no dia de destino.
- Executar upsell.
- Esperado:
  - grade de destino abre;
  - pré-seleção falha por lotação;
  - mensagem amigável de indisponibilidade.

## 4) Usuário clica upsell duas vezes (não duplicar diária pendente)

- Com a mesma diária origem e OM, clicar upsell duas vezes.
- Esperado:
  - reutiliza diária pendente existente para a mesma data;
  - não cria segunda diária duplicada.

## 5) OM fora do dia → upsell cria diária no dia correto

- Em card `FORA_DO_DIA`, clicar `Adicionar diária nesse dia`.
- Se OM tiver 2 encontros fora do dia atual, escolher um no mini modal.
- Esperado:
  - cria/reusa diária no dia escolhido;
  - redireciona para grade destino com `from_upsell=1` e `preselect_oficina_modular_id`.

## 6) Webhook pagamento confirma as duas diárias separadamente

- Confirmar pagamento da diária origem e depois da diária destino (upsell).
- Esperado:
  - ambas processadas sem conflito entre si;
  - confirmação de grade independente por diária;
  - regras de capacidade e idempotência mantidas.
