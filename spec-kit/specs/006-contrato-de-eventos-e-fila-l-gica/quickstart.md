# Quickstart (validação) — spec-006

> Nada foi executado.

| # | Cenário | Resultado esperado | Fonte |
|---|---|---|---|
| Q1 | Criar um questionário já visível | 1 linha em `event`, `status=pending` | §Regras; DEC-31 |
| Q2 | Disparar novamente o evento Moodle do mesmo questionário publicado | Continua 1 evento | §Regras (Q-006-04) |
| Q3 | Postar 2 mensagens num fórum | 2 eventos *(alterado em 25/10/2026 por DEC-03; cenário original: "Postar 2 atualizações relevantes num fórum")* | §Regras; DEC-03 |
| Q4 | Instrumentar o observer | Nenhuma chamada ao adapter LLM | §Regras |
| Q5 | Inspecionar os valores de `status` | Somente os quatro estados | §Regras |
| Q6 | Criar um questionário oculto e torná-lo visível depois | Nenhum evento na criação; 1 evento ao tornar visível | DEC-31 |
| Q7 | Ocultar e mostrar de novo um questionário já publicado; editá-lo depois | Continua 1 evento de publicação | DEC-31 |
| Q8 | Duas edições legítimas do mesmo post | 2 eventos | DEC-08, DEC-21 |
| Q9 | Recepção duplicada da mesma ocorrência | 1 evento | DEC-08 |
| Q10 | Recepção concorrente da mesma publicação | 1 evento | DEC-16, DEC-31 |

*(Q1 ajustado e Q6–Q10 incluídos em 25/10/2026; Q1 original: "Publicar um questionário".)*
