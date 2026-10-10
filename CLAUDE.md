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

PHP >= 8.4 (see `composer.json`). There is a PHPUnit 12 suite in `tests/` (configuration `phpunit.dist.xml`) that needs no Flowable engine, database or kernel: the client is a `FlowableClientInterface` double, and the MCP tool metadata is built from the resource attributes. Run it with `composer install && vendor/bin/phpunit`, or in any PHP 8.4 image with the repository mounted, e.g. `docker run --rm -v "$PWD:/app" -w /app <php-8.4-image> vendor/bin/phpunit`. There is no CI in this repository yet; the bundle is also exercised through its consuming applications (e.g. the za7 API stacks).

<!-- - revised 2026-10-08 (PHPUnit suite) -->
