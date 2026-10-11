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

Edit actions expose their form data under the fixed `payload` key; `resourceKey()` only identifies
the resource and never replaces action metadata. Row actions can supply an optional `hint` for
their own tooltip.

`deletedNameConflict()` takes the index route and conflict session key explicitly. Permanent
deletion handles both model-event cancellation and restrictive foreign keys, while unrelated
database errors propagate.

Resource-specific validation attribute labels belong to the concrete Form Requests, not this
package's generic translations. The pagination view uses ordinary links and preserves its summary
even for an empty list; it is not a Livewire pagination component.

Name comparison follows the database collation. MariaDB with `utf8mb4_unicode_ci` rejects case and
accent variants; SQLite's default comparison is not equivalent. Run the MariaDB integration tests
with `BASICS13_MARIADB_DATABASE` set to a dedicated test database to verify that production contract.

## Important runtime note

The package tests and Blade compilation need a writable temporary directory. The package bootstrap creates or reuses a local `storage/.tmp` directory inside the package and sets `TMPDIR`, `TEMP`, and `TMP` to it so Laravel can compile view cache files reliably in CI and local containers.

## Validation

Run the package suite from the package root from testing13:

```bash
bash runAll.sh bas
```
