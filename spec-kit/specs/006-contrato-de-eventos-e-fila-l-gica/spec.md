# spec-006 — Contrato de Eventos e Fila Lógica

## Objetivo & Escopo

Definir eventos próprios persistentes do assistente e a fila lógica.

## Dependências de Specs Anteriores

spec-003, spec-004, spec-005

## Contratos & Interfaces

`local_assistente_event`:
- course_id;
- event_type;
- payload JSON/text;
- created_at;
- status;
- processed_at;
- response.

Fluxo:
Moodle event -> observer -> assistant event.

## Regras de Validação & Requisitos Técnicos

Um evento representa uma ocorrência.
Estados:
`pending`, `processing`, `processed`, `failure`.
Não executar LLM diretamente no observer.
Um questionário publicado gera exatamente um evento.
Um questionário é publicado quando o questionário passa a ficar visível aos estudantes pela primeira vez, seja criado já visível, seja tornado visível depois. Cada questionário gera um único evento de publicação; ocultar e mostrar de novo, ou editar depois da publicação, não gera novo evento. A data de abertura não faz parte da definição. *(Incluído em 25/10/2026 por DEC-31.)*
Idempotência por evento lógico: a repetição da mesma ocorrência reutiliza o processamento existente; uma nova ocorrência legítima cria novo evento. Entregas concorrentes não duplicam processamento nem resultados. *(Incluído em 22/10/2026 por DEC-08.)*
A identidade técnica para deduplicação é definida por tipo concreto de evento Moodle, depois de verificar os dados reais do evento no Moodle 5.2. Não há identificador universal de ocorrência. Usar identificador estável somente quando comprovado; caso contrário, documentar a estratégia do tipo e suas limitações, sem alegar deduplicação forte quando os dados não distinguirem ocorrências legítimas idênticas. *(Incluído em 22/10/2026 por DEC-10 e DEC-21.)*
Concorrência, tentativas e recuperação do processamento seguem a spec-007: um único processamento lógico por evento, bloqueio por evento lógico e no máximo 3 tentativas totais, incluindo a inicial. *(Incluído em 22/10/2026 por DEC-16 e DEC-22.)*
Transições do evento: `pending` → `processing` → `processed`, ou, em caso de falha, de volta a `pending` enquanto restarem tentativas e `failure` depois da 3ª falha (spec-007). *(Incluído em 25/10/2026 por DEC-32, D4.)*
Toda postagem ou interação no fórum gera evento e dispara o fluxo de atualização, respeitando o processamento assíncrono. *(Alterado em 22/10/2026 por DEC-03; texto original: "Cada atualização relevante de fórum gera evento.")*

## Conformidade Moodle

Moodle Events API deve ser usada para observar eventos; nomes concretos devem ser verificados na versão 5.2 antes da implementação.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
