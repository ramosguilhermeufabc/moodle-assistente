# spec-002 — Esquema de Dados Base / XMLDB

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 2 marcadores de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitados por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search13turn0search3`, `citeturn0search13turn0search3`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Definir o esquema próprio do plugin usando integralmente `db/install.xml` como fonte primária de verdade.

O arquivo auditado contém 21 tabelas e todas as definições de campos, chaves e índices devem permanecer sincronizadas com ele.

## Dependências de Specs Anteriores

spec-001

## Contratos & Interfaces

Fonte primária:

`/mnt/data/install_auditado.xml` / futuro `local/assistente/db/install.xml`.

Persistência deve usar DML; alterações estruturais devem usar XMLDB/upgrade API. A documentação do Moodle descreve DDL/XMLDB como a camada neutra para estruturas de banco e DML como a camada de leitura/escrita.

## Regras de Validação & Requisitos Técnicos

O esquema deve manter:
- prefixo Frankenstyle `local_assistente_`;
- PK `id` sequencial;
- FKs para tabelas Moodle quando definidos no XMLDB;
- índices únicos exatamente onde definidos;
- `number` com `LENGTH` e `DECIMALS` separados;
- nenhum campo ou índice inventado fora do `install.xml`.

Qualquer mudança futura exige atualização do XMLDB e mecanismo de upgrade correspondente. Enquanto o plugin não tiver sido instalado em nenhum ambiente, a mudança aprovada é feita diretamente no `install.xml`, sem `db/upgrade.php`; depois da primeira instalação, toda mudança exige upgrade. *(Esclarecido em 25/10/2026 por DEC-29.)*

## Conformidade Moodle

Moodle XMLDB é a fonte portável de definição de tabelas, campos, chaves e índices. DML deve ser usado para operações de dados.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.

## Inventário exato do `install.xml`

### `local_assistente_config` — Configuracao do assistente por curso
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `course_id`: NAME=course_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `enabled`: NAME=enabled, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `course_id`: {'NAME': 'course_id', 'TYPE': 'foreign-unique', 'FIELDS': 'course_id', 'REFTABLE': 'course', 'REFFIELDS': 'id'}

### `local_assistente_criteria` — Criterios de desempenho por curso
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `config_id`: NAME=config_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `activity_criterion`: NAME=activity_criterion, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
- `forum_criterion`: NAME=forum_criterion, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false
- `frequency_criterion`: NAME=frequency_criterion, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `config_id`: {'NAME': 'config_id', 'TYPE': 'foreign-unique', 'FIELDS': 'config_id', 'REFTABLE': 'local_assistente_config', 'REFFIELDS': 'id'}

### `local_assistente_event` — Eventos do assistente
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `course_id`: NAME=course_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `event_type`: NAME=event_type, TYPE=char, LENGTH=100, NOTNULL=true, SEQUENCE=false
- `payload`: NAME=payload, TYPE=text, NOTNULL=true, SEQUENCE=false
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `status`: NAME=status, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false
- `processed_at`: NAME=processed_at, TYPE=int, LENGTH=10, SEQUENCE=false, NOTNULL=false
- `response`: NAME=response, TYPE=text, SEQUENCE=false, NOTNULL=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `course_id`: {'NAME': 'course_id', 'TYPE': 'foreign', 'FIELDS': 'course_id', 'REFTABLE': 'course', 'REFFIELDS': 'id'}
Índices:
- `status`: {'NAME': 'status', 'UNIQUE': 'false', 'FIELDS': 'status'}
- `course_id-status`: {'NAME': 'course_id-status', 'UNIQUE': 'false', 'FIELDS': 'course_id,status'}
- `created_at`: {'NAME': 'created_at', 'UNIQUE': 'false', 'FIELDS': 'created_at'}

### `local_assistente_processing` — Processamento logico de evento
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `event_id`: NAME=event_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `status`: NAME=status, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `finished_at`: NAME=finished_at, TYPE=int, LENGTH=10, SEQUENCE=false, NOTNULL=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `event_id`: {'NAME': 'event_id', 'TYPE': 'foreign-unique', 'FIELDS': 'event_id', 'REFTABLE': 'local_assistente_event', 'REFFIELDS': 'id'}
Índices:
- `status`: {'NAME': 'status', 'UNIQUE': 'false', 'FIELDS': 'status'}

### `local_assistente_processing_attempt` — Tentativas de processamento
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `processing_id`: NAME=processing_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `attempt_number`: NAME=attempt_number, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `started_at`: NAME=started_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `finished_at`: NAME=finished_at, TYPE=int, LENGTH=10, SEQUENCE=false, NOTNULL=false
- `status`: NAME=status, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false
- `response`: NAME=response, TYPE=text, SEQUENCE=false, NOTNULL=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `processing_id`: {'NAME': 'processing_id', 'TYPE': 'foreign', 'FIELDS': 'processing_id', 'REFTABLE': 'local_assistente_processing', 'REFFIELDS': 'id'}
Índices:
- `processing_id-attempt_number`: {'NAME': 'processing_id-attempt_number', 'UNIQUE': 'true', 'FIELDS': 'processing_id,attempt_number'}
- `status`: {'NAME': 'status', 'UNIQUE': 'false', 'FIELDS': 'status'}

### `local_assistente_processing_context` — Snapshot do contexto utilizado no processamento
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `processing_id`: NAME=processing_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `context_json`: NAME=context_json, TYPE=text, NOTNULL=true, SEQUENCE=false
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `processing_id`: {'NAME': 'processing_id', 'TYPE': 'foreign-unique', 'FIELDS': 'processing_id', 'REFTABLE': 'local_assistente_processing', 'REFFIELDS': 'id'}

### `local_assistente_material` — Materiais do Moodle utilizados como contexto
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `course_id`: NAME=course_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `section_id`: NAME=section_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `cmid`: NAME=cmid, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `material_type`: NAME=material_type, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false
- `name`: NAME=name, TYPE=char, LENGTH=255, NOTNULL=true, SEQUENCE=false
- `availability`: NAME=availability, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false *(Alterado em 26/10/2026 por DEC-51; era int, LENGTH=1)*
- `current_version`: NAME=current_version, TYPE=int, LENGTH=10, NOTNULL=false, SEQUENCE=false *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-20.)*
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `course_id`: {'NAME': 'course_id', 'TYPE': 'foreign', 'FIELDS': 'course_id', 'REFTABLE': 'course', 'REFFIELDS': 'id'}
- `section_id`: {'NAME': 'section_id', 'TYPE': 'foreign', 'FIELDS': 'section_id', 'REFTABLE': 'course_sections', 'REFFIELDS': 'id'}
- `cmid`: {'NAME': 'cmid', 'TYPE': 'foreign', 'FIELDS': 'cmid', 'REFTABLE': 'course_modules', 'REFFIELDS': 'id'}
- `current_version`: {'NAME': 'current_version', 'TYPE': 'foreign', 'FIELDS': 'current_version', 'REFTABLE': 'local_assistente_material_version', 'REFFIELDS': 'id'} *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-20.)*
Índices:
- `course_id-cmid`: {'NAME': 'course_id-cmid', 'UNIQUE': 'true', 'FIELDS': 'course_id,cmid'}

### `local_assistente_material_version` — Versoes dos materiais e respectivos resumos
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `material_id`: NAME=material_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `version`: NAME=version, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `content_hash`: NAME=content_hash, TYPE=char, LENGTH=64, NOTNULL=true, SEQUENCE=false *(Acrescentado em 26/10/2026 por CL-45, nos termos da DEC-29: identificador do conteúdo processável da versão)*
- `summary`: NAME=summary, TYPE=text, SEQUENCE=false, NOTNULL=false
- `summary_status`: NAME=summary_status, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false
- *(Removido em 25/10/2026 por DEC-29; texto original: "`is_current`: NAME=is_current, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false")*
- `availability`: NAME=availability, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false *(Alterado em 26/10/2026 por DEC-51; era int, LENGTH=1)*
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `material_id`: {'NAME': 'material_id', 'TYPE': 'foreign', 'FIELDS': 'material_id', 'REFTABLE': 'local_assistente_material', 'REFFIELDS': 'id'}
Índices:
- `material_id-version`: {'NAME': 'material_id-version', 'UNIQUE': 'true', 'FIELDS': 'material_id,version'}
- *(Removido em 25/10/2026 por DEC-29; texto original: "`material_id-is_current`: {'NAME': 'material_id-is_current', 'UNIQUE': 'false', 'FIELDS': 'material_id,is_current'}")*

### `local_assistente_analysis` — Analises produzidas pelo assistente
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `course_id`: NAME=course_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `processing_id`: NAME=processing_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `analysis_type`: NAME=analysis_type, TYPE=char, LENGTH=20, NOTNULL=true, SEQUENCE=false
- `cmid`: NAME=cmid, TYPE=int, LENGTH=10, SEQUENCE=false, NOTNULL=false
- `summary`: NAME=summary, TYPE=text, SEQUENCE=false, NOTNULL=false
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `course_id`: {'NAME': 'course_id', 'TYPE': 'foreign', 'FIELDS': 'course_id', 'REFTABLE': 'course', 'REFFIELDS': 'id'}
- `processing_id`: {'NAME': 'processing_id', 'TYPE': 'foreign', 'FIELDS': 'processing_id', 'REFTABLE': 'local_assistente_processing', 'REFFIELDS': 'id'}
- `cmid`: {'NAME': 'cmid', 'TYPE': 'foreign', 'FIELDS': 'cmid', 'REFTABLE': 'course_modules', 'REFFIELDS': 'id'}
Índices:
- `analysis_type`: {'NAME': 'analysis_type', 'UNIQUE': 'false', 'FIELDS': 'analysis_type'}

### `local_assistente_questionnaire_analysis` — Analise inicial de questionario
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `coverage`: NAME=coverage, TYPE=number, LENGTH=2, NOTNULL=true, SEQUENCE=false, DECIMALS=1
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign-unique', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}

### `local_assistente_questionnaire_question` — Questoes consideradas na analise inicial do questionario
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `questionnaire_analysis_id`: NAME=questionnaire_analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `question_id`: NAME=question_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `question_version`: NAME=question_version, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `complexity`: NAME=complexity, TYPE=number, LENGTH=2, NOTNULL=true, SEQUENCE=false, DECIMALS=1
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `questionnaire_analysis_id`: {'NAME': 'questionnaire_analysis_id', 'TYPE': 'foreign', 'FIELDS': 'questionnaire_analysis_id', 'REFTABLE': 'local_assistente_questionnaire_analysis', 'REFFIELDS': 'id'}
- `question_id`: {'NAME': 'question_id', 'TYPE': 'foreign', 'FIELDS': 'question_id', 'REFTABLE': 'question', 'REFFIELDS': 'id'}
Índices:
- `questionnaire_analysis_id-question_id-question_version`: {'NAME': 'questionnaire_analysis_id-question_id-question_version', 'UNIQUE': 'true', 'FIELDS': 'questionnaire_analysis_id,question_id,question_version'}

### `local_assistente_question_result` — Resultados das respostas por questao
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `questionnaire_question_id`: NAME=questionnaire_question_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `question_id`: NAME=question_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `question_version`: NAME=question_version, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `average_correctness`: NAME=average_correctness, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
- `correct_count`: NAME=correct_count, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `incorrect_count`: NAME=incorrect_count, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `partial_result`: NAME=partial_result, TYPE=number, LENGTH=10, SEQUENCE=false, DECIMALS=3, NOTNULL=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}
- `questionnaire_question_id`: {'NAME': 'questionnaire_question_id', 'TYPE': 'foreign', 'FIELDS': 'questionnaire_question_id', 'REFTABLE': 'local_assistente_questionnaire_question', 'REFFIELDS': 'id'}
- `question_id`: {'NAME': 'question_id', 'TYPE': 'foreign', 'FIELDS': 'question_id', 'REFTABLE': 'question', 'REFFIELDS': 'id'}
Índices:
- `analysis_id-question_id-question_version`: {'NAME': 'analysis_id-question_id-question_version', 'UNIQUE': 'true', 'FIELDS': 'analysis_id,question_id,question_version'}

### `local_assistente_questionnaire_metric` — Metricas agregadas de questionario
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `mean`: NAME=mean, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
- `population_standard_deviation`: NAME=population_standard_deviation, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
- `minimum`: NAME=minimum, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
- `maximum`: NAME=maximum, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign-unique', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}

### `local_assistente_forum_difficulty` — Dificuldades identificadas em discussoes de forum
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `discussion_id`: NAME=discussion_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `description`: NAME=description, TYPE=text, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}
- `discussion_id`: {'NAME': 'discussion_id', 'TYPE': 'foreign', 'FIELDS': 'discussion_id', 'REFTABLE': 'forum_discussions', 'REFFIELDS': 'id'}

### `local_assistente_performance` — Desempenho por estudante e atividade
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `student_id`: NAME=student_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `activity_id`: NAME=activity_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `grade`: NAME=grade, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}
- `student_id`: {'NAME': 'student_id', 'TYPE': 'foreign', 'FIELDS': 'student_id', 'REFTABLE': 'user', 'REFFIELDS': 'id'}
- `activity_id`: {'NAME': 'activity_id', 'TYPE': 'foreign', 'FIELDS': 'activity_id', 'REFTABLE': 'course_modules', 'REFFIELDS': 'id'}
Índices:
- `analysis_id-student_id-activity_id`: {'NAME': 'analysis_id-student_id-activity_id', 'UNIQUE': 'true', 'FIELDS': 'analysis_id,student_id,activity_id'}

### `local_assistente_frequency` — Frequencia consolidada por estudante e secao
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `student_id`: NAME=student_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `section_id`: NAME=section_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `total_activities`: NAME=total_activities, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `participating_activities`: NAME=participating_activities, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `percentage`: NAME=percentage, TYPE=number, LENGTH=10, NOTNULL=true, SEQUENCE=false, DECIMALS=3
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}
- `student_id`: {'NAME': 'student_id', 'TYPE': 'foreign', 'FIELDS': 'student_id', 'REFTABLE': 'user', 'REFFIELDS': 'id'}
- `section_id`: {'NAME': 'section_id', 'TYPE': 'foreign', 'FIELDS': 'section_id', 'REFTABLE': 'course_sections', 'REFFIELDS': 'id'}
Índices:
- `analysis_id-student_id-section_id`: {'NAME': 'analysis_id-student_id-section_id', 'UNIQUE': 'true', 'FIELDS': 'analysis_id,student_id,section_id'}

### `local_assistente_frequency_activity` — Participacao por atividade no calculo da frequencia
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `frequency_id`: NAME=frequency_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `activity_id`: NAME=activity_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `participated`: NAME=participated, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false
- `reference_date`: NAME=reference_date, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `frequency_id`: {'NAME': 'frequency_id', 'TYPE': 'foreign', 'FIELDS': 'frequency_id', 'REFTABLE': 'local_assistente_frequency', 'REFFIELDS': 'id'}
- `activity_id`: {'NAME': 'activity_id', 'TYPE': 'foreign', 'FIELDS': 'activity_id', 'REFTABLE': 'course_modules', 'REFFIELDS': 'id'}
Índices:
- `frequency_id-activity_id`: {'NAME': 'frequency_id-activity_id', 'UNIQUE': 'true', 'FIELDS': 'frequency_id,activity_id'}

### `local_assistente_criteria_result` — Resultado dos criterios por estudante
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `student_id`: NAME=student_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `activity_status`: NAME=activity_status, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false
- `forum_status`: NAME=forum_status, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false
- `frequency_status`: NAME=frequency_status, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}
- `student_id`: {'NAME': 'student_id', 'TYPE': 'foreign', 'FIELDS': 'student_id', 'REFTABLE': 'user', 'REFFIELDS': 'id'}
Índices:
- `analysis_id-student_id`: {'NAME': 'analysis_id-student_id', 'UNIQUE': 'true', 'FIELDS': 'analysis_id,student_id'}

### `local_assistente_pedagogical_synthesis` — Sintese pedagogica
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `analysis_id`: NAME=analysis_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `course_id`: NAME=course_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-14.)*
- `section_id`: NAME=section_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-14.)*
- `version`: NAME=version, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-14.)*
- `content`: NAME=content, TYPE=text, NOTNULL=true, SEQUENCE=false
- `input_fingerprint`: NAME=input_fingerprint, TYPE=char, LENGTH=64, NOTNULL=true, SEQUENCE=false *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-19.)*
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `analysis_id`: {'NAME': 'analysis_id', 'TYPE': 'foreign', 'FIELDS': 'analysis_id', 'REFTABLE': 'local_assistente_analysis', 'REFFIELDS': 'id'}
- `course_id`: {'NAME': 'course_id', 'TYPE': 'foreign', 'FIELDS': 'course_id', 'REFTABLE': 'course', 'REFFIELDS': 'id'} *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-14.)*
- `section_id`: {'NAME': 'section_id', 'TYPE': 'foreign', 'FIELDS': 'section_id', 'REFTABLE': 'course_sections', 'REFFIELDS': 'id'} *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-14.)*
Índices:
- `course_id-section_id-version`: {'NAME': 'course_id-section_id-version', 'UNIQUE': 'true', 'FIELDS': 'course_id,section_id,version'} *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-14.)*

### `local_assistente_report` — Relatorio por curso e seu ciclo de atualizacao
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `course_id`: NAME=course_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `next_update_at`: NAME=next_update_at, TYPE=int, LENGTH=10, NOTNULL=false, SEQUENCE=false *(Alterado em 25/10/2026 por DEC-29, que aplica DEC-07 e DEC-23; original: NOTNULL=true.)*
- `final_activity_date`: NAME=final_activity_date, TYPE=int, LENGTH=10, NOTNULL=false, SEQUENCE=false *(Alterado em 25/10/2026 por DEC-29, que aplica DEC-07 e DEC-23; original: NOTNULL=true.)*
- `final_update_at`: NAME=final_update_at, TYPE=int, LENGTH=10, NOTNULL=false, SEQUENCE=false *(Alterado em 25/10/2026 por DEC-29, que aplica DEC-07 e DEC-23; original: NOTNULL=true.)*
- `final_completed_at`: NAME=final_completed_at, TYPE=int, LENGTH=10, NOTNULL=false, SEQUENCE=false *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-23.)*
- `current_version`: NAME=current_version, TYPE=int, LENGTH=10, NOTNULL=false, SEQUENCE=false *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-23.)*
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `course_id`: {'NAME': 'course_id', 'TYPE': 'foreign-unique', 'FIELDS': 'course_id', 'REFTABLE': 'course', 'REFFIELDS': 'id'}
- `current_version`: {'NAME': 'current_version', 'TYPE': 'foreign', 'FIELDS': 'current_version', 'REFTABLE': 'local_assistente_report_version', 'REFFIELDS': 'id'} *(Incluído em 25/10/2026 por DEC-29, que aplica DEC-23.)*
Índices:
- `next_update_at`: {'NAME': 'next_update_at', 'UNIQUE': 'false', 'FIELDS': 'next_update_at'}

### `local_assistente_report_version` — Versoes historicas dos relatorios
Campos:
- `id`: NAME=id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=true
- `report_id`: NAME=report_id, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `version`: NAME=version, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- `snapshot_json`: NAME=snapshot_json, TYPE=text, NOTNULL=true, SEQUENCE=false
- `created_at`: NAME=created_at, TYPE=int, LENGTH=10, NOTNULL=true, SEQUENCE=false
- *(Removido em 25/10/2026 por DEC-29; texto original: "`is_current`: NAME=is_current, TYPE=int, LENGTH=1, NOTNULL=true, SEQUENCE=false")*
Chaves:
- `primary`: {'NAME': 'primary', 'TYPE': 'primary', 'FIELDS': 'id'}
- `report_id`: {'NAME': 'report_id', 'TYPE': 'foreign', 'FIELDS': 'report_id', 'REFTABLE': 'local_assistente_report', 'REFFIELDS': 'id'}
Índices:
- `report_id-version`: {'NAME': 'report_id-version', 'UNIQUE': 'true', 'FIELDS': 'report_id,version'}
- *(Removido em 25/10/2026 por DEC-29; texto original: "`report_id-is_current`: {'NAME': 'report_id-is_current', 'UNIQUE': 'false', 'FIELDS': 'report_id,is_current'}")*
