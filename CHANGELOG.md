# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - 2026-01-14

### Added
- **Async Driver**: Core functionality to push audit logs to Redis List using `RPUSH`.
- **Sync Driver**: Fallback driver for local development or environments without Redis.
- **Worker Command**: `audit:work` daemon to consume Redis logs and perform bulk inserts to the database.
- **Auditable Trait**: Easy-to-use trait to automatically track `created`, `updated`, and `deleted` Eloquent events.
- **Recovery System**: Fail-to-disk mechanism (`audit_rescue_*.json`) when database insertion fails, preventing data loss.
- **Recovery Command**: `audit:recover` to restore logs from disk to the database.
- **Pruning Command**: `audit:prune` to clean up old audit records based on retention configuration.
- **Polymorphic Relation**: `morphMany` relationship to easily retrieve logs via `$model->audits`.
- **Dynamic User Resolution**: Automatically detects the application's User model from `auth.providers.users.model`.
- **Configuration**: Publishable config file for tuning batch size, flush intervals, and Redis connections.

### Security
- Implemented `json_decode` validation in worker to prevent processing invalid/corrupted payloads.
