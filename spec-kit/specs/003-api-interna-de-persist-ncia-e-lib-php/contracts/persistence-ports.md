# Contract — Portas de persistência (spec-003)

Contrato lógico interno, consumido pelas specs 004–019. As assinaturas concretas estão NEEDS CLARIFICATION (Q-003-01/04) (04/11/2026: resolvido: Q-003-01 e Q-003-04 resolvidas no plano 003; assinaturas implementadas em `assistente/classes/infrastructure/persistence/repository.php` e nos repositórios do mesmo diretório). *(26/10/2026: Q-003-01 e Q-003-04 resolvidas no plano; assinaturas concretas definidas nas tasks.)*

| Porta | Consumidor | Operações | Regras |
|---|---|---|---|
| `Repository<Aggregate>` | Application services | ler por id/chave natural; inserir; atualizar | DML apenas aqui; sem prefixo físico; respeita únicos do XMLDB [REQ §Regras] *(26/10/2026: um repositório por agregado da seção "Agregados" do plano; valida os domínios de `char` e `int(1)` do data-model 002)* |
| `Reader/Gateway` (derivados) | Consultas (018), relatórios (019) | leituras somente | Domínio não vê SQL [REQ §Contratos] *(26/10/2026: também os readers de dados do Moodle da spec-004)* |
| `Transaction boundary` | Serviços multi-tabela | executar unidade atômica | "quando necessário" (Q-003-02) *(26/10/2026: as unidades da seção "Unidades transacionais" do plano; transação curta; nunca durante chamada à LLM)* |
| `lib.php` callbacks | Moodle core | somente callbacks sem alternativa moderna | Sem lógica de negócio (Q-003-03) *(26/10/2026: só navegação e, se necessário, exclusão de curso)* |

## Regras adicionais *(incluído em 26/10/2026)*

- O serviço de aplicação faz a chamada à LLM fora da transação e só então abre a unidade transacional para gravar.
- Unidades com lock adquirem o lock antes de abrir a transação e o liberam depois do commit ou do rollback.
- Exclusões da Privacy API e do curso são unidades próprias, registradas no log de processamento (DEC-42).
