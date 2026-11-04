# Tests

Two levels of tests. None of them touch production data.

## 1. Unit tests (no WordPress needed)

Pure logic (import validation, daily limit, level access, score bounds, IP resolution, CSV safety, API token
encryption, asset loading detection, periodic statistics). WordPress functions are replaced by small stubs
(`tests/bootstrap.php`).

```bash
phpunit            # PHPUnit 9.6 or 10, PHP 8.0+, uses phpunit.xml.dist
```

Development dependencies are managed through Composer. Install them with `composer install` before running the tests.

## 2. Integration smoke test (real WordPress)

`tests/integration/smoke.sh` drives a real site over HTTP and WP-CLI: access control of the AJAX / admin-post
handlers, daily limit, import safety, REST API token and rate limit, cron events, protected backup folders,
conditional loading of the front-end assets.

**Disposable test site only**: it creates a user, replaces quiz levels and questions, and enables the REST API.

```bash
FQI3_SMOKE_CONFIRM=yes \
WP="wp --path=/var/www/html" WP_URL=http://localhost:8080 ADMIN_USER=admin ADMIN_PASS=secret \
tests/integration/smoke.sh
```

## Static analysis (WordPress Coding Standards)

```bash
phpcs                                   # full standard (.phpcs.xml.dist)
phpcs --standard=WordPress --sniffs=WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput,WordPress.DB.PreparedSQL .
```

The full standard still reports many style and output-escaping notices inherited from older code: use the security
subset above to spot real problems first.
