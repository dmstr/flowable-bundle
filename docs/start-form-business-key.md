<!-- file generated with AI assistance: Claude Code - 2026-07-15 00:00:00 UTC -->

# Start forms: the `x-businessKey` extension

The `businessKey` of a process instance is a caller-side value: the client sends it in the `start` request body, the engine stores it verbatim (see [tenant-and-user.md](tenant-and-user.md) for what to put in it). BPMN itself cannot declare a business key, so without further help every UI has to hard-code the composition rule ("build `fogu-<jahr>-<revierId>` from the form values") — per process, per client.

The `x-businessKey` extension moves that rule into the **process deployment**: the authored start-form schema (`<formKey>.schema.json`, shipped in the same `.bar`/`.zip` as the BPMN, referenced via `flowable:formKey` on the `startEvent`) may carry a top-level `x-businessKey` object. `StartFormInputSchemaResolver` merges it over the default `businessKey` property of the start-operation envelope and strips it from the `variables` schema, so it configures the envelope field instead of becoming a form field.

## Example

```json
{
  "type": "object",
  "x-businessKey": {
    "title": "Business Key",
    "description": "Composed live from the form values: fogu-<jahr>-<revier-id>.",
    "x-watch": {
      "jahr": "#/variables/jahr",
      "revierId": "#/variables/revierId"
    },
    "x-template": "fogu-{{ jahr.value }}-{{ revierId.value }}"
  },
  "required": ["revierId", "jahr"],
  "properties": {
    "revierId": { "type": "string" },
    "jahr": { "type": "integer" }
  }
}
```

`GET /api/flowable/process_definitions/{id}/input_schema` then returns the envelope with the customised property:

```json
{
  "type": "object",
  "properties": {
    "businessKey": {
      "type": "string",
      "description": "Composed live from the form values: fogu-<jahr>-<revier-id>.",
      "title": "Business Key",
      "x-watch": { "jahr": "#/variables/jahr", "revierId": "#/variables/revierId" },
      "x-template": "fogu-{{ jahr.value }}-{{ revierId.value }}"
    },
    "variables": { "…": "the authored fields, x-businessKey removed" },
    "apiConfiguration": { "…": "unchanged" }
  }
}
```

## Semantics

- The extension object is **merged over** the default `businessKey` definition (`array_replace`), so `type: string` and the default description survive unless overridden. Any keys are allowed — the resolver does not interpret them; they are passed through to the form renderer.
- Watch paths are **absolute instance paths in the rendered form**. The start form is served inside the `businessKey` / `variables` / `apiConfiguration` envelope, so authored fields live under `#/variables/<field>` — not `#/<field>`.
- This only shapes the **served input schema** (i.e. what a form renderer displays and pre-fills). The bundle does not compose, validate or enforce the key server-side: `ProcessDefinitionStartProcessor` still passes whatever `businessKey` the caller sends, and Flowable enforces no uniqueness. Callers that need a uniqueness guarantee must check `GET process_instances?businessKey=…&processDefinitionKey=…` before starting.

## Jedison rendering

The `x-watch` / `x-template` pair is native [Jedison](https://github.com/germanbisurgi/jedison) (≥ 1.x) behaviour, which hrzg/vue-za7-admin-ui uses to render the start modal:

- `x-watch` maps an alias to the instance path of another form field; each watched entry exposes `{ value, schema, properties }`.
- `x-template` re-computes the field value on every change of a watched field. Placeholders are `{{ alias.value }}`; a fallback is available via `{{ alias.value || 'default' }}`.

Caveat for IRI-reference fields (`format: iri-reference` + `x-collection`): the form value is the **IRI** (e.g. `/api/revier/12345`), so `{{ revierId.value }}` interpolates the full IRI, not the bare id — plain templates cannot extract substrings. If the key convention needs the bare id, either store the bare id as the field value (an `x-value-property`-aware editor) or accept the IRI in the key.

## Related

- README section "start forms" — deployment layout (`flowable:formKey` + `<formKey>.schema.json` in one `.bar`/`.zip`).
- [tenant-and-user.md](tenant-and-user.md) — what to put into `businessKey` and why it replaces `tenantId` as the correlation key in za7 stacks.
