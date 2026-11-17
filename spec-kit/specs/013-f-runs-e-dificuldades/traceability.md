# Rastreabilidade — spec-013

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-024 | Toda interação/postagem em fórum gera evento para atualização | spec-006; spec-013; decisão confirmada em 2026-10-21 | Confirmado por decisão aprovada |
| AT-056 | Gerar resumo por fórum e dificuldades por discussão/tópico | spec-013; Etapa 3 §3.2; DEC-38 | Confirmado; cardinalidade e atualização das dificuldades por DEC-38 D1 (25/10/2026) |
| AT-057 | Análise incremental de fórum usa resumo corrente + nova mensagem + contexto da seção | spec-013; DEC-38 | Confirmado; edição e exclusão por DEC-38 D2 (25/10/2026) |

*(25/10/2026: AT-056 e AT-057 atualizados depois da DEC-38; textos originais: fontes "spec-013; Etapa 3 §3.2" e "spec-013"; estados "Confirmado".)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-03 | Fóruns |
| DEC-04 | Fóruns |
| DEC-08 | Fóruns |
| DEC-34 | Integração com LLM externa (D3: sem identificadores do autor) *(incluída em 25/10/2026)* |
| DEC-38 | Fóruns *(incluída em 25/10/2026)* |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q13). Validação técnica em `closure-register.md` CL-02 e CL-35. Nada foi executado.
