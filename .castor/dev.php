<?php

declare(strict_types=1);

namespace dev;

use Castor\Attribute\AsTask;
use function CastorTasks\dev_compose;
use function CastorTasks\dev_php_exec;
use function CastorTasks\ensure_data_dir;

#[AsTask(description: 'Ensure local data directory and SQLite file exist')]
function init(): void
{
    ensure_data_dir();
}

#[AsTask(description: 'One-shot local setup (then: castor dev:bootstrap, castor dev:messenger-consume)')]
function setup(): void
{
    init();
    build();
    up();
    composer_install();
}

#[AsTask(description: 'After first up: run importmap install and Tailwind build')]
function bootstrap(): void
{
    dev_php_exec('php bin/console importmap:install');
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
    init();
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

#[AsTask(description: 'Stream Mailpit logs (local)')]
function logs_mailer(): void
{
    dev_compose('logs -f mailer');
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
function composer(string $command): void
{
    dev_php_exec('composer ' . $command);
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

#[AsTask(description: 'Run Messenger consumer for all non-failed transports')]
function messenger_consume(): void
{
    dev_php_exec('php bin/console messenger:consume --all --exclude-receivers=failed -vv');
}

#[AsTask(description: 'Clear async and failed Messenger queues')]
function messenger_clear(): void
{
    dev_php_exec('php bin/console dbal:run-sql "DELETE FROM messenger_messages"');
    dev_php_exec('php bin/console messenger:failed:remove --all --no-interaction');
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
