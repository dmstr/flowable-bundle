<!-- file generated with AI assistance: Claude Code - 2026-07-01 17:10:01 UTC -->

# dmstr/flowable-bundle

Reusable Symfony bundle that exposes a [Flowable](https://www.flowable.com/)
BPMN engine as **Doctrine-less [API Platform](https://api-platform.com/)
pass-through resources**, a per-request HTTP client and matching `flowable:*`
CLI commands.

The bundle owns no database tables: every resource reads from and writes to a
remote Flowable REST engine at request time. Connection details (base URL and
credentials) are resolved per request from an `ApiConfiguration` of type
`flowable` (provided by `dmstr/api-configuration-bundle`).

## Requirements

- PHP >= 8.4
- Symfony 7
- API Platform 4
- A reachable Flowable REST engine (`flowable-rest`)
- `ext-pcntl` — only for `flowable:external-worker:run` as a long-lived process,
  where it enables the graceful shutdown described under
  [External worker tasks](#external-worker-tasks). Not a hard dependency: without
  it the command runs, it just cannot react to SIGTERM.

## Installation

```bash
composer require dmstr/flowable-bundle
```

If you do not use a Symfony Flex recipe that registers the bundle, add it to
`config/bundles.php`:

```php
return [
    // ...
    Dmstr\Flowable\FlowableBundle::class => ['all' => true],
];
```

The bundle is self-wiring: it ships its own service definitions
(`FlowableBundle::loadExtension()`), registers a dedicated `flowable` Monolog
channel and needs no entry in the application's `services.yaml`. API Platform
discovers the resource classes automatically from `src/ApiResource`.

## Configuration

Create an `ApiConfiguration` of type `flowable`. Its `configJson` is validated
against [`schema.json`](schema.json):

```json
{
    "base_url": "http://flowable-rest:8080/flowable-rest/service",
    "auth_type": "basic",
    "username": "rest-admin",
    "password": "..."
}
```

`auth_type` is either `basic` (requires `username` + `password`) or `bearer`
(requires `token`).

### Bundle configuration

The config root is `dmstr_flowable`. Its only options switch the bundle's MCP tools (see [MCP tools](#mcp-tools)); both default to `false`, so an application that does not opt in serves no Flowable tool:

```yaml
# config/packages/dmstr_flowable.yaml
dmstr_flowable:
    mcp:
        read: true    # tools with readOnlyHint: true
        write: false  # every other tool
```

A tool whose annotations declare `readOnlyHint: true` is kept only when `mcp.read` is on; every other tool counts as writing and is kept only when `mcp.write` is on. A switched-off tool is removed from API Platform's resource metadata, so it is neither listed nor callable. API Platform caches that metadata, so run `bin/console cache:clear` after changing a switch.

## API resources

All resources are served under `/api/flowable/*`:

| Resource | Notable operations |
|---|---|
| `FlowDeployment` | `GET /deployments`, `GET /deployments/{id}`, `POST /deployments/upload`, `DELETE` |
| `FlowProcessDefinition` | `GET /process_definitions`, `POST /process_definitions/{id}/start`, `GET /process_definitions/{id}/input_schema` |
| `FlowProcessInstance` | `GET /process_instances`, `POST /process_instances`, `DELETE` |
| `FlowTask` | `GET /tasks`, `POST /tasks/{id}/complete`, `GET /tasks/{id}/input_schema` |
| `FlowExecution` | `GET /executions`, `POST /executions/{id}/trigger` |
| `FlowHistoric*` | read-only history: activities, process instances, tasks, variables |
| `FlowDmnDeployment` | `GET /dmn_deployments`, `GET /dmn_deployments/{id}`, `POST /dmn_deployments/upload`, `DELETE` |
| `FlowDecision` | `GET /decisions`, `GET /decisions/{id}`, `POST /decisions/execute` |
| `FlowHistoricDecisionExecution` | read-only DMN evaluation history (audit, `failed` flag) |
| `FlowEventDeployment` | `GET /event_deployments`, `GET /event_deployments/{id}`, `POST /event_deployments/upload`, `DELETE` |
| `FlowEventDefinition` | read-only `GET /event_definitions`, `GET /event_definitions/{id}` |
| `FlowChannelDefinition` | read-only `GET /channel_definitions`, `GET /channel_definitions/{id}` |
| `FlowEventInstance` | `POST /event_instances` — send an event into the engine |
| `FlowExternalWorkerJob` | `GET /external_worker_jobs`, `POST /external_worker_jobs/acquire`, `POST /external_worker_jobs/{id}/{complete,fail,bpmnError,unacquire}` |
| `FlowJob` | read-only engine jobs: `GET /jobs?kind=async\|timer\|suspended\|deadletter\|history`, `GET /jobs/{id}/stacktrace` |

### External worker tasks

An `<serviceTask flowable:type="external-worker" flowable:topic="…">` inverts the
direction of an integration: the engine calls nothing, it puts a job on a topic
and waits. That removes both problems a `flowable:type="http"` task has — no
inbound route from the engine to the application, and **no credential in the
deployment artifact** (the `.bar` usually lives in git; here the *worker*
authenticates).

Write a handler and you are done — the interface is autoconfigured, so no
attribute and no service entry is needed:

```php
final class InvoiceHandler implements ExternalWorkerHandlerInterface
{
    public function topics(): array { return ['invoice-export']; }

    public function handle(ExternalWorkerJob $job): ExternalWorkerOutcome
    {
        $id = $job->variables['invoiceId'] ?? null;

        return ExternalWorkerOutcome::complete(['exportedAt' => date(DATE_ATOM)]);
        // or: ExternalWorkerOutcome::bpmnError('INVOICE_REJECTED')  → modelled error path
        // or: ExternalWorkerOutcome::fail(retries: 2)               → engine retry/backoff
        // throwing is reported as fail() with the exception message
    }
}
```

Run the poll loop with `flowable:external-worker:run` (see CLI below). Two rules
the runner enforces, both deliberate: a **handler that throws becomes a `fail`**
(the engine owns backoff and dead-lettering — the worker adds no second retry
policy), and a **topic without a handler is `unacquire`d, never failed**, because
failing would spend a retry over an incomplete deployment.

`lockDuration` and `retryTimeout` are **ISO-8601 durations** (`PT10M`), not
numbers. Note that a job which exhausted its retries disappears from
`GET /external_worker_jobs` and is then only visible as
`GET /jobs?kind=deadletter` — which is why the read-only `FlowJob` resource ships
with this feature rather than after it.

### DMN (decision) engine

`flowable-rest` ships the DMN engine in the same container under the `/dmn-api`
prefix (the process engine lives under `/service`); both are reached through the
same `flowable` `ApiConfiguration`. The DMN engine keeps a **separate
repository**, so a `.dmn` packaged inside a process (`.bar`) deployment is *not*
registered as a decision — deploy decision tables via `POST /dmn_deployments/upload`.
`POST /decisions/execute` evaluates a decision by key (`{ "decisionKey": "…",
"inputVariables": {…}, "singleResult": false }`) and returns the matching rule
outputs on the response's `result`.

> **Engine version:** the DMN resources require a Flowable engine **>= 8.0.0**
> (the DMN repository resource is named `decisions`; earlier engines exposed
> `decision-tables`).

### Event registry

`flowable-rest` ships a third engine repository under the `/event-registry-api`
prefix, again behind the same `flowable` `ApiConfiguration`. It stores **event
definitions** (`.event` — the payload contract and correlation keys of a
business event) and **channel definitions** (`.channel` — how events travel:
`type` is `inbound`/`outbound`, `implementation` the transport).

The operation worth having is `POST /event_instances`: it hands an event
**straight to an inbound channel inside the engine, with no message broker in
between**.

```json
{
  "eventDefinitionKey": "myEvent",
  "channelDefinitionKey": "myInboundChannel",
  "eventPayload": { "customerId": "42", "amount": 19.99 }
}
```

Inbound event correlation (BPMN event sub-processes, receive events, boundary
events) is therefore usable and verifiable without running Kafka or RabbitMQ.
The outbound direction (engine → broker) leaves the engine and is a separate,
broker-dependent concern that this bundle does not cover.

Both an event definition reference (`eventDefinitionKey` **or**
`eventDefinitionId`) and a channel definition reference (`channelDefinitionKey`
**or** `channelDefinitionId`) are required — the channel is what turns the
payload into a correlated event. The engine answers **204 No Content**, so the
operation returns no body. `eventPayload` must be a JSON object.

> **No `.bar`/`.zip` bundles here.** Unlike the process and DMN repositories,
> `POST /event_deployments/upload` accepts only a single `.event` or `.channel`
> file (the engine's own API description claims otherwise, but it only ever
> tests those two suffixes). A deployment needing several resources is uploaded
> one file at a time.

The `input_schema` endpoints return the per-task / per-definition input
JSON-Schema, resolved at runtime from either an authored
`<formKey>.schema.json` deployment resource or by converting inline Flowable
form-data — via the pluggable `flowable.task_form_source` chain. Start forms
work exactly like task forms: put `flowable:formKey="<key>"` on the BPMN
`startEvent` and ship `<key>.schema.json` in the same deployment (`.bar`/
`.zip`); the resolved fields are wrapped in the `businessKey` / `variables` /
`apiConfiguration` envelope of the `start` operation.

A start-form schema may carry a top-level `x-businessKey` object to customise
the envelope's `businessKey` property — e.g. a Jedison `x-watch`/`x-template`
pair that composes the key live from the form variables:

```json
{
  "type": "object",
  "x-businessKey": {
    "title": "Business Key",
    "x-watch": { "jahr": "#/variables/jahr", "revierId": "#/variables/revierId" },
    "x-template": "foo-{{ jahr.value }}-{{ revierId.value }}"
  },
  "properties": { "…": {} }
}
```

The object is merged over the default `businessKey` definition and stripped
from the `variables` schema — the composition rule ships with the process
deployment instead of being hard-coded in a UI. Details (semantics, watch
paths, Jedison rendering, IRI caveat):
[docs/start-form-business-key.md](docs/start-form-business-key.md).

A **task**-form field may name the process variable holding its data with
`x-process-data-var`, so a step that produced data can hand it to the next
step's form:

```json
{
  "landkreise": {
    "type": "array",
    "x-process-data-var": "landkreise"
  }
}
```

The variable's value — arrays and objects included, not just scalars like
`{{ token }}` — is moved into the field's `default`, which form renderers use as
the initial value; the keyword itself is stripped. Because completion writes the
field back to that same variable, reopening an unfinished task shows the last
saved state. A missing variable leaves the field without a `default` and keeps
the keyword visible for debugging. Details:
[docs/task-form-prefill.md](docs/task-form-prefill.md).

## MCP tools

The bundle declares 14 [MCP](https://modelcontextprotocol.io/) tools as API Platform `McpTool` entries on its resources. API Platform serves them when its MCP support is installed and enabled; the packages are suggested, not required:

- `symfony/mcp-bundle` serves the tools;
- `mcp/sdk` is the MCP server runtime it runs on.

Nothing is exposed until the [switches](#bundle-configuration) `dmstr_flowable.mcp.read` / `mcp.write` are turned on.

| Tool | Read/write | Security | Tag (`_meta` `de.dmstr/tag`) | Backing operation |
|---|---|---|---|---|
| `flowable_list_process_definitions` | read | `ROLE_USER` | `Flowable` | `GET /process_definitions` (`FlowProcessDefinitionProvider`) |
| `flowable_list_tasks` | read | `ROLE_USER` | `Flowable` | `GET /tasks` (`FlowTaskProvider`) |
| `flowable_get_task_form` | read | `ROLE_USER` | `Flowable` | like `GET /tasks/{id}/input_schema` (`FlowTaskFormProvider`) |
| `flowable_get_process_status` | read | `ROLE_USER` | `Flowable` | composite: process instance, open tasks, executions (`FlowProcessStatusProvider`) |
| `flowable_history_get` | read | `ROLE_USER` | `Flowable/History` | composite: historic activities, historic variables, failed decision executions (`FlowProcessHistoryProvider`) |
| `flowable_system_list_deadletter_jobs` | read | `ROLE_USER` | `Flowable/System` | `GET /jobs?kind=deadletter`, kind fixed (`FlowJobProvider`) |
| `flowable_start_process` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable` | `POST /process_instances`, by key or id (`ProcessInstanceCreateProcessor`) |
| `flowable_complete_task` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable` | `POST /tasks/{id}/complete` (`TaskCompleteProcessor`) |
| `flowable_dmn_evaluate` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable/DMN` | `POST /decisions/execute` (`DecisionExecuteProcessor`) |
| `flowable_events_send` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable/Events` | `POST /event_instances` (`EventInstanceCreateProcessor`) |
| `flowable_system_trigger_execution` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable/System` | `POST /executions/{id}/trigger` (`ExecutionTriggerProcessor`) |
| `flowable_dmn_deploy` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable/DMN` | `POST /dmn_deployments/upload` with an inline file (`DmnDeploymentUploadProcessor`) |
| `flowable_deploy_bundle` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable` | several inline files as one `.bar` deployment (`DeploymentBundleProcessor`) |
| `flowable_system_execute_timer_job` | write | `ROLE_FLOWABLE_ADMIN` | `Flowable/System` | no HTTP operation; `FlowableClientInterface::executeTimerJob()` (`TimerJobExecuteProcessor`) |

Every tool carries `annotations` (`readOnlyHint`, `destructiveHint`, `idempotentHint`), its `security` expression and the `de.dmstr/tag` entry in `_meta`. Each description is one line that names the fields of the result, because some agent frameworks ignore `structuredContent`; the result is the serialised resource (or result object) in API Platform's MCP output format.

Notes on individual tools:

- `flowable_start_process` starts by `processDefinitionKey` (latest version) or `processDefinitionId`, so an agent needs no prior lookup of the definition id; it is backed by `POST /process_instances`, not by `POST /process_definitions/{id}/start`.
- `flowable_dmn_evaluate` changes no engine state except a history row, but it is gated behind `ROLE_FLOWABLE_ADMIN` like its HTTP operation, so it is declared a write tool (`readOnlyHint: false`, `idempotentHint: true`) and appears only with `mcp.write`.
- `flowable_get_process_status` answers only for running instances; `flowable_history_get` covers ended ones. Both are composites: when one of their engine calls fails, the tool fails as a whole and never returns a partial result. Each list holds at most 200 rows; the `*Total` fields tell whether it was cut.
- `flowable_system_execute_timer_job` moves the timer job to the executable jobs (`POST management/timer-jobs/{id}` with `{"action": "move"}`), where the async executor runs it at once. The remaining wait cannot be restored, hence `destructiveHint: true`.
- `flowable_deploy_bundle` refuses `.dmn` files: a process deployment does not register decision tables in the DMN engine, so they go to `flowable_dmn_deploy`. Building the archive needs the PHP `zip` extension.

### Tool arguments

All arguments are top-level. Operations with a JSON body take the body properties plus the URI variable (`id`), which is not part of the body; `apiConfiguration` selects the engine connection (full or partial UUID). List tools take `page`, `itemsPerPage`, `sort`, `order` and the filters of their HTTP collection; relation filters accept an id or an IRI. Variables are passed preferably as a map `{"name": value}`; the explicit list `[{"name", "value", "type"}]` is accepted too.

The advertised input schema of a tool is the operation input file next to its resource class (e.g. `src/ApiResource/FlowTask/complete.input.json`; tool-only files are named `mcp<Verb>.input.json`), with the URI variables added as required properties. Top-level `allOf`/`anyOf` alternatives ("one of these keys is required") are left out of the advertised schema because several MCP clients cannot represent them; the tool description names the rule, and the processor validates the full file.

Files are passed inline instead of as a multipart upload:

```json
{
    "name": "rates.dmn",
    "content": "<?xml version=\"1.0\"?><definitions …>…</definitions>",
    "deploymentName": "rates"
}
```

`name` is the file name including its extension, `content` the file content as text; binary content (`.bar`, `.zip`) is sent base64-encoded with `"contentEncoding": "base64"`. `flowable_deploy_bundle` takes a list of such files plus `deploymentName`:

```json
{
    "deploymentName": "order-process",
    "files": [
        {"name": "order.bpmn20.xml", "content": "<?xml …"},
        {"name": "approve.schema.json", "content": "{\"type\": \"object\", …}"}
    ]
}
```

## CLI

Every REST operation has a matching command; command IDs mirror the resource
URLs:

```
flowable:health
flowable:deployments                 flowable:deployments:upload
flowable:process-definitions
flowable:process-instances           flowable:process-instances:create
flowable:tasks                       flowable:tasks:complete
flowable:executions                  flowable:executions:trigger
flowable:dmn:deployments             flowable:dmn:deployments:upload
flowable:dmn:decisions               flowable:dmn:rule:execute
flowable:events:deployments          flowable:events:deployments:upload
flowable:events:definitions          flowable:events:channels
flowable:events:instances:create
flowable:external-worker-jobs        flowable:external-worker:run
flowable:external-worker:complete    flowable:external-worker:fail
flowable:external-worker:bpmn-error  flowable:external-worker:unacquire
flowable:jobs
```

`flowable:external-worker:run` is the poll loop: `--topic` (repeatable, empty =
every registered handler's topics), `--worker-id`, `--lock-duration=PT10M`,
`--batch`, `--max-jobs`, `--time-limit`, `--once`.

> **Graceful shutdown requires `ext-pcntl`.** With pcntl loaded, SIGTERM / SIGINT
> finish the current job, unacquire whatever is still locked and exit, so a
> container restart leaves no jobs locked. **Without pcntl the command still runs**
> (`getSubscribedSignals()` returns an empty list, so Symfony does not refuse to
> start it) — but nothing handles the signal: on SIGTERM the acquired jobs stay
> locked until their `lockDuration` expires, and the engine hands them out again
> only after that. Install pcntl before running this as a long-lived worker
> process, and keep `--lock-duration` short enough that a lost lock is not
> painful. Verified 2026-07-29 on a php:8.4 image without pcntl.

## Security

Reading is open to any authenticated user. Starting, triggering and completing
(all write operations) require `ROLE_FLOWABLE_ADMIN`. The acting user is taken
from the authenticated identity (JWT `sub`) and propagated to Flowable — see
[`docs/tenant-and-user.md`](docs/tenant-and-user.md).

## Documentation

- [`docs/walkthrough-vacation-request.md`](docs/walkthrough-vacation-request.md)
  — end-to-end walkthrough of a human-task process.
- [`docs/tenant-and-user.md`](docs/tenant-and-user.md) — tenant and acting-user
  model.
- [`docs/start-form-business-key.md`](docs/start-form-business-key.md) — the
  `x-businessKey` start-form extension.
- [`docs/task-form-prefill.md`](docs/task-form-prefill.md) — the
  `x-process-data-var` task-form extension.

## Tests

The PHPUnit suite (`tests/`, configuration `phpunit.dist.xml`) runs without an engine, a database or a kernel: the Flowable client is a test double, and the MCP tool metadata is read from the resource attributes with API Platform's attribute metadata factory.

```bash
composer install
vendor/bin/phpunit
```

## License

[MIT](LICENSE) © diemeisterei GmbH

<!-- - revised 2026-10-08 (bundle configuration, MCP tools, tests) -->
