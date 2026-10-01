# ADR 0003: Keep the job queue in the database

Date: 2026-10-01
Status: Accepted

## Problem

Catalog update jobs are submitted by the API and processed by a separate worker.
That needs a queue. Redis or a hosted broker would be the conventional answer.

## Problem detail

The queue here is small and slow by nature: each job is a batch submission to a
source that completes in seconds, and the source's own rate limit, not the queue,
is what bounds throughput.

## Decision

Keep jobs in a `jobs` table and claim them with `SELECT ... FOR UPDATE SKIP
LOCKED`, which lets several workers take different rows concurrently without
blocking each other.

No Redis. A second piece of infrastructure would have to be run, monitored and
reasoned about for a queue whose depth is bounded by a rate limit measured in
single-digit requests per second. The database is already required, already
transactional, and lets a job's status, its rows and its results be written in one
transaction — with a separate broker, a crash between "message acknowledged" and
"result stored" would lose the outcome.

## Outcome

`docker compose up` starts the whole system with one dependency.

The limit accepted: claiming is a polling loop, so a job waits up to one poll
interval before a worker picks it up, where a broker would deliver it
immediately. At this job rate that latency does not matter. If throughput ever
became the constraint, the queue is behind `JobStore`, and a broker-backed
implementation would replace it without the worker's logic changing.
