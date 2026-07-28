<!-- file generated with AI assistance: Claude Code - 2026-07-29 00:00:00 UTC -->

# Task forms: the `x-process-data-var` extension

A process step often produces the data the *next* step is supposed to work on: an `flowable:type="http"` service task reads a collection, a script task computes a list of candidates, a business-rule task returns rows. Until now that data could not reach the form. `GET /api/flowable/tasks/{id}/input_schema` serves a *schema*, and the only server-side substitution — `ProcessVariablePlaceholderResolver`, which fills `{{ token }}` inside strings — is limited to **scalars** by design: an array has no meaningful string form, so it resolves to an empty string.

The `x-process-data-var` extension closes that gap for whole values. A field of an authored task-form schema names the process variable holding its data; `FormPrefillResolver` moves that variable's value into the field's `default`, which every JSON-schema form renderer already treats as the initial value. No client support is required.

## Example

An HTTP service task fetches a collection; a small expression step lifts the rows into a variable named like the form field:

```xml
<serviceTask id="fetchCollection" flowable:type="http">
  <!-- … resultVariablePrefix=list, saveResponseVariableAsJson=true … -->
</serviceTask>

<serviceTask id="extractRows" name="Take rows"
             flowable:expression="${execution.setVariable('landkreise', listResponseBody.get('hydra:member'))}"/>

<userTask id="choose" flowable:formKey="collection-table"/>
```

The form field points at that variable:

```json
{
  "type": "object",
  "properties": {
    "landkreise": {
      "type": "array",
      "x-format": "table-object",
      "x-process-data-var": "landkreise",
      "items": {
        "type": "object",
        "properties": {
          "externeNummer": { "type": "integer", "title": "Kreisnummer" },
          "name": { "type": "string", "title": "Name" },
          "gewaehlt": { "type": "boolean", "title": "Selected", "default": false }
        }
      }
    }
  }
}
```

`GET /api/flowable/tasks/{id}/input_schema` then serves the same field carrying the rows, with the keyword removed:

```json
{
  "landkreise": {
    "type": "array",
    "x-format": "table-object",
    "default": [
      { "@id": "/api/landkreis/003ba4df-…", "@type": "Landkreis", "externeNummer": 117, "name": "Göppingen" },
      "… 43 more …"
    ],
    "items": { "…": "unchanged" }
  }
}
```

Completing the task posts the (edited) rows back under `variables.landkreise`; `FlowableVariableMapper` stores them as engine type `json` — in the **same** variable the field read from.

## A variable name, not a path

The keyword deliberately takes a plain variable name. Reaching into another variable's structure — the `hydra:member` rows inside an HTTP response body — is the **process's** job, not the form schema's, for two reasons:

- **Separation.** Shaping data belongs to the process definition, where every step can see and reuse it, not to a presentation artefact.
- **Round trip.** A field that reads the same variable its completion writes back keeps user work: open, edit, leave the task unfinished, reopen — and the form shows the last saved state. A field reading a path into the original HTTP response would silently discard those edits and show the source data again.

If no step fills a suitable variable yet, add one — an expression service task or an execution listener is enough (see the example above).

## Semantics

- **Variable exists** → its value is written to `default` (overriding an authored one — the dynamic source wins) and `x-process-data-var` is removed from the served schema.
- **Variable missing** → **no** `default` is written and the keyword stays in place. An unresolvable source therefore remains visible in the served schema instead of silently producing an empty field; renderers ignore unknown `x-` keywords, so it is inert for the client but tells whoever debugs it that the variable was not there.
- Values are passed through **as they are**. The bundle does not reshape foreign data: extra properties (`@id`, `@type`, …) travel along and come back on completion. A renderer shows the columns the `items` schema declares.
- The keyword works **anywhere** in the schema, at any nesting depth, and is not restricted to arrays — a `string` field can read a scalar variable just as well.
- The prefilled value is data, not schema: it is never scanned for further keywords, so a fetched row carrying the keyword as a key of its own means nothing.
- **Task forms only.** At start time no instance and therefore no variable exists, so `StartFormInputSchemaResolver` deliberately does not run this resolver.

## Relation to the other placeholder mechanisms

Three substitutions can appear in one schema; they are separated by concern:

| Syntax | Resolved | By | For |
| --- | --- | --- | --- |
| `{{ name }}` inside a string | server, at request time | `ProcessVariablePlaceholderResolver` | scalars — e.g. `x-collection: "/api/user?rolle=aussendienst_{{ kreisnummer }}"` |
| `x-process-data-var: "name"` on a field | server, at request time | `FormPrefillResolver` | whole values — arrays, objects, scalars |
| `{{ field.value }}` in `x-template` | client, live while typing | Jedison `x-watch` | other fields of the same form |

The dotted `{{ x.value }}` form is Jedison's and must stay untouched on the server — which is why the whole-value mechanism uses a dedicated keyword instead of extending `{{ … }}`.

## Verified

Against flowable-rest 8.0.0 (za7-fogu-api, 2026-07-29), fixture `http-collection-table.bpmn20.xml`: an HTTP task fetched a protected collection of the app's own API (44 rows), the `extractRows` expression step lifted `hydra:member` into `landkreise` — confirming that JUEL can call `.get('…')` on a `json` variable, including a key containing a colon — and the following user task was served with all 44 rows in `default`, keyword removed. Two rows were marked and posted back with `POST …/tasks/{id}/complete` → 204; `landkreise` then held the edited rows as type `json`, each row's `@id` intact. Pointing the field at a variable no step fills leaves the keyword in the served schema with no `default`.

Not exercised by that fixture: the reopen case, since it has only one user task — the round-trip property follows from writing back to the same variable rather than from a separate mechanism.
