# Data Model — spec-014

```text
analysis
  └─1:N─ frequency (student_id, section_id, total_activities, participating_activities, percentage number(10,3))
            └─1:N─ frequency_activity (activity_id=cmid, participated int(1), reference_date)
```
- `percentage = participating_activities / total_activities * 100` [REQ] (arredondamento: Q-014-05). *(25/10/2026: truncado a três casas, DEC-39.)*
- `participated = 1` se houve ≥ 1 participação [REQ] (semântica `int(1)`: Q-002-04). *(25/10/2026: participação conforme DEC-39 D1; 0 caso contrário.)*
- `reference_date` = data final/devida [REQ]. *(25/10/2026: senão `course.enddate`; sem nenhuma, sem linha, DEC-39 D2.)*
- *(25/10/2026)* `analysis.analysis_type = frequency`; imutável; atual = última bem-sucedida.
- *(25/10/2026)* `frequency_activity` só para as atividades no denominador do estudante (data vencida ou participação antecipada); `total_activities` = número dessas linhas.
- *(25/10/2026)* `total_activities = 0` ⇒ `percentage = 0`, estado `no_data` derivado (sem campo novo).
- *(25/10/2026)* Acesso ao curso (sim/não, data do último acesso) no snapshot de contexto da análise, por estudante (DEC-39 D3).
