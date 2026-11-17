# spec-007 — Tasks, Processamento Assíncrono e Retries

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 1 marcador de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitado por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search6`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Definir a execução assíncrona da fila própria.

## Dependências de Specs Anteriores

spec-006

## Contratos & Interfaces

Tasks API:
- Scheduled Task para ciclo periódico;
- mecanismo interno para processar eventos da fila.

Persistência:
`processing` 1:1 com `event`;
até 3 `processing_attempt` por processamento.

## Regras de Validação & Requisitos Técnicos

Intervalo inicial: 15 minutos.
O intervalo é configurado pelo administrador no agendamento nativo das Scheduled Tasks do Moodle, com padrão de 15 minutos; o plugin não tem campo nem formulário próprio para isso. *(Incluído em 25/10/2026 por DEC-32, D1.)*
Administrador pode configurar o intervalo.
Cada execução processa eventos pendentes em lote até esgotar a fila.
O processamento é um laço na Scheduled Task que trata um evento por vez, sob o lock do próprio evento, sem Adhoc Tasks por evento; a ordem é `created_at` crescente. Cada evento recebe no máximo uma tentativa por ciclo. "Até esgotar a fila" significa: o ciclo continua enquanto houver evento `pending` ainda não tentado nele, inclusive os que chegarem durante a execução, e se encerra quando (a) não restar nenhum ou (b) chegar o horário da próxima execução agendada da própria task; no caso (b), o evento em andamento é concluído e os restantes ficam para o ciclo seguinte. A condição usa o intervalo configurado (D1), sem limite numérico novo. *(Incluído em 25/10/2026 por DEC-32, D2, D3, D5 e D6.)* *(Remissão à DEC-59, proposta de organização do registro, data de aprovação confirmada pelo responsável em 03/11/2026 (texto anterior, preservado: "data de aprovação [A CONFIRMAR]"): a DEC-59 admite uma exceção delimitada à D2, a tarefa ad hoc de reentrega, que não processa evento e apenas repete a criação de um evento cuja entrega falhou por trava no observer. O princípio geral acima não muda: o processamento da fila continua sem Adhoc Tasks por evento.)*
Eventos de fórum (toda postagem ou interação) são processados por esta fila, de forma assíncrona. Processar um evento que alimenta a síntese não obriga criar nova versão da síntese (spec-016). *(Incluído em 22/10/2026 por DEC-03 e DEC-04.)*
Máximo de 3 tentativas do mesmo evento, contadas no total e incluindo a tentativa inicial. O contador persistido do plugin é a referência para o limite. *(Esclarecido em 22/10/2026 por DEC-09 e contrato P-04; texto original: "Máximo de 3 tentativas do mesmo evento.")*
Depois de uma falha, a nova tentativa ocorre no ciclo seguinte, sem espera adicional. `processing_attempt.status` assume `processing`, `processed` ou `failure`; `processing.status` usa o vocabulário da spec-006. Depois de uma falha, o evento volta a `pending` se restarem tentativas e passa a `failure` depois da 3ª falha. *(Incluído em 25/10/2026 por DEC-32, D3 e D4.)*
Tentativas e falhas permanecem persistidas.
Processamento deve ser idempotente.
A repetição do mesmo evento lógico reutiliza o processamento existente; uma nova ocorrência legítima tem evento e processamento próprios. *(Incluído em 22/10/2026 por DEC-08.)*
O evento lógico é identificado pela identidade de deduplicação definida por tipo concreto de evento na spec-006. *(Incluído em 22/10/2026 por DEC-21.)*
Se duas tarefas tentarem processar o mesmo evento lógico, existe apenas um processamento lógico. A tarefa concorrente reconhece o processamento existente: não o reinicia se estiver em andamento e não duplica resultados se já tiver terminado. Retentativas pertencem ao mesmo processamento. *(Incluído em 22/10/2026 por DEC-16.)*
O processamento usa bloqueio por evento lógico, registra tentativas e falhas e aplica idempotência aos resultados. Um processamento interrompido é recuperado sem criar novo processamento lógico. Não manter transação de banco aberta durante chamadas à LLM. O backend de bloqueio e a recuperação de execução interrompida devem ser validados no Moodle 5.2 antes da implementação. *(Incluído em 22/10/2026 por DEC-22 e contrato P-04.)*

## Conformidade Moodle

Moodle Tasks API suporta Scheduled Tasks e Adhoc Tasks e é indicada para operações lentas/background. O projeto usa a própria fila lógica e a Tasks API como mecanismo de execução.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
