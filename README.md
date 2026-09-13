# PHP Speed Tooling playground

Executable study for the Darkwood article *PHP Speed Tooling: Partial Function Application, Tokens and Flow*.

Three experiments, one rule: use the PHP primitive first. PFA instead of an adapter helper. `PhpToken` instead of a parser stack. `foreach` instead of Flow, until execution is actually orchestration.

This is not a Flow redesign. Flow `src/` was not modified.

PHP 8.6 is **pre-release**. The isolated image is official `php:8.6-rc-cli` (**8.6.0beta2** at study time). Do not run these examples as production code. The host CLI is PHP 8.5 and **cannot parse** `foo(?)`.

## Requirements

- Docker Desktop
- Local Darkwood Flow tree at `../../darkwood/src/Darkwood/Component/Flow`

## Run

```bash
chmod +x bin/study
./bin/study up
./bin/study composer
./bin/study run
```

Or:

```bash
docker compose run --rm php86 composer install --no-interaction
docker compose run --rm php86 php examples/run-all.php
```

## Scripts

| File | What it shows |
|------|----------------|
| `examples/A-classic-closure.php` | Adapter closures from real Flow consumers |
| `examples/B-partial-application.php` | `?`, `...`, named args, FCC, thunks, `new` |
| `examples/C-flow-pfa.php` | PFA passed to `Flow` / `fn()` with `FiberDriver` |
| `examples/D-native-pipeline.php` | PHP 8.5 `\|>` + PFA versus Flow |
| `examples/E-thunk.php` | Deferred call vs Flow `await()` |
| `examples/F-pfa-step-list.php` | List of PFA steps: `createFlow($steps)` trap vs generator yield |
| `examples/G-scanner-flow.php` | Same `scanFile()` through `FiberDriver` |
| `examples/H-scanner-measure.php` | Plain vs Flow timings for the scanner |
| `examples/measure.php` | Indicative micro-timings only |

## PFA Opportunity Scanner

Lives under `tools/` — Darkwood’s place for small, repository-local engineering tools. Tokenizer-only. No php-parser. Report only.

```bash
docker compose run --rm php86 php tools/pfa-opportunities/bin/self-check.php
docker compose run --rm php86 php tools/pfa-opportunities/bin/scan.php \
  /work/content/nolife-language/src \
  /work/content/flow-pipe/src \
  /work/darkwood/src/Darkwood/Component/Flow
```

Study notes and the article live in the Darkwood task directory, not here.
