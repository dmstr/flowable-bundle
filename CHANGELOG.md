<!-- file generated with AI assistance: Claude Code - 2026-10-09 11:47:34 UTC -->

# Changelog

All notable changes to this bundle. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/) and come from the Git tags. Releases before 0.9.0 are described in the [GitHub releases](https://github.com/dmstr/flowable-bundle/releases).

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

[0.9.0-beta1]: https://github.com/dmstr/flowable-bundle/compare/0.8.0...0.9.0-beta1
