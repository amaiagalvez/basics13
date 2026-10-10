# basics13

`basics13` is a reusable Laravel package for CRUD resources that follow a common pattern:

- soft delete support
- active / archived / trash states
- unique names among active rows
- audit columns (`created_by`, `updated_by`, `deleted_by`)
- shared list views, search, tabs, and row actions

## Contract for a resource using the package

A concrete model is expected to follow the same conventions as the rest of the project:

- it keeps a `name` column
- it has a boolean `active` column
- it uses soft deletes for trash support
- it exposes a list state in the usual active/archive/trash flow
- it uses the shared `ListQueryBase`, `ListTransformer`, and controller base classes

The package centralizes the common logic to keep apps consistent and reduce copy-paste CRUD code.

## Important runtime note

The package tests and Blade compilation need a writable temporary directory. The package bootstrap creates or reuses a local `.tmp` directory and sets `TMPDIR`, `TEMP`, and `TMP` to it so Laravel can compile view cache files reliably in CI and local containers.

## Validation

Run the package suite from the package root:

```bash
./vendor/bin/phpunit
```
