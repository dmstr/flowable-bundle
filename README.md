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
```

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

## License

[MIT](LICENSE) © diemeisterei GmbH
