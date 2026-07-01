<!-- file generated with AI assistance: Claude Code - 2026-06-18 -->

# Tenant and user handling

How the pass-through bundle treats the **acting user** and the **tenant id**,
and the recommended za7 deployment model.

## Acting user — server-derived, never client input

The acting user is **not** taken from the request body. It is read from the
security context — the authenticated user's identifier (Keycloak JWT `sub`) —
via `ActingUserResolver::currentUserId()`. This makes it non-spoofable
(design **D7**).

- **Type**: optional string (`?string`). If no user can be resolved, it is
  simply omitted — it is **never required** and never causes an error.
- **Propagation**:
  - On **start** / **create** → sent as `startUserId` in the payload **and**
    recorded as the process variable `startedBy`.
  - On **trigger** / **task complete** → recorded as the process variable
    `triggeredBy`.
- **Why a variable, not just `startUserId`**: Flowable 7.2 REST ignores a body
  `startUserId` on the runtime start endpoint (it derives the start user from
  the authenticated REST user). The actor is therefore additionally stored as a
  queryable process variable. `startUserId` is still sent for engines that
  honour it.
- `FlowTask.assignee` is unrelated — it is the raw assignee string returned by
  the engine and exposed only as a list filter.

There is nothing for callers to set: the user is always resolved automatically.

## Tenant id — optional pass-through string

`tenantId` is a **free, optional string** on all resources
(`FlowDeployment`, `FlowProcessInstance`, `FlowProcessDefinition`, `FlowTask`).
It is mapped from the engine response (`fromApi()`) and passed through
unchanged — there is no za7-side validation against a tenant registry.

Filtering by `tenantId` is only wired on **deployments**:

| Resource | `tenantId` filter | other list filters |
|---|---|---|
| `FlowDeployment` | yes | `name`, `category` |
| `FlowProcessInstance` | no | `processDefinitionKey`, `processDefinitionId`, `businessKey` |
| `FlowProcessDefinition` | no | `latest`, `key`, `category` |
| `FlowTask` | no | `processInstanceId`, `assignee`, `taskDefinitionKey` |

The Flowable REST API supports `tenantId` filtering on all of these; the bundle
just does not forward it for the other three (the provider `FILTERS` whitelist).
Adding it would be a one-line pass-through change per resource, but see the
deployment model below before doing so.

## Deployment model: one engine per customer

The za7 default is **one Flowable engine per customer**. Tenant isolation
happens at the engine/deployment level, not via `tenantId`. This matches the
single-tenant-per-deployment model used across za7.

Consequences:

- **Leave `tenantId` empty.** Do not set it automatically — there would only
  ever be one value per engine, carrying no information.
- **Do not retrofit the `tenantId` filter** on process instances, definitions
  or tasks: it would be dead configuration when every engine has a single
  tenant. The existing deployment filter is harmless and stays as a pure
  pass-through for engines that do use Flowable multitenancy internally.
- **Use `businessKey` as the correlation key instead of the tenant.** Set it on
  start/create (e.g. `customer:<uuid>`, `project:<uuid>`, or a za7 entity IRI)
  so instances can be found again without engine-internal ids. `businessKey` is
  accepted on both start endpoints and is a list filter on
  `FlowProcessInstance`.

`businessKey` is a **caller-side** convention (what the client sends on start),
not bundle logic — there is nothing to change in the bundle for it.
