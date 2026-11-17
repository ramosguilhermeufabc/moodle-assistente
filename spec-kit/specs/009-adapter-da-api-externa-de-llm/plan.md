# Implementation Plan: spec-009 — Adapter da API Externa de LLM

**Branch**: `feature/sdd-ajustado` (feature dir `009-adapter-da-api-externa-de-llm`) | **Date**: 2026-10-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/009-adapter-da-api-externa-de-llm/spec.md`

**Atualização de 31/10/2026 (DEC-60)**: fornecedor, configuração administrativa, política ZDR e formato externo da resposta, em "Atualização de 31/10/2026" abaixo; aguarda revisão do responsável. O restante do plano permanece como aprovado. *(04/11/2026, revisão dos planos: a DEC-60 foi aprovada pelo responsável e o CL-19 foi Fechado em 03/11/2026.)*

**Aprovação**: aprovado pelo responsável em 25/10/2026, com a validação técnica do §5 (CL-32) como pendência que não bloqueia o planejamento e a escolha de fornecedor e modelo (CL-19) como gate só das tasks do adapter real. A aprovação isolada não autoriza a geração de tasks.

**Note**: Este plano segue `.claude/skills/speckit-plan/SKILL.md` (Spec Kit 1.2.2) e o PROMPT-3 (fonte única: os 23 `spec.md`). Nenhum teste foi executado. **Nenhum fornecedor ou modelo de LLM foi escolhido**: por DEC-34 D1, a escolha é gate só das tasks do adapter real (CL-19). *(Alterado em 25/10/2026; texto original: "(ver C-009-01).")* *(31/10/2026, DEC-60: o fornecedor inicial passou a ser o OpenRouter; URL e modelo são configurações administrativas, sem valor fixo no código. A frase anterior descreve o estado de 25/10/2026.)*

**Legenda**: `[REQ spec-NNN §Seção]` · `[ESCOLHA]` · `NEEDS CLARIFICATION` / `Q-NNN-xx`.

## Summary

Isolar a integração com a API externa de LLM atrás da porta lógica `evaluate(context, instruction) → structured_result`, com um adapter externo responsável por autenticação, request, response, timeout/erro e validação de resposta [REQ spec-009 §Contratos]. A LLM é usada **apenas** para resumo de materiais, cobertura de questionário, complexidade de questão, resumo/dificuldades de fórum e síntese pedagógica. Nunca para contagens, médias, desvio-padrão, mín/máx, frequência ou aplicação de critérios [REQ §Regras]. As credenciais ficam fora do banco funcional e do Git [REQ §Regras].

*(Incluído em 25/10/2026 por DEC-33 D1 e DEC-34.)* A estrutura é substituível: porta, adapter simulado e ponto de integração do adapter real. A porta e o adapter simulado são entregues antes da spec-008; o adapter real é a última entrega e depende da escolha de fornecedor e modelo (CL-19). Fora do desenvolvimento, a credencial vem só de variável de ambiente do processo PHP, nunca de configuração no banco nem do Git; no desenvolvimento, do secret do ambiente (spec-001). Nenhum identificador de estudante é enviado à LLM. *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)*

## Technical Context

**Language/Version**: PHP (spec-001).

**Primary Dependencies**: API externa de LLM. Fornecedor e modelo não escolhidos (DEC-34 D1): gate das tasks do adapter real (Q-009-01, CL-19). A porta e o adapter simulado não dependem dessa escolha. Cliente HTTP do Moodle 5.2 a verificar (CL-32). *(31/10/2026, DEC-60: fornecedor inicial OpenRouter, Responses API, por HTTP com o cliente do Moodle, sem SDK; o modelo não é fixado: configuração administrativa.)* *(Alterado em 25/10/2026; texto original: "API externa de LLM. Fornecedor/modelo **NEEDS CLARIFICATION** (Q-009-01, bloqueador).")* (04/11/2026: resolvido: Q-009-01 resolvida por DEC-60 (OpenRouter; modelo como configuração administrativa, sem modelo fixo) e CL-19 Fechado em 03/11/2026 (T133 [x]); adaptador real em assistente/classes/infrastructure/llm/http_adapter.php (T134 [x]).)

**Storage**: nenhum dado próprio; a porta não persiste nada. Credencial: em dev, Codespaces secrets [spec-001]; fora de dev, exclusivamente variável de ambiente do processo PHP, nunca configuração do plugin no banco nem o Git (DEC-34 D2). *(Alterado em 25/10/2026; texto original: "nenhum dado próprio. Credenciais fora do banco funcional [REQ §Regras]; em dev, Codespaces secrets [spec-001].")* *(31/10/2026, DEC-60: a URL do endpoint e o identificador do modelo são configuração administrativa do plugin, sem valor padrão; não são credencial. A chave continua só na variável de ambiente.)* *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)*

**Testing**: API LLM mockada; falha da API LLM [REQ spec-023]; validação de esquema, faixa e passo; ausência de identificadores de estudantes na requisição (DEC-34 D3). Runner em Q-023-01. *(Alterado em 25/10/2026; texto original: "API LLM mockada; falha da API LLM [REQ spec-023]. Runner em Q-023-01.")*

**Target Platform**: GitHub Codespaces + Moodle 5.2.

**Project Type**: plugin local Moodle, adapter de integração externa.

**Performance Goals**: N/A. O valor do timeout é fixado nas tasks e deve ser menor que o intervalo do ciclo (DEC-32 D5; Q-009-03). *(Alterado em 25/10/2026; texto original: "N/A. Timeout em Q-009-03.")*

**Constraints**: o contrato funcional não muda com a escolha do adapter [REQ §Conformidade]; a LLM nunca é chamada no observer/request [spec-006, spec-022]; sem transação de banco aberta durante a chamada [spec-007]; sem identificadores de estudantes na requisição (DEC-34 D3). *(Alterado em 25/10/2026; incluídos os dois últimos itens.)*

**Scale/Scope**: 5 usos [REQ §Regras].

## Constitution Check

| Princípio | Pré | Pós | Observação |
|---|---|---|---|
| §1 Fidelidade | PASSA | PASSA | Usos permitidos e proibidos copiados da spec. |
| §2 Rastreabilidade | BLOQUEADO | PASSA | Constituição 1.1.0 §2: a rastreabilidade AT fica no `traceability.md` desta spec e em `requirements-traceability.md`; as specs não precisam citar os IDs, e a correspondência entre AT e tarefa é registrada depois das Tasks. *(Reavaliado em 25/10/2026; Pós original: "BLOQUEADO"; observação original: "R-G2.".)* |
| §3 Fluxo SDD | PASSA | PASSA | — |
| §4 Separação | PASSA | PASSA | Porta × adapter. |
| §5 Compatibilidade Moodle | BLOQUEADO | PASSA *(reavaliado em 04/11/2026; anterior: "BLOQUEADO")* | Validação técnica que não bloqueia o planejamento (`closure-register.md` CL-32): cliente HTTP do Moodle 5.2, leitura da variável de ambiente pelo processo PHP (inclusive cron/CLI) e declaração da localização externa na Privacy API. *(Alterado em 25/10/2026 por DEC-34; observação original: "Mecanismo de HTTP e armazenamento de credenciais no Moodle não verificados (Q-009-02).")* *(Reavaliado em 04/11/2026: §5 Compatibilidade Moodle: PASSA, validado em CL-32 (Fechado em 03/11/2026, validação técnica com ressalvas; T138 [x], T134 [x], T137 [x]))* *(04/11/2026, revisão dos planos: DEC-69: a chave não vem mais de variável de ambiente, e sim da configuração de site criptografada; CL-32 Fechado em 03/11/2026.)* |
| §6 Materiais | N/A | N/A | — |
| §7 Integridade | BLOQUEADO | PASSA | No escopo desta spec, a porta não persiste nada e devolve falha sem efeito colateral; resposta inválida é falha e nada é persistido. Preservar o último resultado válido é responsabilidade das specs consumidoras: a 008 já define (DEC-05, DEC-15) e Q-016-04 continua aberta no plano 016. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Falha da LLM não pode apagar o último resultado válido; a política está nas specs consumidoras (Q-016-04, Q-008-05).")* |
| §8 Idempotência | N/A | N/A | Retries em spec-007. |
| §9 Testabilidade | BLOQUEADO | PASSA | Envelope e payload mínimo por uso definidos a partir das specs consumidoras ([contracts/llm-port.md](contracts/llm-port.md)); cenários Q1–Q10 do [quickstart.md](quickstart.md). O formato da síntese segue Q-016-02/03. *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| Esquema de `structured_result` indefinido, logo a validação não é especificável (Q-009-04).")* |
| §10 Transparência | PASSA | PASSA | — |
| §11 Governança | BLOQUEADO | PASSA | C-009-01 resolvida por DEC-34 D1: o fornecedor não é inventado; a escolha é gate explícito das tasks do adapter real (CL-19). *(Alterado em 25/10/2026; texto original: "BLOQUEADO \| C-009-01: escolha de fornecedor reservada à decisão humana.")* *(04/11/2026, revisão dos planos: a DEC-60 escolhe o OpenRouter; CL-19 Fechado em 03/11/2026.)* |
| §12 Plataforma e arquitetura | — | PASSA | O plano usa as APIs oficiais do Moodle e a persistência da spec-003 (XMLDB/DML). A separação em camadas e os caminhos das classes ficam em Q-003-04. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §13 Uso de LLM | — | PASSA | A LLM é usada só nos 5 usos linguísticos e nunca em cálculos; as credenciais ficam fora do Git e do banco funcional [REQ spec-009 §Regras]. Fora de dev, a credencial vem só de variável de ambiente (DEC-34 D2). *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0; alterado no mesmo dia por DEC-34; texto original: "O local das credenciais fora de dev está em Q-009-02.")* *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* |
| §14 Controle de acesso | — | N/A | — *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |
| §15 Interfaces externas | — | PASSA | Integração só de saída com a API externa de LLM; o plugin não expõe nada. *(Avaliado em 25/10/2026, depois da ratificação da constituição 1.1.0.)* |

**Resultado do gate**: princípios BLOQUEADO: §5 (validação técnica, CL-32; não bloqueia o planejamento). Questões Bloqueador: nenhuma. A escolha de fornecedor e modelo (Q-009-01) é gate das tasks do adapter real, não do plano. Nenhuma alteração de esquema, código ou teste foi feita nesta fase. *(Reavaliado em 25/10/2026, depois da ratificação da constituição 1.1.0: §2 passa a PASSA; §§ 12–15 avaliados.)* *(Alterado em 25/10/2026 por DEC-34; texto original: "princípios BLOQUEADO: §5, §7, §9, §11. Questões Bloqueador: Q-009-01, Q-009-04.")* *(Reavaliado em 04/11/2026: passam todos os princípios, inclusive §5 Compatibilidade Moodle, validado em CL-32 (Fechado em 03/11/2026, validação técnica com ressalvas; T138 [x], T134 [x], T137 [x]); nenhum princípio continua BLOQUEADO; texto anterior preservado acima.)*

## Project Structure

### Documentation (this feature)

```text
specs/009-adapter-da-api-externa-de-llm/
├── plan.md · research.md · data-model.md · quickstart.md
└── contracts/llm-port.md
```

### Source Code (repository root)

```text
assistente/
└── [porta LLM + adapter externo + adapter mock]   # caminhos NEEDS CLARIFICATION (Q-003-04) (04/11/2026: resolvido: Q-003-04 resolvida na seção "Layout das classes" de specs/003-*/plan.md (aprovada em 26/10/2026); código em assistente/classes/infrastructure/llm/llm_port.php, http_adapter.php e fake_adapter.php.)
```

## Dependências entre specs

- Upstream: spec-007, spec-008 [REQ §Dependências]. A spec-008 depende tecnicamente desta spec para os resumos de material (circular; ver Q-009-07 e Q-008-10). *(25/10/2026, DEC-33 D1: ordem de entrega porta + adapter simulado → spec-008 → adapter real.)*
- Downstream: 010, 013, 016 (e resumo de material em 008). As specs 013 e 016 aplicam a minimização de DEC-34 D3 na montagem do `context`.

## Fases de planejamento & gates

1. **Porta e adapter simulado** (DEC-33 D1, DEC-34 D1): porta `evaluate()`, envelope de `structured_result`, validação por uso e adapter simulado. Entregues antes da spec-008.
2. **Validação de saída** (Q-009-04): envelope e payload mínimo por uso, conforme [contracts/llm-port.md](contracts/llm-port.md).
3. **Falhas** (Q-009-03): toda falha do adapter é tentativa falha da spec-007, com classe registrada (DEC-32).
4. **Gate G-009**: a porta é exercitada só por mock nos testes; nenhum cálculo determinístico passa pela LLM; nenhuma credencial no Git ou no banco; nenhuma requisição contém identificador de estudante (DEC-34 D3).
5. **Gate G-009-R (adapter real)**: só começa depois da decisão humana sobre fornecedor e modelo e dos termos de contrato e retenção (CL-19); exige a validação técnica de CL-32. *(31/10/2026, DEC-60: a decisão de fornecedor e modelo está formalizada e aguarda revisão; a Q-008-03 e a revisão dos termos do fornecedor continuam abertas; ver CL-19.)* *(08/11/2026: CL-19 foi fechado em 03/11/2026, por decisão do responsável, e CL-32 em 03/11/2026, validação técnica com ressalvas; as pendências afirmadas acima não estão mais abertas. Texto anterior preservado, conforme a constituição §11.)*

*(Alterado em 25/10/2026 por DEC-33 e DEC-34; texto original: "1. **Decisão humana**: fornecedor e modelo (C-009-01 / Q-009-01). 2. Definir os esquemas de `structured_result` por uso e as regras de validação (Q-009-04). 3. Definir timeout, erro e mapeamento para tentativas de spec-007 (Q-009-03). 4. **Gate G-009**: a porta é exercitada só por mock nos testes; nenhum cálculo determinístico passa pela LLM; nenhuma credencial no Git ou no banco.")*

## Questões abertas / Riscos / Contradições

| ID | Tipo | Descrição | Fonte |
|---|---|---|---|
| C-009-01 | Resolvido | A spec diz que "o plano deve escolher e documentar o adapter", mas o PROMPT-3 proíbe resolver lacunas sem base nas specs (nenhum fornecedor é citado). Mantido como bloqueador para decisão humana. **Resolvido por DEC-34 D1 (25/10/2026):** o plano documenta a estrutura substituível (porta, adapter simulado, ponto de integração do adapter real); a escolha de fornecedor e modelo é gate das tasks do adapter real (CL-19). *(Tipo original: "Contradição".)* | §Conformidade |
| Q-009-01 | Resolvida *(tipo original: "Aberto")* | Fornecedor/modelo de LLM. *(25/10/2026, DEC-34 D1: decisão humana pendente em CL-19, como gate só das tasks do adapter real; não bloqueia o plano, a porta, o adapter simulado nem a spec-008. Tipo original: "Bloqueador".)* **Parcial (31/10/2026, DEC-60):** fornecedor = OpenRouter; modelo = configuração administrativa, sem modelo fixo. Falta a aprovação do responsável (CL-19). *(04/11/2026, etapa 5a: Resolvida; evidência: DEC-60 (OpenRouter; URL e modelo administrativos), DEC-69 (chave), DEC-70 e DEC-71 (ZDR); CL-19 Fechado em 03/11/2026.)* | §Conformidade |
| Q-009-02 | Resolvido | Onde ficam as credenciais fora de dev ("fora do banco funcional e do Git"). **Resolvido por DEC-34 D2 (25/10/2026):** exclusivamente variável de ambiente do processo PHP; nunca configuração do plugin no banco nem o Git; em dev, secret do ambiente (spec-001). Validação técnica em CL-32. *(Tipo original: "Aberto".)* *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | §Regras |
| Q-009-03 | Parcial *(03/11/2026: **Resolvida** por decisão do responsável: timeout de 120 segundos, constante `http_adapter::TIMEOUT` sem configuração nova, abaixo do ciclo de 15 minutos da fila (900 s, DEC-32 D5); o texto desta linha foi preservado)* | Valores de timeout, limites e classificação de erros (re-tentável ou não). **Parcial (25/10/2026):** toda falha do adapter (timeout, transporte, HTTP, autenticação, limite de taxa, resposta inválida) é tentativa falha da spec-007, com o limite de 3 tentativas aplicado igualmente; a classe (`timeout`, `transport`, `auth`, `rate_limit`, `invalid_response`) só é registrada na tentativa, sem estado novo (DEC-32 D3–D4). Fica para as tasks o valor do timeout, que deve ser menor que o intervalo do ciclo (DEC-32 D5). *(31/10/2026, DEC-60: mapeamento das falhas do adapter HTTP para as cinco classes em [contracts/llm-port.md](contracts/llm-port.md); sem classe nem estado novo.)* | §Contratos; DEC-32 |
| Q-009-04 | Resolvida *(tipo original: "Parcial")* | Esquema do `structured_result` para cada um dos 5 usos e regras de validação. **Parcial (25/10/2026):** envelope e payload mínimo por uso derivados das specs 002, 010, 013 e 016 ([contracts/llm-port.md](contracts/llm-port.md)). Fica em aberto o formato detalhado da síntese (Q-016-02/03). *(Tipo original: "Bloqueador".)* *(31/10/2026, DEC-60: a conversão da resposta externa da Responses API para o envelope existente está em [contracts/llm-port.md](contracts/llm-port.md); o envelope e os critérios de T052 e T053 não mudam.)* *(04/11/2026, etapa 5a: Resolvida; evidência: esquema por uso em `assistente/classes/infrastructure/llm/result_validator.php` (T053 [x]); o formato da síntese foi fechado por Q-016-02 e Q-016-03, Resolvidas no plano 016.)* | §Contratos; spec-010; spec-013; spec-016 |
| Q-009-05 | Resolvida *(tipo original: "Aberto")* | Conteúdo das instructions (prompts) por uso. *(25/10/2026: não bloqueia; elaborado nas tasks consumidoras.)* *(04/11/2026, etapa 5a: Resolvida; evidência: instruções por uso em `assistente/classes/application/material/material_service.php` (`SUMMARY_TASK`), `assistente/classes/application/quiz/initial_analysis_handler.php` (`COVERAGE_TASK`, `COMPLEXITY_TASK`), `assistente/classes/application/forum/forum_analysis_handler.php` (`ANALYSIS_TASK`) e `assistente/classes/application/synthesis/synthesis_handler.php` (`SYNTHESIS_TASK`); formato por uso em `assistente/classes/infrastructure/llm/http_adapter.php` e validação em `assistente/classes/infrastructure/llm/result_validator.php`, para os 5 usos de `assistente/classes/domain/llm/llm_use_case.php`.)* | §Contratos |
| Q-009-06 | Aceito como limitação (04/11/2026) *(tipo original: "Parcial")* | Envio de dados de estudantes a serviço externo: política de privacidade e minimização não especificada. **Parcial (25/10/2026, DEC-34 D3):** nenhum identificador de estudante é enviado; na análise de fórum, autor, ID de usuário, nome e e-mail ficam fora; a síntese usa só agregados e textos já produzidos; localização externa declarada na Privacy API (validação em CL-32). Os termos de contrato e retenção do fornecedor ficam em CL-19. *(Tipo original: "Risco".)* *(31/10/2026, DEC-60 D4: exigência de ZDR formalizada; a retenção efetiva não está validada (CL-32) e os termos do fornecedor não foram revistos.)* *(04/11/2026, etapa 5a: Aceito como limitação, por decisão do responsável: a retenção efetiva no fornecedor não é verificável pelo código; o ZDR é um pedido ao fornecedor (DEC-71; ressalva da CL-32).)* | §Regras |
| Q-009-07 | Resolvido | Dependência circular com a spec-008: esta spec depende da 008, e a 008 usa o adapter de LLM para resumos de material. Ver Q-008-10. **Resolvido por DEC-33 D1 (25/10/2026):** a porta `evaluate()` desta spec é entregue primeiro, com adapter simulado; depois a 008; o adapter real por último (CL-19). *(Tipo original: "Contradição".)* | spec-009 §Dependências, §Regras; spec-008 §Objetivo |
| R-G4 | Resolvido (04/11/2026) *(tipo original: "Risco")* | Ver spec-001/plan.md. *(R-G1 e R-G2 resolvidos em 25/10/2026 com a ratificação da constituição 1.1.0; linha original: "R-G1/R-G2/R-G4".)* *(04/11/2026, etapa 5a: Resolvido; evidência: CL-01, Fechado em 31/10/2026, Q8: PHP 8.3.36 e MariaDB 11.4.13 com o Moodle 5.2.4+ verificados contra a matriz do Moodle; `closure-register.md`.)* | — |

## Revisão pós-propagação (25/10/2026)

A propagação de 22/10/2026 não alterou o `spec.md` desta feature, e nenhuma DEC trata desta spec. O plano continua válido como está. Continuam abertas: C-009-01 e Q-009-01 (fornecedor e modelo, `closure-register.md` CL-19), Q-009-04 (`structured_result`), Q-009-06 (dados enviados) e a contradição Q-009-07 (ordem de entrega 008 ↔ 009, CL-13). Os nomes dos secrets da LLM no ambiente dependem de Q-009-01 (plano 001, Q-001-09). Nada foi executado nem testado. *(25/10/2026: Q-009-07 resolvida por DEC-33; C-009-01, Q-009-02 e, em parte, Q-009-06 resolvidas por DEC-34; Q-009-03 e Q-009-04 tratadas no plano. Ver a tabela abaixo.)* *(04/11/2026, revisão dos planos: C-009-01 e Q-009-01 resolvidas (DEC-34 D1 e DEC-60); CL-19 Fechado em 03/11/2026.)*

| Item | DEC | Efeito no plano | Estado |
|---|---|---|---|
| Ordem de entrega: porta + adapter simulado, depois 008, depois adapter real | DEC-33 D1 | Q-009-07 resolvida; fases 1 e 5 | Incorporada (25/10/2026) |
| Estrutura substituível; fornecedor e modelo como gate do adapter real | DEC-34 D1 | C-009-01 resolvida; Q-009-01 deixa de ser bloqueador; §11 passa | Incorporada (25/10/2026) |
| Credencial só por variável de ambiente fora de dev *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | DEC-34 D2 | Q-009-02 resolvida; §13 atualizado; §5 em CL-32 | Incorporada; validação pendente (CL-32) |
| Sem identificadores de estudantes; Privacy API | DEC-34 D3 | Q-009-06 parcial; Gate G-009; quickstart Q9–Q10 | Incorporada; validação pendente (CL-32); termos do fornecedor em CL-19 *(04/11/2026, revisão dos planos: CL-32 Fechado em 03/11/2026.)* |
| Falhas como tentativas da spec-007, com classe registrada | DEC-32 (aplicação, sem reabrir) | Q-009-03 parcial | Incorporada (25/10/2026) |
| Envelope e payload por uso | — (derivado das specs 002, 010, 013, 016) | Q-009-04 parcial; §9 e §7 passam | Incorporada (25/10/2026) |
| OpenRouter; URL e modelo administrativos; ZDR; formato externo | DEC-60 (complementa DEC-34 D1; preserva D2 e D3) | Q-009-01 parcial; contrato, quickstart Q11–Q17, tasks T133–T138 | Incorporada documentalmente (31/10/2026); validação pendente (CL-32); CL-19 aguarda revisão *(04/11/2026, revisão dos planos: CL-32 e CL-19 Fechados em 03/11/2026.)* |

O `spec.md` desta feature não foi alterado: DEC-34 afeta só o plano, como DEC-33. As specs 013 e 016 recebem a regra de DEC-34 D3 na revisão dos respectivos planos. Nada foi executado nem testado.

**Referência a DEC-46** *(incluída em 26/10/2026, na revisão do plano 021, com autorização do responsável; alteração somente referencial, sem mudança do conteúdo funcional deste plano aprovado)*: o idioma definido por DEC-46 (idioma forçado do curso ou, na falta dele, idioma padrão do site) é transmitido na `instruction` do contrato `evaluate(context, instruction)` para os usos que geram texto (`material_summary`, `forum_analysis`, `pedagogical_synthesis`). Ver `decision-register-DEC-01-25.md`, DEC-46.

## Atualização de 31/10/2026 (DEC-60)

Origem: `decision-register-DEC-01-25.md`, DEC-60 (D1 a D5), que complementa DEC-34 D1 e preserva D2 e D3. Apenas documental: nenhum código, esquema ou teste foi alterado, nenhuma chamada real foi feita e nada foi validado.

| Tema | Regra | Detalhe |
|---|---|---|
| Fornecedor (D1) | OpenRouter, por HTTP, sem SDK; cliente HTTP do Moodle (T134) | [research.md](research.md) |
| URL e modelo (D2) | Dois campos administrativos independentes, sem valor padrão; URL é o endpoint completo, sem caminho acrescentado; a referência inicial é `https://openrouter.ai/api/v1/responses`; `openai/o4-mini` é só exemplo *(04/11/2026, revisão dos planos: DEC-70 altera a referência inicial para `https://openrouter.ai/api/v1/chat/completions`.)* | [contracts/llm-port.md](contracts/llm-port.md) §Configuração administrativa |
| Credencial (D3) | Só `ASSISTENTE_LLM_API_KEY` no processo PHP; nunca em `config_plugins` (nem na tela administrativa), tabela, Git, log, erro ou teste *(03/11/2026, DEC-69: a chave da API é configurada somente pela administração do Moodle (configuração de site `local_assistente`, `admin_setting_encryptedpassword`) e gravada criptografada; a variável de ambiente `ASSISTENTE_LLM_API_KEY` deixa de existir.)* | DEC-34 D2, preservada |
| ZDR (D4) | `provider.zdr = true` e `provider.data_collection = "deny"` em toda requisição; sem fallback fora da política; sem endpoint compatível = falha controlada *(04/11/2026, revisão dos planos: DEC-71: a exigência de ZDR é a configuração `llmzdr`, ligada por padrão; desligada, o objeto `provider` não é enviado.)* | [contracts/llm-port.md](contracts/llm-port.md) §Requisição externa; [research.md](research.md) |
| Resposta (D5) | Responses API convertida para o `structured_result` existente; nada fora do adaptador *(04/11/2026, revisão dos planos: DEC-70: o adaptador usa a Chat Completions API.)* | [contracts/llm-port.md](contracts/llm-port.md) §Resposta externa e conversão |

Limites desta atualização:
- A política do fornecedor não controla o que o Moodle mantém (resultados válidos persistidos, histórico de DEC-42, logs do servidor, cron e backups).
- Metadados operacionais (modelo, tokens, latência, custo) são distintos do conteúdo e não são persistidos pelo plugin.
- Ausência de treinamento não é ausência de retenção; `data_collection = "deny"` e `zdr = true` são exigências separadas.
- Retenção zero **não** está validada: CL-32 (T138).
- Como o modelo é configurável, limites e capacidades do modelo (PDF, YouTube, tamanho de contexto) não são conhecidos pelo plano: Q-008-03 e T136 seguem abertas, ver [research.md](research.md). *(04/11/2026, revisão dos planos: Q-008-03 resolvida pela DEC-67; T136 cancelada pela DEC-65.)*
- A spec-003 (plano) não previa página de administração do site (`settings.php`: "nenhuma prevista além da página por curso"); a DEC-60 D2 cria essa necessidade, a cargo de T134.

## Complexity Tracking

Nenhuma violação justificável.
