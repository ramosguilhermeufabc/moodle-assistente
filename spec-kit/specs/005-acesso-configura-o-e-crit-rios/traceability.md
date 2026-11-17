# Rastreabilidade — spec-005

> Gerado a partir de `requirements-traceability.md` (coluna "Spec Kit de destino") e da seção 3 de `decision-register-DEC-01-25.md`. Não cria requisito nem decisão nova. O mapeamento de decisões é por área, conforme a seção 3 do registro, e não por requisito. A spec original continua normativa; "Confirmado" não significa implementado ou testado.

## Requisitos atômicos (AT)

| ID | Requisito | Fonte | Estado na reconciliação |
|---|---|---|---|
| AT-015 | Professor acessa somente cursos próprios e administrador todos | Constituição §14 *(1.1.0; referência original: §11 da constituição do pacote modular, ajustada em 25/10/2026)*; spec-005; DEC-30 | Confirmado; critério operacional definido por DEC-30 (25/10/2026) |
| AT-016 | Configurar assistente por curso | spec-005; XMLDB local_assistente_config | Confirmado |
| AT-017 | Definir critérios de atividade numéricos, fórum binário e frequência numérica | spec-005; XMLDB local_assistente_criteria | Confirmado |
| AT-018 | Critérios são definidos na configuração e imutáveis depois | spec-005; spec-020 | Confirmado |
| AT-019 | Alterar critérios sem reprocessar retroativamente resultados anteriores | spec-005 | Confirmado |

## Decisões aprovadas (DEC)

| Decisão | Área (seção 3 do registro) |
|---|---|
| DEC-02 | Critérios |
| DEC-11 | Critérios |
| DEC-30 | Controle de acesso (incluída em 25/10/2026) |

## Teste/validação

A matriz informa "Definir caso de aceitação verificável na feature correspondente" para os itens acima. Cenários planejados: [quickstart.md](quickstart.md). Nada foi executado.
