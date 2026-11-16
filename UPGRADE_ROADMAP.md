# Project Evolution Upgrade Roadmap

This roadmap is derived from the current repository, not from an assumed product backlog. Evidence was checked in `README.md`, `docs/feature-roadmap.md`, `docs/tieba-feature-survey.md`, `docs/feature-upgrade-plan.md`, `docs/reports/evolution-2026-11-13.md`, the PHP entry points, migrations, tests, and CI workflows. “EV” is the unique evolution item ID. Status is evidence-based: a unit test or source file alone does not prove production behavior. Items remain **Proposed** until the implementation and acceptance criteria below are demonstrated in this repository.

## Status and delivery rules

- **Proposed**: candidate work; no completion claim.
- **In progress**: code exists but the acceptance evidence is incomplete.
- **Complete**: the repository already proves the stated capability; keep it as a baseline and protect it with regression checks.
- Production claims require a real deployment, external service, or long-running test where the acceptance criteria say so. A unit test, placeholder, or documentation entry alone is not production evidence.

## 本轮已实施的增量

这些项目在本轮有代码、迁移或回归测试证据；“完成”只描述本地实现，不等于真实生产、并发规模或外部服务验收。

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-050 | 5 | Make API rate limiting atomic under concurrency | `lib/api-rate-limit.php`; `api.php`; `tests/ApiRateLimitTest.php` | Existing windows increment with a bounded UPDATE; first writers converge through a unique-key UPSERT; at-limit requests receive 429 and storage failure fails closed. | Complete (local/unit) |
| EV-051 | 3 | Bind the latest task-run lookup | `lib/class.cron.php`; `tests/CronHistoryTest.php` | Task names containing quotes remain data and cannot alter the lookup SQL. | Complete (local/unit) |
| EV-052 | 3 | Add default security response headers | `init.php`; `healthz.php` | Normal Web responses and health probes emit non-sensitive browser hardening headers without changing response bodies. | Complete (source) |
| EV-053 | 3 | Advertise rate-limit retry timing | `api.php` | A rejected API request returns HTTP 429 and `Retry-After: 60`; existing error contract remains intact. | Complete (source) |
| EV-054 | 5 | Add cron lease owner token and explicit renewal API | `lib/class.cron.php`; `setup/migrations/007600_cron_lease_owner.php`; `setup/install.template.sql`; `tests/CronSchedulerTest.php` | Old processes cannot release a newer owner’s lock; the current owner can renew; migration is idempotent. Automatic periodic renewal and MariaDB concurrency remain follow-up work. | Partial (local/unit) |

## P0 — reliability and data safety

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-001 | 5 | Integrate periodic lease renewal and prove concurrent scheduler behavior | `lib/class.cron.php`; `do.php`; `lib/cron_system_sign.php`; `tests/CronSchedulerTest.php`; MariaDB integration harness | A long-running task renews before TTL expiry, takeover remains safe after a crash, and a concurrent MariaDB run proves only one external execution. | Proposed |
| EV-002 | 5 | Make sign claims idempotent across manual and scheduled paths | `lib/class.SignManager.php`; `ajax.php`; `do.php`; migration `setup/migrations/007500_sign_claim.php` | Manual, retry, and cron paths share claim/ release logic; duplicate workers cannot sign the same row; MariaDB concurrency test passes. | Proposed |
| EV-003 | 5 | Add bounded retry and circuit state for Baidu upstream failures | `lib/class.wcurl.php`; `lib/class.misc.php`; `lib/cron_system_sign_retry.php` | Retry count, backoff, and stop conditions are persisted; repeated upstream failures pause only the affected account/task and produce one actionable notification. | Proposed |
| EV-004 | 4 | Introduce a durable job execution ledger | `lib/class.cron.php`; `tc_cron` migrations; `templates/admin-cron.php` | Each run has a stable ID, start/end/error/owner fields, and a replay-safe outcome; admin history can inspect a failed run without parsing logs. | Proposed |
| EV-005 | 4 | Add graceful cancellation for long-running loops | `do.php`; `docker/s6-overlay`; `README.md` loop section | SIGTERM/reload marker stops after the current bounded unit, releases claims/locks, and exits with the documented code; integration test leaves no stale lock. | Proposed |
| EV-006 | 4 | Define recovery for partial sign batches | `lib/class.SignManager.php`; `lib/cron_system_sign.php`; `templates/sign-logs.php` | A process crash midway through a batch records per-item state and the next run resumes safely without double-counting successes. | Proposed |

## P1 — security and account protection

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-007 | 5 | Require signed release manifests in production profiles | `lib/class.updater.php`; `docs/AUTO_UPDATE.md`; `.github/workflows/release.yml` | CI publishes a detached signature; a configured production key rejects missing, mismatched, expired, or wrong-asset signatures; SHA-256 remains an integrity check only. | Proposed |
| EV-008 | 5 | Add key rotation and rollback for encrypted secrets | `lib/class.SecretBox.php`; migration `007302_secret_encrypt.php`; `tools/migrate.php` | A new key can re-encrypt all supported secret fields transactionally, reports failures, and preserves a recoverable backup; old key is retired only after verification. | Proposed |
| EV-009 | 4 | Enforce password change invalidation with `auth_version` | `setup/install.template.sql`; `lib/session.php`; `templates/set.php` | Password change increments a version, invalidates existing sessions/tokens, and tests prove old sessions cannot continue. | Proposed |
| EV-010 | 4 | Complete CSRF and method audit for all state-changing routes | `setting.php`; `ajax.php`; `api.php`; `tests/InputAndAuthTest.php` | A route inventory identifies every write; browser writes require POST plus CSRF, API writes require documented method/auth, and GET writes are regression-tested absent. | Proposed |
| EV-011 | 4 | Add trusted proxy configuration and secure-client detection | `lib/request.php`; `lib/security.php`; `docker/Caddyfile` | Forwarded headers are ignored unless an explicit proxy CIDR is configured; HTTPS/session cookie decisions are tested behind and outside the trusted proxy. | Proposed |
| EV-012 | 4 | Add login and API token anomaly controls | `lib/class.loginguard.php`; `api.php`; `metrics.php` | Failed-login and token abuse counters have bounded retention, per-account/IP thresholds, safe lockout recovery, and no secret/token logging. | Proposed |

## P1 — backups, migrations, and operations

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-013 | 5 | Implement consistent, low-memory database backup streaming | `lib/cron_system_dbbackup.php`; `tools/backup-restore-drill.php`; `docs/reports/evolution-2026-11-13.md` | A large fixture exports with bounded memory, explicit utf8mb4, stable ordering, and a restore checksum comparison; no request timeout under the documented limit. | Proposed |
| EV-014 | 4 | Add encrypted/off-host backup delivery | `lib/class.notify.php`; `lib/cron_system_dbbackup.php`; `templates/admin-tools.php` | Backup delivery supports a configured destination, encryption before transfer, retry status, retention, and never places database credentials in logs or URLs. | Proposed |
| EV-015 | 4 | Add migration dry-run and resumable checkpoints | `lib/class.migrator.php`; `setup/migrations`; `upgrade.php` | Dry-run reports planned changes without writes; a killed migration resumes from a checkpoint and is idempotent on rerun. | Proposed |
| EV-016 | 4 | Add preflight compatibility report for upgrades | `setup/upgrade.php`; `setup/check.php`; `templates/admin-update.php` | Before download or migration, the UI reports PHP/extensions/database/version/space checks with actionable remediation and a machine-readable result. | Proposed |
| EV-017 | 3 | Add health probes for scheduler freshness and storage capacity | `healthz.php`; `lib/health.php`; `metrics.php` | Liveness remains cheap; readiness/metrics expose last successful scheduler run, backup age, disk threshold, and migration state without secrets. | Proposed |
| EV-018 | 3 | Add log redaction and retention controls | `lib/class.logger.php`; `data/`; `templates/admin-tools.php` | BDUSS/STOKEN, API tokens, URLs with secrets, and provider keys are redacted; retention is configurable and cleanup is covered by a fixture test. | Proposed |

## P1 — performance and scale

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-019 | 4 | Remove plugin metadata N+1 queries | `lib/plugins.php`; `docs/feature-upgrade-plan.md` | One request loads plugin metadata in bounded queries; a query-count test or SQL trace proves no per-plugin metadata query. | Proposed |
| EV-020 | 4 | Eliminate runtime DDL from request paths | `lib/class.SignManager.php`; `lib/class.cron.php`; `lib/class.Notice.php`; `lib/class.OAuth.php` | Schema readiness is established by migrations; normal requests issue zero `CREATE TABLE IF NOT EXISTS` statements. | Proposed |
| EV-021 | 4 | Optimize tieba health latest-row aggregation | `lib/class.SignManager.php`; `templates/analysis.php` | A representative fixture shows fewer queries and lower runtime than the correlated-subquery baseline while returning identical values. | Proposed |
| EV-022 | 4 | Replace deep OFFSET pagination with cursor pagination | `api.php`; `templates/sign-logs.php`; `templates/admin-cron.php` | Logs and history support stable cursor paging with documented ordering; old limit/offset callers remain compatible or receive a versioned response. | Proposed |
| EV-023 | 3 | Add retention/partition strategy for high-volume history | `setup/install.template.sql`; `lib/class.SignManager.php`; `templates/admin-tools.php` | Operators can set retention; cleanup is incremental and indexed; a million-row fixture avoids table-locking the sign path. | Proposed |
| EV-024 | 3 | Bound async sign fan-out and external connections | `do.php`; `lib/class.misc.php`; `lib/class.wcurl.php` | Concurrency has a hard cap, per-host connection timeout, cancellation, and aggregate failure reporting; low-resource mode remains usable. | Proposed |

## P1 — missing user capabilities

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-025 | 5 | Restore a viable message-center data source | `docs/feature-roadmap.md`; `templates/messages.php`; `lib/class.MessageCenter.php` | A supported Baidu endpoint/protocol is documented and verified with fixtures; pull is opt-in, deduplicated, and linked to notification delivery; blocked upstream returns a clear state. | Proposed |
| EV-026 | 4 | Add follow/unfollow Tieba management | `docs/tieba-feature-survey.md`; `templates/baiduid.php`; `lib/class.misc.php` | Users can preview, confirm, and audit follow/unfollow operations; unsupported/failed operations do not mutate local follow state. | Proposed |
| EV-027 | 4 | Add account social profile summary | `docs/tieba-feature-survey.md`; `templates/baiduid.php`; `api.php` | Profile fields are fetched through a verified protocol, timestamped, cached, and shown as unknown when unavailable; no fabricated values. | Proposed |
| EV-028 | 4 | Add image upload for supported post/reply flows | `docs/tieba-feature-survey.md`; `plugins/ver4_reply`; `lib/class.wcurl.php` | Upload validates MIME/size, uses account proxy and timeout, stores no unnecessary image copy, and posts only after upload success; failure is retry-safe. | Proposed |
| EV-029 | 4 | Add configurable post moderation preview | `plugins/ver4_reply`; `plugins/ver4_review`; `templates/admin-plugins.php` | Generated title/body/reply can be previewed, edited, approved, and audited before dispatch; scheduled mode retains per-account quotas. | Proposed |
| EV-030 | 3 | Add multi-invite campaigns and expiration | `templates/admin-invite.php`; `setup/install.template.sql`; `lib/reg.php` | Admin can create scoped, expiring, single/multi-use invite codes; redemption is atomic and audit-visible. | Proposed |
| EV-031 | 3 | Add optional registration policy controls | `templates/admin-set.php`; `lib/reg.php`; `docs/tieba-feature-survey.md` | Admin can enable email/domain/allowlist/rate controls independently; default behavior remains compatible and every rejection has a non-sensitive reason. | Proposed |
| EV-032 | 3 | Add self-service session and token management | `templates/set.php`; `lib/session.php`; `api.php` | Users can list/revoke sessions and rotate personal API tokens; revocation takes effect immediately and old token hashes cannot authenticate. | Proposed |

## P2 — API, plugins, and integration quality

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-033 | 4 | Version and publish an OpenAPI document | `lib/api-contract.php`; `api.php`; `tests/ApiContractTest.php` | `action=openapi` validates against a checked-in schema containing methods, auth, errors, pagination, and examples; CI detects contract drift. | Proposed |
| EV-034 | 4 | Add webhook event signatures and replay protection | `lib/class.webhook.php`; `lib/class.notify.php`; `templates/admin-set.php` | Outbound events include timestamped HMAC signatures and IDs; receiver guidance and tests reject stale/replayed events. | Proposed |
| EV-035 | 3 | Add API idempotency keys for mutating actions | `api.php`; migrations; `tests/ApiContractTest.php` | Repeating a request with the same key returns the original result without duplicate sign/post/notification side effects; keys expire safely. | Proposed |
| EV-036 | 3 | Add plugin capability and permission declarations | `lib/plugins.php`; `templates/admin-plugins.php`; `plugins/README.md` | A plugin declares required hooks, permissions, network access, and PHP compatibility; install/enable UI blocks incompatible or undeclared capabilities. | Proposed |
| EV-037 | 3 | Add plugin sandbox limits and execution telemetry | `lib/plugins.php`; `lib/class.logger.php`; `metrics.php` | Plugin runtime, network failures, and output are bounded and observable per plugin; an overrun cannot skip core cleanup. | Proposed |
| EV-038 | 3 | Add notification delivery status API | `lib/class.notify.php`; `api.php`; `templates/set.php` | Users/admins can query accepted, failed, retrying, and disabled-channel states with timestamps and stable codes; secrets are omitted. | Proposed |

## P2 — web experience and accessibility

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-039 | 3 | Complete keyboard and screen-reader audit | `templates/`; `source/css/`; `tests/browser/cron-smoke.cjs` | Navigation, dialogs, forms, charts, and status messages have labels, focus order, keyboard alternatives, and useful non-visual text; audit covers desktop and mobile layouts. | Proposed |
| EV-040 | 3 | Consolidate inline styles and event handlers | `docs/feature-upgrade-plan.md`; `templates/`; `source/js/` | Priority pages move inline `style`/`onclick` behavior into CSS/JS modules; a CSP report-only policy runs without blocking required interactions. | Proposed |
| EV-041 | 3 | Finish or remove partial i18n | `lib/i18n.php`; `lang/zh-CN.php`; `templates/` | All user-visible strings on selected pages use translation keys and an `en-US` fallback exists, or the unfinished switch is removed and docs are corrected. | Proposed |
| EV-042 | 3 | Migrate pages from Bootstrap 3 compatibility | `source/css/bootstrap.min.css`; `source/css/b3-compat.css`; `templates/` | A page batch uses the chosen Bootstrap version without layout regressions in the existing desktop/tablet/mobile screenshots. | Proposed |
| EV-043 | 2 | Add reduced-motion and chart data alternatives | `source/js/`; `templates/analysis.php`; `templates/calendar.php` | `prefers-reduced-motion` disables nonessential animation; each chart has a table or text summary available to keyboard and screen-reader users. | Proposed |

## P2 — release, deployment, and documentation

| EV ID | EV | Item | Evidence and likely files | Acceptance criteria | Status |
|---|---:|---|---|---|---|
| EV-044 | 4 | Verify signed release through a real old-client update | `tests/UpdaterTest.php`; `docs/RELEASE.md`; `docs/AUTO_UPDATE.md` | A pre-upgrade fixture discovers a signed Release, validates the matching asset, installs it, runs migration, and returns to the new version; failure leaves the old code runnable. | Proposed |
| EV-045 | 3 | Publish SBOM and dependency provenance | `composer.lock`; `.github/workflows/ci.yml`; `.github/workflows/release.yml` | Every Release contains a machine-readable PHP dependency SBOM and build metadata; CI fails on unreviewed high-severity dependency advisories. | Proposed |
| EV-046 | 3 | Add PHP 8.5 and MariaDB upgrade matrix when supported | `.github/workflows/ci.yml`; `composer.json`; `docker/docker-compose.yml` | The matrix is expanded only after compatibility checks; CI records supported and unsupported versions explicitly and keeps PHP 8.2/8.4 coverage. | Proposed |
| EV-047 | 3 | Add repeatable disaster-recovery runbook | `docs/RELEASE.md`; `docs/AUTO_UPDATE.md`; `tools/backup-restore-drill.php` | A clean operator can restore code, database, secrets, and scheduler from documented artifacts; time-to-recovery and data-loss bounds are recorded from a drill. | Proposed |
| EV-048 | 2 | Add release notes generated from verified changes | `CHANGELOG.md`; `release-notes/`; `tools/release-gate.php` | A tag is rejected when `version.php`, release notes, and migration metadata disagree; generated notes link to user-visible changes and upgrade actions. | Proposed |
| EV-049 | 2 | Keep web package boundary policy machine-checkable | `tools/publish-github.ps1`; `.gitattributes`; `.github/workflows/ci.yml` | Packaging tests continue to reject tests, secrets, local data, and development-only files while checking required runtime files and checksums. | Proposed |

## Existing capabilities to preserve

These are not new promises; they are repository-proven baselines that every roadmap item must keep working:

| Capability | Evidence | Status |
|---|---|---|
| Four sign modes, retries, health checks, and scheduler loop | `README.md`; `lib/class.misc.php`; `lib/cron_system_sign_retry.php`; related PHPUnit tests | Complete |
| Encrypted sensitive options and secret scanning | `lib/class.SecretBox.php`; migration `007302_secret_encrypt.php`; `tools/secret-scan.php`; CI | Complete |
| Bearer API contract with user/global scopes and stable errors | `api.php`; `lib/api-contract.php`; `tests/ApiContractTest.php` | Complete |
| Docker health and Web package dry-run gates | `.github/workflows/ci.yml`; `healthz.php`; `tools/publish-github.ps1` | Complete |
| Migration, scheduler, API, backup, and notification integration harnesses | `tools/*-integration-test.php`; `composer.json` | Complete |

## Suggested sequencing

1. **P0:** EV-001, EV-002, EV-003, EV-004, EV-005, EV-006.
2. **P1 security/operations:** EV-007 through EV-012 and EV-013 through EV-018.
3. **P1 scale/product:** EV-019 through EV-024, then EV-025 through EV-028.
4. **P2 product/API/UX:** EV-029 through EV-032, EV-033 through EV-038, EV-039 through EV-043.
5. **P2 release:** EV-044 through EV-049.

Each completed item should add or update focused tests and documentation, record the exact verification command, and move from **Proposed** only when its acceptance criteria are evidenced.
