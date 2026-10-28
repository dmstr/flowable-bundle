<!-- file generated with AI assistance: Claude Code - 2026-07-15 -->

# CLAUDE.md

This is a public open-source package maintained under github.com/dmstr.

Doctrine-less API Platform pass-through resources, an HTTP client and a CLI
bridging API Platform to a Flowable BPMN REST engine. Resources are served
under `/api/flowable/*` (deployments, process definitions/instances, tasks,
executions, historic data).

## Language policy

**Everything in this repository is in English** — commit messages, code,
comments, documentation, and all communication about the repository
(merge/pull request titles and descriptions, code review, issues, and chat
with the coding assistant). Do not write German here, even though related
company/customer projects (e.g. the consuming application) use German. These
packages are public and English-only.

## Requirements

PHP >= 8.5 (see `composer.json`). The PHPUnit 12 suite in `tests/` (configuration `phpunit.dist.xml`) needs no Flowable engine or database. It has unit tests (the client is a `FlowableClientInterface` double, MCP tool metadata is built from the resource attributes) and kernel-based suites under `tests/Kernel` and `tests/Http` that boot a test kernel and send HTTP and MCP requests; their test authentication (`X-Test-Roles` header) lives only under `tests/`. Run it with the compose CLI runner: `docker compose run --rm php composer install && docker compose run --rm php vendor/bin/phpunit`. CI is the GitHub Action `.github/workflows/tests.yml` (PHP 8.5); the bundle is also exercised through its consuming applications (e.g. the za7 API stacks).

## Permissions

The bundle checks only `flowable:*` permissions (`Dmstr\Flowable\Security\FlowablePermissions`, registered as `za7_core.permission` tags in `config/services.yaml`) with `is_granted('<permission>')` and subject `null`. No `ROLE_*` name appears in `src/`, `config/` or the docs: the application maps permissions to roles through its own voter. A new operation or tool gets a permission constant, a tag and a test, and the README permission table is updated in the same change.

<!-- - revised 2026-10-08 (PHPUnit suite) -->
<!-- - revised 2026-10-28 (PHP 8.5, CI, kernel tests, permissions) -->
