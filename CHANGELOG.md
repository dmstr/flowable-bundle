<!-- file generated with AI assistance: Claude Code - 2026-10-09 11:47:34 UTC -->

# Changelog

All notable changes to this bundle. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/) and come from the Git tags. Releases before 0.9.0 are described in the [GitHub releases](https://github.com/dmstr/flowable-bundle/releases).

## [0.13.0] - 2026-11-26

Hardens the input of every transport (part A of [#18](https://github.com/dmstr/flowable-bundle/issues/18)): a strict selector for the engine configuration, one rule for ids that enter an engine path, one rule for upload file names, size limits for inline files, and MCP tool arguments validated against the schemas the tools publish. Several inputs that were accepted before are now rejected; read the breaking changes before upgrading.

### Breaking changes

- **`FlowableClientLocator::__construct(HttpClientInterface, EntityManagerInterface, ConfigSecrets)`**: the `UuidResolver` argument is gone, the locator looks the configurations up itself. It is wired in the bundle's `config/services.yaml`; this only matters if you instantiate the locator yourself, for example in tests.
- **Strict selector for an explicitly chosen configuration.** An explicit `apiConfiguration` (string or `{"uuid": …}`) must be the first 8 characters or the full UUID, lowercase, of an **active** `ApiConfiguration` of type `flowable`. Before, partial UUIDs of any length (9 to 35 characters included) and uppercase were accepted, an inactive configuration could be selected, and an ambiguous prefix was a server error (500). A malformed selector, an unknown id, an inactive configuration, another type and a prefix shared by two configurations now all answer the same 400 (`FlowableClientLocator::INVALID_SELECTION`: `apiConfiguration does not select an active Flowable API configuration. …`), which names no other configuration and no other type. The implicit selection (no selector, exactly one active `flowable` configuration) is unchanged.
  - **HTTP:** applies to the `apiConfiguration` query parameter and body property. Where a JSON body is validated against its input schema first, a malformed selector gets the schema's 422 instead of the 400; the `{uuid}` form of the schemas now carries the same pattern as the string form.
  - **MCP:** applies to the `apiConfiguration` argument of every tool; a malformed selector gets the schema's 422, as tool arguments are validated first. A `{uuid}` whose value is not a string (e.g. `{"uuid": 5}`) is rejected instead of falling back to the implicit selection.
  - **CLI:** applies to `--api-configuration` of every `flowable:*` command; a longer prefix or uppercase that worked before is rejected.
  - **Worker:** `flowable:external-worker:run` resolves its configuration once at start, so a start with an invalid selector or an inactive configuration fails. A running worker keeps its client until it is restarted, even if its configuration is deactivated meanwhile.
- **Ids that enter an engine path must match `^[A-Za-z0-9][A-Za-z0-9:._-]*$`.** `FlowableClient` checks every id it puts into a path segment of an engine request (deployment, process definition, process instance, task, execution, job, decision, event and channel ids, …) and answers 400 (`Invalid Flowable id: …`) before any engine request is sent, for every caller: HTTP, MCP, CLI, the external worker and direct use of `FlowableClient`. This rejects the empty string, `.`/`..`, `/`, `%` and every non-ASCII character. Consequences: a process definition whose BPMN key starts with `_` or contains non-ASCII letters (id e.g. `_key:1:5`) can no longer be addressed by id through the bundle, and the job ids the external worker gets back from the engine fall under the rule as well. The rule is derived from the id formats Flowable generates (UUID, numeric, `key:version:id`) and not verified against a live engine. Deployment resource names (e.g. `<formKey>.schema.json`, a form key may contain `/` or spaces) are exempt; only `.` and `..` segments are rejected there.
- **One rule for upload file names** (`Dmstr\Flowable\State\UploadRule`), used by the HTTP upload operations (`/deployments/upload`, `/dmn_deployments/upload`, `/event_deployments/upload`), `flowable_dmn_deploy` and the files of `flowable_deploy_bundle`: at most 255 characters, no `/`, no `\`, no `..`, no leading `.`, no quotes and no control characters (CR/LF among them); otherwise 400. `flowable_deploy_bundle` previously rejected only paths and a leading dot, the single uploads checked only the extension. Over HTTP, PHP strips the path from the uploaded file name already, so `/` and `\` only arrive over MCP.
- **MCP: `flowable_dmn_deploy` takes only `.dmn`.** `.bar` and `.zip` (and e.g. `.DMN.zip`) are rejected; schema pattern and tool description say so. Over HTTP, `POST /dmn_deployments/upload` still takes `.dmn`, `.bar` and `.zip`.
- **MCP: the arguments of the six read tools and of `flowable_dmn_deploy` are validated against the input schema the tool publishes** (URI variables included), with a 422 `Input validation failed: …`. Before, the read tools only advertised their schema. Calls that passed before are now rejected: unknown keys, strings for numbers (`"page": "2"`), `itemsPerPage` above 200; `flowable_system_list_deadletter_jobs` rejects `kind`, `flowable_list_tasks` rejects `processInstance` (the filter is `processInstanceId`). The HTTP read operations and their query parameters are unchanged.
- **MCP: size limits for inline files**, fixed, not configurable: at most 1 MiB per file after decoding (base64 or text) in `flowable_dmn_deploy` and `flowable_deploy_bundle`, and for `flowable_deploy_bundle` at most 20 files and 4 MiB in total after decoding; otherwise 400. The input schemas add `maxLength` 1400000 for `content` (a pre-filter, the byte count is the limit), 255 for file names and for `deploymentName`, `deploymentSource`, `category` and `tenantId`, and `maxItems` 20 for `files`. Limits in front of the bundle apply first: the consumer's web server (nginx `client_max_body_size`, default `1m`, already refuses a request carrying a 1 MiB file) and the HTTP transport of `mcp/sdk`, which refuses request bodies above 4 MiB (4194304 bytes) by default (`StreamableHttpTransport::DEFAULT_MAX_BODY_BYTES`). The whole JSON-RPC request counts there, and base64 content is a third larger than its decoded bytes, so with the default transport limit a bundle near the 4 MiB total is refused by the transport before the bundle's own check. HTTP multipart uploads get no new size limit from the bundle.

### Added

- `Dmstr\Flowable\State\McpToolInputValidator`: validates MCP tool arguments against the published input schema, fail-closed (`\RuntimeException`) when the tool has no buildable schema; a no-op over HTTP.
- An optional trailing constructor parameter `McpToolInputValidator $mcpInput = new McpToolInputValidator()` on `AbstractFlowableProvider`, `AbstractFlowableProcessor` and `FlowTaskFormProvider`, and `AbstractFlowableProvider::validateMcpInput()`. Existing subclasses keep working unchanged; an application's own provider behind an `McpTool` has to call `validateMcpInput()` itself to get the validation.
- `Dmstr\Flowable\State\UploadRule` with the file name rule and the limits as constants (`MAX_FILE_NAME_LENGTH`, `MAX_INLINE_BYTES`, `MAX_BUNDLE_FILES`, `MAX_BUNDLE_BYTES`).
- `src/Schema/api-configuration.json` holds the `apiConfiguration` definition once, instead of a copy in 20 input files; `Dmstr\Flowable\Schema\InputSchemaLoader` resolves it for the MCP `inputSchema`, the input validation, the OpenAPI `requestBody` and the form-schema fallback, so no `$ref` or `$defs` reaches a client. `Dmstr\Flowable\Schema\InputSchemaFileResolver` is now the class of the service `flowable.input_schema_resolver` and composes `OperationInputSchemaResolver` of `dmstr/openapi-json-schema-bundle`.

### Changed

- **MCP: a tool whose input schema cannot be built is hidden.** When the input file of a tool is missing, holds no valid JSON or its path contains `..`, `McpToolSwitchResourceMetadataCollectionFactory` removes the tool from the resource metadata, so it is neither listed nor callable, while all other tools stay available. This applies with every combination of the switches, all three on included. The error is logged once per metadata cache warmup on the `flowable` channel (level `error`, message `flowable.mcp.tool_hidden`, context `tool`, `reason`, `error`, `exception`). A tool that its switch removes anyway is not checked.
- `McpToolInputSchemaFactory` acts only on the bundle's resources (`Dmstr\Flowable\ApiResource\`, the same guard as the tool switches, `McpToolSwitchResourceMetadataCollectionFactory::isBundleResource()`); a foreign resource that uses the same `EXTRA_KEY` keeps the schema API Platform builds for it. `McpToolInputSchemaFactory::path()` rejects an input path containing `..`.
- The help text of `--api-configuration` describes the strict format.
- `dmstr/api-platform-utils-bundle` stays a dependency in `composer.json`, although the locator no longer uses it.

### Security

- The explicit selector no longer reaches the database: the locator checks its format and compares it in PHP with the ids of the active `flowable` configurations. Before, it went into the `LIKE` lookup of `UuidResolver`, where `%` and `_` acted as wildcards.
- The upload file name is escaped in the `Content-Disposition` header of the multipart request to the engine (`"`, CR and LF, as browsers do), and the file name rule rejects quotes and control characters before, so a name cannot end the header parameter.
- The id rule keeps dot segments, `/` and encoded characters out of the engine paths, for every caller.

### Development

- `tests/Schema/InputSchemaSnapshotTest.php` compares the resolved input schemas of all loading paths with a snapshot of 0.12.1 (`tests/Schema/snapshot-0.12.1/`), byte for byte, with the intended changes of this release (selector pattern in the `{uuid}` form, upload limits, `.dmn` only), and asserts that no output carries `$ref` or `$defs`.
- Regression tests for the selector over HTTP, MCP, CLI and the worker start, the id rule, the upload rules and limits, the read tool validation and the hidden tools. The suite now has 1001 tests.

## [0.12.1] - 2026-11-24

### Changed

- Allows `dmstr/openapi-json-schema-bundle` `^0.5.0` (adds `InputSchemaValidator`, now required by symfony-job-queue-bundle 0.6 and symfony-system-resources-bundle 0.5), replacing `^0.3.0 || ^0.4.0`; nothing in this bundle changes.

## [0.12.0] - 2026-11-13

### Changed

- **Requires `dmstr/api-configuration-bundle` `^0.7`** (adds the optional `ApiConfiguration::$sourceKey`; applications run its migration `Version20261111100000`). Applications on api-configuration-bundle 0.6 stay on 0.11.0.

### Development

- The advisory [PKSA-s52d-n7sx-trsg](https://github.com/modelcontextprotocol/php-sdk/security/advisories/GHSA-f4rf-6ghj-mhr6) on `mcp/sdk` 0.8 is ignored in `config.audit.ignore` so the test suite installs again. `mcp/sdk` is a dev-only dependency of this bundle (applications choose their own version); `api-platform/core` 4.4 supports `mcp/sdk` 0.9 only from [api-platform/core#8837](https://github.com/api-platform/core/pull/8837) on. The entry is removed once that is released.

## [0.11.0] - 2026-10-29

The bundle no longer checks role names: every HTTP operation and every MCP tool checks its own permission.

### Breaking changes

- **Every operation and every MCP tool needs a permission voter in the application.** Each of the 13 permissions (`flowable:read`, `flowable:read_stacktrace`, `flowable:mcp_read`, `flowable:deploy`, `flowable:deploy_delete`, `flowable:decision_evaluate`, `flowable:process_start`, `flowable:process_delete`, `flowable:task_complete`, `flowable:event_send`, `flowable:execution_trigger`, `flowable:worker`, `flowable:job_execute`) is checked with `is_granted()` and subject `null`. Without a voter that maps them, everything answers `403`, so provide the mapping before upgrading. Before: the read operations required the base user role, all write operations and all 14 MCP tools required `ROLE_FLOWABLE_ADMIN`; the job stacktrace declared the same role but did not enforce it (see Security). To reproduce that, map the former user role to `flowable:read` and the former administrator role to all 13 permissions (the map has no hierarchy, so the administrator needs `flowable:read` explicitly). The README has a migration section with an example voter.
- **Requires PHP >= 8.5.**
- **Requires `dmstr/api-configuration-bundle` `^0.6`** (and `doctrine/orm` `^3`). Applications on api-configuration-bundle 0.5 stay on 0.10.0.
- **Requires `symfony/expression-language` and `symfony/object-mapper`** (`^7.0`). The first evaluates the operations' `security` expressions; the second because API Platform enables its MCP integration only when it is installed.

### Added

- `Dmstr\Flowable\Security\FlowablePermissions` with the 13 permission constants and `all()`. They are registered as 13 `za7_core.permission` container tags (tag attribute `permission`) in `config/services.yaml`, so applications that collect such tags can offer them for assignment. The bundle has no permission map, no config node and no za7-core dependency. The MCP switches `dmstr_flowable.mcp.read` / `mcp.write` / `mcp.deploy` are unchanged and independent: a tool needs its switch and its permission.
- Kernel-based HTTP and MCP test suite (`tests/Kernel`, `tests/Http`) with its own test authentication (`X-Test-Roles` header, only under `tests/`), a GitHub Action (`.github/workflows/tests.yml`, PHP 8.5) and a `docker-compose.yml` CLI runner (`docker compose run --rm php`). Partially addresses Issue #14 (bundle test suite).

### Security

- `GET /jobs/{id}/stacktrace`, `GET /tasks/{id}/input_schema` and `GET /process_definitions/{id}/input_schema` did not evaluate their `security` expression: API Platform skips it for a custom controller with `read: false`, so every request that passed the firewall got them, with or without the declared role (the expression only filtered the hydra:operation list). The three controllers now check `flowable:read_stacktrace` and `flowable:read` themselves before calling the engine: `403` without the permission, `401` without authentication. This was already the case in 0.10.0 and earlier.

## [0.10.0] - 2026-10-21

### Breaking changes

- **Requires `dmstr/api-platform-utils-bundle` `^0.6.0`.** The constraint `^0.2.0 || ^0.3.0 || ^0.4.0 || ^0.5.0` is dropped, so the bundle installs next to applications that moved to utils 0.6 (for example through `hrzg/za7-core` 2.9). No code changes: the only class used from utils, `UuidResolver`, is unchanged between 0.5 and 0.6. Applications on utils 0.5 or older stay on 0.9.0.

## [0.9.0] - 2026-10-10

Engine credentials are stored encrypted, using the secret handling of `dmstr/api-configuration-bundle` 0.5. Includes the MCP tools of 0.9.0-beta1 and 0.9.0-beta2.

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

[0.13.0]: https://github.com/dmstr/flowable-bundle/compare/0.12.1...0.13.0
[0.11.0]: https://github.com/dmstr/flowable-bundle/compare/0.10.0...0.11.0
[0.10.0]: https://github.com/dmstr/flowable-bundle/compare/0.9.0...0.10.0
[0.9.0]: https://github.com/dmstr/flowable-bundle/compare/0.9.0-beta2...0.9.0
[0.9.0-beta2]: https://github.com/dmstr/flowable-bundle/compare/0.9.0-beta1...0.9.0-beta2
[0.9.0-beta1]: https://github.com/dmstr/flowable-bundle/compare/0.8.0...0.9.0-beta1

<!-- - revised 2026-10-09 (0.9.0-beta2) -->
<!-- - revised 2026-11-26 (0.13.0) -->
