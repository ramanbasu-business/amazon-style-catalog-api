-- Schema for the catalog API. Applied in order by bin/migrate.
-- MySQL 8: the job queue relies on SELECT ... FOR UPDATE SKIP LOCKED (ADR-0003).

CREATE TABLE IF NOT EXISTS schema_migrations (
    filename   VARCHAR(255) NOT NULL PRIMARY KEY,
    applied_at DATETIME     NOT NULL
) ENGINE = InnoDB;

-- Cached products. `source_id` is the marketplace's own identifier; `sku` is the
-- retailer's internal one. Both are looked up directly, so both are indexed.
CREATE TABLE IF NOT EXISTS products (
    source_id    VARCHAR(64)    NOT NULL PRIMARY KEY,
    sku          VARCHAR(64)    NULL,
    title        VARCHAR(512)   NOT NULL,
    brand        VARCHAR(255)   NULL,
    category     VARCHAR(255)   NULL,
    price_amount DECIMAL(12, 2) NULL,
    price_currency CHAR(3)      NULL,
    attributes   JSON           NULL,
    fetched_at   DATETIME       NOT NULL,
    UNIQUE KEY uq_products_sku (sku),
    KEY idx_products_title (title(191)),
    KEY idx_products_fetched_at (fetched_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;

-- One row per rate-limited source operation. Updated under a row lock, so two
-- workers cannot both spend the last token.
CREATE TABLE IF NOT EXISTS rate_limit_buckets (
    operation     VARCHAR(64)   NOT NULL PRIMARY KEY,
    tokens        DOUBLE        NOT NULL,
    last_refilled DATETIME(6)   NOT NULL
) ENGINE = InnoDB;

-- Catalog update jobs. `idempotency_key` is unique, which is what makes a replayed
-- submission return the original job instead of creating a second one.
CREATE TABLE IF NOT EXISTS jobs (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    type            VARCHAR(32)  NOT NULL,
    status          VARCHAR(32)  NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    row_count       INT UNSIGNED NOT NULL,
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    source_job_id   VARCHAR(64)  NULL,
    last_error      VARCHAR(512) NULL,
    available_at    DATETIME     NOT NULL,
    created_at      DATETIME     NOT NULL,
    updated_at      DATETIME     NOT NULL,
    UNIQUE KEY uq_jobs_idempotency_key (idempotency_key),
    KEY idx_jobs_claim (status, available_at)
) ENGINE = InnoDB;

-- The rows of a job, and their individual outcomes. A job can be partially
-- accepted, so the result lives per row rather than only on the job.
CREATE TABLE IF NOT EXISTS job_rows (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    job_id        CHAR(36)        NOT NULL,
    line_number   INT UNSIGNED    NOT NULL,
    sku           VARCHAR(64)     NOT NULL,
    payload       JSON            NOT NULL,
    result        VARCHAR(32)     NOT NULL DEFAULT 'pending',
    error_message VARCHAR(512)    NULL,
    UNIQUE KEY uq_job_rows_line (job_id, line_number),
    CONSTRAINT fk_job_rows_job FOREIGN KEY (job_id) REFERENCES jobs (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4;
