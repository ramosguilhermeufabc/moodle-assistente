# Data Model — spec-003

Esta spec não cria entidades novas: opera sobre as 21 tabelas de spec-002 (ver `../002-esquema-xmldb/data-model.md`).

| Elemento lógico | Responsabilidade | Fonte | Estado |
|---|---|---|---|
| Repository (por agregado) | Ler/gravar agregados próprios via DML | §Contratos | agregados NEEDS CLARIFICATION (Q-003-01) (04/11/2026: resolvido: onze agregados na seção "Agregados" do plano; repositórios em `assistente/classes/infrastructure/persistence/`) *(26/10/2026: onze agregados, seção "Agregados" do plano)* |
| Gateway/Reader | Ler dados derivados | §Contratos | escopo NEEDS CLARIFICATION (Q-003-05) (04/11/2026: resolvido: definido no plano 003; leitores em `assistente/classes/infrastructure/moodle/`) *(26/10/2026: dados do Moodle (spec-004) e modelos de leitura de 018/019, sem gravação)* |
| Transação | Garantir atomicidade multi-tabela | §Regras | critério NEEDS CLARIFICATION (Q-003-02) (04/11/2026: resolvido: unidades transacionais do plano 003; implementadas em `assistente/classes/infrastructure/persistence/unit_of_work.php`) *(26/10/2026: unidades da seção "Unidades transacionais" do plano)* |

Regra de validação: respeitar as chaves e os índices do XMLDB, incluindo a unicidade [REQ §Regras]. *(26/10/2026: e os domínios de valores definidos no data-model da spec-002, que o XMLDB não restringe.)*
