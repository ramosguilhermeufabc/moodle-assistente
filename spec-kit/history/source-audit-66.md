# Reconciliação completa dos 66 requisitos candidatos — Etapa 1

> **`HISTÓRICO`** · movido em 08/11/2026 de `spec-kit/source-audit-66.md` para `spec-kit/history/source-audit-66.md`, sem alteração de conteúdo. Índice do histórico: [`README.md`](README.md).

> **(04/11/2026) Documento histórico: retrato da reconciliação de 21/10/2026 (Etapa 1). O estado vigente de cada AT está em requirements-traceability.md e as decisões em decision-register-DEC-01-25.md.** O texto abaixo foi preservado sem reescrita.

### Lacunas resolvidas depois

Numeração da seção "Lacunas que permanecem antes do congelamento" deste documento. Cada linha foi conferida em `decision-register-DEC-01-25.md` em 04/11/2026; o fechamento das validações está em `closure-register.md`.

| Lacuna | Assunto | Resolvida por | Evidência de fechamento |
|---|---|---|---|
| 1 | Atividades elegíveis para frequência | DEC-25, DEC-39 | CL-04 Fechado no nível funcional (25/10/2026); CL-36 Fechado (VT) em 03/11/2026 |
| 2 | Igualdade ao limiar de um critério | DEC-11 | mínimo `>=` atende; máximo `<=` atende (CL-05 Fechado em 25/10/2026) |
| 3 | Critério binário de fórum | DEC-40 D3 | CL-05 e CL-12 Fechados em 25/10/2026 |
| 4 | Relevância operacional da síntese | DEC-19, DEC-41 D2 | CL-03 Fechado no nível funcional (25/10/2026) |
| 5 | Idempotência | DEC-21, DEC-31 | CL-02 Fechada em 02/11/2026, com limitações residuais |
| 6 | Relatório e calendário | DEC-44, DEC-17 | CL-06 e CL-17 Fechados no nível funcional (26/10/2026); CL-39 Fechada em 28/10/2026 |
| 7 | Garantia técnica de versão corrente única | DEC-29 (CL-14) | CL-14 Fechado em 03/11/2026 |
| 8 | Esquema de síntese | DEC-14, DEC-29 (CL-07) | CL-07 Fechado (VT) em 02/11/2026, com ressalvas |
| 9 | Escopo de estudantes relevantes para contexto de material | DEC-26 | definição de estudantes relevantes, "disponível" e momento de avaliação |
| 10 | Ambiente | DEC-28, DEC-49, T008 | T008 `[x]` em `specs/001-*/tasks.md`; CL-01 e CL-22 Fechados |

## Escopo e método

Fontes examinadas: `constitution.md`, `spec-001.md` a `spec-023.md`, `install_auditado.xml` (inventário descrito na spec-002) e PDF da Metodologia, especialmente §§3.2–3.6. As specs do ZIP são a fonte técnica atual; a Etapa 3 é a fonte principal dos requisitos funcionais; decisões SDD aprovadas complementam as fontes. Esta matriz não cria aprovação nova nem autoriza alteração de XMLDB.

**Estados:** Confirmado = explicitamente sustentado; Parcial/ambíguo = objetivo existe, mas falta regra operacional; Fórmula confirmada; caso-limite aberto = fórmula literal existe, mas faltam entradas-limite; Pendente de plano = a própria spec remete a decisão posterior.

## Matriz item a item

| ID | Requisito candidato reconciliado | Fonte | Estado | Observação de auditoria |
|---|---|---|---|---|
| AT-001 | Usar GitHub Codespaces como ambiente de desenvolvimento | spec-001; Etapa 3 §3.3 | Confirmado | Ambiente nomeado explicitamente. |
| AT-002 | Executar Moodle em containers Docker | spec-001; Etapa 3 §3.3 | Confirmado | Docker/containers explícitos. |
| AT-003 | Alvo Moodle 5.2 / MOODLE_502_STABLE | spec-001; Constituição §4 | Confirmado | Regra técnica do pacote; não é requisito funcional da Etapa 3. |
| AT-004 | Plugin local_assistente no caminho Moodle /var/www/html/local/assistente | spec-001; Constituição §4 | Confirmado | Caminho definido no pacote. |
| AT-005 | Preservar repositório Git e não commitar segredos | spec-001 §Validação | Confirmado | Segredos da API externa fora do Git. |
| AT-006 | Escolher imagem, portas, banco e serviços no plan.md | spec-001 §Validação | Pendente de plano | A spec proíbe inventar esses valores antes do plano. |
| AT-007 | Usar install.xml como fonte de verdade do esquema | spec-002; Constituição §3 | Confirmado | Não acrescentar campos/índices sem atualização aprovada. |
| AT-008 | Manter 21 tabelas e definições sincronizadas com XMLDB | spec-002 | Confirmado | A spec declara 21 tabelas; validar contra o arquivo XMLDB durante implementação. |
| AT-009 | Usar DML para dados e XMLDB/upgrade para mudanças estruturais | spec-002; spec-003 | Confirmado | Regra técnica explícita. |
| AT-010 | Encapsular persistência em repositórios; domínio não conhece SQL | spec-003 | Confirmado | Contrato arquitetural explícito. |
| AT-011 | Manter lib.php mínimo, apenas callbacks necessários | Constituição §6; spec-003 | Confirmado | Regra arquitetural explícita. |
| AT-012 | Isolar leitura dos dados originais do Moodle em readers/adapters | spec-004 | Confirmado | A lista de domínios de leitura está na spec-004. |
| AT-013 | Usar APIs Moodle preferencialmente; DML quando não houver API adequada | spec-004 | Confirmado | Regra explícita. |
| AT-014 | Ler notas finais do gradebook e identificar questões por question_id + question_version | spec-004 | Confirmado | A spec diz que slot não é requisito. |
| AT-015 | Professor acessa somente cursos próprios e administrador todos | Constituição §11; spec-005 | Confirmado | Deve ser validado também no backend. |
| AT-016 | Configurar assistente por curso | spec-005; XMLDB local_assistente_config | Confirmado | A tabela tem configuração por curso. |
| AT-017 | Definir critérios de atividade numéricos, fórum binário e frequência numérica | spec-005; XMLDB local_assistente_criteria | Confirmado | Tipos de critério expressos. |
| AT-018 | Critérios são definidos na configuração e imutáveis depois | spec-005; spec-020 | Confirmado | Regra repetida nas duas specs. |
| AT-019 | Alterar critérios sem reprocessar retroativamente resultados anteriores | spec-005 | Confirmado | A spec proíbe reprocessamento retroativo. |
| AT-020 | Criar evento próprio persistido para cada ocorrência do assistente | spec-006 | Confirmado | Um evento representa uma ocorrência. |
| AT-021 | Persistir tipo, payload, curso, timestamps, estado e resposta do evento | spec-006; XMLDB local_assistente_event | Confirmado | Campos listados no contrato. |
| AT-022 | Usar estados pending, processing, processed e failure para eventos | spec-006 | Confirmado | Estados exatos explicitados. |
| AT-023 | Publicação de questionário gera exatamente um evento | spec-006; spec-010 | Confirmado | Regra explícita; garantir idempotência contra eventos duplicados. |
| AT-024 | Toda interação/postagem em fórum gera evento para atualização | spec-006; spec-013; decisão confirmada em 2026-10-21 | Confirmado por decisão aprovada | Toda postagem/interação dispara o fluxo; isso não exige executar o LLM na requisição web nem garante, por si só, uma nova versão da síntese. |
| AT-025 | Observer não executa diretamente chamada de LLM | spec-006; spec-022 | Confirmado | Delegar processamento assíncrono. |
| AT-026 | Usar Scheduled Task para varrer/processar a fila própria | spec-007 | Confirmado | Mecanismo descrito na spec. |
| AT-027 | Criar um processing para cada evento (relação 1:1) | spec-007; XMLDB | Confirmado | Relação 1:1 explícita. |
| AT-028 | Executar ciclo inicial a cada 15 minutos, com intervalo configurável por administrador | spec-007 | Confirmado | Intervalo e configuração explícitos. |
| AT-029 | Processar eventos pendentes em lote até esgotar a fila | spec-007 | Confirmado | Regra explícita. |
| AT-030 | Limitar a três tentativas totais por processamento e persistir tentativas/falhas | spec-007; spec-023; decisão confirmada em 2026-10-21 | Confirmado | Três no total, incluindo a tentativa inicial. A aplicação deve garantir o limite; o índice não o garante sozinho. |
| AT-031 | Garantir idempotência do processamento | spec-007; spec-023 | Confirmado, critério incompleto | A exigência existe, mas a chave/semântica de idempotência não está definida. |
| AT-032 | Processar materiais PDF, texto e YouTube | spec-008; decisão SDD aprovada | Confirmado | Tipos funcionais confirmados; Book/Folder fora do escopo conforme decisão anterior. |
| AT-033 | Gerar resumos de materiais de forma assíncrona | spec-008; Etapa 3 §3.2 | Confirmado | Resumo automático de material disponibilizado. |
| AT-034 | Nova disponibilidade de material gera processamento assíncrono | spec-008 | Confirmado | Regra explícita. |
| AT-035 | Cada alteração de material gera nova versão preservando a anterior | spec-008; spec-017 | Confirmado | Histórico não deve ser apagado. |
| AT-036 | Versão nova torna-se o contexto corrente | spec-008 | Confirmado | Regra explícita; unicidade da versão corrente depende da aplicação. |
| AT-037 | Material indisponível não fornece contexto futuro | spec-008 | Confirmado | Regra explícita. |
| AT-038 | Contexto de atividade/questionário/fórum usa materiais da mesma seção | spec-008; spec-023 | Confirmado | Isolamento por seção explicitado. |
| AT-039 | Criar snapshot imutável do contexto por processamento | spec-008; spec-017 | Confirmado | Contrato explícito. |
| AT-040 | Material processável se visível a pelo menos um aluno, mas contexto geral só se visível a todos os alunos relevantes | Decisão SDD aprovada em conversa; spec-008 | Confirmado por decisão aprovada | Distinção aprovada: processabilidade ≠ elegibilidade para contexto aplicado ao grupo todo. |
| AT-041 | Não reprocessar análises históricas retroativamente após mudança de material | spec-008 | Confirmado | Regra explícita. |
| AT-042 | Rastrear todos os materiais e versões efetivamente usados | spec-008; spec-017 | Confirmado | Regra explícita. |
| AT-043 | Isolar chamadas de LLM atrás de evaluate(context, instruction) -> structured_result | spec-009 | Confirmado | Porta lógica explícita. |
| AT-044 | Adapter trata autenticação, request/response, timeout, erro e validação | spec-009 | Confirmado | Itens explícitos. |
| AT-045 | Não escolher fornecedor/modelo sem decisão no plano | spec-009 | Confirmado | Fornecedor/modelo não está definido no SDD. |
| AT-046 | Usar LLM para resumos de materiais, cobertura/complexidade, fóruns e síntese | spec-009 | Confirmado | Lista explícita. |
| AT-047 | Não usar LLM para contagens, médias, desvio-padrão, min/max, frequência ou critérios matemáticos | spec-009 | Confirmado | Cálculos determinísticos. |
| AT-048 | Manter credenciais fora do banco funcional e do Git | spec-001; spec-009 | Confirmado | Regra de segurança explícita. |
| AT-049 | Validar saída estruturada do LLM antes de persistir | spec-010; spec-009 | Confirmado | A spec-010 pede validação da saída LLM. |
| AT-050 | Analisar questionário publicado com materiais elegíveis da mesma seção | spec-010 | Confirmado | Escopo explícito. |
| AT-051 | Produzir coverage do questionário e complexity por questão em escala 0,0–1,0, passo 0,1 | spec-010 | Confirmado | A spec diz que não há requisito de tipo de raciocínio. |
| AT-052 | Calcular métricas por questão: average_correctness, correct_count, incorrect_count, partial_result | spec-011 | Confirmado | Campos explícitos. |
| AT-053 | Calcular mean, population_standard_deviation, minimum e maximum ao fechar questionário | spec-011 | Confirmado | Agregados consideram todas as respostas no fechamento. |
| AT-054 | Calcular partial_result = correct_items_answered / total_correct_items | spec-011; decisão confirmada em 2026-10-21 | Fórmula confirmada; regra de caso-limite confirmada | Se o denominador for zero, o resultado numérico será 0. |
| AT-055 | Persistir desempenho por estudante/atividade com nota final do gradebook, sem pesos próprios | spec-012 | Confirmado | Média do estudante deriva das notas conforme configuração Moodle. |
| AT-056 | Gerar resumo por fórum e dificuldades por discussão/tópico | spec-013; Etapa 3 §3.2 | Confirmado | Funcionalidade presente nas fontes. |
| AT-057 | Análise incremental de fórum usa resumo corrente + nova mensagem + contexto da seção | spec-013 | Confirmado | Regra explícita; o que constitui atualização relevante segue ambíguo. |
| AT-058 | Calcular presença como participação pelo menos uma vez e percentual participating_activities / total_activities * 100 | spec-014; decisão confirmada em 2026-10-21 | Fórmula confirmada; regra de caso-limite parcialmente confirmada | Se o denominador for zero, o resultado numérico será 0. A definição de atividades elegíveis ainda precisa ser reconciliada. |
| AT-059 | Usar data final/devida da atividade como referência temporal da frequência | spec-014 | Confirmado | Regra explícita. |
| AT-060 | Aplicar critérios determinísticos aos resultados de atividade, fórum e frequência | spec-015 | Parcial; regra de ordenação confirmada separadamente | A decisão de ordenar estudantes empatados alfabeticamente não resolve se igualdade ao limiar significa atender ao critério nem a semântica do critério binário de fórum. |
| AT-061 | Toda nova interação/evento elegível dispara atualização; criar versão da síntese somente quando uma nova síntese for efetivamente produzida | spec-016; decisão confirmada em 2026-10-21 | Parcial; regra de versionamento confirmada em parte | A decisão define que o evento não obriga criar versão. Os critérios operacionais para decidir quando produzir nova síntese ainda precisam ser especificados. |
| AT-062 | Manter cadeia de rastreabilidade source -> event -> processing -> attempt -> snapshot -> analysis -> result -> report | spec-017; Constituição §9 | Confirmado | Cadeia explícita. |
| AT-063 | Preservar estados anteriores e histórico quando fonte é atualizada/removida | spec-008; spec-017; Constituição §10 | Confirmado | Regra transversal explícita. |
| AT-064 | Oferecer consultas predefinidas sem perguntas livres em linguagem natural; respeitar função/curso | spec-018; spec-021 | Confirmado | Consultas explicitadas: notas, métricas, frequência, fóruns, critérios, materiais, síntese, histórico. |
| AT-065 | Manter um relatório lógico por curso, com versões; atualizar a cada 3 dias até a data final mais distante e uma vez no dia seguinte | spec-019; decisões confirmadas em 2026-10-21 | Regra base confirmada; casos de calendário parcialmente definidos | Se não houver atividade com data final, não agendar atualizações até existir uma. Se periódica e final coincidirem, executar uma vez e identificá-la como final. Fuso horário, mudança/reabertura de datas e recuperação de falha final continuam pendentes. |
| AT-066 | Usar Form API/Output API/UI Moodle e testar unidade, integração, XMLDB, eventos/tasks, LLM mockada, regras, acesso, histórico e UI aplicável | spec-020–023; Etapa 3 §3.4 | Confirmado | Testes unitários por entrega e usabilidade exploratória pelo pesquisador; sem participantes externos. |

## Decisões confirmadas após a reconciliação inicial (2026-10-21)

1. **Divisão por zero:** para as métricas de questionário (spec-011) e frequência (spec-014), se o denominador for zero, o resultado numérico será `0`.
2. **Ordenação de estudantes empatados:** quando estudantes tiverem a mesma pontuação, ordenar pelo nome em ordem alfabética crescente (ex.: Amanda antes de Carlos, ambos com 10 pontos). Esta decisão define ordenação de exibição/desempate, não o tratamento de igualdade ao limiar de um critério.
3. **Eventos de fórum:** toda interação/postagem gera evento e dispara o fluxo de atualização. O processamento continua assíncrono.
4. **Versões da síntese:** todo evento dispara o fluxo de atualização, mas só criar uma nova versão quando uma nova síntese for efetivamente produzida. A regra operacional que determina quando uma nova síntese deve ser produzida ainda precisa ser detalhada.
5. **Tentativas:** máximo de três tentativas totais por processamento, incluindo a tentativa inicial.
6. **Atualização periódica/final coincidente:** realizar uma única execução, identificada como atualização final.
7. **Curso sem atividade com data final:** não agendar atualizações periódicas até existir uma atividade com data final aplicável.
8. **Histórico e versão corrente:** preservar histórico imutável; garantir uma única versão corrente por material; vincular cada síntese ao contexto que a originou. A viabilidade e a implementação desse contrato devem ser verificadas contra o XMLDB atual, sem acrescentar campos/índices por suposição.

## Lacunas que permanecem antes do congelamento

1. **Atividades elegíveis para frequência** — spec-014 enumera fontes de participação e usa a data final/devida como referência, mas não fecha todos os critérios para compor `total_activities`.
2. **Igualdade ao limiar de um critério** — a ordenação alfabética em caso de empate foi confirmada, mas não define se um valor exatamente igual ao limite atende ao critério. Manter em aberto.
3. **Critério binário de fórum** — spec-005 o chama binário, mas spec-015 não especifica o teste exato nem a interpretação do valor armazenado.
4. **Relevância operacional da síntese** — está decidido que todo evento dispara atualização e que só uma síntese efetivamente produzida gera versão. Ainda falta definir quais mudanças de dados/resultados exigem produzir uma nova síntese.
5. **Idempotência** — exigida em spec-007/023, mas a chave de deduplicação e a semântica de “mesmo processamento” não estão especificadas. A regra de máximo de três tentativas está decidida, mas idempotência é uma questão separada.
6. **Relatório e calendário** — casos de coincidência, ausência de data final e não agendamento até existir data final foram decididos. Fuso horário, alteração/reabertura de datas e recuperação de falha na atualização final ainda não estão definidos.
7. **Garantia técnica de versão corrente única** — a regra funcional está confirmada, mas o índice não único `material_id + is_current` não garante sozinho a unicidade. Verificar como garantir pela persistência/aplicação; não alterar XMLDB sem decisão e auditoria.
8. **Esquema de síntese** — confirmar se `local_assistente_pedagogical_synthesis` permite representar as versões exigidas e seus vínculos de origem sem inventar campo ou índice.
9. **Escopo de estudantes relevantes para contexto de material** — a decisão aprovada distingue processabilidade de material e elegibilidade para contexto coletivo. A definição de “estudantes relevantes” deve permanecer coerente com a turma/atividade e a visibilidade efetiva no Moodle.
10. **Ambiente** — imagem, banco, portas, comandos e serviços auxiliares ficam para o plano conforme spec-001; não são lacunas de requisito funcional.

## Correções de interpretação aplicadas nesta reconciliação

- `question_id + question_version` é exigência expressa; `slot` não é requisito (spec-004).
- Coverage e complexity usam faixa 0,0–1,0 e passo 0,1; não há requisito de tipo de raciocínio (spec-010).
- `partial_result` usa a fórmula literal da spec-011; por decisão confirmada, denominador zero produz resultado numérico 0.
- A presença usa a regra “participou pelo menos uma vez = presente” e a fórmula literal da spec-014; por decisão confirmada, denominador zero produz resultado numérico 0.
- O limite confirmado é de três tentativas totais, incluindo a inicial; a implementação precisa garanti-lo e testá-lo (spec-007/023).
- Materiais processáveis e materiais elegíveis para contextualizar uma análise para todos os estudantes não são a mesma condição, conforme decisão SDD previamente aprovada.
- Nenhum fornecedor/modelo de LLM, evento Moodle concreto, hook, API específica, valor de imagem/porta/banco ou novo campo XMLDB foi acrescentado por inferência.

## Conclusão da Etapa 1

- Total de itens reconciliados: **66**.
- Os itens confirmados podem seguir para a consolidação da matriz de requisitos, preservando a fonte e o nível de detalhe original.
- As decisões confirmadas acima complementam a matriz; os itens ainda ambíguos permanecem como pendências explícitas e não devem ser resolvidos por suposição pelo plano ou pelo agente de código.
- A etapa seguinte recomendada é consolidar estas pendências em uma lista de decisões mínimas e, em paralelo, finalizar o inventário XMLDB por tabela/campo/índice antes de congelar a arquitetura de features.
