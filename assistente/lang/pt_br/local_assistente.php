<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Portuguese (Brazil) language strings for local_assistente (DEC-46).
 *
 * @package    local_assistente
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['assistente:correctcriteria'] = 'Corrigir critérios de um curso já salvos por outra pessoa (DEC-45 D2)';
$string['assistente:manage'] = 'Configurar o assistente docente de um curso';
$string['assistente:view'] = 'Ver o assistente docente de um curso';
$string['pluginname'] = 'Assistente docente';
$string['task_process_queue'] = 'Processar a fila do assistente docente';
$string['task_detect_quiz_close'] = 'Detectar questionários encerrados';
$string['task_detect_reference_dates'] = 'Detectar datas de referência vencidas';
$string['task_report_dispatcher'] = 'Gerar as versões vencidas dos relatórios';
$string['task_reconcile_material_versions'] = 'Reconciliar as versões de material deixadas por uma falha (concluída para corrente, pendente abandonada para falha)';

// API de Privacidade (T104; DEC-34 D3, DEC-42, DEC-56).
$string['privacy:metadata:openrouter'] = 'OpenRouter, o serviço externo do modelo de linguagem usado para escrever resumos e sínteses. Por padrão o plugin pede ao fornecedor que não retenha nem colete os dados (política Zero Data Retention: zdr e data_collection deny em toda requisição), mas isso não foi validado como retenção zero. O administrador do site pode desligar esse pedido nas configurações do plugin; desligado, o fornecedor pode reter ou usar os dados enviados. O fornecedor mantém metadados operacionais de cada chamada (modelo, tokens, latência e custo), sem conteúdo, mesmo sob essa política.';
$string['privacy:metadata:openrouter:model'] = 'O identificador do modelo configurado pelo administrador.';
$string['privacy:metadata:openrouter:context'] = 'Textos de materiais, textos de mensagens de fórum escritas pelos participantes (com o papel genérico de quem escreveu, professor ou estudante) e resultados agregados, sem identificador, nome de usuário ou e-mail de estudante. O texto das mensagens pode conter nomes escritos pelos próprios autores, que o plugin não remove (DEC-34, fora do escopo).';
$string['privacy:metadata:openrouter:instruction'] = 'A instrução da tarefa, no idioma do curso.';
$string['privacy:path'] = 'Assistente docente';
$string['privacy:path:performance'] = 'Desempenho';
$string['privacy:path:frequency'] = 'Frequência';
$string['privacy:path:criteria'] = 'Critérios';
$string['privacy:path:snapshots'] = 'Entradas nos retratos';
$string['privacy:metadata:local_assistente_performance'] = 'Nota de um estudante em uma atividade, em percentual, em cada análise de desempenho.';
$string['privacy:metadata:local_assistente_performance:student_id'] = 'O estudante.';
$string['privacy:metadata:local_assistente_performance:activity_id'] = 'O módulo de curso da atividade.';
$string['privacy:metadata:local_assistente_performance:grade'] = 'A nota em percentual.';
$string['privacy:metadata:local_assistente_frequency'] = 'Frequência de um estudante em uma seção do curso, em cada análise de frequência.';
$string['privacy:metadata:local_assistente_frequency:student_id'] = 'O estudante.';
$string['privacy:metadata:local_assistente_frequency:section_id'] = 'A seção.';
$string['privacy:metadata:local_assistente_frequency:total_activities'] = 'Atividades que entraram no cálculo.';
$string['privacy:metadata:local_assistente_frequency:participating_activities'] = 'Atividades em que o estudante participou.';
$string['privacy:metadata:local_assistente_frequency:percentage'] = 'A frequência em percentual.';
$string['privacy:metadata:local_assistente_frequency_activity'] = 'Atividades do cálculo da frequência de um estudante, e se o estudante participou.';
$string['privacy:metadata:local_assistente_frequency_activity:activity_id'] = 'O módulo de curso da atividade.';
$string['privacy:metadata:local_assistente_frequency_activity:participated'] = 'Se o estudante participou.';
$string['privacy:metadata:local_assistente_frequency_activity:reference_date'] = 'A data de referência da atividade.';
$string['privacy:metadata:local_assistente_criteria_result'] = 'Resultado dos critérios do curso para um estudante: abaixo, atende ou sem dados em atividades, fórum e frequência.';
$string['privacy:metadata:local_assistente_criteria_result:student_id'] = 'O estudante.';
$string['privacy:metadata:local_assistente_criteria_result:activity_status'] = 'O resultado das atividades.';
$string['privacy:metadata:local_assistente_criteria_result:forum_status'] = 'O resultado do fórum.';
$string['privacy:metadata:local_assistente_criteria_result:frequency_status'] = 'O resultado da frequência.';
$string['privacy:metadata:local_assistente_processing_context'] = 'Retrato do que um processamento usou. Guarda, para os estudantes, o identificador, se e quando acessaram o curso e valores individuais.';
$string['privacy:metadata:local_assistente_processing_context:context_json'] = 'O retrato, com as entradas dos estudantes.';
$string['privacy:metadata:local_assistente_event'] = 'Evento da fila. O payload dos eventos de um estudante guarda o identificador do estudante.';
$string['privacy:metadata:local_assistente_event:payload'] = 'O payload, com o usuário.';
$string['privacy:metadata:local_assistente_event:response'] = 'O resultado do processamento, que pode citar o estudante numa falha.';
$string['privacy:metadata:local_assistente_processing_attempt'] = 'Tentativa de processar um evento.';
$string['privacy:metadata:local_assistente_processing_attempt:response'] = 'O resultado da tentativa, que pode citar o estudante numa falha.';
$string['privacy:metadata:local_assistente_analysis'] = 'Análise feita pelo plugin. O resumo da análise de um fórum é gerado a partir de mensagens e pode citar um estudante.';
$string['privacy:metadata:local_assistente_analysis:summary'] = 'O resumo, um texto gerado.';
$string['privacy:metadata:local_assistente_forum_difficulty'] = 'Dificuldades dos estudantes encontradas em um fórum, um texto gerado que pode citar um estudante.';
$string['privacy:metadata:local_assistente_forum_difficulty:description'] = 'A descrição da dificuldade.';
$string['privacy:metadata:local_assistente_pedagogical_synthesis'] = 'Síntese pedagógica de uma seção, um texto gerado que pode citar um estudante.';
$string['privacy:metadata:local_assistente_pedagogical_synthesis:content'] = 'O texto da síntese.';
$string['privacy:metadata:local_assistente_material_version'] = 'Versão de um material didático com o resumo gerado.';
$string['privacy:metadata:local_assistente_material_version:summary'] = 'O resumo do material.';
$string['privacy:metadata:local_assistente_report_version'] = 'Versão do relatório do curso, como um retrato.';
$string['privacy:metadata:local_assistente_report_version:snapshot_json'] = 'O retrato do relatório.';
$string['query:no_data'] = 'sem dados';
$string['query:error'] = 'erro técnico';
$string['query:no_materials'] = 'sem materiais';
$string['query:source_removed'] = 'fonte removida';
$string['query:not_enabled'] = 'assistente não habilitado';
$string['query:criteria_below'] = 'abaixo';
$string['query:criteria_meets'] = 'atende';
$string['query:access_yes'] = 'acessou o curso';
$string['query:access_no'] = 'não acessou o curso';
$string['query:access_last'] = 'último acesso: {$a}';
$string['task:retry_student_erasure'] = 'Repetir a exclusão dos dados pessoais de um estudante';
$string['task:redeliver_event'] = 'Entregar de novo um evento que não pôde ser criado porque um lock estava ocupado';
$string['event:student_erasure_deferred'] = 'Exclusão de um estudante adiada';
$string['event:student_erasure_completed'] = 'Exclusão de um estudante concluída';
$string['event:student_erasure_stopped'] = 'Exclusão de um estudante interrompida';
$string['form:enabled'] = 'Habilitar o assistente neste curso';
$string['form:activity_criterion'] = 'Critério de atividades (%)';
$string['form:forum_criterion'] = 'Exigir participação nos fóruns';
$string['form:frequency_criterion'] = 'Critério de frequência (%)';
$string['form:criteria_readonly'] = 'Os critérios estão salvos. Somente um administrador pode corrigi-los, e a correção vale apenas para novas avaliações.';
$string['form:error_percentage'] = 'Informe um número de 0 a 100 com no máximo três casas decimais.';
$string['form:filter_all'] = 'Todos';
$string['form:filter_group'] = 'Grupo';
$string['form:filter_section'] = 'Seção';
$string['form:filter_version'] = 'Versão do relatório';
$string['form:filter_apply'] = 'Aplicar filtros';
$string['form:filter_error'] = 'Escolha uma das opções da lista.';
$string['nav:assistant'] = 'Assistente';
$string['col:name'] = 'Estudante';
$string['col:course_total'] = 'Total do curso';
$string['col:activities'] = 'Atividades';
$string['col:grade'] = 'Nota';
$string['col:status'] = 'Situação';
$string['col:course_access'] = 'Acesso ao curso';
$string['col:sections'] = 'Seções';
$string['col:criteria'] = 'Critérios';
$string['col:activity'] = 'Atividades';
$string['col:forum'] = 'Fórum';
$string['col:frequency'] = 'Frequência';
$string['col:quiz'] = 'Questionário';
$string['col:coverage'] = 'Cobertura';
$string['col:metrics'] = 'Métricas';
$string['col:questions'] = 'Questões';
$string['col:summary'] = 'Resumo';
$string['col:difficulties'] = 'Dificuldades';
$string['col:description'] = 'Descrição';
$string['col:material'] = 'Material';
$string['col:version'] = 'Versão';
$string['col:section'] = 'Seção';
$string['col:content'] = 'Conteúdo';
$string['col:type'] = 'Tipo';
$string['col:created_at'] = 'Criado em';
$string['col:generated_at'] = 'Gerado em';
$string['col:current'] = 'Vigente';
$string['col:source'] = 'Fonte';
$string['col:complexity'] = 'Complexidade';
$string['col:text'] = 'Texto';
$string['tab:grades'] = 'Notas';
$string['tab:frequency'] = 'Frequência';
$string['tab:below_criteria'] = 'Abaixo dos critérios';
$string['tab:quiz_metrics'] = 'Questionários';
$string['tab:forums'] = 'Fóruns';
$string['tab:materials'] = 'Materiais';
$string['tab:synthesis'] = 'Síntese';
$string['tab:report'] = 'Relatório';
$string['tab:history'] = 'Histórico';
$string['tab:config'] = 'Configuração';
$string['report:current_version'] = 'Versão atual: {$a}';
$string['report:history_link'] = 'Ver o histórico do relatório';
$string['report:none'] = 'Nenhum relatório foi gerado ainda.';
$string['block:grades'] = 'Notas';
$string['block:frequency'] = 'Frequência';
$string['block:below_criteria'] = 'Abaixo dos critérios';
$string['block:forums'] = 'Fóruns';
$string['block:materials'] = 'Materiais';
$string['block:synthesis'] = 'Síntese';
$string['block:quiz_metrics'] = 'Questionários';
$string['block:material_summaries'] = 'Materiais';
$string['block:section_synthesis'] = 'Síntese';
$string['form:filter_from'] = 'Executada a partir de';
$string['form:filter_to'] = 'Executada até';
$string['form:filter_period_error'] = 'O último dia não pode ser anterior ao primeiro dia.';
$string['filter:no_results'] = 'Nenhuma versão atende aos filtros.';
$string['form:criteria_immutable'] = 'Os critérios estão salvos e somente um administrador pode corrigi-los. Nada foi alterado.';
$string['settings:ytdlppath'] = 'Caminho do programa yt-dlp';
$string['settings:ytdlppath_desc'] = 'Caminho completo do executável yt-dlp (padrão /usr/local/bin/yt-dlp), usado para ler o título, a descrição e a legenda de vídeos do YouTube. Não usa API nem chave do YouTube. Se ficar vazio, ou apontar para um arquivo inexistente, os materiais do YouTube ficam sem conteúdo. O yt-dlp depende do site do YouTube e pode deixar de funcionar até ser atualizado.';
$string['settings:llmurl'] = 'URL da API da LLM';
$string['settings:llmurl_desc'] = 'Endpoint completo da Chat Completions API, usado exatamente como digitado (para o OpenRouter: https://openrouter.ai/api/v1/chat/completions). Deve usar https e não ter usuário nem senha. Não há valor padrão. Os destinos são verificados pela lista de bloqueio do Moodle (curlsecurityblockedhosts); a lista padrão não cobre toda a faixa 169.254.0.0/16, os IPv6 privados e link-local (fc00::/7, fe80::/10) nem outros endereços de metadados de nuvem (por exemplo 100.100.100.200); se quiser, acrescente-os a essa lista.';
$string['settings:llmurl_invalid'] = 'A URL deve usar https e não pode conter usuário nem senha.';
$string['settings:llmmodel'] = 'Modelo da LLM';
$string['settings:llmmodel_desc'] = 'Identificador do modelo, como o fornecedor o chama. Não há valor padrão.';
$string['settings:llmzdr'] = 'Exigir Zero Data Retention (ZDR)';
$string['settings:llmzdr_desc'] = 'Marcada (o padrão), toda requisição pede ao fornecedor que use só endpoints com Zero Data Retention e sem coleta de dados (provider.zdr = true e provider.data_collection = deny) e nunca é repetida sem esses parâmetros; se nenhum endpoint servir, a chamada falha. Desmarcada, esses parâmetros não são enviados: o fornecedor e o endpoint do modelo podem guardar ou usar o conteúdo do curso que for enviado (textos de materiais, textos de mensagens de fórum escritas pelos participantes e resultados agregados; nunca identificadores, nomes de usuário ou e-mails de estudantes, embora o texto das mensagens possa conter nomes escritos pelos próprios autores). Esta é uma decisão de privacidade do administrador do site.';
$string['settings:llmapikey'] = 'Chave da API da LLM';
$string['settings:llmapikey_desc'] = 'Chave da API do fornecedor. É gravada criptografada e não é mostrada de novo depois de salva; deixe como está para manter a chave salva. Não há valor padrão.';
