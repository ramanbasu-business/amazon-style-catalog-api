# ADR 0002: MySQL 8 rather than PostgreSQL

Date: 2026-10-01
Status: Accepted

## Problem

The service needs one datastore for the product cache, the rate-limit buckets and
the job queue. PostgreSQL would be the usual default for a new service of this
shape.

## Decision

Use MySQL 8.

It is what the author runs day to day, so the SQL in this repository is written
with real knowledge of how this engine behaves rather than copied from a
PostgreSQL idiom and adjusted. Everything the project needs is available in MySQL
8: `SELECT ... FOR UPDATE SKIP LOCKED` for queue claiming (see ADR 0003), a JSON
column type for product attributes, and `ON DUPLICATE KEY UPDATE` for the cache
upsert.

## Outcome

Nothing in the design depends on MySQL beyond those three features, each of which
has a direct PostgreSQL equivalent (`FOR UPDATE SKIP LOCKED`, `jsonb`, `ON
CONFLICT ... DO UPDATE`). Moving would mean rewriting the migration files and the
upsert statements in `MysqlProductRepository`, and nothing above the repository.

The repository is named for the engine rather than hiding it, so the coupling is
visible: `ProductStore` is the interface, `MysqlProductRepository` the one
implementation.
