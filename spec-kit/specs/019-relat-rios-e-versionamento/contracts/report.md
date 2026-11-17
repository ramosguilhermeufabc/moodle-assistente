# Contract — Relatório (spec-019)

| Operação | Entrada | Saída | Regras |
|---|---|---|---|
| `updateDueReports(now)` | relógio | novas `report_version` para relatórios com `next_update_at ≤ now` | 72 horas desde a última atualização bem-sucedida; até a data final; final D+1, única quando coincide com a periódica; sem data final não agenda; falha não reinicia o intervalo [REQ; DEC-06, DEC-07, DEC-17]. *(Alterado em 25/10/2026; texto original: "3 dias; até a data final; final D+1 [REQ]")* |
| `getCurrent(user, course)` | usuário, curso | versão atual | escopo de acesso [REQ §Conformidade] *(26/10/2026: renderizada do manifesto, DEC-44 D1)* |
| `getHistory(user, course)` | usuário, curso | versões anteriores | consultáveis [REQ §Regras] |

Conteúdo do snapshot: Q-019-01. *(26/10/2026: resolvida por DEC-44 D1; ver abaixo.)*

## Manifesto (`snapshot_json`) *(incluído em 26/10/2026, DEC-44 D1)*

| Campo | Conteúdo |
|---|---|
| `type` | `periodic` ou `final` |
| `generated_at` | momento da geração |
| `blocks[]` | um por bloco: notas, métricas e cobertura de questionários, frequência, fóruns, abaixo dos critérios, resumos de materiais, síntese por seção (o histórico não entra) |
| `blocks[].refs` | `analysis_id` das análises atuais usadas; `material_version` ids; versões de síntese por seção |
| `blocks[].state` | `valid` / `no_data` / `error` do bloco no momento da geração |

Sem cópia de valores de resultados.

## Renderização (`getCurrent`, `getHistory`)

- Lê as análises referenciadas e aplica as regras do plano 018 (truncamento a duas casas, estados, "sem materiais", acesso ao curso como evidência, snapshot do fórum, síntese escapada, "fonte removida", desempate DEC-02).
- Partes individuais: restrição por grupo na leitura (DEC-43 D2).
- **Privacidade:** registros individuais removidos por DEC-42 D1 não reaparecem; a renderização não expõe resultados de estudantes excluídos, mesmo que uma referência do manifesto aponte para a análise de origem.

## Agenda

- **Elegibilidade e data:** atividades da DEC-39 D1; data de entrega ou encerramento própria da atividade (sem `course.enddate`); a mais distante define `final_activity_date`.
- **Dia seguinte (D2):** `final_update_at` = 00:00 do próximo dia-calendário após `final_activity_date`, no fuso do site Moodle. As 72 horas são absolutas.
- **Criação:** `report` criado quando o despachante encontra a primeira atividade elegível datada; primeira versão na mesma execução.
- **Reabertura (D3):** depois de `final_completed_at`, se a última data elegível for movida para o futuro: limpar `final_completed_at`, recalcular `final_activity_date`/`final_update_at` (DEC-18) e retomar as 72 horas; versões anteriores, inclusive a final, preservadas. Sem alteração de datas, nenhuma versão nova.
- **Falhas:** registradas; a atualização (inclusive a final) é retomada na execução seguinte; não conta como sucesso nem reinicia as 72 horas (DEC-17).
- **Execução:** despachante em Scheduled Task; Lock API por relatório; versão gravada e `current_version` trocada numa transação (P-05, DEC-23, DEC-29).
- **Exclusão do curso:** remove `report` e todas as `report_version` (DEC-42 D2).
