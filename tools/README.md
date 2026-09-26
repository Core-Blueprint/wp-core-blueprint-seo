# Core Blueprint SEO tooling

SEO uses the suite-owned canonical first-party localization workflow, one read-only product check gate and one fail-closed customer release entrypoint.

## Product checks

Run the canonical read-only quality gate with:

```bash
./tools/check
```

The check gate validates PHP syntax, shipped JavaScript syntax, the runtime harness shell syntax, canonical localization and every SEO product regression. It does not mutate source or release-visible catalogs.

## Localization

Mutating catalog workflow:

```bash
tools/i18n/update
```

Read-only localization gate:

```bash
tools/i18n/check
```

English source is authority, POT is generated from source, and the six reviewed PO files are translation authority. MO files are release artifacts only and are compiled fresh from the reviewed PO sources during packaging.

Do not add alternate translation sync scripts, local translation maps or machine-translation authority.

## Customer release

Build the customer artifact with:

```bash
./tools/build-release
```

The builder is fail-closed. It first requires `./tools/check` to pass, then stages only explicit runtime/public release paths, validates the staged PHP and shipped JavaScript, verifies ZIP integrity and the canonical `core-blueprint-seo/` root, rejects development-only paths, and emits the deterministic ZIP plus its SHA-256 checksum in `dist/`.

The builder does not mutate POT/PO source or repair product code during packaging. It compiles fresh MO catalogs only inside isolated release staging.

A successful archive build is package evidence only. Outstanding staging, field or release blockers remain blockers until separately closed.
