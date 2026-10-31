"""Verify the selected project skills without executing third-party scripts."""
import hashlib
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
EXPECTED = {
    "php-pro", "software-architecture", "database-design",
    "backend-security-coder", "webapp-testing", "systematic-debugging",
    "test-driven-development", "verification-before-completion",
    "ui-ux-pro-max", "accessibility-compliance-accessibility-audit",
    "sql-optimization-patterns", "deployment-procedures",
    "llm-evaluation", "rag-implementation",
    "security-auditor", "error-handling-patterns", "code-review-excellence",
}


def main():
    manifest = json.loads((ROOT / "docs/skills-installation.json").read_text(encoding="utf-8-sig"))
    if set(manifest["skills"]) != EXPECTED:
        raise ValueError("Unexpected skill selection")
    if not re.fullmatch(r"[a-f0-9]{40}", manifest["revision"]):
        raise ValueError("Source revision must be a complete commit SHA")
    revisions = manifest.get("skill_revisions", {})
    if not isinstance(revisions, dict) or set(revisions) - EXPECTED:
        raise ValueError("Invalid per-skill source revisions")
    inventory = []
    for name in sorted(EXPECTED):
        revision = revisions.get(name, manifest["revision"])
        if not isinstance(revision, str) or not re.fullmatch(r"[a-f0-9]{40}", revision):
            raise ValueError(f"Invalid source revision: {name}")
        directory = ROOT / ".agents/skills" / name
        entry = directory / "SKILL.md"
        content = entry.read_text(encoding="utf-8-sig")
        if not content.startswith("---") or not re.search(r"^name:\s*['\"]?" + re.escape(name) + r"['\"]?\s*$", content, re.M):
            raise ValueError(f"Invalid skill metadata: {name}")
        if not re.search(r"^description:\s*\S", content, re.M):
            raise ValueError(f"Missing skill description: {name}")
        files = sorted(path for path in directory.rglob("*")
                       if path.is_file() and "__pycache__" not in path.parts
                       and path.suffix != ".pyc")
        inventory.append({
            "name": name,
            "revision": revision,
            "files": [{"path": path.relative_to(directory).as_posix(),
                       "sha256": hashlib.sha256(path.read_bytes()).hexdigest()}
                      for path in files],
        })
        print(f"OK: {name} ({len(files)} files)")
    output = ROOT / "output/audit/skills-inventory.json"
    output.parent.mkdir(parents=True, exist_ok=True)
    output.write_text(json.dumps({"revision": manifest["revision"], "skills": inventory}, indent=2), encoding="utf-8")
    print(f"OK: {len(EXPECTED)} skill manifests verified; inventory recorded; no third-party scripts executed")


if __name__ == "__main__":
    main()
