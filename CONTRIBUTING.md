# Contributing

## Tested compatibility target

This repository's CI integration tests currently target:

- **Pterodactyl Panel:** `v1.15.1`
- **PHP:** `8.2` (matching the Panel `v1.15.1` Composer platform and CI baseline)

The test harness is pinned to this Panel reference in `.github/workflows/integration-tests.yml` (`PANEL_REF`).

## How CI is assembled

The integration workflow:

1. Checks out this add-on repository.
2. Checks out `pterodactyl/panel` at the pinned compatibility reference.
3. Installs the Panel dependencies from the Panel lock file.
4. Configures a disposable MySQL database named `panel_integration_test`.
5. Installs this add-on as a local Composer **path** package (`../pterodactyl-api-addon`) so the tested code is the exact commit under test.
6. Verifies package discovery and custom route registration.
7. Runs syntax checks, code style checks, static analysis, and this package's integration test suite.

No production credentials or external secrets are required.

## Running integration tests locally

From the add-on repository root (`/home/runner/work/pterodactyl-api-addon/pterodactyl-api-addon` in CI):

1. Check out the Panel next to this repository:

   ```bash
   cd ..
   git clone https://github.com/pterodactyl/panel.git panel
   cd panel
   git checkout v1.15.1
   ```

2. Configure a disposable test environment in the Panel checkout:

   ```bash
   cp .env.example .env
   printf '\nAPP_ENV=testing\nDB_CONNECTION=mysql\nDB_HOST=127.0.0.1\nDB_PORT=3306\nDB_DATABASE=panel_integration_test\nDB_USERNAME=root\nDB_PASSWORD=root\nCACHE_DRIVER=array\nQUEUE_CONNECTION=sync\nSESSION_DRIVER=array\nMAIL_MAILER=log\n' >> .env
   ```

   > The Panel test bootstrap refuses to run against databases whose names do not contain `test`.

3. Install the Panel dependencies and this local add-on checkout:

   ```bash
   composer install --no-interaction --prefer-dist
   composer config repositories.local-addon '{"type":"path","url":"../pterodactyl-api-addon","options":{"symlink":true}}'
   composer require rene-roscher/pterodactyl-api-addon:@dev --no-interaction
   ```

4. Run checks from the add-on repository:

   ```bash
   cd ../pterodactyl-api-addon
   find src tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
   ../panel/vendor/bin/php-cs-fixer fix --dry-run --diff --config=../panel/.php-cs-fixer.php src tests
   ../panel/vendor/bin/phpstan analyse --no-progress --autoload-file=../panel/vendor/autoload.php src tests
   PTERODACTYL_PANEL_PATH=../panel ../panel/vendor/bin/phpunit -c phpunit.integration.xml
   ```

## Understanding failures

- **Provider/route verification failures** mean package discovery did not register the service provider or expected routes.
- **403/401/404 assertion failures** in integration tests indicate authentication/authorization regressions.
- **Validation assertion failures** indicate input hardening regressions in API-key creation.
- **Database assertion failures** indicate token-creation or deletion integrity regressions.

## Test coverage focus

Current integration tests cover:

- Application API route authentication and administrator boundary behavior.
- Application API ACL enforcement for `users` and `allocations` resources.
- API-key listing/creation/deletion integrity and validation.
- Client API file-update route access restrictions and permission enforcement.
