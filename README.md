# Doctrine Session Bundle

[English](README.md) | [中文](README.zh-CN.md)

A Symfony Bundle for managing HTTP sessions with Doctrine DBAL support and advanced caching capabilities.

## Features

- PDO-based session storage with database persistence
- Redis/cache integration for improved performance
- HTTP-aware session management
- Automatic garbage collection
- Schema management integration

## Commands

### doctrine:session:gc

Cleans up expired session records from the database.

**Usage:**
```bash
php bin/console doctrine:session:gc
```

**Description:**
- Automatically removes expired session records from the sessions table
- Uses the session lifetime configuration to determine expired sessions
- Provides detailed feedback on the number of cleaned records
- Safe to run in production environments

**Example Output:**
```
Clearing Expired Sessions
=========================

[OK] Successfully cleaned 42 expired session records
```

## Configuration

The bundle integrates with Doctrine DBAL to provide persistent session storage. Configure your database connection and the bundle will automatically create and manage the sessions table.

### Environment Variables

- `DOCTRINE_SESSION_NAME` (optional, default: `PHPSESSID`): The session cookie name used by the application

## Testing

Run the test suite with:
```bash
vendor/bin/phpunit packages/doctrine-session-bundle
```