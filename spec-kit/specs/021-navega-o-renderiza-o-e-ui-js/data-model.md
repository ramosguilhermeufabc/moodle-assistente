# Data Model — spec-021

Sem entidades persistidas. Renderables (view models) derivam dos contratos de spec-018 (consultas) e spec-019 (relatório atual e histórico), mais um indicador "fonte removida" cuja fonte de dado está NEEDS CLARIFICATION (04/11/2026: resolvido: Q-021-04 resolvida em 26/10/2026 por DEC-42 D3, ver a nota ao final; implementação em `assistente/classes/application/history/source_status_resolver.php`, com teste `assistente/tests/application/history/source_status_resolver_test.php`) (Q-021-04). *(26/10/2026: Q-021-04 resolvida — o indicador é derivado na leitura, verificando se o registro Moodle referenciado ainda existe, DEC-42 D3.)*

*(Incluído em 26/10/2026:)* cada renderable recebe, para cada valor, o estado (`valid`/`no_data`/`error`) e as indicações aplicáveis ("sem materiais", "fonte removida", "assistente não habilitado"); textos gerados pela LLM chegam como texto simples, com o idioma da análise de origem (DEC-46).
