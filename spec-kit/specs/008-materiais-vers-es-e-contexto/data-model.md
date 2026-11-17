# Data Model — spec-008

## `local_assistente_material`
`course_id`, `section_id` (→ `course_sections`), `cmid` (→ `course_modules`), `material_type` char(20), `name`, `availability` char(20) (`available`, `unavailable`, `pending`; DEC-51), `current_version` (nullable → `material_version.id`), `created_at`. Único `course_id,cmid`. `material_type`: PDF (`mod_resource` com PDF), texto (`mod_page`), YouTube (`mod_url`), conforme DEC-33 D2.

## `local_assistente_material_version`
`material_id`, `version`, `content_hash` char(64) *(CL-45, 26/10/2026)*, `summary` (nullable), `summary_status` char(20) (`pending`, `completed`, `failed`; DEC-50), `availability` char(20) (`available`, `unavailable`, `pending`; DEC-51), `created_at`. Único `material_id,version`. A versão corrente é a referência `material.current_version` (DEC-20, DEC-29).

*(Alterado em 25/10/2026 por DEC-29 e DEC-33; texto original: em `material`, os campos sem `current_version`; em `material_version`, "`summary_status` char(20), `is_current` int(1), `availability` int(1)".)* *(Corrigido em 31/10/2026 (T058): `material.availability` também é `char(20)` desde DEC-51, como no `install.xml`; este documento ainda dizia `int(1)`.)*

### Disponibilidade (DEC-26, DEC-51; T058)
- `material.availability`: a disponibilidade mais recente conhecida do material. `pending` ao registrar; `available` quando pelo menos um estudante do curso acessa o módulo (disponibilidade parcial é `available`); `unavailable` quando nenhum acessa. Sem estudantes no curso, ou se a avaliação falhar, nada é determinado e o valor guardado permanece.
- `material_version.availability`: o valor observado quando a versão foi criada (`pending` se nada foi determinado). Nunca é alterado depois; reavaliar o material não toca nas versões e não cria versão.
- A avaliação roda a cada `material_changed` processado (inclui a edição de restrições pelo formulário e a sincronização inicial), com a mesma regra por usuário do snapshot. Mudanças sem evento do Moodle (ícone do olho, mover, datas, grupos, inscrições) não a disparam; o valor pode ficar defasado até o próximo evento, e isso é aceito.
- Não decide elegibilidade: o snapshot avalia a disponibilidade para os estudantes relevantes de cada análise e não lê estes campos (DEC-26).

## `local_assistente_processing_context`
`processing_id` (foreign-unique), `context_json` text, `created_at`. Imutável [REQ §Contratos].

## Regras
- Elegível ⇔ processado com sucesso e disponível a todos os estudantes relevantes da análise; disponível a só parte deles pode ser resumido, mas não compõe o contexto; disponibilidade avaliada e registrada no snapshot [REQ; DEC-05, DEC-26]. *(Alterado em 25/10/2026; texto original: "Elegível ⇔ disponível aos estudantes [REQ] (parcial: Q-008-01).")*
- Alteração de conteúdo processável (DEC-33 D3) ⇒ `version+1`; depois de a nova versão estar persistida, `material.current_version` passa a apontar para ela, sob lock e transação; a anterior é preservada [REQ; DEC-20, DEC-29] (troca atômica: Q-003-02). *(Alterado em 25/10/2026; texto original: "Alteração ⇒ `version+1`, nova `is_current=1`, anterior preservada [REQ] (troca atômica: Q-003-02; unicidade: Q-002-05).")*
- Contexto só com versões correntes, elegíveis e da mesma `section_id` [REQ].
- *(Incluído em 01/11/2026 por DEC-61.)* **Etapas da persistência de uma versão.** Três etapas independentes, cada uma sob o lock `material_<id>` e em transação própria: (a) criar a versão com `summary_status = pending`; (b) gravar o resumo e passar a `completed`, depois do processamento externo da LLM; (c) trocar `material.current_version` para uma versão `completed`. A LLM é chamada entre (a) e (b), sem transação aberta. Estados válidos que decorrem disso: versão `pending` ou `failed` não corrente; versão `completed` não corrente (a etapa (c) falhou, ou outra versão mais nova já é a corrente). A versão anterior continua corrente até (c). Uma versão que não é corrente não compõe o contexto. A recuperação de "`completed` e não corrente" é uma nova execução do fluxo, que só refaz a etapa (c), sem nova chamada à LLM.
- *(Incluído em 01/11/2026 por DEC-62.)* **Monotonicidade.** `current_version` não retrocede. A troca (c) aceita a versão somente se (1) pertence ao material, (2) está `completed` e (3) seu `version` é estritamente maior que o `version` da versão atualmente corrente (sem versão corrente, a condição (3) não se aplica). A comparação é pelo número `version`, lido dentro do lock e da transação. Número igual (inclusive a própria corrente) ou menor é rejeitado e não altera nada. A regra vale para a **seleção da corrente**; a consulta de qualquer versão, a leitura de snapshots históricos e a existência de versões `completed` de número menor não são restringidas. Voltar a um conteúdo anterior cria versão nova com número maior (DEC-33 D3).

## Estados de versão
```text
[nova versão] summary_status = pending --resumo assíncrono--> completed | failed   (failed --nova tentativa--> pending; DEC-50)
[corrente] --nova alteração--> [histórica] (preservada)
```
