> **`HISTÓRICO`** · movido em 08/11/2026 de `spec-kit/PROMPT-CLAUDE-SPECKIT.md` para `spec-kit/history/PROMPT-CLAUDE-SPECKIT.md`, sem alteração de conteúdo. Índice do histórico: [`README.md`](README.md).

> **Atenção — fluxo atualizado:** este arquivo é uma orientação histórica consolidada. Para execução, use os quatro prompts independentes em `prompts/`, na ordem numerada. Não execute as fases combinadas descritas abaixo como um único fluxo.
>
> - Prompt 1: criação do ambiente.
> - Prompt 2: configuração do ambiente.
> - Prompt 3: Plan usando exclusivamente as Specs como contexto.
> - Prompt 4: Tasks usando Specs + artefatos aprovados do Plan.
>
> Cada prompt deve terminar com relatório e aguardar autorização antes de avançar.

---

# Prompt de trabalho para Claude — Spec Kit / SDD

Você está trabalhando somente no diretório `/spec-kit` que contém as 23 specs migradas. Use os comandos oficiais do GitHub Spec Kit já instalados na integração **Claude**. Não use Codex. Não faça pesquisa/varredura do repositório GitHub; as fontes são os arquivos presentes neste pacote.

## Objetivo

Consolidar e garantir rastreabilidade para os 66 requisitos candidatos AT-001 a AT-066, incorporando às specs e artefatos Spec Kit as decisões DEC-01 a DEC-25 já aprovadas, sem inventar comportamentos e sem implementar código nesta etapa.

## Leitura obrigatória

1. `requirements-traceability.md` — matriz AT-001…AT-066.
2. `source-audit-66.md` — reconciliação de origem que sustenta a matriz.
3. `decision-register-DEC-01-25.md` — decisões aprovadas e contratos P-01…P-07, inclusive validações técnicas ainda pendentes.
4. `decision-propagation.md` — regras de propagação e áreas/pedidos de teste.
5. `.specify/memory/constitution.md` e as 23 `specs/*/spec.md`.
6. `research.md` e `install_auditado.xml` como referências preservadas.

## Procedimento exigido

1. Verifique o estado da integração Spec Kit e confirme que a integração ativa é Claude. Se os comandos do Spec Kit não estiverem instalados para Claude, pare e relate o bloqueio; não substitua por outro agente nem reinstale usando outro agente.
2. Execute `/speckit.specify` para a consolidação de rastreabilidade. Não gere uma spec genérica que esconda o detalhamento dos ATs: todos os IDs AT-001…AT-066 precisam ser mencionados em uma matriz ou checklist rastreável, com fonte, spec(s) destino e validação/teste esperado.
3. Faça `/speckit.clarify` somente para pendências identificadas explicitamente. Não repita perguntas já respondidas por DEC-01 a DEC-25. Quando uma pendência exigir decisão do usuário ainda não registrada, marque-a como pendente; não invente resposta.
4. Gere `plan.md` com `/speckit.plan`. O plano deve incluir decisões aprovadas, ordem de dependências, isolamento por curso/seção, estratégia de persistência a validar, segurança/acesso, validação de Moodle 5.2 e gates sobre XMLDB/DML/locks/eventos.
5. Gere checklist de qualidade/rastreabilidade com `/speckit.checklist`, se suportado pela integração.
6. Gere `tasks.md` com `/speckit.tasks`. Cada requisito AT deve apontar para uma tarefa que o especifique/valide, ou ficar listado como pendente com motivo. Não é obrigatório criar 66 tarefas, mas nenhum AT pode desaparecer sem rastreabilidade.
7. Execute `/speckit.analyze` e corrija cobertura, contradições, ambiguidades de especificação e dependências. Repita a análise até não haver requisito omitido sem justificativa.
8. **NÃO execute `/speckit.implement`.** Não edite código do plugin, não altere `install_auditado.xml`, não altere XMLDB, não escolha fornecedor/modelo LLM, nem valores de infraestrutura que as fontes deixam para o plano sem justificativa. Nesta fase, produza e revise artefatos SDD apenas.
9. Faça alterações nas specs existentes em commits pequenos conceitualmente separados por tema, mantendo os textos históricos rastreáveis. Remova Book/Folder do escopo funcional: os únicos tipos funcionais de material são PDF, TEXT e YouTube.
10. Ao final, apresente relatório com: (a) os 66 IDs individualmente classificados; (b) specs alteradas; (c) cobertura AT→spec→tarefa→teste; (d) pendências restantes; (e) confirmação de que código/XMLDB não foram modificados; (f) confirmação de que Claude, e não Codex, foi a integração utilizada.

## Portões de qualidade

- AT-001…AT-066 devem aparecer exatamente uma vez na matriz principal, ainda que um AT tenha várias referências.
- DEC-01…DEC-25 são decisões aprovadas: não reabri-las sem evidência nova ou solicitação explícita.
- Contratos P-01…P-07 aprovados não equivalem a compatibilidade técnica testada; mantenha testes/validações como tarefas e gates.
- Não afirme “implementado” ou “testado” quando só há especificação.
- Não invente dados nem resolva ambiguidades por conhecimento geral do Moodle; valide na versão-alvo ou marque como pendência.
- Não acesse GitHub nem outras fontes externas para buscar o projeto. A referência Spec Kit serve apenas para seguir o formato e os comandos já instalados.
