# Quickstart (validação) — spec-016

> Nada foi executado. LLM mockada.

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Nova informação relevante | Nova síntese ligada ao processing | §Regras, §Conformidade |
| Q2 | Nenhuma informação nova | Nenhuma nova síntese | §Regras *(25/10/2026: fingerprint igual ⇒ sem chamada à LLM)* |
| Q3 | Mock falha | Síntese anterior preservada (Q-016-04) | constituição §7 *(25/10/2026: nenhuma versão nova)* |
| Q4 | Primeira síntese da seção | `version = 1` | spec-016 |
| Q5 | Recálculo de notas com os mesmos valores | Mesmo fingerprint; sem LLM e sem versão | DEC-41 D2; DEC-19 |
| Q6 | Inspecionar o `context` enviado | Só agregados e textos já produzidos; sem identificadores, nomes ou notas individuais; sem critérios da 015 | DEC-34 D3; DEC-41 D1 |
| Q7 | Seção com 2 atividades | Estatísticas por atividade; nenhum índice único da seção | DEC-41 D1 |
| Q8 | Nova postagem altera o resumo do fórum da seção | Novo fingerprint; nova versão | DEC-41 D2 |
| Q9 | Mesmo conjunto com ordem de entidades e timestamps diferentes | Mesmo fingerprint | DEC-41 (canonização) |
| Q10 | Recálculo de frequência de um estudante com atividades em 3 seções | 3 eventos de síntese; um pendente existente é reutilizado | DEC-41 D3; DEC-21 |
| Q11 | Seção só com materiais; depois com uma nota | Sem síntese; depois síntese criada, frequência como `no_data` | DEC-41 D3 |
| Q12 | `section_id` explícito de outro curso; `cmid` sem seção resolvível | Sem síntese; pendência registrada | spec-016 |
| Q13 | Mudança na seção A | Síntese da seção B inalterada | DEC-13 |
| Q14 | Mock devolve conteúdo com HTML | Gravado como texto; exibido escapado | tratamento de Q-016-03 |

*(Q4–Q14 incluídos em 25/10/2026, depois de DEC-34 e DEC-41.)*
