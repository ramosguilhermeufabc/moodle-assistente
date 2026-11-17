# Registro da verificação do pesquisador (spec-021, Q1–Q13)

Registro das execuções da [checklist do pesquisador](usability-researcher.md). Cada execução ocupa uma seção. Não há resultado
antecipado: enquanto nenhuma execução foi feita, a lista abaixo fica vazia. Sem professores nem estudantes reais.

## Modelo de uma execução

```text
### Execução AAAA-MM-DD
- Pesquisador:
- Moodle (versão e build):
- Plugin (versão do version.php, upgrade.php executado: sim/não):
- Curso e usuários de teste usados:
- Navegador e tecnologia assistiva (Q14):

| Cenário | Resultado (aprovado / falhou / não executado) | Observação |
|---|---|---|
| Q1 |  |  |
| Q2 |  |  |
| Q3 |  |  |
| Q4 |  |  |
| Q5 |  |  |
| Q6 |  |  |
| Q7 |  |  |
| Q8 |  |  |
| Q9 |  |  |
| Q10 |  |  |
| Q11 |  |  |
| Q12 |  |  |
| Q13 |  |  |
| Q14 (teclado e leitor de tela) |  |  |
```

## Execuções

Nenhuma execução registrada (T130 pendente).

*(04/11/2026: texto acima preservado como histórico; a execução abaixo foi preparada e aguarda o responsável.)*

### Execução de 04/11/2026
- Pesquisador: Guilherme
- Moodle (versão e build): 5.2.4+ (Build 20261030)
- Plugin (versão do version.php, upgrade.php executado: sim/não): versão 2026110202, upgrade.php executado: sim (versão em disco = versão no banco)
- Curso e usuários de teste usados: curso T130A (com dados) e T130B (sem configuração); usuários `t130_*` criados por `spec-kit/validation/t130/prepare_course.php`
- Navegador e tecnologia assistiva (Q14): Chrome, sem tecnologia assistiva (leitor de tela dispensado, CL-41)

- Q1: Resultado: Aprovado (responsável, 04/11/2026)
- Q2: Resultado: Aprovado (responsável, 04/11/2026)
- Q3: Resultado: Aprovado (responsável, 04/11/2026)
- Q4: Resultado: Aprovado (responsável, 04/11/2026)
- Q5: Resultado: Aprovado (responsável, 04/11/2026)
- Q6: Resultado: Aprovado (responsável, 04/11/2026)
- Q7: Resultado: Aprovado (responsável, 04/11/2026)
- Q8: Resultado: Aprovado (responsável, 04/11/2026)
- Q9: Resultado: Aprovado (responsável, 04/11/2026)
- Q10: Resultado: Aprovado (responsável, 04/11/2026)
- Q11: Resultado: Aprovado (responsável, 04/11/2026)
- Q12: Resultado: Aprovado (responsável, 04/11/2026)
- Q13: Resultado: Aprovado (responsável, 04/11/2026)
- Teclado (Q14): Resultado: Aprovado (responsável, 04/11/2026)
- Leitor de tela (Q14): limitação aceita pelo responsável (CL-41, 03/11/2026); não será executado

Observações do responsável (04/11/2026):
1. Os passos da checklist estavam pouco claros: ela dizia o que verificar, mas não com qual usuário, em qual URL ou em qual aba. A checklist ganhou a tabela "Como executar" (T140).
2. Com a interface em inglês apareceu o erro "Invalid parameter value detected ... the value is "pt_br", the server was expecting "lang" type" (lib/ajax/service-nologin.php). Causa: o `prepare_course.php` forçou o idioma `pt_br` no curso T130A sem o pacote de idioma `pt_br` do Moodle instalado (instalado depois, em 04/11/2026 às 11:17); `PARAM_LANG` só aceita idiomas instalados. Não é defeito do plugin. O script passou a parar com mensagem quando o pacote não está instalado.

*(Ajuste final do responsável, 04/11/2026: os campos "Pesquisador" e "Plugin" foram preenchidos; o texto anterior dizia que não haviam sido informados.)*
