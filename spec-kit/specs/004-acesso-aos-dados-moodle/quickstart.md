# Quickstart (validação) — spec-004

> Nada foi executado. Fixtures e runner: Q-023-01/05.

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Ler uma questão em duas versões | Distinguidas por `question_id + question_version`, sem depender de `slot` | §Regras |
| Q2 | Ler a nota de um estudante com configuração de avaliação não padrão | Valor igual ao gradebook do Moodle | §Regras |
| Q3 | Executar todos os readers | Nenhuma linha nova em tabelas `local_assistente_*` | §Regras |
| Q4 | Revisar a documentação de cada reader | API Moodle citada, ou justificativa de DML | §Contratos |
| Q5 | (Planejado, não executado) Critérios 1 e 6: resolver a seção de um `cmid` válido no `course_id` informado | Retorna o `course_sections.id` da seção diretamente associada ao módulo, válida para o curso e igual ao `course_sections.id` usado em `material.section_id` e `frequency.section_id` | §Regras (resolução de seção); Q-004-07 |
| Q6 | (Planejado, não executado) Critério 2: resolver a seção de um `cmid` inexistente ou inválido | Condição explícita de não resolvido, sem valor padrão | §Regras (resolução de seção); Q-004-07 |
| Q7 | (Planejado, não executado) Critério 3: resolver a seção de um `cmid` que pertence a outro curso | Condição explícita de não resolvido, distinta da de `cmid` inexistente | §Regras (resolução de seção); Q-004-07 |
| Q8 | (Planejado, não executado) Critério 4: validar um `section_id` explícito com o `course_id` | Aceito somente se a seção existir naquele curso; seção inexistente ou de outro curso resulta em não resolvido | §Regras (resolução de seção); Q-004-07 |
| Q9 | (Planejado, não executado) Critério 5: resolver a seção de um módulo em subseção (seção delegada) | Retorna a seção delegada; sem resolução, a síntese por seção não é criada e a pendência é registrada pelo mecanismo de processamento | §Regras (resolução de seção); spec-016; Q-004-07 |
| Q10 | (Planejado, não executado) Critério 7: executar a resolução e a validação de seção | Nenhuma linha nova em tabelas `local_assistente_*` (não regressão de Q3); a pendência de processamento é registrada por outro mecanismo | §Regras (resolução de seção); Q3 |
| Q11 | Validação técnica (CL-16): resolver a seção de módulos em seção comum, em subseção delegada, em remoção e de outro curso no Moodle 5.2 | A verificar, sem resultado antecipado: qual API fornece a seção e como cada caso se comporta; registrar comandos, saídas e divergências em CL-16 | Q-004-07 |
| Q12 | Validação técnica (CL-38): curso com matrículas ativas e suspensas, papéis avaliados e não avaliados, grupos separados | A verificar, sem resultado antecipado: como obter os estudantes por DEC-37/47 e os grupos visíveis ao usuário | DEC-37; DEC-43; DEC-47 |
| Q13 | Validação técnica (CL-36): módulo com restrição atendida por um estudante e não por outro; recurso e atividade | A verificar, sem resultado antecipado: disponibilidade por usuário e distinção atividade × recurso | DEC-26; DEC-39 |
| Q14 | Validação técnica (CL-33): questionário com slots fixos e aleatórios e questão editada | A verificar, sem resultado antecipado: leitura das versões vigentes, das candidatas e da entrada do banco de questões | DEC-35; DEC-36 |
| Q15 | Validação técnica (CL-34): item com escala, item com substituição, total do curso com pesos | A verificar, sem resultado antecipado: nota final, mínimo e máximo e total depois da reavaliação | DEC-37 |
| Q16 | Validação técnica (CL-35): fórum com mensagens de estudante e professor | A verificar, sem resultado antecipado: leitura das mensagens e do papel de quem postou | DEC-38 |
| Q17 | Validação técnica (CL-02, CL-41): `mod_resource` com PDF, `mod_page`, `mod_url` do YouTube; curso com idioma forçado | A verificar, sem resultado antecipado: detecção do tipo e leitura do conteúdo pela File API; leitura do idioma forçado e do fuso do site | DEC-33; DEC-44; DEC-46 |

*(Q11–Q17 incluídos em 26/10/2026.)*
