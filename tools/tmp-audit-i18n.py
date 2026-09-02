#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import re
import polib

DOMAIN = "core-blueprint-seo"
ROOT = Path(__file__).resolve().parents[1]
LANG_DIR = ROOT / "languages"
LOCALES = ("nl_NL", "de_DE", "fr_FR", "es_ES", "it_IT", "pt_PT")
FUNCS = ("__", "_e", "esc_html__", "esc_html_e", "esc_attr__", "esc_attr_e")

pattern = re.compile(
    r"(?:" + "|".join(re.escape(name) for name in FUNCS) + r")\s*\(\s*'((?:\\.|[^'])*)'\s*,\s*'" + re.escape(DOMAIN) + r"'\s*\)",
    re.DOTALL,
)


def decode_php_single_quoted(value: str) -> str:
    return value.replace("\\'", "'").replace("\\\\", "\\")


source_ids: set[str] = set()
for php_file in sorted(ROOT.rglob("*.php")):
    if ".git" in php_file.parts:
        continue
    text = php_file.read_text(encoding="utf-8")
    source_ids.update(decode_php_single_quoted(match.group(1)) for match in pattern.finditer(text))

print(f"SOURCE_MSGIDS={len(source_ids)}")

pot_path = LANG_DIR / f"{DOMAIN}.pot"
pot = polib.pofile(str(pot_path))
pot_ids = {entry.msgid for entry in pot if entry.msgid}
missing_pot = sorted(source_ids - pot_ids)
obsolete_pot = sorted(pot_ids - source_ids)
print(f"POT_MSGIDS={len(pot_ids)} MISSING={len(missing_pot)} OBSOLETE={len(obsolete_pot)}")
for msgid in missing_pot:
    print(f"POT_MISSING: {msgid}")
for msgid in obsolete_pot:
    print(f"POT_OBSOLETE: {msgid}")

for locale in LOCALES:
    po_path = LANG_DIR / f"{DOMAIN}-{locale}.po"
    po = polib.pofile(str(po_path))
    by_id = {entry.msgid: entry for entry in po if entry.msgid}
    missing = sorted(source_ids - set(by_id))
    untranslated = sorted(msgid for msgid in source_ids if msgid in by_id and not by_id[msgid].msgstr.strip())
    obsolete = sorted(set(by_id) - source_ids)
    print(f"{locale}: ENTRIES={len(by_id)} MISSING={len(missing)} UNTRANSLATED={len(untranslated)} OBSOLETE={len(obsolete)}")
    for msgid in missing:
        print(f"{locale}_MISSING: {msgid}")
    for msgid in untranslated:
        print(f"{locale}_UNTRANSLATED: {msgid}")

print("AUDIT_COMPLETE")
