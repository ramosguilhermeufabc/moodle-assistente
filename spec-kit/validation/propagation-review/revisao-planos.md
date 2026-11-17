# Revisão do conteúdo propagado aos artefatos de plano — etapa 5a-complemento, TAREFA 2

> **(08/11/2026) Documento histórico: retrato da revisão de propagação feita em 04/11/2026**
> sobre 135 arquivos e 6.921 linhas. As 56 divergências aprovadas na seção 5 foram aplicadas na
> mesma data e seguem valendo como registro. Já a seção 4, "Pendências reais", foi superada: os
> seis itens que ela lista como abertos estão resolvidos — CL-22 Fechado, e T007, T008, T130,
> T139 e T140 marcadas `[x]`. A observação do item D25 na seção 5, sobre a fixação de versões
> continuar pendente (T008), também foi superada. O estado vigente está em
> `closure-register.md`, seção "Estado atual". O texto abaixo foi preservado sem reescrita.

**Data:** 04/11/2026
**Estado:** decisões do responsável de 04/11/2026 registradas nas seções 5 e 6 e aplicadas na TAREFA 3 *(estado anterior, preservado: "folha para decisão do responsável. Nenhum artefato foi alterado nesta tarefa (só este arquivo foi criado)" — vale para a TAREFA 2)*.
**Escopo:** `spec-kit/specs/*/{plan,research,data-model,quickstart,traceability}.md` e `spec-kit/specs/*/contracts/*.md` (135 arquivos, 6.921 linhas). Os `tasks.md` ficam fora.
**Evidências consultadas:** `decision-register-DEC-01-25.md`, estado de cada CL em `closure-register.md` e marcas [x] / [ ] dos `tasks.md`.

## 1. Cadeias de decisões alteradas por decisões posteriores (item a.1)

| # | Decisão alterada | Alterada ou complementada por |
|---|---|---|
| C1 | DEC-34 D2 (credencial só por variável de ambiente) | DEC-69 (chave na configuração de site criptografada; a variável deixa de existir) |
| C2 | DEC-60 D3 (credencial) | DEC-69 |
| C3 | DEC-60 D2(a) (URL de referência) e D5 (formato, Responses API) | DEC-70 (Chat Completions API; emenda da cerca de Markdown) |
| C4 | DEC-60 D4 (ZDR em toda requisição) e DEC-70 (2) | DEC-71 (ZDR é a configuração `llmzdr`, ligada por padrão) |
| C5 | DEC-34 D1 (fornecedor e modelo não escolhidos) | DEC-60 D1 e D2 (OpenRouter; URL e modelo configuráveis) |
| C6 | DEC-67 (yt-dlp pelo gerenciador de pacotes) | DEC-68 (executável oficial em versão fixa, com SHA-256) |
| C7 | DEC-28 (baseline: `--no-dev`, sem cron, sem versões fixas, sem `forwardPorts`, secret da LLM) | DEC-49 (altera parcialmente) |
| C8 | T136 e R-013-02 (divisão do conteúdo de fóruns grandes) | DEC-65 (premissa de até cerca de 100 mensagens; T136 cancelada) |
| C9 | DEC-64 (partial_result só de `match` e `ddwtos`) | complemento de 03/11/2026 (inclui `multianswer`) |
| C10 | DEC-26 item 5 e DEC-51 (disponibilidade) | DEC-72 (mesma propriedade nativa `uservisible`; não altera a regra) |

Cadeias sem divergência nos planos: C6 (nenhuma linha afirma a instalação antiga do yt-dlp), C9 (o contrato da 011 cita a DEC-64) e C10 (a regra não mudou).

## 2. Totais

### 2.1 Item (a)

| Medida | Total |
|---|---|
| Arquivos no escopo | 135 |
| Linhas no escopo | 6.921 |
| Linhas que citam uma decisão da lista (palavras-chave das cadeias C1 a C10) | 192 |
| Dessas, sem citar a decisão posterior na própria linha | 123 |
| Divergências de origem (a) na tabela (linhas D…) | 20 |

As demais das 123 foram lidas e descartadas porque a regra citada não mudou (por exemplo as linhas da DEC-28 que a DEC-49 não altera, as da C8, C9 e C10) ou porque a seção já tem nota (as linhas 81 e 82 de `009-*/contracts/llm-port.md` ficam cobertas pela nota de seção da linha 71, DEC-70).

### 2.2 Item (b)

| Medida | Total |
|---|---|
| Linhas do `grep` do item (b) no escopo | 174 |
| Ignoradas (texto histórico preservado ou com nota de resolução) | 69 |
| Examinadas | 105 |
| Divergências de origem (b) na tabela | 36 (30 vindas do `grep`; 6 achadas pela leitura do contexto: D12, D19, D20, D21, D28, D30b) |
| Registro cronológico, sugerido não corrigir (seção 3.1) | 6 |
| Restantes: não afirmam pendência (estado `pending` de fila, "N/A, não especificado", regra geral, texto de cenário), ou são limitação já documentada (seção 4) | demais linhas examinadas |

## 3. Tabela de divergências

Total: 56 linhas (20 de origem (a) e 36 de origem (b)). Em todas, a correção proposta é uma nota "(<data>, revisão dos planos: ...)" fora de qualquer citação, preservando o texto anterior. Linhas D43 a D52 são textos datados da aprovação do plano (25/10 ou 26/10/2026); se o responsável preferir tratá-los como histórico, a decisão é "manter".

| ID | arquivo:linha | Origem | trecho atual | decisão ou evidência vigente | problema | correção proposta |
|---|---|---|---|---|---|---|
| D01 | 009-adapter-da-api-externa-de-llm/contracts/llm-port.md:91 | (a) | …e de 3 (DEC-32). A tabela é documental; os códigos HTTP vêm da referência da Responses API e não foram exercitados.… | DEC-70 (1): Chat Completions API, mesmos códigos HTTP | Cita a Responses API como referência, sem citar a DEC-70 | Acrescentar nota: a API adotada é a Chat Completions (DEC-70) |
| D02 | 009-adapter-da-api-externa-de-llm/contracts/llm-port.md:65 | (a) | …\| Sem endpoint compatível \| O fornecedor devolve erro (a documentação não informa o código p… | DEC-71: ZDR é configuração `llmzdr`, ligada por padrão | Afirma a falha por falta de endpoint ZDR sem dizer que vale só com `llmzdr` ligada | Acrescentar nota: vale com `llmzdr` ligada (DEC-71) |
| D03 | 009-adapter-da-api-externa-de-llm/contracts/llm-port.md:100 | (a) | …nt sem suporte ao formato solicitado; **sem endpoint compatível com ZDR ou `data_collection`**; requisição rejeitada \| `transport`… | DEC-71 (idem D02) | Idem D02 | Acrescentar nota: só com `llmzdr` ligada (DEC-71) |
| D04 | 009-adapter-da-api-externa-de-llm/plan.md:49 | (a) | …md` CL-32): cliente HTTP do Moodle 5.2, leitura da variável de ambiente pelo processo PHP (inclusive cron/CLI) e declaração da loc… | DEC-69: chave só na configuração criptografada; variável de ambiente deixa de existir | Lista a leitura da variável de ambiente como validação técnica, sem citar a DEC-69 | Acrescentar nota (DEC-69; CL-32 Fechado em 03/11/2026) |
| D05 | 009-adapter-da-api-externa-de-llm/plan.md:134 | (a) | …int completo, sem caminho acrescentado; a referência inicial é `https://openrouter.ai/api/v1/responses`; `openai/o4-mini` é só exe… | DEC-70: referência `https://openrouter.ai/api/v1/chat/completions` | URL da Responses API sem nota de superação | Acrescentar nota: URL de referência alterada pela DEC-70 |
| D06 | 009-adapter-da-api-externa-de-llm/plan.md:136 | (a) | …\| ZDR (D4) \| `provider.zdr = true` e `provider.data_collection = "deny"` em toda requisição; sem fallba… | DEC-71: `provider` só é enviado com `llmzdr` ligada (padrão) | Afirma ZDR em toda requisição sem citar a DEC-71 | Acrescentar nota (DEC-71) |
| D07 | 009-adapter-da-api-externa-de-llm/plan.md:137 | (a) | …\| Resposta (D5) \| Responses API convertida para o `structured_result` existente; nada fora do adaptador \| [c… | DEC-70: Chat Completions API | Cita a Responses API sem a DEC-70 | Acrescentar nota (DEC-70) |
| D08 | 009-adapter-da-api-externa-de-llm/research.md:18 | (a) | …- **Decision**: ZDR em toda requisição (`provider.zdr = true`, `provider.data_collection = "deny"`), sem f… | DEC-71 | ZDR em toda requisição sem a DEC-71 | Acrescentar nota (DEC-71) |
| D09 | 009-adapter-da-api-externa-de-llm/research.md:19 | (a) | …- **Decision**: formato externo Responses API, convertido para o `structured_result` existente dentro do a… | DEC-70 | Responses API sem a DEC-70 | Acrescentar nota (DEC-70) |
| D10 | 009-adapter-da-api-externa-de-llm/research.md:37 | (a) | …\| Q-009-02 Credenciais fora de dev \| RESOLVIDO — DEC-34 D2 *(original: "ABERTO")* \| Decisão do responsável \| ver acima \|… | DEC-69 altera a DEC-34 D2 | Resolução por variável de ambiente sem citar a DEC-69 | Acrescentar nota (DEC-69) |
| D11 | 009-adapter-da-api-externa-de-llm/research.md:25 | (a) | …- O endpoint Responses é `POST /responses`; aceita `model`, `input` (texto ou lista de itens… | DEC-70 (Chat Completions) | Pesquisa de 31/10/2026 sobre a Responses API, sem nota de que a API adotada mudou (as linhas 26, 29 e 31 são do mesmo bloco) | Uma nota única no início do bloco (linhas 22 a 31): API adotada é a Chat Completions (DEC-70); a pesquisa é histórica |
| D12 | 009-adapter-da-api-externa-de-llm/research.md:31 | (b) | …**Não** conferido (a confirmar em T134 e CL-32): lista completa de valores de `status` e o f… | CL-32 Fechado em 03/11/2026; DEC-70 | Itens "a confirmar em T134 e CL-32" sem nota de resolução | Acrescentar nota: CL-32 Fechado em 03/11/2026 (T134 [x], T138 [x]) |
| D13 | 009-adapter-da-api-externa-de-llm/research.md:41 | (a) | …\| Q-009-06 Privacidade \| PARCIAL — DEC-34 D3; ZDR exigido (DEC-60 D4), sem validação (CL-32); termos do fornecedor … | CL-32 Fechado em 03/11/2026; DEC-71 | "sem validação (CL-32)" e ZDR exigido, sem a DEC-71 | Acrescentar nota (CL-32 Fechado; DEC-71) |
| D14 | 009-adapter-da-api-externa-de-llm/traceability.md:25 | (a) | …a em 31/10/2026; complementa DEC-34 D1; DEC-34 D2 e D3 preservadas) \|… | DEC-69 (altera DEC-34 D2 e DEC-60 D3), DEC-70 (D2(a), D5), DEC-71 (D4) | Diz que a D2 e a D3 foram preservadas e lista ZDR e formato externo sem citar as DEC posteriores | Acrescentar nota (DEC-69, DEC-70, DEC-71) |
| D15 | 009-adapter-da-api-externa-de-llm/plan.md:144 | (a) | …ontexto) não são conhecidos pelo plano: Q-008-03 e T136 seguem abertas, ver [research.md](research.md).… | DEC-67 (Q-008-03) e DEC-65 (T136 cancelada) | Afirma Q-008-03 e T136 abertas | Acrescentar nota: Q-008-03 resolvida (DEC-67); T136 cancelada (DEC-65) |
| D16 | 009-adapter-da-api-externa-de-llm/plan.md:7 | (b) | … em "Atualização de 31/10/2026" abaixo; aguarda revisão do responsável. O restante do plano permanece como aprovado.… | CL-19 Fechado em 03/11/2026: o responsável aprovou a DEC-60 | Diz que aguarda revisão | Acrescentar nota: aprovada em 03/11/2026 (CL-19) |
| D17 | 009-adapter-da-api-externa-de-llm/plan.md:121 | (b) | …corporada documentalmente (31/10/2026); validação pendente (CL-32); CL-19 aguarda revisão \|… | CL-32 e CL-19 Fechados em 03/11/2026 | Estado de CL-32 e CL-19 desatualizado | Acrescentar nota (CL-32 e CL-19 Fechados em 03/11/2026) |
| D18 | 009-adapter-da-api-externa-de-llm/plan.md:118 | (b) | …G-009; quickstart Q9–Q10 \| Incorporada; validação pendente (CL-32); termos do fornecedor em CL-19 \|… | CL-32 Fechado em 03/11/2026 | Idem | Acrescentar nota |
| D19 | 009-adapter-da-api-externa-de-llm/plan.md:111 | (b) | …pec. O plano continua válido como está. Continuam abertas: C-009-01 e Q-009-01 (fornecedor e modelo, `closure-register.md` CL-19),… | DEC-34 D1 e DEC-60 resolvem C-009-01 e Q-009-01; CL-19 Fechado | Afirma abertas (texto de 25/10/2026; a nota seguinte cita só Q-009-02, 06, 07) | Acrescentar nota: C-009-01 e Q-009-01 resolvidas (DEC-34 D1, DEC-60); CL-19 Fechado |
| D20 | 009-adapter-da-api-externa-de-llm/plan.md:55 | (b) | …EC-34 D1: o fornecedor não é inventado; a escolha é gate explícito das tasks do adapter real (CL-19). *(Alterado em 25/10/2026; te… | DEC-60 escolhe o OpenRouter; CL-19 Fechado em 03/11/2026 | Descreve a escolha como gate ainda por fazer | Acrescentar nota (DEC-60; CL-19 Fechado) |
| D21 | 009-adapter-da-api-externa-de-llm/quickstart.md:24 | (b) | …e do Moodle, formato real da resposta \| A registrar em CL-32, sem resultado antecipado \| DEC-60; CL-32 \|… | CL-32 Fechado em 03/11/2026 (T138 [x]) | Cenário "a registrar" já registrado | Acrescentar nota (CL-32 Fechado em 03/11/2026) |
| D22 | 013-f-runs-e-dificuldades/plan.md:33 | (a) | …**Performance Goals**: N/A. Ver R-013-02.… | DEC-65: R-013-02 resolvido; sem divisão do conteúdo | Remete a um risco já resolvido, sem nota | Acrescentar nota (DEC-65) |
| D23 | 013-f-runs-e-dificuldades/plan.md:7 | (b) | …dação técnica do §5 (CL-02, CL-35) e os riscos R-013-01 e R-013-02 continuam pendentes e não bloqueiam o planejamento; Q-013-07 se… | DEC-65 (R-013-02); CL-02 FECHADA 02/11/2026; CL-35 Fechado (VT) 03/11/2026 | Afirma pendentes | Acrescentar nota |
| D24 | 001-infraestrutura-github-codespaces-e-container-moodle/plan.md:116 | (a) | …json` \| Mapeamentos aprovados (DEC-28); `forwardPorts` em aberto \|… | DEC-49: `forwardPorts` 8080 e 8025; CL-01 Fechado em 31/10/2026 | "Decisão formal" diz em aberto o que a DEC-49 decidiu | Acrescentar nota (DEC-49) |
| D25 | 001-infraestrutura-github-codespaces-e-container-moodle/plan.md:117 | (a) | ….sh` \| Comando atual aprovado (DEC-28); instalação do Moodle e fixação de versões em aberto \|… | DEC-49 D2 (instalação por CLI, versões fixadas); T008 [ ] (fixação das versões) | Idem; a fixação das versões continua pendente na T008 | Acrescentar nota (DEC-49); manter a T008 como pendência |
| D26 | 001-infraestrutura-github-codespaces-e-container-moodle/plan.md:118 | (a) | …ose.yml` \| `mailhog` aprovado (DEC-28); cron em aberto (lacuna para spec-007/019) \|… | DEC-49 D3: serviço `cron` no Compose | Idem | Acrescentar nota (DEC-49) |
| D27 | 001-infraestrutura-github-codespaces-e-container-moodle/plan.md:119 | (a) | …de dev aprovadas via Q-001-09 (DEC-28); credenciais de admin do Moodle em aberto \|… | DEC-49 D2: senha do administrador por secret | Idem | Acrescentar nota (DEC-49) |
| D28 | 001-infraestrutura-github-codespaces-e-container-moodle/plan.md:109 | (b) | …baseline (DEC-28, que decide C-001-01). As lacunas listadas na coluna "Decisão formal" continuam abertas. Nada abaixo foi validado… | DEC-49 (26/10/2026) | Diz que as lacunas continuam abertas; parte foi decidida pela DEC-49 | Acrescentar nota (DEC-49) |
| D29 | 001-infraestrutura-github-codespaces-e-container-moodle/research.md:32 | (b) | …\| Q-001-08 Montagem × sincronização \| PARCIAL (DEC-27): montagem; origem futura e validação pendentes \| A spec diz "montagem/sin… | Q-001-08 Resolvida (plan.md:164, nota de 04/11/2026); CL-24 Fechado | Estado desatualizado, diferente do plan.md | Acrescentar nota: Q-001-08 Resolvida; CL-24 Fechado |
| D30 | 001-infraestrutura-github-codespaces-e-container-moodle/traceability.md:21 | (b) | …o registrada e propagada em 25/10/2026; origem ausente no repositório; não validada em execução \|… | DEC-27 e CL-24 (Fechado em 31/10/2026): o diretório `assistente/` existe; montagem observada em T013 [x] | Diz origem ausente e montagem não validada | Acrescentar nota (CL-24 Fechado; T013 [x]) |
| D30b | 001-infraestrutura-github-codespaces-e-container-moodle/plan.md:175 | (b) | …rigem `assistente/` \| DEC-27 \| Q-001-08 continua parcial (CL-24) \| Sem mudança \|… | Q-001-08 Resolvida (plan.md:157) | Diz que continua parcial | Acrescentar nota (Q-001-08 Resolvida) |
| D31 | 002-esquema-xmldb/plan.md:144 | (b) | …xml` não foram alterados nesta revisão. Continuam pendentes, sem bloquear o planejamento: `summary_status` e `availability` (Q-008… | Q-008-05 (DEC-50), Q-008-07 (DEC-51); CL-07 Fechado (VT) 02/11/2026; CL-37 Fechado (VT) 28/10/2026 | Afirma pendentes | Acrescentar nota |
| D32 | 005-acesso-configura-o-e-crit-rios/contracts/access-and-config.md:7 | (b) | …orização antes de persistir (spec-020); capability de escrita indefinida (Q-005-08) \|… | DEC-45 D1 (Q-005-08); CL-42 Fechado | Capability "indefinida" | Acrescentar nota (DEC-45) |
| D33 | 005-acesso-configura-o-e-crit-rios/plan.md:109 | (b) | … Semântica do critério binário de fórum continua em aberto [REQ spec-005 §Regras] \| — \| Q-005-06 permanece aberta (`closure-regist… | DEC-40 D3; CL-05 Fechado em 25/10/2026 | Q-005-06 "permanece aberta" | Acrescentar nota (DEC-40; CL-05 Fechado) |
| D34 | 005-acesso-configura-o-e-crit-rios/plan.md:117 | (b) | …Continuam pendentes e rastreadas (`closure-register.md` CL-27): Q-005-03, 04, 05, 07 e 08;… | DEC-45; CL-27 Fechado no nível funcional em 26/10/2026 | Q-005-03, 04, 05, 07 e 08 "pendentes" | Acrescentar nota (DEC-45; CL-27) |
| D35 | 006-contrato-de-eventos-e-fila-l-gica/contracts/assistant-event.md:111 | (b) | …ento dos eventos no Moodle em execução (pendente da VT T132, conforme `022-integra-o-events-hooks/tasks.md`); o formato exato de `… | T132 [x] (`022-*/tasks.md`); CL-02 FECHADA em 02/11/2026 | VT dada como pendente | Acrescentar nota |
| D36 | 006-contrato-de-eventos-e-fila-l-gica/plan.md:111 | (b) | …sure-register.md` CL-02) \| Incorporada; validação pendente \|… | CL-02 FECHADA em 02/11/2026 | Idem | Acrescentar nota |
| D37 | 007-tasks-processamento-e-retries/plan.md:115 | (b) | …nde de Q-006-01/Q-006-04 \| Incorporada; validação pendente \|… | CL-02 FECHADA em 02/11/2026 | Idem | Acrescentar nota |
| D38 | 010-an-lise-inicial-de-question-rios/plan.md:111 | (b) | …nuam bloqueados por Q-006-01/Q-006-04 \| Validação pendente \|… | CL-02 FECHADA em 02/11/2026 | Idem | Acrescentar nota |
| D39 | 017-hist-rico-e-rastreabilidade/plan.md:121 | (b) | …oqueio a validar (CL-02) \| Incorporada; validação pendente \|… | CL-02 FECHADA em 02/11/2026 | Idem | Acrescentar nota |
| D40 | 017-hist-rico-e-rastreabilidade/plan.md:123 | (b) | …2026; validação em CL-07 \| Incorporada; validação pendente *(estado original: "Depende de CL-07 \\| Bloqueada")* \|… | CL-07 Fechado (VT) em 02/11/2026 | Idem | Acrescentar nota |
| D41 | 008-materiais-vers-es-e-contexto/plan.md:130 | (b) | …003). Validação em CL-07 \| Incorporada; validação pendente \|… | CL-07 Fechado (VT) em 02/11/2026 | Idem | Acrescentar nota |
| D42 | 008-materiais-vers-es-e-contexto/research.md:14 | (b) | …o nível funcional (DEC-26, 22/10/2026); API a validar (Q-004-01) *(alterado em 25/10/2026; original: "ABERTO — decisão humana")* \|… | Q-004-01 resolvida no plano 004; readers validados em CL Fechadas | Idem | Acrescentar nota |
| D43 | 010-an-lise-inicial-de-question-rios/plan.md:7 | (b) | …ação; CL-33, banco de questões e slots) continua pendente e não bloqueia o planejamento; nenhum cenário do quickstart foi executad… | CL-02 FECHADA 02/11/2026; CL-33 Fechado (VT) 03/11/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D44 | 011-respostas-e-m-tricas-de-question-rios/plan.md:7 | (b) | …ação técnica do §5 (CL-33) e a Q-011-07 continuam pendentes e não bloqueiam o planejamento; R-011-01 fica como risco a avaliar na … | CL-33 Fechado (VT) 03/11/2026; Q-011-07 resolvida pela DEC-52 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D45 | 012-notas-e-desempenho/plan.md:7 | (b) | …2026. A validação técnica do §5 (CL-34) continua pendente e R-012-01 fica como risco; nenhum dos dois bloqueia o planejamento. Nen… | CL-34 Fechado (VT) 03/11/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D46 | 014-frequ-ncia-e-participa-o/plan.md:7 | (b) | …ico). A validação técnica do §5 (CL-36) continua pendente e R-014-01 fica como risco documentado; nenhum dos dois bloqueia o plane… | CL-36 Fechado (VT) 03/11/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D47 | 016-s-ntese-pedag-gica/plan.md:7 | (b) | …cnicas CL-16 (seção) e CL-07 (Lock API) continuam pendentes e R-016-01 fica como risco documentado; nenhum deles bloqueia o planej… | CL-16 Fechado (VT) 03/11/2026; CL-07 Fechado (VT) 02/11/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D48 | 018-consultas-funcionais/plan.md:7 | (b) | …C-43. A validação técnica do §5 (CL-38) continua pendente e não bloqueia o planejamento. Nenhum cenário do quickstart foi executad… | CL-38 Fechado (VT) 03/11/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D49 | 019-relat-rios-e-versionamento/plan.md:7 | (b) | …ica de fuso, cron, concorrência e datas continua pendente em CL-39, sem resultado antecipado, e R-019-01 fica como risco; nenhum d… | CL-39 FECHADA 28/10/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D50 | 020-form-api/plan.md:7 | (b) | …ncia. A validação técnica do §5 (CL-40) continua pendente e R-020-01 fica como risco; nenhum dos dois bloqueia o planejamento. Nen… | CL-40 Fechado (VT) 03/11/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D51 | 021-navega-o-renderiza-o-e-ui-js/plan.md:7 | (b) | … 018. A validação técnica do §5 (CL-41) continua pendente e R-G3 fica como risco; nenhum dos dois bloqueia o planejamento. Nenhum … | CL-41 Fechado (VT) 03/11/2026 | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D52 | 022-integra-o-events-hooks/plan.md:7 | (b) | …-41) e da leitura de matrículas (CL-38) continua pendente e não bloqueia o planejamento; R-G3 fica como risco. Nenhum cenário do q… | CL-02 (02/11), CL-33 a CL-36, CL-38, CL-40, CL-41 (03/11) e CL-37 (28/10): todos Fechados | Texto datado da aprovação (25/10 ou 26/10/2026) afirma a validação técnica pendente | Acrescentar nota de resolução (ou manter como histórico datado: decisão do responsável) |
| D53 | 020-form-api/plan.md:145 | (b) | …L-38 (grupos) e CL-39 (fuso do site), **pendentes**.… | CL-38 Fechado (VT) 03/11/2026; CL-39 FECHADA 28/10/2026 | Afirma pendentes | Acrescentar nota |
| D54 | 020-form-api/quickstart.md:43 | (b) | …Validação técnica pendente (sem resultado antecipado): grupos separados e API de grupos no… | idem D53 | Idem | Acrescentar nota |
| D55 | 023-testes-e-qualidade-transversal/plan.md:118 | (a) | …ão das dependências de desenvolvimento (o Dockerfile usa `composer install --no-dev`), questão a tratar junto com Q-001-05. *(26/1… | DEC-49 D1: remover `--no-dev` da instalação do Composer no ambiente de desenvolvimento | Descreve o baseline da DEC-28 (`--no-dev`) sem citar a DEC-49 | Acrescentar nota (DEC-49 D1); CL-22 continua Aberto (VT) |

### 3.1 Registro cronológico em `002-esquema-xmldb/quickstart.md` (sugerido: manter, sem correção)

As linhas 168, 184, 328, 512, 520 e 668 pertencem ao registro sequencial da Etapa 6 e dizem "pendente" ou "aguardando revisão" no momento em que foram escritas. O encerramento formal da CL-07 (02/11/2026) está registrado depois, na mesma seção, e as linhas 628 e 632 já têm a nota de superação. CL-50 e CL-51 foram encerradas em 02/11/2026 como limitação aceita (`closure-register.md`). Proposta: manter como registro cronológico. Se o responsável preferir, acrescentar uma nota por linha.

**Decisão do responsável (04/11/2026):** manter sem correção. Motivo: diário sequencial da CL-07, cujo encerramento está registrado depois, na mesma seção.

## 4. Pendências reais (continuam abertas; não são divergência)

| Item | Onde aparece no escopo | Evidência de que continua aberto |
|---|---|---|
| CL-22 (runners PHPUnit e Behat) | `023-*/plan.md:118`; `001-*/plan.md` (§5) | `closure-register.md`: CL-22 "Aberto (VT)" |
| T007 e T008 (cenários da CL-01 e fixação de versões) | `001-*/quickstart.md:29`; `001-*/plan.md:117` | `001-*/tasks.md`: T007 [ ], T008 [ ] |
| T139 e T140 (suítes completas e gate G-023) | referências à matriz da 023 | `023-*/tasks.md`: T139 [ ], T140 [ ] |
| T130 (checklist do pesquisador) | `021-*/tasks.md` | `021-*/tasks.md`: T130 [ ] |
| Limitações documentadas (isolamento transacional e vários servidores: "inferência, não testada") | `002-*/quickstart.md:138` e `:139` | texto da própria tabela de limitações; não é pendência de execução |

## 5. Decisões do responsável (04/11/2026)

Decisão geral: as 56 divergências (D01 a D55, incluindo D30b) foram aprovadas com a correção proposta; as notas "(04/11/2026, revisão dos planos: ...)" foram aplicadas fora de citações, sem alterar o texto anterior (69 linhas alteradas, 56 deste item e 13 do item 6).


| ID | Decisão (aprovar / alterar / manter) | Observação |
|---|---|---|
| D01 | Aprovada; nota aplicada (04/11/2026) |  |
| D02 | Aprovada; nota aplicada (04/11/2026) |  |
| D03 | Aprovada; nota aplicada (04/11/2026) |  |
| D04 | Aprovada; nota aplicada (04/11/2026) |  |
| D05 | Aprovada; nota aplicada (04/11/2026) |  |
| D06 | Aprovada; nota aplicada (04/11/2026) |  |
| D07 | Aprovada; nota aplicada (04/11/2026) |  |
| D08 | Aprovada; nota aplicada (04/11/2026) |  |
| D09 | Aprovada; nota aplicada (04/11/2026) |  |
| D10 | Aprovada; nota aplicada (04/11/2026) |  |
| D11 | Aprovada; nota aplicada (04/11/2026) |  |
| D12 | Aprovada; nota aplicada (04/11/2026) |  |
| D13 | Aprovada; nota aplicada (04/11/2026) |  |
| D14 | Aprovada; nota aplicada (04/11/2026) |  |
| D15 | Aprovada; nota aplicada (04/11/2026) |  |
| D16 | Aprovada; nota aplicada (04/11/2026) |  |
| D17 | Aprovada; nota aplicada (04/11/2026) |  |
| D18 | Aprovada; nota aplicada (04/11/2026) |  |
| D19 | Aprovada; nota aplicada (04/11/2026) |  |
| D20 | Aprovada; nota aplicada (04/11/2026) |  |
| D21 | Aprovada; nota aplicada (04/11/2026) |  |
| D22 | Aprovada; nota aplicada (04/11/2026) |  |
| D23 | Aprovada; nota aplicada (04/11/2026) |  |
| D24 | Aprovada; nota aplicada (04/11/2026) |  |
| D25 | Aprovada; nota aplicada (04/11/2026) | Nota diz que a instalação foi decidida pela DEC-49 D2 e que a fixação das versões continua pendente (T008) |
| D26 | Aprovada; nota aplicada (04/11/2026) |  |
| D27 | Aprovada; nota aplicada (04/11/2026) |  |
| D28 | Aprovada; nota aplicada (04/11/2026) |  |
| D29 | Aprovada; nota aplicada (04/11/2026) |  |
| D30 | Aprovada; nota aplicada (04/11/2026) |  |
| D30b | Aprovada; nota aplicada (04/11/2026) |  |
| D31 | Aprovada; nota aplicada (04/11/2026) |  |
| D32 | Aprovada; nota aplicada (04/11/2026) |  |
| D33 | Aprovada; nota aplicada (04/11/2026) |  |
| D34 | Aprovada; nota aplicada (04/11/2026) |  |
| D35 | Aprovada; nota aplicada (04/11/2026) |  |
| D36 | Aprovada; nota aplicada (04/11/2026) |  |
| D37 | Aprovada; nota aplicada (04/11/2026) |  |
| D38 | Aprovada; nota aplicada (04/11/2026) |  |
| D39 | Aprovada; nota aplicada (04/11/2026) |  |
| D40 | Aprovada; nota aplicada (04/11/2026) |  |
| D41 | Aprovada; nota aplicada (04/11/2026) |  |
| D42 | Aprovada; nota aplicada (04/11/2026) |  |
| D43 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D44 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D45 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D46 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D47 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D48 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D49 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D50 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D51 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D52 | Aprovada; nota aplicada (04/11/2026) | Nota de resolução aplicada (decisão do responsável: não tratar como histórico) |
| D53 | Aprovada; nota aplicada (04/11/2026) |  |
| D54 | Aprovada; nota aplicada (04/11/2026) |  |
| D55 | Aprovada; nota aplicada (04/11/2026) |  |

## 6. Propagações pendentes aos planos-alvo (decisão do responsável, opção A)

Para cada linha "Propagações pendentes" dos planos 010 a 022 conferiu-se, em plan, research, data-model, quickstart, traceability e contracts de cada plano-alvo, se a DEC é citada. Alvos sem citação tiveram a implementação conferida (tarefas [x], CL fechadas e código); todos tinham evidência, por isso não houve parada. Os planos-alvo não foram reabertos.

| Plano de origem | DEC | Alvos com citação | Alvos sem citação | Evidência |
|---|---|---|---|---|
| 010 | DEC-35 | 004, 011, 018, 019, 021 | — | — |
| 011 | DEC-36 | 018, 021, 022 | 015, 016, 019 | T091/T093 [x] (015); T095/T097 [x] (016); T115/T116 [x], CL-39 FECHADA (019) |
| 012 | DEC-37 | 015, 016, 018, 021, 022 | 019 | T115/T116 [x], CL-39 FECHADA em 28/10/2026 |
| 013 | DEC-38 | 002, 016, 017, 018, 022 | 015 | T091 [x], DEC-40 D3, CL-12 Fechado |
| 014 | DEC-39 | 015, 016, 018, 019, 021, 022 | — | — |
| 015 | DEC-40 | 016, 018, 021, 020 | 019 | T115/T116 [x] (bloco "abaixo dos critérios"), CL-39 FECHADA |
| 016 | DEC-41 | 017, 018, 019, 021 | — | — |
| 017 | DEC-42 | 002, 019, 021, 023 | — | — |
| 018 | DEC-43 | 019, 020, 021, 023 | — | — |
| 019 | DEC-44 | 020, 021, 023 | — | — |
| 020 | DEC-45 | 018, 021, 023 | — | — |
| 021 | DEC-46 | 023 | 022 | Q-022-02 resolvida (só hook de navegação), CL-41 Fechado (VT) em 03/11/2026 |
| 022 | DEC-47 | 023 | — | — |
