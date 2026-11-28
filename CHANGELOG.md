<!-- file generated with AI assistance: Claude Code - 2026-10-09 11:47:34 UTC -->

# Changelog

All notable changes to this bundle. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/) and come from the Git tags. Releases before 0.9.0 are described in the [GitHub releases](https://github.com/dmstr/flowable-bundle/releases).

## [0.17.0] - 2026-11-28

Offers PHP consumers the HTTP status the engine answered with, which the generic messages of 0.14.0 no longer carry ([#39](https://github.com/dmstr/flowable-bundle/issues/39)). The release adds public API and changes no observable behaviour: HTTP responses, MCP errors, CLI output, log and audit entries are as in 0.16.0. It is a minor rather than a patch because it adds public API, which a consumer can then require with `^0.17.0`.

### Added

- **`FlowableApiException::getUpstreamStatus(): ?int`**, closing [#39](https://github.com/dmstr/flowable-bundle/issues/39). For every exception built from an engine response (`fromUpstreamStatus()`) it returns the status the engine answered with, unmapped, also behind a 404 or 422: e.g. 503 or 504 behind the bundle's 502, 400 behind a 422, 404 behind a 404. It is `null` when the exception was not built from an engine response: a transport error (`unreachable()`: timeout, refused connection, transport error while reading the body, the bundle's 504) or the inherited constructor. So an engine that itself answers 504 (status code 502, upstream status 504) can be told from a transport timeout (status code 504, `null`).
  - Since 0.14.0 ([#18](https://github.com/dmstr/flowable-bundle/issues/18)) the upstream status is no longer in the message, so a consumer that decides by it (e.g. a fallback when the engine is down) had nothing to read. What counts as an outage is the consumer's decision.
  - It is offered to PHP consumers only: the status is in no exception message, HTTP response (with or without debug), response header, MCP error message or CLI output, and `flowable:health` and `getHealthInfo()` are unchanged. A consumer should not write it into a response either; the intent of #18 stays. See "Errors and correlation id" in the README.
  - Unchanged: the status mapping (404, 422, 502, 504), the signatures of `fromUpstreamStatus()` and `unreachable()`, the `flowable.upstream_error` log entry (which already carried `upstreamStatus` for a 502) and the audit entries.

### Development

- `tests/Exception/FlowableApiExceptionTest.php` pins the upstream status of the factories and the constructor; `tests/Client/FlowableClientErrorTest.php` the status through the real client, for engine errors, transport errors and errors while reading the body.
- `tests/Http/UpstreamErrorTest.php` proves that an engine 503 shows in no HTTP response, with or without debug, no MCP error, CLI output, health output or audit failure context. The correlation id is replaced before the substring checks and the debug trace is left out, so neither can falsify the result.

## [0.16.0] - 2026-11-27

The external worker releases every job of an acquired batch it does not finish, instead of leaving it locked until `lockDuration` expires, and a stop no longer waits for the rest of a batch. Consumers see new audit entries and, with `--batch` above 1, a shorter shutdown; this behaviour change is why the release is a minor. Everything else is tests that record the current behaviour.

### Fixed

- **External worker: the jobs of a batch are released on stop or failure** (`ExternalWorkerRunner`, the loop of `flowable:external-worker:run`). Before, the runner registered a job as held only while it processed it and forgot it in the same iteration, so the release at the end of `run()` never found anything: when a report failed, a topic was claimed by two handlers or the worker was stopped, the other jobs of the batch stayed locked until `lockDuration` (default `PT10M`) expired. A stop request was only checked between topics, so a batch was always finished.
  - Now every job of an acquired batch (with a non-empty id) is held from the acquire until the engine accepted its complete, fail or BPMN error report or its `unhandled_topic` unacquire. A stop request (SIGTERM, SIGINT) is checked before each job: the job in flight is finished and reported, the rest of the batch is unacquired and does not count as processed. Whatever is still held when `run()` ends is released one job at a time, in batch order; each release writes the audit pair `flowable.external_worker.unacquire.attempt` / `flowable.external_worker.unacquire` with `reason: "shutdown"`. A release that fails writes `flowable.external_worker.unacquire_failed` and never masks the original exception.
  - A job whose report the engine did not accept (e.g. a 502 or 504) is unacquired at exit as well. If the engine had in fact applied the report, it rejects that unacquire; this shows as `unacquire_failed` and is harmless.
  - What consumers see: new audit entries with `reason: "shutdown"`, and with `--batch` greater than 1 a stop no longer finishes the batch. With the default `--batch` of 1 nothing changes except the release after a failed report. Unchanged: the counting rules, the fail report of a throwing handler, and `unacquireAllExternalWorkerJobs` is never used.
  - Known and not changed: with an explicit `--topic`, a topic claimed by two handlers is still detected only after the acquire (the batch is now released); `--time-limit` is checked once per polling round.

### Development

- Tests that record the current behaviour, closing [#4](https://github.com/dmstr/flowable-bundle/issues/4), [#8](https://github.com/dmstr/flowable-bundle/issues/8), [#10](https://github.com/dmstr/flowable-bundle/issues/10) and [#14](https://github.com/dmstr/flowable-bundle/issues/14):
  - the external worker runner, handler registry, job and outcome (`tests/Worker/`), including the release of a batch described above;
  - `ProcessVariablePlaceholderResolver` and `FlowableClient::getTaskVariables()`, the path prefix of every public client method including the external worker API, and the upload request of an Event Registry deployment (`tests/Client/`);
  - the query of every list provider (`tests/State/ListQueryTest.php`): the default sort per provider (the time field, descending, for 11 providers, none for 6), an explicit client sort and its direction, the filter allow-list, relation filters given as IRI, absolute URL, bare id or IRI of another resource, and the clamped page size;
  - the Event Registry input over HTTP and the CLI (`tests/Http/EventRegistryInputTest.php`): the suffixes the event/channel upload refuses and accepts, in upper and lower case, and the event instance input (event and channel reference, `eventPayload` only as an object), rejected by `POST /event_instances` and `flowable:events:instances:create` with the same message;
  - the input file of every write operation and MCP write tool through `InputSchemaPath::forOperation()` (`tests/Schema/InputSchemaPathTest.php`);
  - a handler that only implements `ExternalWorkerHandlerInterface`, registered as an autoconfigured service without tag or attribute, is in `ExternalWorkerHandlerRegistry::topics()` (`tests/Kernel/ExternalWorkerHandlerAutoconfigurationTest.php`, with the new test kernel option `external_worker_handlers`).
- A test against a live engine stays open ([#31](https://github.com/dmstr/flowable-bundle/issues/31)).

## [0.15.0] - 2026-11-26

The action operations of the HTTP API are marked as custom actions, so that `dmstr/api-platform-utils-bundle` 0.8 lists them as `schema:Action` in the Hydra documentation. No operation behaves differently; the raised requirements are the reason to read the breaking changes.

### Breaking changes

- **Requires `dmstr/api-platform-utils-bundle` `^0.8.0`**, replacing `^0.6.0`. Utils 0.8 classifies custom actions by the marker only, no longer by path depth. Applications on utils 0.6 or 0.7 stay on 0.14.0.
- **Requires `dmstr/api-configuration-bundle` `^0.9.0`**, replacing `^0.7`. Applications on api-configuration-bundle 0.7 or 0.8 stay on 0.14.0. The constraint `dmstr/openapi-json-schema-bundle` `^0.5.0 || ^0.6.0` is unchanged, but api-configuration-bundle 0.9 requires openapi-json-schema-bundle `^0.6.0`, so only 0.6 can be installed.

### Added

- `extraProperties: ['custom_action' => true]` on 14 HTTP operations: `POST /decisions/execute`, `/deployments/upload`, `/dmn_deployments/upload`, `/event_deployments/upload`, `/event_instances`, `/executions/{id}/trigger`, `/external_worker_jobs/acquire`, `/external_worker_jobs/{id}/complete`, `/fail`, `/bpmnError`, `/unacquire`, `/process_definitions/{id}/start`, `/tasks/{id}/complete` and `GET /jobs/{id}/stacktrace`. In `/api/docs.jsonld` each carries `@type` `schema:Action`, the operation name as `hydra:title` (e.g. `flow_task_complete`) and its URL in `@id` or, for a path with `{id}`, in `hydra:uriTemplate`. Not marked: the two `input_schema` GETs, `POST /process_instances`, the other CRUD operations including `DELETE`, and the MCP tools. Compared with the path heuristic of utils 0.7 and older, the `input_schema` GETs are no longer listed as actions and `POST /event_instances` is listed for the first time. See "Custom actions in the Hydra documentation" in the README.
- `tests/Metadata/CustomActionMarkerTest.php` and `tests/Http/CustomActionHydraDocumentationTest.php` pin the marked set and its shape in the Hydra documentation.

## [0.14.0] - 2026-11-26

Completes [#18](https://github.com/dmstr/flowable-bundle/issues/18) after part A in 0.13.0: errors from the engine reach callers only as a generic message with a correlation id, every write leaves an attempt entry and then a success or failure entry in the audit log, for every transport, the arguments of every MCP tool of the bundle are validated in one central place, and the MCP switches must be literal booleans. Messages and log entries that callers or log pipelines match change; read the breaking changes before upgrading.

### Breaking changes

- **Generic 502 and 504 messages with a correlation id.** Every engine status of 400 or above other than 400 and 404 (401, 403 and 409 included) is still a 502, now with the message `Flowable backend error (correlation id: <id>).` instead of `Flowable backend error (upstream <n>): <engine message or raw body>`; a transport failure, also while reading the body, is still a 504, now `Flowable engine unreachable (correlation id: <id>).` instead of `Flowable engine unreachable: <transport error text, including the URL>`. `<id>` is a random id of 16 hex characters generated by the bundle; nothing from the engine (body, host, URL, exception text) is in the message any more. The details are logged once by `FlowableClient` on the `flowable` channel as `flowable.upstream_error` (level `error`, constant `FlowableClient::LOG_UPSTREAM_ERROR`) with the same `correlationId`; search the log for the id a user reports. As an engine 409 was and is a 502, its engine text is no longer visible to the caller, only in the log. Callers and tests that match the old message or read the engine text from it have to change. See "Errors and correlation id" in the README.
  - **HTTP:** the id is also sent as the response header `X-Correlation-Id` (`FlowableApiException::CORRELATION_ID_HEADER`), so it reaches the client even without debug mode, where API Platform replaces the detail of a 5xx response.
  - **MCP:** the JSON-RPC error message is the generic text.
  - **CLI:** the commands print the generic text; `flowable:health` and `FlowableClient::getHealthInfo()` report it as `detail` of `status: DOWN` (`reachable` is false only for a 504), the `endpoint:` line of `flowable:health` stays.
  - **Worker:** `ExternalWorkerRunner` logs and forwards the generic text where it used the engine's message before.
- **Shorter 422 message.** An engine 400 is still a 422 `Flowable rejected the request: <message>`, but `<message>` is now only the first line of the engine's error message (`message`, else `exception` of its error JSON), at most 200 characters (`FlowableApiException::ENGINE_MESSAGE_MAX_LENGTH`, `...` appended when cut); before it was the full message. Without a usable engine message the 422 is `Flowable rejected the request.` (`FlowableApiException::REJECTED`); the fallback to the raw response body is gone for every status. A 404 is unchanged (`Flowable resource not found.`).
- **Audit log: three entries per write, new keys, new key order.** Every write the bundle sends to the engine now logs `flowable.<action>.attempt` (level `info`) right before the engine call, then `flowable.<action>` (level `info`) on success or `flowable.<action>.failure` (level `warning`) when the call throws; the exception is rethrown unchanged. The success entry keeps the message of the former single entry, so a consumer matching it still sees exactly the successful writes. The context starts with `state` (`attempt`, `success`, `failure`), `via` (`http`, `mcp`, `cli`, `worker`), `tool` (the MCP tool name, only for `via: mcp`) and `actor`, followed by the action's context; a failure adds `status`, `correlationId` (for a 502 or 504) and `exception` (the class). Before there was one entry after success only, with `actor` as the last key, and an `actor` key in the action's context won over the authenticated user; now these keys are reserved and a context key of the same name is dropped. New context keys: `process_instance.create` carries `definition` and `definitionKey`; deployments carry `sha256` of the uploaded file, `flowable_deploy_bundle` (`deployment.bundle`) carries `fileSha256` (file name to hash), `archive` and `archiveSha256` of the `.bar` that is sent. A call rejected before the engine call (permission, input, selector, a missing `--acting-user`) still writes no entry. Log pipelines that parse these entries by key position or that count every `flowable.*` info line as a write have to change. See "Audit" in the README.
  - **HTTP:** `via: http`, `actor` the authenticated user; `POST /external_worker_jobs/acquire` is audited as before.
  - **MCP:** `via: mcp` with `tool`; a call rejected by the central argument validation (see below) does not reach the processor and writes no entry.
  - **CLI:** the write commands now write audit entries with `via: cli` and the `--acting-user` value as `actor`; before they wrote none, although the help of `--acting-user` of the upload commands said "recorded on the audit channel". The job commands `flowable:external-worker:complete`, `:fail`, `:bpmn-error` and `:unacquire` use the worker id as `actor`; `flowable:external-worker:unacquire --all` logs the new action `external_worker.unacquire_all`.
  - **Worker:** `ExternalWorkerRunner` (the loop of `flowable:external-worker:run`) writes the three entries with `via: worker` and the worker id as `actor` for complete, fail, BPMN error and unacquire (shutdown and unhandled topic); the acquire poll is not audited. Its former outcome line `flowable.external_worker.<kind>` and the shutdown line `flowable.external_worker.unacquire` are replaced by the audit entries with the same message, except that **`flowable.external_worker.bpmnError` is now `flowable.external_worker.bpmn_error`**: alerts and filters matching the old message have to change.
- **The MCP switches must be literal booleans.** `dmstr_flowable.mcp.read`, `mcp.write` and `mcp.deploy` filter API Platform's resource metadata, which is cached, so a value resolved at runtime would be frozen in that cache. An environment placeholder (`'%env(bool:…)%'`), a string (`'true'`, `'1'`) or any other non-boolean now fails the container build with a message naming the switch (`Dmstr\Flowable\DependencyInjection\LiteralMcpSwitchesPass`), whether it comes from the `dmstr_flowable` configuration or from an application override of the parameters. Set `true` or `false` per environment (e.g. `config/packages/prod/dmstr_flowable.yaml` or `when@prod:`) and run `cache:clear` after changing a switch. See "Bundle configuration" in the README.
- **MCP: the URI variables of the write tools are validated.** The central validation (see Added) checks every tool's arguments against the schema it publishes, URI variables included, before the processor runs. Calls that passed before are rejected with 422 `Input validation failed: …`, e.g. `flowable_complete_task` with `"id": 5`, which was sent to the engine as `"5"`. A missing `id` now gets the schema's `The required properties (id) are missing` instead of `Invalid Flowable id: …`. HTTP is not affected.
- **Uploads check the full suffix.** `flowable_deploy_bundle` refuses `.dmn.xml` and `.cmmn.xml` files, which passed as `.xml` before; `POST /deployments/upload` and `flowable:deployments:upload` refuse `.cmmn.xml`. The check ignores letter case; `.bpmn20.xml` and plain `.xml` stay allowed. Deploy DMN through `flowable_dmn_deploy` or the DMN upload.
- **CLI: the upload commands apply the HTTP upload rules.** `flowable:deployments:upload`, `flowable:dmn:deployments:upload` and `flowable:events:deployments:upload` check the base name of `--file` with the file name rule (`UploadRule::assertFileName()`) and accept the extensions of their HTTP operation, with the same messages. Before, the process and DMN commands checked neither and the event command only the extension, so a file with an unsupported extension or a name the HTTP upload refuses (quotes, a leading `.`, more than 255 characters) now fails before anything is sent.
- **`$mcpInput` and `AbstractFlowableProvider::validateMcpInput()` are removed.** The optional constructor parameter and property `$mcpInput` of `AbstractFlowableProvider`, `AbstractFlowableProcessor` and `FlowTaskFormProvider`, added in 0.13.0, are gone, and so is `validateMcpInput()`. An application provider that calls `validateMcpInput()` or passes `$mcpInput` to the parent constructor drops the call or the argument: the central validation now covers every tool of the bundle's resources, also when the application replaced its provider.
- **`AbstractFlowableProcessor::audit()` and `::schemaPath()` are removed.** Subclasses run the engine call through `audited(string $action, array $context, array $auditContext, \Closure $call, ?\Closure $successContext = null)`, which writes the three audit entries, and declare their input file in `public const INPUT_SCHEMA` (path relative to `src/ApiResource/`, e.g. `'FlowTask/complete.input.json'`) and validate the body with `validatedBody($context)`; `inputSchemaPath()` returns the resolved path.
- **`AbstractFlowableCommand::__construct()` takes a fourth parameter `FlowableAuditLogger $auditLogger`.** Subclasses of the command base that call the parent constructor have to take it and pass it on.
- **`FlowableApiException::unreachable()` takes no argument and `fromUpstreamStatus(int $upstreamStatus, ?string $engineMessage = null)` takes the engine message, null when there is none.** Code that builds these exceptions itself (e.g. a `FlowableClientInterface` double in tests) has to adapt; the transport error text belongs in the log, not in the exception.

### Added

- `Dmstr\Flowable\Service\FlowableAuditLogger` with `audited()`, `attempt()`, `success()` and `failure()` and the constants `STATE_ATTEMPT`, `STATE_SUCCESS`, `STATE_FAILURE`, `VIA_HTTP`, `VIA_MCP`, `VIA_CLI`, `VIA_WORKER`; it is the one place that writes the audit entries, on the `flowable` channel.
- `FlowableApiException::getCorrelationId()` (null for a 404 or 422), `shortenEngineMessage()` and the constants `CORRELATION_ID_HEADER`, `ENGINE_MESSAGE_MAX_LENGTH`, `NOT_FOUND`, `REJECTED`, `BACKEND_ERROR`, `UNREACHABLE`; `FlowableClient::LOG_UPSTREAM_ERROR`.
- An optional trailing constructor parameter `LoggerInterface $flowableLogger = new NullLogger()` on `FlowableClient` and `FlowableClientLocator`; the bundle wires the `flowable` logger into the locator, which hands it to every client it builds. A client built without it writes no `flowable.upstream_error` entry.
- **Central MCP argument validation:** `Dmstr\Flowable\State\McpToolInputValidationProvider`, registered by `Dmstr\Flowable\DependencyInjection\McpToolInputValidationPass` as a decorator of the read step of API Platform's MCP provider chain (`api_platform.mcp.state_provider.read` with `use_symfony_listeners: true`, otherwise `api_platform.state_provider.read`), after the `pre_read` security check and before the provider. It validates every tool call of the bundle's resources against the published input schema, fail-closed, also when the application replaced the tool's provider; tools of other bundles and of the application pass through, and requests without MCP arguments are untouched. When API Platform's MCP handler exists but neither chain does, the container build fails with a `LogicException`; with MCP off nothing is registered. Tested with API Platform 4.4.10 in both modes; the lower bound 4.3.18 is not tested. Limitation: for a tool whose `security` expression uses `object`, the arguments are validated before that object-level check. See "Tool arguments" in the README.
- `Dmstr\Flowable\Schema\InputSchemaPath`; `public const INPUT_SCHEMA` on the write processors, which the write tools publish as well, so the published and the enforced input file are one.
- `UploadRule::rejectedSuffix()`, `assertResourceSuffix()`, `decodeInlineContent()` and `XML_DOUBLE_SUFFIX_KINDS`; the extension lists are public constants `ALLOWED_EXTENSIONS` on `DeploymentUploadProcessor`, `DmnDeploymentUploadProcessor` and `EventDeploymentUploadProcessor`.
- `FlowableClientLocator::SELECTOR_DESCRIPTION`, the description of the `apiConfiguration` query parameter and multipart field.
- README sections "Errors and correlation id", "Audit" and "Rate limiting" (the bundle ships no rate limiter; an example `kernel.request` listener for the MCP endpoint with `symfony/rate-limiter`, not run in the suite).

### Changed

- **MCP: the six read tools and `flowable_dmn_deploy` accept `apiConfiguration` as `{"uuid": …}` as well as a string**, like the write tools, and select that configuration; 0.13.0 rejected the object form by schema. The read providers resolve the selector like the processors (`configurationId()`, now in `OperationInputTrait`), so a value that is neither a string nor `{uuid}` is a 400 instead of a silent fallback to the default connection. HTTP is unchanged: the query parameter is a string, an array there was and is a 400.
- The selector descriptions say the exact rule instead of "full or partial UUID": the first 8 characters of the UUID or the full UUID, lowercase. This changes the schema definitions in `src/Schema/api-configuration.json` and the description of the `apiConfiguration` query parameters and multipart fields, visible in the OpenAPI document and in `tools/list`.
- `src/Schema/api-configuration.json` no longer has the definition `apiConfigurationString` (no input file used it after the change above) and holds `page`, `itemsPerPage`, `sort` and `order` as shared definitions; the published schemas are unchanged by the latter.
- `McpResource` entries of the bundle's resources follow `mcp.read`; the bundle declares none yet.
- **MCP: `flowable_history_get` keeps the newest 200** activities and failed decision executions of an instance, still returned oldest first; before it kept the oldest 200, so the rows someone debugging a long-running instance needs were cut.
- Tool descriptions corrected: a failure is answered as a JSON-RPC error, not as a tool error; `flowable_dmn_evaluate` returns `result` as a list of rows, each the engine's list of result variables `{name, type, value}`, not a map, and every call adds a history row; `flowable_system_execute_timer_job` moves the job to the executable jobs, which only an active async executor of the engine runs; `flowable_get_process_status` has no total for the executions. The README is corrected accordingly, and explains `structuredContent` and `outputSchema` and that top-level `oneOf` is left out of the advertised schema like `allOf`/`anyOf`.
- Correction to the 0.9.0-beta1 entry: the `processDefinition` filter listed there among the MCP additions is an HTTP query relation filter on `GET /api/flowable/external_worker_jobs` (the same filter exists on tasks, jobs, process instances, historic process instances and historic tasks). `FlowExternalWorkerJob` has no MCP tool; the only tool with a `processDefinition` argument is `flowable_list_tasks`.
- Allows `dmstr/openapi-json-schema-bundle` `^0.5.0 || ^0.6.0`; the suite is green with both, and the bundle does not call `getUnifiedSchema()`.

### Fixed

- `flowable:external-worker:bpmn-error` looked for an input schema file that does not exist and failed on every call.

### Security

- A 502 or 504 no longer passes engine internals to the caller: no response body, host, URL or transport error text, which could reveal the internal topology or data of the engine. The details stay in the `flowable` log, linked by the correlation id.
- The central MCP argument validation keeps a bundle tool validated when an application replaces its provider; in 0.13.0 such a provider had to call `validateMcpInput()` itself.
- Failed writes are now audited (`flowable.<action>.failure`), and writes through the CLI and the external worker are audited at all.

### Unchanged

- The `outputSchema` of `flowable_dmn_evaluate` declares the items of `result` as objects, while the rows are arrays of result variables, so a client that validates `structuredContent` strictly against it rejects a non-empty result. The timer "run now", the DMN result shape and the history filter are pinned against a simulated engine; their verification against a live engine is tracked in a follow-up issue.

### Development

- The test kernel takes variants (MCP switches, extra parameters, `use_symfony_listeners`, debug off), the simulated engine can answer with an HTTP or a transport error, and the `flowable` channel is recorded. Regression tests for the generic errors over HTTP, MCP, CLI and health, the audit entries of every write and transport, the switch build failure, the central validation in both provider chains, the upload suffixes and the described tool behaviour. The suite now has 1422 tests.

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

[0.17.0]: https://github.com/dmstr/flowable-bundle/compare/0.16.0...0.17.0
[0.16.0]: https://github.com/dmstr/flowable-bundle/compare/0.15.0...0.16.0
[0.15.0]: https://github.com/dmstr/flowable-bundle/compare/0.14.0...0.15.0
[0.14.0]: https://github.com/dmstr/flowable-bundle/compare/0.13.0...0.14.0
[0.13.0]: https://github.com/dmstr/flowable-bundle/compare/0.12.1...0.13.0
[0.12.1]: https://github.com/dmstr/flowable-bundle/compare/0.12.0...0.12.1
[0.12.0]: https://github.com/dmstr/flowable-bundle/compare/0.11.0...0.12.0
[0.11.0]: https://github.com/dmstr/flowable-bundle/compare/0.10.0...0.11.0
[0.10.0]: https://github.com/dmstr/flowable-bundle/compare/0.9.0...0.10.0
[0.9.0]: https://github.com/dmstr/flowable-bundle/compare/0.9.0-beta2...0.9.0
[0.9.0-beta2]: https://github.com/dmstr/flowable-bundle/compare/0.9.0-beta1...0.9.0-beta2
[0.9.0-beta1]: https://github.com/dmstr/flowable-bundle/compare/0.8.0...0.9.0-beta1

<!-- - revised 2026-10-09 (0.9.0-beta2) -->
<!-- - revised 2026-11-26 (0.13.0) -->
<!-- - revised 2026-11-26 (0.14.0) -->
<!-- - revised 2026-11-26 (0.15.0) -->
<!-- - revised 2026-11-27 (0.16.0) -->
<!-- - revised 2026-11-28 (release 0.17.0) -->
