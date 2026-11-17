# spec-023 — Testes e Qualidade Transversal

## Objetivo & Escopo

Definir a estratégia de qualidade que valida todas as specs anteriores antes da implementação ser considerada concluída.

## Dependências de Specs Anteriores

spec-001 até spec-022

## Contratos & Interfaces

Testes:
- unitários;
- integração;
- persistência/XMLDB;
- eventos/tasks;
- API LLM mockada;
- regras determinísticas;
- acesso;
- histórico;
- UI quando aplicável.

## Regras de Validação & Requisitos Técnicos

Testar especialmente:
- máximo de 3 tentativas totais por processamento, incluindo a tentativa inicial; *(Alterado em 22/10/2026 por DEC-09; texto original: "máximo de 3 retries;")*
- execução da fila: uma tentativa por evento por ciclo, retentativa no ciclo seguinte, retorno a `pending` e `failure` depois da 3ª falha, ordem por `created_at` e encerramento do ciclo sem fila vazia ao atingir o horário da próxima execução, com os restantes no ciclo seguinte; *(Incluído em 25/10/2026 por DEC-32.)*
- idempotência;
- recepção duplicada da mesma ocorrência, duas ocorrências legítimas do mesmo objeto, entrega concorrente do mesmo evento e recuperação de processamento interrompido sem novo processamento lógico; *(Incluído em 22/10/2026 por DEC-08, DEC-10, DEC-16, DEC-21 e DEC-22.)*
- versionamento;
- versão corrente de material: no máximo uma por material, transição atômica e preservação da versão anterior em caso de falha, inclusive sob concorrência; *(Incluído em 22/10/2026 por DEC-12 e DEC-15.)*
- referência única `current_version` em material e relatório, e unicidade de (curso, seção, versão) na síntese, inclusive sob concorrência e rollback; *(Incluído em 25/10/2026 por DEC-14, DEC-20, DEC-23 e DEC-29.)*
- evento de fórum sem mudança no conjunto de informações pedagógicas não cria versão da síntese; mudança relevante cria; mudança em uma seção não altera a síntese de outra; *(Incluído em 22/10/2026 por DEC-03, DEC-04, DEC-13 e DEC-19.)*
- isolamento por seção;
- elegibilidade de material: disponível a todos os estudantes relevantes entra no contexto; disponível a só parte deles é resumido, mas fica fora do contexto; restrição de acesso não atendida conta como indisponível; a avaliação ocorre ao montar o snapshot; *(Incluído em 22/10/2026 por DEC-05 e DEC-26.)*
- versão de material: mudança de arquivo, texto da página ou URL cria versão; mudança só de nome, visibilidade, restrição ou seção não cria; só `mod_resource` com PDF, `mod_page` e `mod_url` do YouTube são materiais funcionais; *(Incluído em 25/10/2026 por DEC-33.)*
- escopo Professor/Admin;
- cálculos;
- critérios de mínimo e de máximo com valor igual ao limite e imediatamente acima e abaixo dele; *(Incluído em 22/10/2026 por DEC-11.)*
- desempate de estudantes com pontuação igual por ordem alfabética crescente do nome; *(Incluído em 22/10/2026 por DEC-02.)*
- denominador zero (0 com `no_data`), zero real com denominador positivo (`valid`) e erro técnico (`error`, nunca convertido em zero), em métricas de questionário e frequência e na apresentação; *(Incluído em 22/10/2026 por DEC-01 e DEC-24.)*
- frequência: acesso ao curso registrado sem entrar no denominador; a mesma atividade com vários eventos conta uma vez; só entram as categorias da spec-014; *(Incluído em 25/10/2026 por DEC-25.)*
- publicação de questionário: criado visível gera 1 evento; criado oculto e tornado visível gera 1 evento na visibilidade; ocultar e mostrar de novo, ou editar depois, não gera outro; recepção duplicada e concorrente não duplica; *(Incluído em 25/10/2026 por DEC-31.)*
- falha da API LLM;
- atualização final de relatório;
- relatório sem data final aplicável, mudança da data final, coincidência entre atualização periódica e final, e falha que não reinicia o intervalo de 72 horas nem substitui o último resultado válido. *(Incluído em 22/10/2026 por DEC-06, DEC-07, DEC-17 e DEC-18.)*
- despachante de relatórios: reagendamento idempotente, serialização por relatório, execução atrasada pelo cron e registro da conclusão final; *(Incluído em 25/10/2026 por DEC-23 e DEC-29.)*
A metodologia do projeto exige testes unitários por entrega e verificação de usabilidade pelo pesquisador, sem professores/estudantes como participantes.

## Conformidade Moodle

A execução deve ocorrer no ambiente Codespaces/container definido em spec-001 e respeitar ferramentas/test runners compatíveis com Moodle 5.2.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
