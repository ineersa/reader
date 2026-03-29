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

function stop_conflicting_dev_port_containers(): void
{
    run(<<<'BASH'
sh -lc 'set -eu;
project_name="${COMPOSE_PROJECT_NAME:-$(basename "$PWD")}";
for port in "${HTTP_PORT:-8080}" "${HTTPS_PORT:-8443}"; do
  [ -n "$port" ] || continue;
  for container_id in $(docker ps --filter "publish=${port}" --format "{{.ID}}"); do
    owner="$(docker inspect --format "{{ index .Config.Labels \"com.docker.compose.project\" }}" "$container_id" 2>/dev/null || true)";
    if [ "$owner" != "$project_name" ]; then
      container_name="$(docker inspect --format "{{.Name}}" "$container_id" 2>/dev/null || true)";
      container_name="${container_name#/}";
      echo "Stopping container ${container_name:-$container_id} bound to host port ${port}";
      docker stop "$container_id" >/dev/null;
    fi;
  done;
done'
BASH);
}
