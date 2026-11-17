# Quickstart (validação) — spec-021

> Nada foi executado. *(26/10/2026: Q14 é validação técnica no Moodle 5.2, CL-41.)*

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Usuário sem capability abre a URL da página | Acesso negado | §Regras |
| Q2 | Abrir o relatório | Versão atual exibida; link para o histórico | §Regras |
| Q3 | Resultado com fonte removida | Indicação visível (Q-021-04) | §Regras *(26/10/2026: "fonte removida", DEC-42 D3)* |
| Q4 | Inspecionar a UI | Nenhum campo de chat | §Regras |
| Q5 | Usuário com `view` sem `manage` | Vê as abas de consulta e relatório; não vê "Configuração" | DEC-45 D1 |
| Q6 | Curso desabilitado | Todas as abas mostram "assistente não habilitado" | DEC-45 D3 |
| Q7 | Valor `no_data`, valor `error` e valor 0 válido | "sem dados", "erro técnico" e "0,00%" | DEC-24 |
| Q8 | Cobertura 0.0 sem materiais | Indicação "sem materiais" | DEC-35 D2 |
| Q9 | Síntese gerada contendo `<script>` ou `<b>` | Exibida como texto, escapada | DEC-41 |
| Q10 | Professor em grupos separados | Listas individuais só com seus grupos | DEC-43 |
| Q11 | Curso com idioma forçado `pt_br`; usuário com idioma `en` | Interface em inglês; resumos e síntese em português | DEC-46 |
| Q12 | Idioma do curso muda depois de uma síntese | A síntese existente continua no idioma original; a próxima é gerada no novo idioma | DEC-46 |
| Q13 | Empate de valores numa lista | Ordem por nome A–Z | DEC-02 |
| Q14 | Moodle 5.2 (validação técnica, CL-41): (a) registrar a entrada na navegação do curso; (b) renderizar um renderable por template Mustache com conteúdo contendo HTML; (c) curso com idioma forçado e site com idioma padrão diferente; (d) navegar pelas abas e tabelas só com teclado e leitor de tela | A verificar na validação, sem resultado antecipado: (a) qual mecanismo (callback ou hook) adiciona a entrada e se ela respeita a capability; (b) se `{{ }}` escapa o conteúdo e onde `{{{ }}}` precisa ser evitado; (c) como obter o idioma forçado do curso e o padrão do site; (d) se abas e tabelas nativas atendem à navegação acessível. Registrar comandos, saídas e divergências em CL-41 | CL-41 |

*(Q5–Q14 incluídos em 26/10/2026, depois das decisões DEC-24 a DEC-46.)*
