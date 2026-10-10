#!/usr/bin/env bash
# Reutiliza los assets de Copilot (.github) y AGENTS.md en Claude Code sin duplicar contenido.
# Idempotente: se puede ejecutar las veces que haga falta (tras cambiar .github/agents).
set -euo pipefail

root="$(git rev-parse --show-toplevel)"
cd "$root"

mkdir -p .claude/commands .claude/agents

# Skills: mismo formato SKILL.md -> symlink a la carpeta completa
ln -sfn ../.github/skills .claude/skills

# Prompts -> slash commands (/full-review, /fix-review)
for f in .github/prompts/*.prompt.md; do
    [ -e "$f" ] || continue
    n="$(basename "$f" .prompt.md)"
    ln -sfn "../../$f" ".claude/commands/$n.md"
done

# Agentes: Claude Code exige name en kebab-case; los de Copilot llevan "Laravel Reviewer", "Devil's Advocate"...
# Se generan (no se versionan) cambiando solo la línea name del frontmatter.
rm -f .claude/agents/*.md
for f in .github/agents/*.agent.md; do
    [ -e "$f" ] || continue
    n="$(basename "$f" .agent.md)"
    sed "0,/^name:.*/s//name: $n/" "$f" > ".claude/agents/$n.md"
done

# .gitignore: solo lo generado / personal
for line in ".claude/agents/" ".claude/settings.local.json"; do
    grep -qxF "$line" .gitignore 2>/dev/null || echo "$line" >> .gitignore
done

echo "Claude Code assets enlazados:"
echo "  skills   -> $(ls .github/skills | wc -l)"
echo "  commands -> $(ls .claude/commands | wc -l)"
echo "  agents   -> $(ls .claude/agents | wc -l)"