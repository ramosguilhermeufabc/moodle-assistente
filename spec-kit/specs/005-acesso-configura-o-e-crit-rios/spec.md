# spec-005 — Acesso, Configuração e Critérios

## Objetivo & Escopo

Definir o controle de acesso por curso e a configuração inicial dos critérios.

## Dependências de Specs Anteriores

spec-003, spec-004

## Contratos & Interfaces

- Access API/context de curso.
- Form API posteriormente em spec-020.
- Persistência: `local_assistente_config`, `local_assistente_criteria`.

## Regras de Validação & Requisitos Técnicos

Professor: somente cursos que possui.
Administrador: todos.
O acesso é autorizado pela capability do plugin `local/assistente:view`, verificada no backend. O professor possui um curso quando tem essa capability no contexto do curso; por padrão ela é atribuída ao arquétipo `editingteacher`, e não ao `teacher` (sem edição), que pode recebê-la pelas permissões do Moodle. O administrador acessa todos os cursos pela mesma capability no contexto de sistema, atribuída por padrão ao arquétipo `manager`; o administrador do site passa na verificação pelo modelo do Moodle. A autorização é por capability, não por nome de papel. *(Incluído em 25/10/2026 por DEC-30.)*
Critérios separados:
- atividades: numérico;
- fórum: binário;
- frequência: numérico.
Critério numérico de mínimo: atende se valor >= limite; fica abaixo se valor < limite. Critério numérico de máximo: atende se valor <= limite; ultrapassa se valor > limite. A comparação respeita a natureza do critério. A semântica do critério binário de fórum continua em aberto (`closure-register.md`, CL-05). *(Incluído em 22/10/2026 por DEC-11.)* *(04/11/2026, revisão da propagação: a semântica do critério binário de fórum foi definida pela DEC-40 (D3): `forum_criterion = 1` exige pelo menos uma mensagem em cada fórum elegível cuja data de referência já passou; a CL-05 foi fechada em 25/10/2026; contrato no plano 015. Texto anterior, preservado: "A semântica do critério binário de fórum continua em aberto (`closure-register.md`, CL-05)".)*
Critérios definidos na criação/configuração do curso, imutáveis depois e sem reprocessamento retroativo.

## Conformidade Moodle

Usar Access API e contexts Moodle no backend; não confiar somente na UI. Form API será detalhada depois. O plugin deve respeitar o modelo de autorização do Moodle.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
