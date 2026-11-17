# spec-016 — Síntese Pedagógica

## Objetivo & Escopo

Consolidar resultados e produzir informação pedagógica quando surgir nova informação relevante.

## Dependências de Specs Anteriores

spec-004, spec-008, spec-009, spec-010, spec-011, spec-012, spec-013, spec-014, spec-015

## Contratos & Interfaces

Entrada:
- desempenho;
- frequência;
- fóruns/dificuldades;
- resumos de materiais;
- resultados relevantes.

Saída:
`local_assistente_pedagogical_synthesis.content`.

Cada síntese é produzida em uma `analysis` própria e tem como escopo uma combinação de curso e seção. *(Alterado em 21/10/2026 por decisão funcional sobre síntese por curso e seção; DEC-13 e decisões relacionadas, conforme aplicável.)*

## Regras de Validação & Requisitos Técnicos

Só gerar nova síntese quando houver nova informação relevante.
Todo evento relevante, inclusive toda postagem ou interação no fórum, dispara o fluxo de atualização de forma assíncrona. Disparar o fluxo não obriga criar versão: uma nova versão só é criada se houver mudança nos resultados pedagógicos ou no conjunto de informações usado para produzir a síntese. Sem mudança, a versão existente é preservada, sem versão redundante. *(Incluído em 22/10/2026 por DEC-03 e DEC-04.)*
A mudança é determinada pelo conjunto normalizado de informações pedagógicas efetivamente utilizado, e não pela diferença textual na saída da LLM. A comparação exclui metadados transitórios que não alteram as entradas pedagógicas. A normalização e o mecanismo de comparação ainda não estão especificados (`closure-register.md`, CL-03). *(Incluído em 22/10/2026 por DEC-19.)* *(04/11/2026, revisão da propagação: a normalização e o mecanismo de comparação foram definidos pela DEC-41 (D2 e tratamentos confirmados): conjunto comparável igual ao conjunto enviado à LLM e fingerprint SHA-256 hexadecimal sobre JSON canônico; CL-03 fechada no nível funcional; contrato no plano 016. A regra da DEC-19 continua vigente. Texto anterior, preservado: "A normalização e o mecanismo de comparação ainda não estão especificados (`closure-register.md`, CL-03)".)*
Mudanças em uma seção não alteram automaticamente a síntese de outra seção. *(Incluído em 22/10/2026 por DEC-13.)*
A síntese deve refletir a informação mais recente.
A interpretação linguística usa API externa de LLM.

A síntese é iniciada a partir de eventos relevantes, quando os resultados necessários estiverem disponíveis. Os eventos exatos e o comportamento diante de resultados ausentes estão pendentes (plano 006, Q-006-07).

A seção da síntese representa o escopo da síntese e não precisa ser a da atividade que disparou o evento. A seção é obtida assim:
- se o evento tiver um `cmid` cuja relação com uma seção seja inequívoca, a seção é derivada pela regra da spec-004;
- se não houver `cmid` adequado, o processamento deve receber um `section_id` explícito, transportado no contexto do processamento, com origem e validação a definir;
- antes de criar a síntese, valida-se que a seção existe e pertence ao curso informado;
- se não houver seção válida ou o contexto não puder ser resolvido, a síntese por seção não é criada e a condição é registrada como pendência de processamento, sem atribuir seção arbitrária.

Elegibilidade das entradas para a seção-alvo:
- desempenho (spec-012): somente resultados de atividades associadas de forma inequívoca à seção-alvo;
- frequência (spec-014): somente registros correspondentes à seção-alvo;
- critérios transversais (spec-015): excluídos os resultados sem associação inequívoca à seção;
- questionário e fórum (specs 010 e 013): usa-se a seção da atividade quando a derivação por `cmid` for válida;
- materiais (spec-008): somente os aplicáveis à seção da síntese.

A primeira versão da síntese é 1. A versão seguinte é `max(version) + 1` por combinação de curso e seção, protegida pela Lock API. Cada versão registra `course_id`, `section_id`, `version` e `input_fingerprint`, com unicidade de (curso, seção, versão); é imutável e mantém o vínculo com o processamento e o contexto de origem por `analysis_id`; a versão vigente é a de maior sequência, sem indicador separado de versão corrente. O `input_fingerprint` registra o conjunto normalizado de informações usado (DEC-19); a normalização e o algoritmo continuam em aberto (`closure-register.md`, CL-03). *(Alterado em 25/10/2026 por DEC-14, DEC-19 e DEC-29; texto substituído: "Esta regra não altera o esquema XMLDB.")* *(Alterado em 21/10/2026 por decisão funcional sobre síntese por curso e seção; DEC-13 e decisões relacionadas, conforme aplicável.)* *(04/11/2026, revisão da propagação: a normalização e o algoritmo do `input_fingerprint` foram definidos pela DEC-41 (D2 e tratamentos confirmados): fingerprint SHA-256 hexadecimal sobre JSON canônico; CL-03 fechada no nível funcional; contrato no plano 016. Texto anterior, preservado: "a normalização e o algoritmo continuam em aberto (`closure-register.md`, CL-03)".)*

## Conformidade Moodle

A persistência deve associar a síntese ao processing que a gerou.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
