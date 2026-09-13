# Core Blueprint SEO tooling

SEO uses the suite-owned canonical first-party localization workflow and one fail-closed customer release entrypoint.

## Localization

Mutating catalog workflow:

```bash
tools/i18n/update
```

Read-only release gate:

```bash
tools/i18n/check
```

English source is authority, POT is generated from source, and the six reviewed PO files are translation authority. SEO currently commits reproducible MO runtime artifacts (`commit_mo: true`); `tools/i18n/check` must prove those artifacts match the reviewed PO sources before packaging.

Do not add alternate translation sync scripts, local translation maps or machine-translation authority.

## Customer release

Build the customer artifact with:

```bash
tools/build-release
```

The builder is fail-closed. It requires the canonical localization gate, executes the existing SEO product regressions, stages only explicit runtime/public release paths, validates PHP and shipped JavaScript syntax, verifies ZIP integrity and the canonical `core-blueprint-seo/` root, rejects development-only paths, and emits a SHA-256 checksum.

The builder does not mutate POT/PO source or repair product code during packaging.

A successful archive build is package evidence only. Outstanding localization execution, staging, field or release blockers remain blockers until separately closed.
