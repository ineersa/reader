<?php

declare(strict_types=1);

namespace dev;

use Castor\Attribute\AsTask;
use function CastorTasks\dev_compose;
use function CastorTasks\dev_php_exec;
use function CastorTasks\stop_conflicting_dev_port_containers;

#[AsTask(description: 'One-shot local setup (then: castor dev:bootstrap)')]
function setup(): void
{
    stop_conflicting_dev_port_containers();
    build();
    up();
    composer_install();
}

#[AsTask(description: 'After first up: run importmap install and Tailwind build')]
function bootstrap(): void
{
    stop_conflicting_dev_port_containers();
    dev_php_exec('php bin/console importmap:install');
    dev_php_exec("php -r \"if (gethostbyname('github.com') === 'github.com') {fwrite(STDERR, 'DNS lookup failed for github.com inside php container. Check Docker DNS config (DOCKER_DNS_PRIMARY / DOCKER_DNS_SECONDARY).'.PHP_EOL); exit(2);} \"");
    dev_php_exec('php -r "\$binaries = glob(\'var/tailwind/*/tailwindcss-*\'); if (\$binaries === false) { \$binaries = []; } foreach (\$binaries as \$binary) { if (!is_file(\$binary)) { continue; } if (filesize(\$binary) > 0 && is_executable(\$binary)) { continue; } @unlink(\$binary); fwrite(STDERR, \"Removed invalid Tailwind binary: \".\$binary.PHP_EOL); }"');
    dev_php_exec('php bin/console tailwind:build');
}

#[AsTask(description: 'Build local development images')]
function build(): void
{
    dev_compose('build');
}

#[AsTask(description: 'Start local development stack')]
function up(): void
{
    stop_conflicting_dev_port_containers();
    dev_compose('up -d --build');
}

#[AsTask(description: 'Stop local development stack')]
function down(): void
{
    dev_compose('down');
}

#[AsTask(description: 'Stop local services without removing containers')]
function stop(): void
{
    dev_compose('stop');
}

#[AsTask(description: 'Restart local development stack')]
function restart(): void
{
    stop_conflicting_dev_port_containers();
    dev_compose('restart');
}

#[AsTask(description: 'Show local service status')]
function ps(): void
{
    dev_compose('ps');
}

#[AsTask(description: 'Stream all local logs')]
function logs(): void
{
    dev_compose('logs -f');
}

#[AsTask(description: 'Stream PHP service logs (local)')]
function logs_php(): void
{
    dev_compose('logs -f php');
}

#[AsTask(description: 'Pull latest base images')]
function pull(): void
{
    dev_compose('pull');
}

#[AsTask(description: 'Remove local stack and orphan containers')]
function prune(): void
{
    dev_compose('down --remove-orphans');
}

#[AsTask(description: 'Open shell in PHP container as current user')]
function sh(): void
{
    dev_compose('exec -u $(id -u):$(id -g) php bash');
}

#[AsTask(description: 'Open root shell in PHP container')]
function root_sh(): void
{
    dev_compose('exec php bash');
}

#[AsTask(description: 'Run composer command in local container')]
function composer(string $cmd): void
{
    dev_php_exec('composer ' . $cmd);
}

#[AsTask(description: 'Install PHP dependencies (works with or without a running container)')]
function composer_install(): void
{
    dev_compose('run --rm --entrypoint="" -u $(id -u):$(id -g) php composer install --no-interaction');
}

#[AsTask(description: 'Update PHP dependencies in running local container')]
function composer_update(): void
{
    dev_php_exec('composer update');
}

#[AsTask(description: 'Run Symfony console command in local container')]
function console(string $cmd): void
{
    dev_php_exec('php bin/console ' . $cmd);
}

#[AsTask(description: 'Run PHPUnit tests in local container')]
function test(): void
{
    dev_php_exec('php bin/phpunit');
}

#[AsTask(description: 'Run PHPUnit with coverage reports (text, HTML, Clover)')]
function test_coverage(): void
{
    dev_compose('exec php sh -lc "mkdir -p /app/.phpunit.cache/code-coverage /app/var/coverage/html && chown -R $(id -u):$(id -g) /app/.phpunit.cache /app/var/coverage"');
    dev_compose('exec -e XDEBUG_MODE=coverage -u $(id -u):$(id -g) php php bin/phpunit --coverage-text --coverage-html var/coverage/html --coverage-clover var/coverage/clover.xml');
}

#[AsTask(description: 'Run PHP CS Fixer in local container')]
function cs_fix(): void
{
    dev_php_exec('php vendor/bin/php-cs-fixer fix');
}

#[AsTask(description: 'Run PHPStan in local container')]
function phpstan(): void
{
    dev_compose('exec php sh -lc "mkdir -p /app/var/phpstan && chown -R $(id -u):$(id -g) /app/var/phpstan"');
    dev_php_exec('php vendor/bin/phpstan analyse -c phpstan.dist.neon');
}

#[AsTask(description: 'Run cs-fix, phpstan, and tests')]
function quality(): void
{
    cs_fix();
    phpstan();
    test();
}

#[AsTask(description: 'Run lightweight local sanity checks')]
function check(): void
{
    dev_php_exec('php -v');
    dev_php_exec('php bin/console about');
}

#[AsTask(description: 'Validate local compose configuration')]
function config(): void
{
    dev_compose('config');
}
