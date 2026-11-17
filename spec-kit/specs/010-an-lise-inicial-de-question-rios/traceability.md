# Rastreabilidade — spec-010

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-023 | Publicação de questionário gera exatamente um evento | spec-006; spec-010; DEC-31 | Confirmado; publicação definida por DEC-31 (25/10/2026) |
| AT-049 | Validar saída estruturada do LLM antes de persistir | spec-010; spec-009 | Confirmado; faixa e passo inválidos = `invalid_response`, sem normalizar (25/10/2026) |
| AT-050 | Analisar questionário publicado com materiais elegíveis da mesma seção | spec-010; DEC-35 | Confirmado; questões analisadas e ausência de materiais definidas por DEC-35 (25/10/2026) |
| AT-051 | Produzir coverage do questionário e complexity por questão em escala 0,0–1,0, passo 0,1 | spec-010; DEC-35 | Confirmado; `coverage = 0.0` determinístico sem material elegível (DEC-35 D2) (25/10/2026) |

*(25/10/2026: colunas "Fonte" e "Estado" alinhadas a `requirements-traceability.md` e às DEC-31, 34 e 35; textos originais: fontes "spec-006; spec-010", "spec-010" e estado "Confirmado".)*

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-31 | Eventos de questionário; Análise inicial de questionário |
| DEC-34 | Integração com LLM externa (D1 adapter simulado; D3 sem dados de estudantes) |
| DEC-35 | Análise inicial de questionário |

*(Incluído em 25/10/2026; texto original: "Nenhuma decisão tem esta spec listada na seção 3 do registro.")*

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md) (Q1–Q12). Validação técnica em `closure-register.md` CL-02 e CL-33. Nada foi executado.
