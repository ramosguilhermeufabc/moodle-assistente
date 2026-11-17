# Quickstart (validação) — spec-020

> Nada foi executado. *(26/10/2026: Q14 é validação técnica no Moodle 5.2, CL-40.)* *(Declaração da etapa de planejamento, mantida como histórico. Execuções posteriores estão registradas nas tarefas de `tasks.md`; para a sincronização inicial, Q9 a Q13 foram executados em 29/10/2026 e Q10 foi ampliado com o fórum e reexecutado em 31/10/2026 por `assistente/tests/application/sync/initial_sync_service_test.php` (ver T121). Q8 foi ampliado e reexecutado em 02/11/2026 com o fechamento da CL-42 (ver nota na própria linha e `closure-register.md` CL-42). Esta nota não afirma execução de nenhum outro cenário.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Submeter critérios válidos | Persistidos via serviço | §Contratos |
| Q2 | Submeter de novo para alterar | Rejeitado | §Regras *(26/10/2026: para professor; ver Q8 para o administrador)* |
| Q3 | POST de usuário sem permissão | Rejeitado antes de persistir | §Regras |
| Q4 | Inspecionar o código | Nenhum HTML/POST manual onde há moodleform | §Conformidade |
| Q5 | Usuário só com `local/assistente:view` envia o formulário de configuração por chamada direta | Rejeitado no backend | DEC-45 D1 |
| Q6 | `activity_criterion` = 100.5, -1 ou 60.1234 | Rejeitado pela validação de faixa/formato | DEC-40 D1 |
| Q7 | Professor abre os critérios já salvos | Somente leitura | DEC-45 D2 |
| Q8 | Administrador do site **ou manager** corrige `activity_criterion` de 60 para 50 *(02/11/2026: CL-42 fechada — o manager é reconhecido pela capability `local/assistente:correctcriteria`, não só o administrador do site; executado em `course_config_service_test.php::test_manager_corrects_without_reprocessing` e `course_config_form_test.php::test_manager_corrects_the_criteria`, e no cenário Behat "A manager corrects criteria already saved by the teacher")* | Novas `criteria_check` usam 50; as anteriores continuam com 60 registrado no seu contexto; nenhuma reavaliação automática | DEC-45 D2; CL-42 |
| Q9 | Curso novo | `enabled = 0`; nenhum processamento; consultas mostram "assistente não habilitado" | DEC-45 D3 |
| Q10 | Habilitar curso com 2 questionários publicados, 3 materiais e 1 fórum | Eventos por item: 2 análises iniciais, 3 materiais, notas e frequência por estudante e 1 `forum_rebuild_requested` para o fórum (um por módulo `forum`, fóruns ocultos e de avisos incluídos; payload só com `cmid`; sem `forum_post_deleted` nem `forum_discussion_deleted`); depois do processamento pela fila, o snapshot do fórum traz todas as discussões e mensagens existentes, sem respostas privadas *(31/10/2026: fórum incluído, CL-47)* | DEC-45 D3; DEC-38 D2 |
| Q11 | Desabilitar e postar no fórum | Nenhum evento criado; histórico intacto; pendentes não processados | DEC-45 D3 |
| Q12 | Reabilitar o mesmo curso | Nova sincronização; nenhuma segunda análise inicial dos 2 questionários; nenhum material com versão redundante | DEC-45 D3; DEC-31; DEC-33 D3 |
| Q13 | Habilitar sem critérios | Habilitado; sem `criteria_result` | DEC-45 D3; DEC-40 |
| Q14 | Moodle 5.2 (validação técnica, CL-40): (a) formulário com sesskey inválida; (b) definição da capability `manage` e arquétipos na instalação; (c) página de configuração no contexto do curso; (d) disparo da sincronização ao salvar `enabled = 1` | A verificar na validação, sem resultado antecipado: (a) a Form API rejeita o envio; (b) `editingteacher` e `manager` recebem a capability por padrão; (c) a página é acessível só com `manage`; (d) qual mecanismo dispara a sincronização (evento ao salvar ou tarefa) e se gera um evento por item. Registrar comandos, saídas e divergências em CL-40 | CL-40 |

*(Q5–Q14 incluídos em 26/10/2026, depois de DEC-40, DEC-45 e dos tratamentos da revisão.)*

## Filtros do relatório e do histórico (Q-020-02) *(incluído em 29/10/2026)*

Cenários que tornam verificável o detalhamento de Q-020-02 em [contracts/forms.md](contracts/forms.md). Regra de datas: execução da versão (`created_at`), dias do fuso do site, limites inclusivos. A coluna "Evidência" lista os testes que os executam.

| # | Cenário | Resultado esperado | Fonte | Evidência |
|---|---|---|---|---|
| Q15 | Relatório sem filtros | Versão atual | Q-020-02; DEC-44 | `report_view_model_test::test_without_a_filter_the_current_version_is_shown` |
| Q16 | Relatório com uma versão específica, atual ou histórica | A versão pedida, com os valores das suas próprias referências; número inexistente: sem resultado | Q-020-02; DEC-44 D1 | `report_view_model_test::test_a_version_is_selected_by_its_number` |
| Q17 | Intervalo de datas de execução, nos limites e fora deles | Inclusivo no primeiro segundo do primeiro dia e no último segundo do último; fora, nada; um só limite é intervalo aberto | Q-020-02; DEC-44 D2 | `version_filter_test`; `report_view_model_test::test_the_execution_date_interval_is_inclusive_and_combines_with_the_number` |
| Q18 | Versão e intervalo combinados | A versão tem de passar nos dois | Q-020-02 | `report_view_model_test` (mesmo teste); `history_filter_test::test_filter_by_version_and_dates` |
| Q19 | Histórico sem filtros | Todas as versões, mais nova primeiro, a mais nova como vigente | Q-020-02; spec-019 | `history_filter_test::test_without_a_filter_the_history_is_what_it_was` |
| Q20 | Histórico por versão, por datas e pelos dois | Só as versões que passam; a marca de vigente não muda | Q-020-02 | `history_filter_test::test_filter_by_version`, `test_filter_by_dates`, `test_filter_by_version_and_dates`, `test_the_filter_does_not_change_which_version_is_current`; Behat `report_filters` exercita só o filtro por datas do histórico (primeiro cenário); a versão sozinha e a combinação ficam só no PHPUnit |
| Q21 | Último dia antes do primeiro | Erro de validação do formulário no último dia; nenhum resultado e nenhuma consulta com o intervalo inválido | Form API (convenção Moodle) | `query_filter_form_test::test_an_inverted_period_is_refused_by_the_form`; `version_filter_test::test_invalid_input_is_refused`; `history_filter_test::test_an_inverted_interval_is_refused`; Behat `report_filters` |
| Q22 | Filtro sem resultados | "Nenhuma versão atende aos filtros.", sem tabela e sem erro; sem filtro, as mensagens de antes | Q-020-02 | `renderables_test::test_no_results_for_a_filter_has_its_own_message`; `index_page_test::test_the_report_and_the_history_use_the_filter`; Behat `report_filters` |
| Q23 | Grupo no relatório | Só as partes individuais restringem; os blocos agregados não; a página oferece o grupo no relatório e não no histórico | DEC-43 D1 | `report_view_model_test::test_group_and_version_filters_together_follow_the_permissions`; `index_page_test::test_each_tab_offers_its_own_filters`; `report_view_model_test::test_questionnaire_and_material_blocks_are_never_filtered_by_group` (blocos de questionários e de materiais); Behat `group_filters` verifica só as opções do seletor de grupo (no relatório sim, no histórico não); a restrição das linhas é verificada no PHPUnit |
| Q24 | Seção | Só nos dados por seção (consultas agregadas); não no relatório, no histórico nem nas consultas individuais | Q-020-02 | `index_page_test::test_the_section_filter_only_affects_data_of_a_section`, `test_each_tab_offers_its_own_filters` |
| Q25 | Consultar uma versão histórica | Manifesto, ponteiro da versão atual e análises referenciadas intactos; nada recalculado | DEC-44 D1 | `report_view_model_test::test_reading_an_old_version_through_the_filters_changes_nothing` |
| Q26 | Parâmetros de filtro e acesso | Sem `local/assistente:view` ou com um grupo que o usuário não vê: nenhum acesso e nenhum estudante a mais | DEC-30; DEC-43 D2 | `report_view_model_test::test_the_filters_do_not_open_the_report_to_who_has_no_access`, `test_group_and_version_filters_together_follow_the_permissions`; `history_filter_test::test_access_and_the_assistant_off` |

Validação técnica pendente (sem resultado antecipado): grupos separados e API de grupos no Moodle em execução (CL-38) e fuso do site com o dia seguinte (CL-39). Os testes acima rodam no ambiente de testes do PHPUnit e do Behat. *(04/11/2026, revisão dos planos: CL-38 Fechado (VT) em 03/11/2026; CL-39 FECHADA em 28/10/2026.)*
