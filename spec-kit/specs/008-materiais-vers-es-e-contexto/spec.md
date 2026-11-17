# spec-008 — Materiais, Versões e Contexto

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 1 marcador de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitado por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search4`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Objetivo & Escopo

Processar materiais PDF, texto e YouTube, gerar resumos assíncronos e controlar versões/contexto por seção.

## Dependências de Specs Anteriores

spec-004, spec-007

## Contratos & Interfaces

Tabelas:
`local_assistente_material`
`local_assistente_material_version`
`local_assistente_processing_context`

Contexto: snapshot imutável por processing.

## Regras de Validação & Requisitos Técnicos

- Material só é elegível quando disponível aos estudantes.
- Tipos funcionais: PDF = recurso de arquivo (`mod_resource`) cujo arquivo é PDF; texto = página (`mod_page`); YouTube = URL (`mod_url`) que aponta para o YouTube. Outros tipos de arquivo, rótulos (`label`), Book e Folder ficam fora do escopo funcional. *(Incluído em 25/10/2026 por DEC-33, D2.)*
- Detecção da versão no Moodle, processamento bem-sucedido do conteúdo e elegibilidade para análise são estados distintos. A detecção não torna a versão elegível; versões anteriores e referências históricas são preservadas. *(Incluído em 22/10/2026 por DEC-05.)*
- Material disponível a pelo menos um estudante pode ser detectado, processado e resumido. Para compor o contexto de uma análise, deve estar disponível a todos os estudantes relevantes dessa análise; se estiver disponível a só parte deles, pode ser resumido, mas não compõe esse contexto. *(Incluído em 22/10/2026 por DEC-26.)*
- Estudantes relevantes são aqueles para quem a atividade analisada (questionário, fórum) está disponível; para a síntese pedagógica de seção, todos os estudantes do curso. Disponível a um estudante significa módulo visível e restrições de acesso do Moodle atendidas para esse estudante; a API exata deve ser validada no Moodle 5.2. *(Incluído em 22/10/2026 por DEC-26.)*
- A disponibilidade é avaliada ao montar o snapshot de contexto de cada análise, e o snapshot registra o resultado. *(Incluído em 22/10/2026 por DEC-26.)*
- A inclusão de um novo material elegível inicia o processamento assíncrono correspondente. Alterações exclusivamente na disponibilidade do material atualizam seu estado de disponibilidade, sem gerar nova versão ou processamento de extração e resumo. *(Alterado em 31/10/2026, T058, para resolver a ambiguidade de AT-034; texto original: "Nova disponibilidade gera processamento assíncrono.") *(Leitura de 03/11/2026, sem alterar o enunciado: "elegível", nesta frase, designa o material funcional (PDF, TEXT ou YouTube) cujo conteúdo pode ser processado; não significa elegível para o contexto de uma análise. O processamento e o resumo não dependem de disponibilidade a todos os estudantes (DEC-26, item 1); a elegibilidade para o contexto é regra separada, avaliada ao montar o snapshot (DEC-26, itens 2, 3 e 6).)**
- Qualquer alteração gera nova versão.
- "Alteração" significa: nova versão só quando muda o conteúdo processável (o arquivo, o texto da página ou a URL); mudar só o nome, a visibilidade ou as restrições de acesso não cria versão, porque a disponibilidade é avaliada no snapshot (DEC-26); mover o material de seção atualiza a seção do material sem criar versão, e o contexto usa a seção vigente no momento do snapshot. *(Incluído em 25/10/2026 por DEC-33, D3.)*
- Versão anterior permanece.
- Nova versão vira contexto corrente. *(Leitura de 01/11/2026, sem alterar o enunciado: a nova versão só se torna corrente depois de processada com sucesso (`completed`) e se o seu número for maior que o da corrente, conforme o item "Monotonicidade da versão corrente" abaixo; até lá a anterior continua corrente.)*
- Existe no máximo uma versão corrente por material. A troca da versão corrente é atômica na camada de persistência: a versão anterior só deixa de ser corrente quando a nova estiver registrada corretamente, e uma falha no processamento não apaga nem invalida o histórico anterior. *(Incluído em 22/10/2026 por DEC-12 e DEC-15. Em 25/10/2026, por DEC-29, foi retirada a frase "O mecanismo técnico (DEC-20) depende da alteração de esquema registrada em `closure-register.md`, CL-07.", substituída pelo item seguinte.)*
- A versão corrente é identificada por uma referência única, `current_version`, no registro lógico do material, que aponta para a versão. A referência só é atualizada depois de a nova versão estar persistida com sucesso, sob Lock API por material e em transação DML; se a transição falhar, a versão anterior continua corrente. A versão de material não tem indicador próprio de versão corrente. A elegibilidade para o contexto continua separada da identificação da versão corrente. *(Incluído em 25/10/2026 por DEC-20 e DEC-29.)*
- **Monotonicidade da versão corrente.** `current_version` não retrocede. Uma versão só pode ser selecionada como corrente quando (a) pertence ao material, (b) está com `summary_status = completed` e (c) o seu número de versão (`material_version.version`) é estritamente superior ao número da versão atualmente corrente. A comparação é feita pelo número da versão, nunca por identificador, data de criação ou ordem de conclusão do resumo, e é feita sob o lock do material e dentro da transação da própria troca. Uma seleção de número igual ou inferior, inclusive a da própria versão corrente, é rejeitada sem alterar `current_version`. Enquanto `current_version` for nulo, a primeira seleção de uma versão `completed` do material é permitida. A regra vale para a seleção da versão corrente: as versões históricas continuam consultáveis, e uma versão `completed` rejeitada por essa regra permanece válida como versão histórica. Voltar a um conteúdo anterior cria uma versão nova com número maior (DEC-33, D3). Essa regra não altera a persistência em etapas independentes, cada uma com lock e transação (DEC-61). *(Incluído em 01/11/2026 por DEC-62, com a confirmação expressa do responsável de que a regra deve constar desta spec, de que a primeira seleção com ponteiro nulo é permitida e de que a seleção da própria versão corrente é rejeitada.)*
- Indisponível não fornece contexto futuro.
- Somente materiais da mesma seção podem contextualizar atividade/questionário/fórum/síntese pedagógica. Para a síntese, a seção é a seção-alvo da síntese. *(Alterado em 21/10/2026 por decisão funcional sobre síntese por curso e seção; DEC-13 e decisões relacionadas, conforme aplicável.)*
- Análises históricas não são reprocessadas retroativamente.
- Todos os materiais/versões efetivamente usados devem ser rastreáveis.

## Conformidade Moodle

Arquivos Moodle devem ser acessados pela File API, não por cópia arbitrária de armazenamento. A File API fornece armazenamento/serving e controle de acesso dos arquivos Moodle.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
