#!/usr/bin/env python3
from __future__ import annotations

from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
import re
import sys
import polib

DOMAIN = "core-blueprint-seo"
VERSION = "1.0.0-rc4"
ROOT = Path(__file__).resolve().parents[1]
LANG_DIR = ROOT / "languages"
LOCALES = ("nl_NL", "de_DE", "fr_FR", "es_ES", "it_IT", "pt_PT")
FUNCS = ("__", "_e", "esc_html__", "esc_html_e", "esc_attr__", "esc_attr_e")

CALL_RE = re.compile(
    r"(?:" + "|".join(re.escape(name) for name in FUNCS) + r")\s*\(\s*'((?:\\.|[^'])*)'\s*,\s*'" + re.escape(DOMAIN) + r"'\s*\)",
    re.DOTALL,
)
PLACEHOLDER_RE = re.compile(r"%(?:\d+\$)?[sd]")

NEW = {
    "nl_NL": {
        "%d automatic": "%d automatisch",
        "%d review": "%d ter beoordeling",
        "A compatible Core Blueprint Base installation with API 1.0 or newer is required. Activate or update Core Blueprint Base first.": "Een compatibele installatie van Core Blueprint Base met API 1.0 of nieuwer is vereist. Activeer of werk Core Blueprint Base eerst bij.",
        "Automatic": "Automatisch",
        "Automatic mappings are preselected only when the semantic match is strong and unambiguous. Review candidates remain disabled until you explicitly map them. If multiple source fields target the same Core Blueprint field on one object, the first non-empty mapped source wins and later conflicts are skipped.": "Automatische mappings worden alleen vooraf geselecteerd wanneer de semantische overeenkomst sterk en eenduidig is. Kandidaten ter beoordeling blijven uitgeschakeld totdat je ze expliciet toewijst. Als meerdere bronvelden op één object naar hetzelfde Core Blueprint-veld verwijzen, wint de eerste niet-lege toegewezen bron en worden latere conflicten overgeslagen.",
        "Core Blueprint discovers existing WordPress metadata by field semantics rather than vendor-specific import profiles. Stored field names are shown as-is so you can review the source before importing.": "Core Blueprint ontdekt bestaande WordPress-metadata op basis van veldsemantiek in plaats van leverancierspecifieke importprofielen. Opgeslagen veldnamen worden ongewijzigd weergegeven, zodat je de bron vóór het importeren kunt controleren.",
        "Do not import": "Niet importeren",
        "Import": "Importeren",
        "Import existing SEO metadata": "Bestaande SEO-metadata importeren",
        "Import selected metadata": "Geselecteerde metadata importeren",
        "Map to": "Toewijzen aan",
        "No compatible SEO metadata candidates were discovered in WordPress post or term metadata.": "Er zijn geen compatibele kandidaten voor SEO-metadata gevonden in WordPress-bericht- of termmetadata.",
        "No valid SEO metadata mappings were selected, so nothing was imported.": "Er zijn geen geldige mappings voor SEO-metadata geselecteerd, dus er is niets geïmporteerd.",
        "Posts/CPTs: %1$d · Terms: %2$d": "Berichten/CPT's: %1$d · Termen: %2$d",
        "Review": "Beoordelen",
        "Robots: noarchive": "Robots: noarchive",
        "Robots: nofollow": "Robots: nofollow",
        "Robots: noimageindex": "Robots: noimageindex",
        "Robots: noindex": "Robots: noindex",
        "Robots: nosnippet": "Robots: nosnippet",
        "SEO metadata import completed. %1$d content objects changed, %2$d fields were imported through %3$d mappings, and %4$d existing Core Blueprint values were preserved.": "Import van SEO-metadata voltooid. %1$d contentobjecten gewijzigd, %2$d velden geïmporteerd via %3$d mappings en %4$d bestaande Core Blueprint-waarden behouden.",
        "SEO metadata imported": "SEO-metadata geïmporteerd",
        "Social image attachment ID": "Bijlage-ID van social-afbeelding",
        "Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win. Global plugin settings and templates are intentionally not guessed because WordPress has no canonical SEO option schema.": "Bronmetadata wordt nooit gewijzigd of verwijderd en bestaande Core Blueprint SEO-waarden hebben altijd voorrang. Globale plugininstellingen en templates worden bewust niet geraden, omdat WordPress geen canoniek SEO-optieschema heeft.",
        "Suggested: %s. Review before importing.": "Suggestie: %s. Controleer vóór het importeren.",
    },
    "de_DE": {
        "%d automatic": "%d automatisch",
        "%d review": "%d zur Prüfung",
        "A compatible Core Blueprint Base installation with API 1.0 or newer is required. Activate or update Core Blueprint Base first.": "Eine kompatible Core Blueprint Base-Installation mit API 1.0 oder neuer ist erforderlich. Aktiviere oder aktualisiere zuerst Core Blueprint Base.",
        "Automatic": "Automatisch",
        "Automatic mappings are preselected only when the semantic match is strong and unambiguous. Review candidates remain disabled until you explicitly map them. If multiple source fields target the same Core Blueprint field on one object, the first non-empty mapped source wins and later conflicts are skipped.": "Automatische Zuordnungen werden nur vorausgewählt, wenn die semantische Übereinstimmung eindeutig und zuverlässig ist. Kandidaten zur Prüfung bleiben deaktiviert, bis du sie ausdrücklich zuordnest. Wenn mehrere Quellfelder eines Objekts demselben Core Blueprint-Feld zugeordnet sind, gewinnt die erste nicht leere zugeordnete Quelle; spätere Konflikte werden übersprungen.",
        "Core Blueprint discovers existing WordPress metadata by field semantics rather than vendor-specific import profiles. Stored field names are shown as-is so you can review the source before importing.": "Core Blueprint erkennt vorhandene WordPress-Metadaten anhand der Feldsemantik statt über anbieterspezifische Importprofile. Gespeicherte Feldnamen werden unverändert angezeigt, damit du die Quelle vor dem Import prüfen kannst.",
        "Do not import": "Nicht importieren",
        "Import": "Import",
        "Import existing SEO metadata": "Vorhandene SEO-Metadaten importieren",
        "Import selected metadata": "Ausgewählte Metadaten importieren",
        "Map to": "Zuordnen zu",
        "No compatible SEO metadata candidates were discovered in WordPress post or term metadata.": "In den WordPress-Beitrags- oder Term-Metadaten wurden keine kompatiblen SEO-Metadatenkandidaten gefunden.",
        "No valid SEO metadata mappings were selected, so nothing was imported.": "Es wurden keine gültigen Zuordnungen für SEO-Metadaten ausgewählt, daher wurde nichts importiert.",
        "Posts/CPTs: %1$d · Terms: %2$d": "Beiträge/CPTs: %1$d · Begriffe: %2$d",
        "Review": "Prüfen",
        "Robots: noarchive": "Robots: noarchive",
        "Robots: nofollow": "Robots: nofollow",
        "Robots: noimageindex": "Robots: noimageindex",
        "Robots: noindex": "Robots: noindex",
        "Robots: nosnippet": "Robots: nosnippet",
        "SEO metadata import completed. %1$d content objects changed, %2$d fields were imported through %3$d mappings, and %4$d existing Core Blueprint values were preserved.": "Import der SEO-Metadaten abgeschlossen. %1$d Inhaltsobjekte geändert, %2$d Felder über %3$d Zuordnungen importiert und %4$d vorhandene Core Blueprint-Werte beibehalten.",
        "SEO metadata imported": "SEO-Metadaten importiert",
        "Social image attachment ID": "Anhang-ID des Social-Media-Bildes",
        "Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win. Global plugin settings and templates are intentionally not guessed because WordPress has no canonical SEO option schema.": "Quellmetadaten werden niemals geändert oder gelöscht, und vorhandene Core Blueprint SEO-Werte haben immer Vorrang. Globale Plugin-Einstellungen und Vorlagen werden bewusst nicht erraten, da WordPress kein kanonisches SEO-Optionsschema besitzt.",
        "Suggested: %s. Review before importing.": "Vorschlag: %s. Vor dem Import prüfen.",
    },
    "fr_FR": {
        "%d automatic": "%d auto",
        "%d review": "%d à vérifier",
        "A compatible Core Blueprint Base installation with API 1.0 or newer is required. Activate or update Core Blueprint Base first.": "Une installation compatible de Core Blueprint Base avec l’API 1.0 ou une version ultérieure est requise. Activez ou mettez d’abord à jour Core Blueprint Base.",
        "Automatic": "Automatique",
        "Automatic mappings are preselected only when the semantic match is strong and unambiguous. Review candidates remain disabled until you explicitly map them. If multiple source fields target the same Core Blueprint field on one object, the first non-empty mapped source wins and later conflicts are skipped.": "Les correspondances automatiques ne sont présélectionnées que lorsque la correspondance sémantique est forte et sans ambiguïté. Les candidats à vérifier restent désactivés jusqu’à ce que vous les associiez explicitement. Si plusieurs champs source ciblent le même champ Core Blueprint sur un objet, la première source associée non vide est utilisée et les conflits suivants sont ignorés.",
        "Core Blueprint discovers existing WordPress metadata by field semantics rather than vendor-specific import profiles. Stored field names are shown as-is so you can review the source before importing.": "Core Blueprint détecte les métadonnées WordPress existantes selon la sémantique des champs plutôt qu’au moyen de profils d’import propres à un fournisseur. Les noms de champs enregistrés sont affichés tels quels afin que vous puissiez vérifier la source avant l’import.",
        "Do not import": "Ne pas importer",
        "Import": "Importation",
        "Import existing SEO metadata": "Importer les métadonnées SEO existantes",
        "Import selected metadata": "Importer les métadonnées sélectionnées",
        "Map to": "Associer à",
        "No compatible SEO metadata candidates were discovered in WordPress post or term metadata.": "Aucun candidat de métadonnées SEO compatible n’a été détecté dans les métadonnées des publications ou des termes WordPress.",
        "No valid SEO metadata mappings were selected, so nothing was imported.": "Aucune correspondance de métadonnées SEO valide n’a été sélectionnée ; rien n’a donc été importé.",
        "Posts/CPTs: %1$d · Terms: %2$d": "Publications/CPT : %1$d · Termes : %2$d",
        "Review": "À vérifier",
        "Robots: noarchive": "Robots : noarchive",
        "Robots: nofollow": "Robots : nofollow",
        "Robots: noimageindex": "Robots : noimageindex",
        "Robots: noindex": "Robots : noindex",
        "Robots: nosnippet": "Robots : nosnippet",
        "SEO metadata import completed. %1$d content objects changed, %2$d fields were imported through %3$d mappings, and %4$d existing Core Blueprint values were preserved.": "Import des métadonnées SEO terminé. %1$d objets de contenu modifiés, %2$d champs importés via %3$d correspondances et %4$d valeurs Core Blueprint existantes conservées.",
        "SEO metadata imported": "Métadonnées SEO importées",
        "Social image attachment ID": "ID de pièce jointe de l’image sociale",
        "Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win. Global plugin settings and templates are intentionally not guessed because WordPress has no canonical SEO option schema.": "Les métadonnées source ne sont jamais modifiées ni supprimées, et les valeurs SEO Core Blueprint existantes sont toujours prioritaires. Les réglages globaux des extensions et les modèles ne sont volontairement pas déduits, car WordPress ne définit aucun schéma canonique d’options SEO.",
        "Suggested: %s. Review before importing.": "Suggestion : %s. Vérifiez avant l’import.",
    },
    "es_ES": {
        "%d automatic": "%d automáticos",
        "%d review": "%d para revisar",
        "A compatible Core Blueprint Base installation with API 1.0 or newer is required. Activate or update Core Blueprint Base first.": "Se requiere una instalación compatible de Core Blueprint Base con la API 1.0 o posterior. Activa o actualiza primero Core Blueprint Base.",
        "Automatic": "Automático",
        "Automatic mappings are preselected only when the semantic match is strong and unambiguous. Review candidates remain disabled until you explicitly map them. If multiple source fields target the same Core Blueprint field on one object, the first non-empty mapped source wins and later conflicts are skipped.": "Las asignaciones automáticas solo se preseleccionan cuando la coincidencia semántica es sólida y no presenta ambigüedades. Los candidatos para revisar permanecen desactivados hasta que los asignes explícitamente. Si varios campos de origen apuntan al mismo campo de Core Blueprint en un objeto, se utiliza el primer origen asignado que no esté vacío y se omiten los conflictos posteriores.",
        "Core Blueprint discovers existing WordPress metadata by field semantics rather than vendor-specific import profiles. Stored field names are shown as-is so you can review the source before importing.": "Core Blueprint detecta metadatos de WordPress existentes por la semántica de los campos en lugar de usar perfiles de importación específicos de proveedores. Los nombres de campo almacenados se muestran tal cual para que puedas revisar el origen antes de importar.",
        "Do not import": "No importar",
        "Import": "Importar",
        "Import existing SEO metadata": "Importar metadatos SEO existentes",
        "Import selected metadata": "Importar metadatos seleccionados",
        "Map to": "Asignar a",
        "No compatible SEO metadata candidates were discovered in WordPress post or term metadata.": "No se han detectado candidatos de metadatos SEO compatibles en los metadatos de entradas o términos de WordPress.",
        "No valid SEO metadata mappings were selected, so nothing was imported.": "No se seleccionaron asignaciones de metadatos SEO válidas, por lo que no se importó nada.",
        "Posts/CPTs: %1$d · Terms: %2$d": "Entradas/CPT: %1$d · Términos: %2$d",
        "Review": "Revisar",
        "Robots: noarchive": "Robots: noarchive",
        "Robots: nofollow": "Robots: nofollow",
        "Robots: noimageindex": "Robots: noimageindex",
        "Robots: noindex": "Robots: noindex",
        "Robots: nosnippet": "Robots: nosnippet",
        "SEO metadata import completed. %1$d content objects changed, %2$d fields were imported through %3$d mappings, and %4$d existing Core Blueprint values were preserved.": "Importación de metadatos SEO completada. Se modificaron %1$d objetos de contenido, se importaron %2$d campos mediante %3$d asignaciones y se conservaron %4$d valores existentes de Core Blueprint.",
        "SEO metadata imported": "Metadatos SEO importados",
        "Social image attachment ID": "ID de adjunto de la imagen social",
        "Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win. Global plugin settings and templates are intentionally not guessed because WordPress has no canonical SEO option schema.": "Los metadatos de origen nunca se modifican ni se eliminan, y los valores SEO existentes de Core Blueprint siempre tienen prioridad. Los ajustes globales de plugins y las plantillas no se deducen deliberadamente porque WordPress no dispone de un esquema canónico de opciones SEO.",
        "Suggested: %s. Review before importing.": "Sugerencia: %s. Revisa antes de importar.",
    },
    "it_IT": {
        "%d automatic": "%d automatici",
        "%d review": "%d da rivedere",
        "A compatible Core Blueprint Base installation with API 1.0 or newer is required. Activate or update Core Blueprint Base first.": "È richiesta un’installazione compatibile di Core Blueprint Base con API 1.0 o successiva. Attiva o aggiorna prima Core Blueprint Base.",
        "Automatic": "Automatico",
        "Automatic mappings are preselected only when the semantic match is strong and unambiguous. Review candidates remain disabled until you explicitly map them. If multiple source fields target the same Core Blueprint field on one object, the first non-empty mapped source wins and later conflicts are skipped.": "Le associazioni automatiche vengono preselezionate solo quando la corrispondenza semantica è forte e non ambigua. I candidati da rivedere restano disattivati finché non li associ esplicitamente. Se più campi sorgente puntano allo stesso campo Core Blueprint su un oggetto, viene usata la prima sorgente associata non vuota e i conflitti successivi vengono ignorati.",
        "Core Blueprint discovers existing WordPress metadata by field semantics rather than vendor-specific import profiles. Stored field names are shown as-is so you can review the source before importing.": "Core Blueprint rileva i metadati WordPress esistenti in base alla semantica dei campi invece di usare profili di importazione specifici del fornitore. I nomi dei campi memorizzati vengono mostrati così come sono, in modo da poter verificare la sorgente prima dell’importazione.",
        "Do not import": "Non importare",
        "Import": "Importa",
        "Import existing SEO metadata": "Importa i metadati SEO esistenti",
        "Import selected metadata": "Importa i metadati selezionati",
        "Map to": "Associa a",
        "No compatible SEO metadata candidates were discovered in WordPress post or term metadata.": "Non sono stati rilevati candidati di metadati SEO compatibili nei metadati di articoli o termini WordPress.",
        "No valid SEO metadata mappings were selected, so nothing was imported.": "Non sono state selezionate associazioni valide per i metadati SEO, quindi non è stato importato nulla.",
        "Posts/CPTs: %1$d · Terms: %2$d": "Articoli/CPT: %1$d · Termini: %2$d",
        "Review": "Rivedi",
        "Robots: noarchive": "Robots: noarchive",
        "Robots: nofollow": "Robots: nofollow",
        "Robots: noimageindex": "Robots: noimageindex",
        "Robots: noindex": "Robots: noindex",
        "Robots: nosnippet": "Robots: nosnippet",
        "SEO metadata import completed. %1$d content objects changed, %2$d fields were imported through %3$d mappings, and %4$d existing Core Blueprint values were preserved.": "Importazione dei metadati SEO completata. %1$d oggetti di contenuto modificati, %2$d campi importati tramite %3$d associazioni e %4$d valori Core Blueprint esistenti mantenuti.",
        "SEO metadata imported": "Metadati SEO importati",
        "Social image attachment ID": "ID allegato dell’immagine social",
        "Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win. Global plugin settings and templates are intentionally not guessed because WordPress has no canonical SEO option schema.": "I metadati sorgente non vengono mai modificati o eliminati e i valori SEO Core Blueprint esistenti hanno sempre la precedenza. Le impostazioni globali dei plugin e i modelli non vengono deliberatamente dedotti perché WordPress non dispone di uno schema canonico per le opzioni SEO.",
        "Suggested: %s. Review before importing.": "Suggerimento: %s. Verifica prima dell’importazione.",
    },
    "pt_PT": {
        "%d automatic": "%d automáticos",
        "%d review": "%d para rever",
        "A compatible Core Blueprint Base installation with API 1.0 or newer is required. Activate or update Core Blueprint Base first.": "É necessária uma instalação compatível do Core Blueprint Base com a API 1.0 ou posterior. Ative ou atualize primeiro o Core Blueprint Base.",
        "Automatic": "Automático",
        "Automatic mappings are preselected only when the semantic match is strong and unambiguous. Review candidates remain disabled until you explicitly map them. If multiple source fields target the same Core Blueprint field on one object, the first non-empty mapped source wins and later conflicts are skipped.": "Os mapeamentos automáticos só são pré-selecionados quando a correspondência semântica é forte e inequívoca. Os candidatos para revisão permanecem desativados até que os mapeie explicitamente. Se vários campos de origem apontarem para o mesmo campo do Core Blueprint num objeto, é usada a primeira origem mapeada não vazia e os conflitos seguintes são ignorados.",
        "Core Blueprint discovers existing WordPress metadata by field semantics rather than vendor-specific import profiles. Stored field names are shown as-is so you can review the source before importing.": "O Core Blueprint deteta metadados WordPress existentes pela semântica dos campos, em vez de utilizar perfis de importação específicos de fornecedores. Os nomes dos campos armazenados são apresentados tal como estão para que possa rever a origem antes de importar.",
        "Do not import": "Não importar",
        "Import": "Importar",
        "Import existing SEO metadata": "Importar metadados SEO existentes",
        "Import selected metadata": "Importar metadados selecionados",
        "Map to": "Mapear para",
        "No compatible SEO metadata candidates were discovered in WordPress post or term metadata.": "Não foram detetados candidatos de metadados SEO compatíveis nos metadados de artigos ou termos do WordPress.",
        "No valid SEO metadata mappings were selected, so nothing was imported.": "Não foram selecionados mapeamentos válidos de metadados SEO, pelo que nada foi importado.",
        "Posts/CPTs: %1$d · Terms: %2$d": "Artigos/CPTs: %1$d · Termos: %2$d",
        "Review": "Rever",
        "Robots: noarchive": "Robots: noarchive",
        "Robots: nofollow": "Robots: nofollow",
        "Robots: noimageindex": "Robots: noimageindex",
        "Robots: noindex": "Robots: noindex",
        "Robots: nosnippet": "Robots: nosnippet",
        "SEO metadata import completed. %1$d content objects changed, %2$d fields were imported through %3$d mappings, and %4$d existing Core Blueprint values were preserved.": "Importação de metadados SEO concluída. %1$d objetos de conteúdo alterados, %2$d campos importados através de %3$d mapeamentos e %4$d valores existentes do Core Blueprint preservados.",
        "SEO metadata imported": "Metadados SEO importados",
        "Social image attachment ID": "ID do anexo da imagem social",
        "Source metadata is never changed or deleted, and existing Core Blueprint SEO values always win. Global plugin settings and templates are intentionally not guessed because WordPress has no canonical SEO option schema.": "Os metadados de origem nunca são alterados nem eliminados e os valores SEO existentes do Core Blueprint têm sempre prioridade. As definições globais dos plugins e os modelos não são deliberadamente inferidos porque o WordPress não possui um esquema canónico de opções SEO.",
        "Suggested: %s. Review before importing.": "Sugestão: %s. Reveja antes de importar.",
    },
}


def decode_php_single_quoted(value: str) -> str:
    return value.replace("\\'", "'").replace("\\\\", "\\")


def placeholders(text: str) -> list[str]:
    return sorted(PLACEHOLDER_RE.findall(text))


def source_catalog() -> dict[str, list[tuple[str, int]]]:
    found: dict[str, list[tuple[str, int]]] = defaultdict(list)
    for php_file in sorted(ROOT.rglob("*.php")):
        if ".git" in php_file.parts:
            continue
        text = php_file.read_text(encoding="utf-8")
        for match in CALL_RE.finditer(text):
            msgid = decode_php_single_quoted(match.group(1))
            line = text.count("\n", 0, match.start()) + 1
            rel = php_file.relative_to(ROOT).as_posix()
            found[msgid].append((rel, line))
    return dict(found)


def metadata(language: str | None = None) -> dict[str, str]:
    now = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M+0000")
    data = {
        "Project-Id-Version": f"Core Blueprint SEO {VERSION}",
        "Report-Msgid-Bugs-To": "",
        "POT-Creation-Date": now,
        "PO-Revision-Date": now,
        "Last-Translator": "Core Blueprint",
        "Language-Team": language or "",
        "MIME-Version": "1.0",
        "Content-Type": "text/plain; charset=UTF-8",
        "Content-Transfer-Encoding": "8bit",
        "Generated-By": "Core Blueprint rc4 i18n refresh",
    }
    if language:
        data["Language"] = language
        data["Plural-Forms"] = "nplurals=2; plural=(n > 1);" if language == "fr_FR" else "nplurals=2; plural=(n != 1);"
    return data


catalog = source_catalog()
source_ids = set(catalog)
print(f"Refreshing {len(source_ids)} source strings")

# Regenerate POT from current source only, so removed vendor-specific UI strings disappear.
pot = polib.POFile()
pot.metadata = metadata()
for msgid in sorted(source_ids, key=str.casefold):
    entry = polib.POEntry(msgid=msgid, msgstr="", occurrences=catalog[msgid])
    if placeholders(msgid):
        entry.flags.append("php-format")
    pot.append(entry)
pot.save(str(LANG_DIR / f"{DOMAIN}.pot"))

errors: list[str] = []
for locale in LOCALES:
    path = LANG_DIR / f"{DOMAIN}-{locale}.po"
    old = polib.pofile(str(path))
    old_by_id = {entry.msgid: entry for entry in old if entry.msgid}
    fresh = polib.POFile()
    fresh.metadata = metadata(locale)

    for msgid in sorted(source_ids, key=str.casefold):
        old_entry = old_by_id.get(msgid)
        translation = old_entry.msgstr if old_entry and old_entry.msgstr.strip() else NEW.get(locale, {}).get(msgid, "")
        if not translation.strip():
            errors.append(f"{locale}: missing translation: {msgid}")
            continue
        if placeholders(msgid) != placeholders(translation):
            errors.append(
                f"{locale}: placeholder mismatch for {msgid!r}: source={placeholders(msgid)} translation={placeholders(translation)}"
            )
            continue
        entry = polib.POEntry(
            msgid=msgid,
            msgstr=translation,
            occurrences=catalog[msgid],
            comment=old_entry.comment if old_entry else "",
            tcomment=old_entry.tcomment if old_entry else "",
        )
        flags = set(old_entry.flags if old_entry else [])
        flags.discard("fuzzy")
        if placeholders(msgid):
            flags.add("php-format")
        entry.flags = sorted(flags)
        fresh.append(entry)

    if errors:
        continue

    fresh.save(str(path))
    fresh.save_as_mofile(str(LANG_DIR / f"{DOMAIN}-{locale}.mo"))
    print(f"{locale}: {len(fresh)} translated entries; MO compiled")

if errors:
    for error in errors:
        print(f"ERROR: {error}", file=sys.stderr)
    sys.exit(1)

# Final integrity checks.
for locale in LOCALES:
    po = polib.pofile(str(LANG_DIR / f"{DOMAIN}-{locale}.po"))
    ids = {entry.msgid for entry in po if entry.msgid}
    missing = source_ids - ids
    obsolete = ids - source_ids
    untranslated = [entry.msgid for entry in po if entry.msgid and not entry.msgstr.strip()]
    fuzzy = [entry.msgid for entry in po if entry.msgid and "fuzzy" in entry.flags]
    if missing or obsolete or untranslated or fuzzy:
        raise SystemExit(
            f"{locale} integrity failure: missing={len(missing)} obsolete={len(obsolete)} untranslated={len(untranslated)} fuzzy={len(fuzzy)}"
        )

print("I18N_REFRESH_OK")
