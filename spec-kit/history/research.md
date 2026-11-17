# Research Notes

> **`HISTÓRICO`** · movido em 08/11/2026 de `spec-kit/research.md` para `spec-kit/history/research.md`, sem alteração de conteúdo. Índice do histórico: [`README.md`](README.md).

> **Nota de 08/11/2026 (Fase 3 da reorganização documental).** Foram removidos 10 marcadores de citação que a ferramenta geradora do texto original deixou neste arquivo, delimitados por caracteres invisíveis de uso privado (U+E200 a U+E202). Eles não apareciam no Markdown renderizado, quebravam a busca textual e apontavam fontes não resolvíveis: `turn0search13` e equivalentes não identificam nenhum documento consultável. **Nenhuma palavra, regra, valor ou condição do texto foi alterada**; apenas os marcadores e o espaço que os precedia saíram. Texto removido, preservado conforme a constituição §11: `citeturn0search0`, `citeturn0search3`, `citeturn0search13`, `citeturn0search6`, `citeturn0search4`, `citeturn0search14`, `citeturn0search9`, `citeturn1search6turn1search7`, `citeturn1search0turn1search9`, `citeturn1search2`. As afirmações que os traziam ficam sem citação de fonte e dependem de verificação na documentação oficial do Moodle 5.2.

## Moodle 5.2

- Local plugins are the generic local-customisation plugin type; `lib.php` should be kept small and internal logic should be in autoloaded classes.
- DML is the official database access API and abstracts supported RDBMSs.
- XMLDB/DDL provides the portable database structure definition used during install/upgrade.
- Tasks API supports Scheduled Tasks and Adhoc Tasks for background work.
- File API is the official interface for Moodle-managed files.
- Hooks API uses PSR-14 and replaces some older callback patterns.
- Moodle 5.2 requires PHP 8.3 minimum and supports PHP 8.4.

## GitHub Codespaces

- `devcontainer.json` is the primary dev-container configuration and can reference a Dockerfile or use an image; it may configure forwarding, features, and lifecycle commands.
- `postCreateCommand` runs after container creation.
- Development environment secrets are the appropriate mechanism for sensitive values that should not be committed.
