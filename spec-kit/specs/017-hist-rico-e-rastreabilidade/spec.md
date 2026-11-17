# spec-017 — Histórico, Versionamento e Rastreabilidade

## Objetivo & Escopo

Permitir reconstruir origem, contexto, processamento e versão de qualquer informação derivada.

## Dependências de Specs Anteriores

spec-002, spec-006, spec-007, spec-008, spec-010–016

## Contratos & Interfaces

Cadeia:
Moodle source -> assistant event -> processing -> attempt -> context snapshot -> analysis -> result -> report.

O contexto persistido deve registrar as versões efetivamente usadas.

## Regras de Validação & Requisitos Técnicos

Toda atualização preserva estado anterior.
Remoção de fonte não apaga histórico.
Múltiplos materiais usados = todos associados.
Detecção, processamento e elegibilidade de cada versão de material são estados distintos e preservados no histórico. O snapshot de contexto registra, para cada material, a versão usada e o resultado da avaliação de disponibilidade feita ao montá-lo. *(Incluído em 22/10/2026 por DEC-05 e DEC-26.)*
Timestamp e versão devem permanecer disponíveis.
Versões anteriores de materiais e de sínteses são preservadas. Cada síntese mantém vínculo com o processamento e o contexto que a originaram. Uma falha na transição de versão de material preserva a versão corrente anterior e o histórico. *(Incluído em 22/10/2026 por DEC-12 e DEC-15.)*
A síntese pedagógica tem histórico próprio por combinação de curso e seção; uma seção não sobrescreve nem altera a síntese de outra. Sem mudança no conjunto de informações usado, não se cria versão redundante. *(Incluído em 22/10/2026 por DEC-04 e DEC-13.)*
O histórico da síntese é sequencial e imutável por curso e seção (`version`, único com curso e seção). A versão corrente de material é uma referência única (`current_version`) no registro lógico do material, sem segunda fonte de verdade; as versões históricas são preservadas. *(Incluído em 25/10/2026 por DEC-14, DEC-20 e DEC-29.)*
Mudanças só de nome, visibilidade, restrições ou seção de um material não criam versão. O snapshot registra a seção vigente e o resultado da disponibilidade no momento da análise, preservando o histórico. *(Incluído em 25/10/2026 por DEC-33, D3.)*
Uma ocorrência lógica corresponde a um único processamento no histórico. Repetições, entregas concorrentes e retentativas não criam processamentos nem resultados duplicados; tentativas e falhas ficam registradas no mesmo processamento. *(Incluído em 22/10/2026 por DEC-08, DEC-16 e DEC-22.)*

## Conformidade Moodle

Implementar com as tabelas próprias do XMLDB e transações DML quando necessárias.

## Ambiente & Contexto de Execução

GitHub Codespaces + Moodle 5.2

## Fora do Escopo

Nenhum código de implementação é produzido nesta fase. O agente deve transformar esta especificação em plano e tarefas posteriormente.
