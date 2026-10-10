<!-- file generated with AI assistance: Claude Code - 2026-10-09 11:47:34 UTC -->

# Changelog

All notable changes to this bundle. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/) and come from the Git tags. Releases before 0.9.0 are described in the [GitHub releases](https://github.com/dmstr/flowable-bundle/releases).

## [Unreleased]

Engine credentials are stored encrypted, using the secret handling of `dmstr/api-configuration-bundle` 0.5.

### Breaking changes

- **Requires `dmstr/api-configuration-bundle` `^0.5`.** The constraint `^0.2.0 || ^0.3.0 || ^0.4.0` is dropped, because the bundle now depends on `ConfigSecrets` from 0.5. Applications on api-configuration-bundle 0.4 or older stay on flowable-bundle 0.9.0-beta2 or 0.8.
- **`password` and `token` are `writeOnly`.** `schema.json` marks both keys with `"writeOnly": true`. As a result, api-configuration-bundle encrypts them at rest (`enc:v1:…`), returns them as `********` in API responses (also for administrators) and keeps the stored value when an update omits the key or sends the mask back. A usable `CREDENTIALS_ENCRYPTION_KEY` (`dmstr_api_platform_utils.credential_encryption.key`) is required as soon as a `flowable` configuration holds a password or token.
- **`FlowableClientLocator` takes a fourth constructor argument**, `ConfigSecrets $secrets`. It is wired in the bundle's `config/services.yaml`; this only matters if you instantiate the locator yourself.

### Existing clear-text records

Records created with 0.9.0-beta2 or older hold `password`/`token` in clear text. `ConfigSecrets::decrypt()` only touches values with the `enc:v1:` prefix and returns every other value unchanged, so these records keep working after the upgrade: the locator passes the clear-text value to the engine as before. They stay unencrypted in the database, though, until they are written again. Encrypt them in one of two ways:

- run `bin/console app:api-configuration:encrypt-secrets` from api-configuration-bundle (idempotent, `--dry-run` available), or
- re-save each `flowable` configuration through the API with the secret entered again. The Doctrine `onFlush` listener encrypts every secret of an inserted or updated record, so any write that Doctrine schedules as an update also encrypts a kept clear-text value. A save that sends only `********` and changes nothing else may not be scheduled as an update at all and then leaves the value in clear text; the console command is the reliable way.

Responses mask the clear-text values as well, because masking follows the schema and not the stored format.

### Changed

- `dmstr/api-platform-utils-bundle` `^0.5.0` is accepted in addition to `^0.2.0 || ^0.3.0 || ^0.4.0`.
- **Credential seam:** `FlowableClientLocator::createClient()` decrypts the configuration with `ConfigSecrets::decrypt()` before it builds the `FlowableClient`. All consumers resolve their client through the locator: the API state providers and processors, every `flowable:*` command including `flowable:health`, and the external worker runner. The decrypted values exist only inside the client. A decryption failure (missing or changed key, corrupted value) is rethrown as `SecretEncryptionException` naming the configuration id. The bundle never falls back to sending the stored ciphertext to the engine. Over HTTP, this surfaces as a server error (500).

### Unchanged

- **Completing a task that does not exist (any more):** `POST /api/flowable/tasks/{id}/complete` still answers HTTP 404 when the engine answers 404, for example for an already completed task. `FlowableClient::completeTask()` does not catch the engine status, and `FlowableApiException::fromUpstreamStatus()` maps an upstream 404 to a 404 problem response (`Flowable resource not found.`). Callers that need idempotent completion have to treat this 404 themselves.

## [0.9.0-beta2] - 2026-10-09

The content of 0.9.0-beta1, now merged to `master` (PR 17). No code changes; 0.9.0-beta1 was tagged on the feature branch before the merge.

## [0.9.0-beta1] - 2026-10-09

Pre-release for integration testing against a Flowable engine in a consuming application.

### Added

- 14 Flowable operations as API Platform `McpTool`s: 6 read tools (process definitions, tasks, task form, process status, process history, dead-letter jobs) and 8 write tools (start process, complete task, evaluate DMN, send event, trigger execution, deploy DMN, deploy bundle, execute timer job). See "MCP tools" in the README.
- Bundle configuration under the new root `dmstr_flowable` with the switches `mcp.read`, `mcp.write` and `mcp.deploy`, all `false` by default. A switched-off tool is removed from the resource metadata, so it is neither listed nor callable. The deploy tools need `mcp.deploy` in addition to `mcp.write`, because deployed BPMN can run code on the engine.
- State providers and processors read tool arguments from `$context['mcp_data']` when invoked as an MCP tool; uploads accept inline files (`name`, `content`, optional `contentEncoding: base64`).
- `FlowableClientInterface::executeTimerJob()`. Implementations of the interface outside this bundle have to add it. The timer-jobs `move` action is not yet verified against a live engine.
- `processDefinition` filter on `FlowExternalWorkerJob`.
- PHPUnit suite (69 tests, no engine needed).

### Security

- Every MCP tool requires `ROLE_FLOWABLE_ADMIN`, the read tools included; `ROLE_USER` gets no MCP access. The HTTP operations keep their roles.
- Tools that change engine state irreversibly declare `destructiveHint: true` (start process, complete task, send event, trigger execution, both deploy tools, execute timer job), so MCP clients ask before calling them.
- `api-platform/core` requires at least 4.3.18: earlier releases do not evaluate `security` on MCP tools when Symfony listeners are used and do not filter `tools/list`.

### Fixed

- An empty object nested in MCP tool arguments (e.g. `eventPayload: {}`) no longer fails the input schema; the MCP SDK hands it over as an empty array.

### Changed

- `symfony/yaml` is required; `symfony/mcp-bundle` and `mcp/sdk` are suggested.

[0.9.0-beta2]: https://github.com/dmstr/flowable-bundle/compare/0.9.0-beta1...0.9.0-beta2
[0.9.0-beta1]: https://github.com/dmstr/flowable-bundle/compare/0.8.0...0.9.0-beta1

<!-- - revised 2026-10-09 (0.9.0-beta2) -->
