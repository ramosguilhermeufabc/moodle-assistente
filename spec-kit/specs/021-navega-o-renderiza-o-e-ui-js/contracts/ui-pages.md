# Contract — Páginas de UI (spec-021)

| Página (lógica) | Dados | Regras |
|---|---|---|
| Entrada do assistente no curso | lista de funções de consulta | context/capability [REQ]; navegação Q-021-01 *(26/10/2026: navegação secundária do curso, só com `view`; mecanismo em CL-41)* |
| Página por função de consulta (8, spec-018) | view model da consulta | funções separadas [REQ] *(26/10/2026: uma aba por consulta)* |
| Relatório | versão atual + histórico (spec-019) | mostra a atual; permite o histórico [REQ] *(26/10/2026: renderizado do manifesto, DEC-44)* |
| Formulários (spec-020) | moodleform | — *(26/10/2026: aba "Configuração" só com `manage`, DEC-45)* |

Proibido: chat em linguagem natural [REQ]. JS: só quando necessário (Q-021-03). *(26/10/2026: Q-021-03 resolvida — nenhum JS próprio obrigatório.)*

## Regras de apresentação *(incluído em 26/10/2026; DEC-24 a DEC-46)*

| Regra | Onde | Fonte |
|---|---|---|
| Verificar context e capability no backend antes de renderizar | todas as páginas | DEC-30; DEC-45 |
| Curso sem configuração ou desabilitado: só "assistente não habilitado" | todas as abas de consulta e relatório | DEC-45 D3 |
| `valid` mostra o valor; `no_data` mostra "sem dados"; `error` mostra "erro técnico"; nunca zero | todos os valores | DEC-24 |
| Percentuais truncados a duas casas | todos os valores percentuais | DEC-36 D2; DEC-37 D2; DEC-39 |
| "Sem materiais" junto à cobertura definida sem material elegível | questionários | DEC-35 D2 |
| Acesso ao curso como evidência complementar | frequência | DEC-39 D3 |
| Estados `1`/`0`/`2` como "atende"/"abaixo"/"sem dados" | abaixo dos critérios | DEC-40 |
| Snapshot atual do fórum; anteriores no histórico | fóruns | DEC-38 |
| Texto gerado pela LLM (resumos, dificuldades, síntese) **sempre escapado**, nunca HTML | toda página com texto da LLM | DEC-41 |
| "Fonte removida" quando o registro Moodle referenciado não existe mais | todos os resultados | DEC-42 D3 |
| Listas individuais restritas aos grupos visíveis; filtro de grupo opcional | notas, frequência, abaixo dos critérios, partes individuais do relatório | DEC-43 |
| Versão atual por padrão; versões e execuções anteriores no histórico | relatório e histórico | DEC-44; plano 018 |
| Critérios em somente leitura para professor | configuração | DEC-45 D2 |
| Desempate por nome A–Z | todas as listas | DEC-02 |
| Interface em `lang/en` e `lang/pt_br`, no idioma do usuário; conteúdo gerado no idioma registrado na análise, sem tradução | todas as páginas | DEC-46 |
