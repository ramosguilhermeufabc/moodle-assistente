# Checklist do pesquisador — usabilidade e interface (spec-021, cenários Q1–Q13)

Derivada do [quickstart da spec-021](../../021-navega-o-renderiza-o-e-ui-js/quickstart.md). Serve para o pesquisador conferir a interface do
plugin no Moodle em execução, além dos testes automatizados. Não envolve professores nem estudantes reais: o pesquisador usa usuários e
cursos de teste criados para a verificação. O resultado de cada execução vai em [usability-records.md](usability-records.md).

## Preparação

- [x] Moodle 5.2 do ambiente da spec-001, plugin instalado na versão do `version.php` e `upgrade.php` executado (o gancho de navegação só
  vale depois disso).
- [x] Curso de teste com: um professor com edição, um professor sem edição com a capability `local/assistente:view` concedida só nesse
  curso, um estudante sem a capability, estudantes com nomes que empatam em valor, dois grupos e modo de grupos separados.
- [x] Assistente habilitado no curso para os cenários que mostram dados; um segundo curso novo, sem configuração.

*(04/11/2026: preparação feita e conferida; ver `spec-kit/validation/t130/` e a tabela "Como executar" ao final.)*

## Cenários

| # | O que o pesquisador faz | O que deve ver | Feito |
|---|---|---|---|
| Q1 | Entra como estudante e abre a URL `/local/assistente/index.php?id=<curso>` | Acesso negado; nenhuma entrada "Assistente" no menu do curso | [x] |
| Q2 | Entra como professor e abre a aba "Relatório" | Versão atual do relatório e o link "Ver o histórico do relatório"; o link leva à lista de versões | [x] |
| Q3 | Abre uma consulta cujo resultado veio de uma fonte removida | A indicação "fonte removida" visível junto ao resultado | [x] |
| Q4 | Percorre todas as abas | Nenhum campo de chat ou de pergunta livre | [x] |
| Q5 | Entra como professor sem edição, com `view` | Vê as abas de consulta e o relatório; não vê "Configuração"; a URL de configuração é negada | [x] |
| Q6 | Abre cada aba num curso sem configuração ou desabilitado | "assistente não habilitado" em todas, sem tabela | [x] |
| Q7 | Procura um valor `no_data`, um `error` e um zero válido | "sem dados", "erro técnico" e "0,00%", respectivamente | [x] |
| Q8 | Procura uma cobertura 0.0 de questionário sem materiais | A indicação "sem materiais" | [x] |
| Q9 | Abre uma síntese cujo texto contém `<script>` e `<b>` | O texto aparece literal, sem executar nem formatar | [x] |
| Q10 | Entra como professor sem edição em grupos separados e abre uma consulta individual | O filtro oferece só os grupos dele; a lista só tem os estudantes desses grupos | [x] |
| Q11 | Curso com idioma forçado `pt_br` e usuário com idioma `en` | Interface em inglês; resumos e síntese em português | [x] |
| Q12 | Muda o idioma forçado do curso depois de uma síntese | A síntese existente continua no idioma original | [x] |
| Q13 | Abre uma lista com valores iguais | Ordem alfabética (A–Z) pelo nome | [x] |

## Acessibilidade (cenário Q14, validação técnica CL-41)

- [x] Navegar pelas abas e pelas tabelas só com o teclado. *(04/11/2026: aprovado pelo responsável, ver `usability-records.md`.)*
- [ ] Conferir com leitor de tela o título da tabela, os cabeçalhos de coluna e o cabeçalho de linha.
  *(04/11/2026: **NÃO EXECUTADO; limitação aceita pelo responsável** (CL-41, 03/11/2026): a conferência com leitor de tela não será feita; o item não é aprovado nem executado, e o texto acima fica como histórico.)*

## Regras do registro

- Registrar só o que foi observado, com a data e a versão do Moodle; não antecipar resultado.
- Um cenário que não pôde ser executado fica como "não executado", com o motivo.
- Dado pessoal real não entra no registro.

## Como executar (acrescentado em 04/11/2026, T140; o texto acima fica preservado)

Os dados vêm de `spec-kit/validation/t130/prepare_course.php` (curso **T130A**, id 49, com dados; curso **T130B**, id 50, sem configuração). As
URLs usam o domínio encaminhado da porta 8080 do Codespace: `<domínio>/local/assistente/index.php?id=49&tab=<aba>`. Abas: `grades`, `frequency`,
`quiz_metrics`, `synthesis`, `report`, `history`. Textos na interface em inglês (idioma dos usuários de teste); entre parênteses, o texto em português.

**Preparação do ambiente**
1. Instalar o pacote de idioma `pt_br` do Moodle (Administração > Idioma > Pacotes de idioma) **antes** de rodar o script: o idioma forçado do curso só aceita idiomas instalados; sem o pacote o script para com mensagem.
2. Rodar `php spec-kit/validation/t130/prepare_course.php` como `www-data` (`su -s /bin/bash www-data -c "cd /var/www/html && env -u ASSISTENTE_LLM_API_KEY php /workspaces/moodle-assistente/spec-kit/validation/t130/prepare_course.php"`). As senhas aparecem só na saída. Para obter senhas novas depois, rodar o mesmo comando com `--reset-passwords`; `--delete` remove tudo para recriar.
3. Usuários: `t130_prof_edicao` (professor com edição, cursos A e B), `t130_prof_leitura` (professor sem edição, `view` só no curso A, Grupo A), `t130_aluno_ana`, `_bia`, `_carlos`, `_davi`, `_eva` (estudantes sem a capability; Ana e Carlos no Grupo A; Bia, Davi e Eva no Grupo B).

| # | Entre como | Faça | Deve ver |
|---|---|---|---|
| Q1 | `t130_aluno_ana` | Abra `index.php?id=49` | Acesso negado; no menu do curso não há "Assistente" |
| Q2 | `t130_prof_edicao` | Abra `index.php?id=49&tab=report`; clique em "See the history of the report" (Ver o histórico do relatório) | Versão atual (2) do relatório e o link; o link leva à lista com as versões 1 e 2 |
| Q3 | `t130_prof_edicao` | Abra `tab=grades` e olhe a linha "Carlos Teste" | Uma atividade sem nome com "source removed" (fonte removida) ao lado da nota |
| Q4 | `t130_prof_edicao` | Percorra todas as abas do curso 49 | Nenhum campo de chat nem de pergunta livre |
| Q5 | `t130_prof_leitura` | Abra o curso 49 e depois `configure.php?id=49` | Abas de consulta e relatório; sem a aba "Configuração"; a URL de configuração é negada |
| Q6 | `t130_prof_edicao` | Abra cada aba do curso 50 (`index.php?id=50&tab=grades`, `frequency`, `quiz_metrics`, `synthesis`, `report`, `history`) | "assistant not enabled" (assistente não habilitado) em todas, sem tabela |
| Q7 | `t130_prof_edicao` | Em `tab=grades` veja "Davi Teste" e "Ana Teste"; em `tab=report` veja o bloco de questionários da versão atual | "no data" (sem dados) para Davi; "0,00%" para Ana; "technical error" (erro técnico) no bloco de questionários do relatório |
| Q8 | `t130_prof_edicao` | Abra `tab=quiz_metrics`; olhe "Questionário sem materiais" | Cobertura "no materials" (sem materiais) |
| Q9 | `t130_prof_edicao` | Abra `tab=synthesis` | O texto `<script>alert(1)</script> e <b>negrito</b>` aparece literal, sem executar nem formatar |
| Q10 | `t130_prof_leitura` | Abra `tab=grades` e abra o filtro de grupo | O filtro oferece só "Grupo A"; a lista tem só "Ana Teste" e "Carlos Teste" |
| Q11 | `t130_prof_edicao` (idioma `en`) | Abra `tab=synthesis` do curso 49 (idioma forçado `pt_br`) | Interface em inglês; a síntese "Síntese gerada em português do Brasil." em português |
| Q12 | `t130_prof_edicao` | Em Administração do curso > Editar configurações > Aparência, mude "Forçar idioma" para English; abra `tab=synthesis` | A síntese existente continua em português. Depois devolva o idioma a Português (pt_br) |
| Q13 | `t130_prof_edicao` | Abra `tab=grades` | "Ana Teste" e "Bia Teste" empatam em 0,00% e aparecem em ordem alfabética (Ana antes de Bia) |
| Teclado (Q14) | qualquer usuário com acesso | Em `tab=grades` e `tab=frequency`, percorra abas e tabelas só com Tab, Shift+Tab e Enter | Foco visível e todas as abas e linhas alcançáveis |
