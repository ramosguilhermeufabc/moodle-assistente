# Propagação controlada de decisões SDD às specs

> **`HISTÓRICO`** · movido em 08/11/2026 de `spec-kit/decision-propagation.md` para `spec-kit/history/decision-propagation.md`, sem alteração de conteúdo. Índice do histórico: [`README.md`](README.md).

> **Nota de escopo, 08/11/2026.** Este documento foi escrito quando o registro de decisões
> cobria DEC-01 a DEC-25; ele hoje cobre DEC-01 a DEC-72, e as auditorias de propagação de
> 04/11/2026 alcançam DEC-71. As regras de propagação abaixo continuam valendo; a enumeração
> "DEC-01 a DEC-25" deve ser lida como a numeração vigente do registro. Está superada a parte
> da regra 4 que manda não fechar por suposição os itens AT-031, AT-057, AT-058, AT-060, AT-061
> e AT-065: todos estão resolvidos ou confirmados em `requirements-traceability.md`. O texto
> abaixo foi preservado sem reescrita, conforme a constituição §11.

## Finalidade

Registrar as decisões DEC-01 a DEC-25 aprovadas e os artefatos que devem refletir essas regras. Este arquivo é um plano de reconciliação; não afirma que as decisões já foram incorporadas ao conteúdo das 23 specs originais.

## Regras obrigatórias para o agente Claude / Spec Kit

1. Ler `requirements-traceability.md`, a constituição, as 23 specs e o registro DEC-01 a DEC-25 antes de gerar plano ou tarefas.
2. Não reescrever as specs originais em lote nem considerar o ato de migrá-las para `specs/NNN-slug/spec.md` como prova de cobertura.
3. Para toda decisão, registrar quais requisitos AT são afetados, quais specs deverão ser alteradas e qual cenário de teste demonstrará a regra.
4. Separar regras aprovadas de questões ainda em aberto no documento de reconciliação. Não fechar por suposição os itens AT-031, AT-057, AT-058, AT-060, AT-061, AT-065 nem as lacunas listadas na reconciliação da Etapa 1.
5. Não alterar `install_auditado.xml`, XMLDB ou código do plugin neste passo. Primeiro gerar os artefatos Spec Kit e apresentar análise das mudanças propostas para revisão.
6. Materiais funcionais: PDF, TEXT e YouTube. Book/Folder estão fora de escopo funcional; nenhuma tarefa/spec nova deve restaurá-los.
7. Uma decisão aprovada não comprova que API Moodle 5.2, estratégia de lock, transação DML ou alteração de esquema é tecnicamente válida. Manter a validação técnica e testes como gates.

## Plano de reconciliação por área

| Área | Decisões | Specs principais | Validação que deve entrar em tasks.md |
|---|---|---|---|
| Divisão por zero / sem dados | DEC-01, DEC-24 | 011, 014, 019, 021, 023 | Denominador zero = valor 0 com estado `no_data`; zero válido com denominador positivo; erro técnico como `error`, não como zero |
| Ordenação | DEC-02 | 015, 018, 019, 021, 023 | Estudantes com mesma pontuação em ordem alfabética crescente |
| Evento de fórum / síntese | DEC-03, DEC-04, DEC-13, DEC-14, DEC-19 | 006, 007, 013, 016, 017, 022, 023 | Todo evento dispara fluxo; sem diferença no conjunto normalizado de dados pedagógicos não há versão redundante; versionamento separado por curso+seção |
| Material / contexto | DEC-05, DEC-12, DEC-15, DEC-20 | 008, 017, 023 | Detecção, processamento bem-sucedido e elegibilidade para análise como estados distintos; disponibilidade para todos os estudantes relevantes; atualização atômica da versão corrente e histórico imutável |
| Idempotência / concorrência | DEC-08, DEC-09, DEC-10, DEC-16, DEC-21, DEC-22 | 006, 007, 017, 022, 023 | Uma ocorrência lógica = um processamento; repetições reutilizam; concorrência não duplica resultados; máximo de três tentativas totais; identidade por tipo documentada e limitações explícitas |
| Critérios | DEC-11 | 005, 015, 020, 023 | Mínimo atende `>=`; máximo atende `<=`; testar igualdade e valores imediatamente acima/abaixo |
| Relatórios | DEC-06, DEC-07, DEC-17, DEC-18, DEC-23 | 019, 023 | 72h desde último sucesso; final no dia seguinte à data final mais distante; coincidência executa uma vez como final; sem data final não agenda; falha mantém último sucesso e relatório lógico |
| Frequência | DEC-25 | 014, 015, 023 | Acesso ao curso é evidência de presença, não atividade no denominador; deduplicar por aluno/atividade; categorias elegíveis não podem ser inventadas |

## Pendências que não devem ser ocultadas

- AT-006: imagem, banco, portas, comandos/serviços do ambiente ficam para `plan.md` e precisam ser escolhidos explicitamente.
- AT-031: identidade e semântica concreta de idempotência técnica devem ser documentadas.
- AT-057/AT-061: gatilho de síntese usa mudança normalizada de informação pedagógica, conforme DEC-19; especificar normalização/fingerprint e exclusão de metadados transitórios sem inventar política pedagógica nova.
- AT-058/DEC-25: reconciliar categorias elegíveis de atividade com `spec-014` e Etapa 3; não criar módulos/categorias por suposição.
- AT-060/DEC-11: critérios mínimo/máximo têm semântica definida; semântica do critério binário de fórum permanece a documentar nas fontes antes de implementação.
- AT-065: fuso horário, mudança/reabertura da data final e recuperação de falha de atualização final permanecem pendentes conforme matriz original.
- Versão corrente de material e esquema da síntese: contratos aprovados; tipo de campo, índices, upgrades e compatibilidade XMLDB/DML continuam a exigir validação técnica antes de qualquer edição de XMLDB.
- Eventos Moodle: verificar eventos reais e payloads de Moodle 5.2; não assumir ID universal.

## Ordem Spec Kit (com Claude)

1. `/speckit.specify`: consolidar os 66 itens como requisitos rastreáveis e relacionar cada um à spec funcional correspondente; sem alterar decisões abertas.
2. `/speckit.clarify`: usar apenas para questões explicitamente pendentes; não perguntar de novo sobre DEC-01 a DEC-25 já aprovadas.
3. `/speckit.plan`: gerar `plan.md` com contratos técnicos aprovados e gates de validação, sem escolher valores/provedores/mecanismos não suportados.
4. `/speckit.checklist`: gerar lista de qualidade/rastreabilidade, inclusive referência para AT-001…AT-066.
5. `/speckit.tasks`: cada AT deve ter correspondência explícita com tarefa de especificação/teste ou ser marcada como pendente com justificativa. A contagem de tarefas não precisa ser 66, mas a cobertura de requisitos sim.
6. `/speckit.analyze`: resolver inconsistências e requisitos não cobertos antes de qualquer `/speckit.implement`.
7. `/speckit.implement` só depois de revisão e aprovação humana das specs, do plano e das tarefas. Não executar implementação neste pacote.
