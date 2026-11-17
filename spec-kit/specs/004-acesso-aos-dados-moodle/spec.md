# spec-004 — Camada de Acesso aos Dados do Moodle

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 1 marcador de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitado por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search3`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Isolar a consulta dos dados originais do Moodle usados pelas análises.

## Dependências de Specs Anteriores

spec-003

## Contratos & Interfaces

Readers/adapters lógicos para:
- Course/Section;
- User/Student;
- Course modules;
- Questionnaires/questions/attempts/responses;
- Forums/discussions/messages;
- Gradebook;
- Completion/participation;
- materiais/arquivos.

Preferir APIs de domínio Moodle; usar DML quando não existir API apropriada.

## Regras de Validação & Requisitos Técnicos

Não copiar genericamente dados Moodle para tabelas próprias.
`cmid` identifica o módulo no curso.
Resolução de seção. A camada de leitura deve oferecer duas operações. (a) Resolver a seção de um módulo: dado um `course_id` e um `cmid` válido nesse curso, retorna o `course_sections.id` da seção diretamente associada ao módulo, desde que a seção seja válida para o curso; se o módulo estiver em uma subseção (seção delegada), retorna a seção delegada. (b) Validar uma seção: dado um `course_id` e um `section_id`, aceita a seção somente se ela existir naquele curso.

Nos demais casos o resultado é uma condição explícita de não resolvido, sem valor padrão: `cmid` inexistente ou inválido; `cmid` pertencente a outro curso (condição distinta da anterior); `section_id` inexistente ou de outro curso. O valor retornado é o mesmo `course_sections.id` usado em `material.section_id` e `frequency.section_id`. A resolução não grava linhas em tabelas `local_assistente_*`; o registro de uma pendência de processamento cabe ao mecanismo de processamento. A resolução da seção não implica a criação de síntese, que continua sujeita à spec-016; sem resolução, a síntese por seção não é criada e a pendência é registrada pelo mecanismo de processamento.

Pendência técnica: a disponibilidade dessas operações nos readers de Course/Section e Course modules ainda não foi verificada (plano 004, Q-004-07). Isso não resolve, por si só, a Q-004-01, que cobre as APIs dos oito readers. *(Alterado em 21/10/2026 por decisão funcional sobre síntese por curso e seção, motivado pela questão técnica Q-004-07 do plano 004 (resolução de seção por `cmid`); DEC-13 e decisões relacionadas, conforme aplicável.)*
Notas devem vir do gradebook conforme configuração do Moodle.
Questões são identificadas por `question_id + question_version`; `slot` não é requisito.

## Conformidade Moodle

A camada deve usar APIs Moodle 5.2 e DML como último nível de acesso persistente. DML é a API oficial para leitura/escrita de dados.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
