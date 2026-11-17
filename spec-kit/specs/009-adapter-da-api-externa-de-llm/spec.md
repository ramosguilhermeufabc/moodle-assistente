# spec-009 — Adapter da API Externa de LLM

## Objetivo & Escopo

Isolar a integração com a API externa de LLM das regras de negócio.

## Dependências de Specs Anteriores

spec-007, spec-008

## Contratos & Interfaces

Porta lógica:
`evaluate(context, instruction) -> structured_result`

Adapter externo:
- autenticação;
- request;
- response;
- timeout/error;
- validação de resposta.

## Regras de Validação & Requisitos Técnicos

Usar LLM para:
- resumo de materiais;
- cobertura de questionário;
- complexidade de questão;
- resumo/dificuldades de fórum;
- síntese pedagógica.

Não usar LLM para:
- contagens;
- médias;
- desvio-padrão;
- mínimo/máximo;
- frequência;
- aplicação matemática de critérios.

Credenciais ficam fora do Git; no banco, somente criptografadas pela API nativa do Moodle (`admin_setting_encryptedpassword`). *(03/11/2026, DEC-69 e Constituição 1.2.0; texto anterior: "Credenciais ficam fora do banco funcional e do Git.")*

## Conformidade Moodle

Não há fornecedor/modelo definido no SDD. O plano deve escolher e documentar o adapter sem alterar o contrato funcional.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
