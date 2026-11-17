# Contract — Formulários (spec-020)

```text
moodleform -> validation (convenções Moodle) -> authorization check -> application service
```

| Form | Serviço | Regras |
|---|---|---|
| Configuração | serviço de configuração (005) | autorização antes de persistir [REQ] *(26/10/2026: `local/assistente:manage`; campo `enabled`; habilitar dispara a sincronização inicial, DEC-45 D1/D3)* |
| Critérios | `defineCriteria` (005) | imutáveis após a definição [REQ] *(26/10/2026: somente leitura para professor; `correctCriteria` só para administrador, DEC-45 D2)* |
| Filtros | consultas (018) / relatórios (019) | sem regra de domínio no form [REQ] *(26/10/2026: grupo, seção, versão/data; DEC-43, DEC-44)* |

## Regras *(incluído em 26/10/2026, DEC-45 e tratamentos da revisão)*

| Form | Campos e validação | Autorização | Efeito |
|---|---|---|---|
| Configuração | `enabled` (0/1) | `local/assistente:manage` no curso (padrão `editingteacher`, `manager`) | `enabled = 1`: sincronização inicial do estado atual (materiais elegíveis, questionários publicados sem análise inicial, notas, frequência, reconstrução dos fóruns), eventos por item, sem recriar eventos históricos. `enabled = 0`: o observer não cria eventos; pendentes não são processados; histórico preservado. Reabilitar: nova sincronização |
| Critérios — definição | `activity_criterion` e `frequency_criterion`: decimal 0–100, até três casas; `forum_criterion`: caixa 0/1; opcionais ao habilitar | `local/assistente:manage` | Gravados uma vez; depois, somente leitura para professores |
| Critérios — correção | mesmos campos | administrador (`manager` ou administrador do site, no contexto apropriado) | Vale só para novas `criteria_check`; nenhuma reavaliação automática; cada `criteria_check` registra no seu contexto os limites usados |
| Filtros | grupo (só consultas individuais e partes individuais do relatório); seção; versão e data de execução | `local/assistente:view` (DEC-30) | Parâmetros de leitura; nenhuma regra de domínio |

**Comum:** sesskey e validação do formulário pela Form API; autorização verificada no backend antes de persistir, inclusive em chamada direta sem a UI; comparação dos critérios só no serviço (DEC-11). Curso sem configuração ou desabilitado: consultas e relatórios informam "assistente não habilitado".

## Filtros do relatório e do histórico (Q-020-02) *(incluído em 29/10/2026)*

Detalhamento de Q-020-02 (grupo, seção, versão e data de execução; DEC-43, DEC-44), pedido pelo responsável na tarefa de resolução de Q-020-02. **Não altera DEC-43 nem DEC-44**: o intervalo `de`/`até`, a inclusividade dos limites e a combinação de filtros não constam do texto original dessas decisões nem da Q-020-02 e vêm desse pedido. Nenhum campo novo, nenhuma entidade e nenhuma alteração de esquema.

| Item | Regra |
|---|---|
| Data de execução | `report_version.created_at`, o instante em que a versão foi gerada (o `generated_at` do manifesto é o mesmo instante). Não se usam datas de atividades, de criação de análises nem outro campo |
| Intervalo | `de` e `até`, dois dias-calendário, cada um opcional e **inclusivo**. `de` começa às 00:00 do primeiro dia; `até` vai até o último segundo do último dia (dia final inteiro, inclusive em dia de 25 horas). Os dias são os do **fuso do site Moodle** (DEC-44 D2; `core_date::get_server_timezone()`), não os do usuário. Um só limite é um intervalo aberto do outro lado |
| Versão | número da versão do relatório (`report_version.version`), escolhido na lista das versões que existem. Combina com o intervalo: a versão tem de passar nos dois |
| Intervalo invertido | `até` anterior a `de`: erro de validação do formulário (Form API), no campo `até`; o formulário não entrega dados, a página não mostra resultado e nada é calculado com o intervalo inválido. O mesmo dia nos dois campos é válido |
| Relatório | sem filtro, a versão atual (`current_version`). Com filtro, a versão **mais recente** (maior número) que passa nos dois critérios, renderizada **pelo manifesto dela** (análises e versões de síntese que ela referencia, DEC-44 D1): nada é recalculado com dados atuais, nenhum snapshot, ponteiro ou análise é alterado. Nenhuma versão passa: mensagem "Nenhuma versão atende aos filtros." |
| Histórico | sem filtro, todas as versões do relatório, da mais nova para a mais antiga, a mais nova marcada como vigente (comportamento anterior, inalterado). Com filtro por número e/ou datas, só as versões que passam nos dois critérios; a marca de vigente continua sendo a da versão mais nova do relatório, esteja ou não na lista. Nenhuma passa: a mesma mensagem. O filtro vale só para o histórico do **relatório** (as datas dos demais históricos são datas de análise) |
| Grupo | só nas partes individuais do relatório (notas, frequência, abaixo dos critérios) e nas consultas individuais (DEC-43 D1). Os blocos agregados do relatório e o histórico não são filtrados por grupo. Estudantes visíveis seguem as permissões do usuário e os grupos separados do Moodle (DEC-43 D2): um grupo que o usuário não vê não dá estudantes, e o parâmetro não amplia o acesso |
| Seção | só onde o dado é por seção: consultas agregadas (questionários, fóruns, materiais, sínteses). Não se aplica às consultas individuais, ao relatório nem ao histórico |
| Autorização | `local/assistente:view`, verificada no backend antes de qualquer leitura, também com os filtros |

Evidência: [quickstart.md](../quickstart.md) Q15–Q26 e a tarefa T143.
