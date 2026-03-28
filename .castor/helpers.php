<?php

declare(strict_types=1);

namespace CastorTasks;

use function Castor\run;

const DEV_COMPOSE = 'docker compose';
const PROD_COMPOSE = 'docker compose -f compose.yaml -f compose.prod.yaml';
const PHP_SERVICE = 'php';

function ensure_data_dir(): void
{
    run('mkdir -p data && touch data/app');
}

function dev_compose(string $command): void
{
    run(DEV_COMPOSE . ' ' . $command);
}

function prod_compose(string $command): void
{
    run(PROD_COMPOSE . ' ' . $command);
}

function dev_php_exec(string $command): void
{
    dev_compose(sprintf('exec -u $(id -u):$(id -g) %s %s', PHP_SERVICE, $command));
}

function prod_php_exec(string $command): void
{
    prod_compose(sprintf('exec %s %s', PHP_SERVICE, $command));
}
