# Constituição do Projeto — Assistente Inteligente para Moodle

**Versão:** 1.2.0 (emenda da 1.1.0) *(texto anterior: "1.1.0 (emenda da 1.0.0)")*
**Status:** Aprovada pelo responsável em 25/10/2026; emenda 1.2.0 aprovada pelo responsável em 03/11/2026

## 1. Fidelidade aos requisitos e às Specs

As Specs aprovadas são a fonte normativa dos requisitos funcionais e comportamentais do sistema.

- Nenhum requisito funcional pode ser inventado, alterado ou removido por inferência do agente.
- Ambiguidades, contradições e lacunas devem ser registradas explicitamente e submetidas à decisão humana quando necessário.
- As decisões aprovadas devem ser propagadas para todos os artefatos afetados, preservando sua rastreabilidade.
- O código deve implementar os comportamentos especificados, sem introduzir funcionalidades não autorizadas.
- Hierarquia de fontes: a Etapa 3 da metodologia é a fonte primária dos requisitos funcionais; as Specs são a fonte normativa dos requisitos detalhados; as decisões aprovadas complementam essas fontes sem contradizê-las; `db/install.xml` é a fonte primária do esquema, que as Specs de dados descrevem sem substituir.

## 2. Rastreabilidade integral

Os 66 requisitos atômicos identificados no levantamento devem permanecer individualmente rastreáveis ao longo do ciclo de desenvolvimento.

- Cada atomicidade deve possuir um identificador estável (AT-001 a AT-066).
- Cada requisito deve estar relacionado à sua fonte, à Spec correspondente, às tarefas pertinentes e às evidências de validação.
- Essa relação é mantida na matriz `requirements-traceability.md` e nos `traceability.md` de cada Spec. As Specs não precisam citar os identificadores AT. As Tasks citam a Spec e a seção de origem; a correspondência entre AT e tarefa é registrada na matriz depois da geração das Tasks.
- Requisitos ausentes, parcialmente cobertos, conflitantes ou não verificáveis devem ser identificados explicitamente.
- A contagem de requisitos ou a existência de uma matriz não constitui, isoladamente, prova de cobertura ou implementação.

## 3. Desenvolvimento orientado por especificações

O projeto deve seguir o fluxo do Spec Kit, utilizando Claude como agente de execução.

A sequência obrigatória é:

1. Create Spec Kit Environment.
2. Configure Spec Kit Environment.
3. Plan, utilizando as Specs como fonte de requisitos.
4. Tasks, utilizando as Specs e os artefatos aprovados do Plan.

Cada fase deve respeitar seu escopo e seus critérios de conclusão. A fase seguinte somente pode começar após a revisão e a autorização humana da fase anterior.

A geração de tarefas não autoriza, por si só, a implementação do código.

## 4. Separação entre requisitos, decisões e implementação

Specs, decisões técnicas, planos, tarefas, código e testes são artefatos distintos, com responsabilidades próprias.

- O Plan deve organizar a solução sem redefinir os requisitos.
- As Tasks devem decompor o trabalho planejado em unidades executáveis e verificáveis.
- Decisões técnicas não podem contrariar requisitos funcionais aprovados.
- Mudanças de escopo devem ser documentadas e aprovadas antes de serem propagadas.
- Os artefatos derivados devem manter referências claras às fontes que os justificam.

## 5. Compatibilidade com o Moodle

A solução deve respeitar as APIs, os eventos, os mecanismos de persistência, as permissões e as convenções oficiais da versão-alvo do Moodle.

- Recursos da plataforma devem ser verificados antes de serem utilizados.
- A existência de uma API, um evento ou um identificador não pode ser presumida sem evidência.
- Alterações de banco de dados devem respeitar os mecanismos de instalação e atualização do Moodle.
- O desenvolvimento deve permanecer integrado à arquitetura de plugin definida pelo projeto.
- A versão-alvo é o Moodle 5.2 (`MOODLE_502_STABLE`); a plataforma e a arquitetura do plugin estão no §12.

## 6. Escopo dos materiais didáticos

Os tipos de materiais funcionais suportados são PDF, TEXT e YouTube.

Book e Folder estão fora do escopo funcional aprovado.

O processamento, a disponibilidade e a elegibilidade de um material para uso como contexto de análise são conceitos distintos e devem respeitar as regras aprovadas nas Specs e nas decisões do projeto.

A disponibilidade de um material para apenas parte dos estudantes não pode ser tratada automaticamente como elegibilidade para uma análise que abrange toda a turma.

O histórico das versões processadas deve ser preservado conforme os requisitos aprovados.

## 7. Integridade dos dados e histórico

O sistema deve preservar a consistência, a rastreabilidade e a integridade dos dados produzidos.

- Versões históricas que devam ser imutáveis não podem ser sobrescritas silenciosamente.
- Transições de versão devem preservar um estado consistente em caso de falha.
- Resultados devem manter vínculo com o processamento, a versão do material e o contexto que os originaram, conforme especificado.
- Falhas não podem apagar indevidamente o último resultado válido.

## 8. Idempotência e concorrência

Operações assíncronas e reprocessamentos devem respeitar as regras de idempotência, concorrência e recuperação definidas nas Specs e decisões aprovadas.

- Operações lentas devem ser executadas em segundo plano. A fila lógica própria e o histórico de processamento são obrigatórios para as operações que as Specs encaminham por ela.
- Eventos duplicados não podem gerar processamentos ou resultados duplicados indevidos.
- Ocorrências legítimas distintas não podem ser descartadas como duplicatas sem justificativa.
- Operações concorrentes devem preservar a consistência do estado.
- Tentativas, falhas e recuperações devem respeitar os limites e as regras definidos para cada processamento.

## 9. Testabilidade e qualidade

Todo comportamento especificado deve possuir critérios verificáveis de aceitação.

- Cada tarefa de implementação deve identificar como seu resultado será validado.
- Testes devem cobrir os comportamentos esperados, os limites, as falhas e os cenários de concorrência relevantes.
- Testes não podem ser alterados apenas para fazer uma implementação incorreta passar.
- Uma funcionalidade não deve ser declarada concluída sem evidências de validação compatíveis com seus critérios de aceitação.
- Limitações de cobertura ou de ambiente devem ser registradas explicitamente.
- Cada entrega de código deve possuir testes unitários conforme a metodologia, e as integrações relevantes devem ser testadas.

## 10. Transparência e evidências

O agente deve distinguir claramente entre trabalho planejado, artefatos gerados, verificações executadas e resultados efetivamente comprovados.

- Não declarar como executada uma ação que apenas foi planejada.
- Não declarar como aprovado um artefato que não foi revisado.
- Não declarar cobertura integral sem verificar a rastreabilidade dos requisitos.
- Não declarar testes aprovados sem evidências de sua execução.
- Informar pendências, limitações e divergências ao final de cada fase.

## 11. Governança e controle de mudanças

O responsável pelo projeto mantém a autoridade final sobre requisitos, decisões, escopo e autorização das fases.

Mudanças devem ser documentadas, justificadas e propagadas aos artefatos afetados. Nenhum agente pode ampliar unilateralmente o escopo aprovado.

- **Decisões:** decisões do responsável são registradas como DEC-nn no registro de decisões, com contexto, regra, artefatos afetados e critério de validação. A propagação a um artefato preserva o texto substituído e identifica a data e a DEC que o alterou.
- **Emendas:** qualquer alteração desta Constituição é proposta com um relatório de impacto (versão, princípios alterados, seções incluídas ou removidas, artefatos a atualizar) e só vigora após aprovação explícita do responsável.
- **Versionamento:** versão semântica. MAJOR para remoção ou redefinição incompatível de princípio ou de regra de governança; MINOR para princípio ou seção nova, ou orientação ampliada de forma material; PATCH para esclarecimentos sem efeito semântico.
- **Conformidade:** todo `plan.md` avalia esta Constituição no Constitution Check antes e depois do design. Os itens não conformes ficam registrados como BLOQUEADO, com justificativa. Cada emenda aprovada exige reavaliar os planos afetados.
- **Ratificação:** a Constituição só é ratificada por aprovação explícita do responsável, registrada com data. Enquanto o status for "Proposta para aprovação", nenhuma fase pode declará-la aprovada.
- **Constituição original:** esta Constituição substitui a do pacote modular (`spec_kit_modular_assistente_moodle/.specify/memory/constitution.md`), que fica preservada apenas como fonte histórica. Os princípios dela foram incorporados aqui. *(04/11/2026: o pacote modular foi removido do repositório; esta Constituição o substitui e é a referência vigente do projeto.)*

## 12. Plataforma e arquitetura do plugin

O plugin é `local_assistente`, componente `local/assistente`, destinado ao Moodle 5.2 (`MOODLE_502_STABLE`).

- O plugin deve usar as APIs oficiais do Moodle, XMLDB para o esquema e DML para o acesso a dados.
- O ambiente de desenvolvimento segue a spec-001 e as decisões aprovadas.
- As responsabilidades de apresentação, aplicação, domínio e infraestrutura devem ficar separadas.
- `lib.php` deve permanecer mínimo, restrito aos callbacks necessários; a lógica deve ficar em classes autocarregáveis.

## 13. Uso de LLM externo

Operações que exigem interpretação linguística usam uma API externa de LLM. Cálculos determinísticos não usam LLM.

- As credenciais da API externa ficam fora do Git; no banco, somente criptografadas pela API nativa do Moodle (`admin_setting_encryptedpassword`). *(03/11/2026, emenda 1.2.0, DEC-69; texto anterior: "As credenciais da API externa ficam fora do Git e do banco funcional.")*

## 14. Controle de acesso

O professor acessa apenas os próprios cursos; o administrador acessa todos os cursos.

- O controle de acesso deve ser aplicado também no backend, e não apenas na interface.

## 15. Interfaces externas

Na versão inicial do plugin, o assistente não expõe API REST nem web services a sistemas externos.

- Chamadas internas da própria interface do plugin não constituem API pública.

## Critério geral de conformidade

Uma entrega só pode ser considerada conforme quando respeitar esta Constituição, os requisitos e as decisões aprovados, os critérios de aceitação aplicáveis e as evidências exigidas para a fase correspondente.

Em caso de conflito, o agente deve interromper a progressão afetada, registrar o conflito e solicitar a decisão necessária, em vez de escolher silenciosamente uma interpretação.

## Histórico de emendas

- **1.1.0 (25/10/2026):** emenda da 1.0.0, aprovada pelo responsável em 25/10/2026.
- **1.2.0 (03/11/2026), MINOR, aprovada pelo responsável em 03/11/2026 (DEC-69):** §13, regra das credenciais da API externa. Texto anterior: "As credenciais da API externa ficam fora do Git e do banco funcional." Texto novo: "As credenciais da API externa ficam fora do Git; no banco, somente criptografadas pela API nativa do Moodle (`admin_setting_encryptedpassword`)." Artefatos a atualizar: spec-009 (linha 40), planos, quickstarts, contratos e tarefas das specs 001 e 009 (T134, T138), rastreabilidade e CL-32, com nota da DEC-69.

**Versão**: 1.2.0 | **Ratificada**: 2026-10-25 | **Última emenda**: 2026-11-03 *(texto anterior: "**Versão**: 1.1.0 | **Ratificada**: 2026-10-25 | **Última emenda**: 2026-10-25")*
