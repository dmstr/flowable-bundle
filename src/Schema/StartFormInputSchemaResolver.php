<?php
// file generated with AI assistance: Claude Code - 2026-07-14 13:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;

/**
 * Dynamic, per-definition input schema for the `flow_process_definition_start`
 * operation — the start-form counterpart of {@see TaskFormInputSchemaResolver}.
 *
 * It only claims the operation when a process definition id is present in the
 * resolution $context — i.e. at runtime (the per-definition form endpoint),
 * NOT at OpenAPI build time, where the static
 * `FlowProcessDefinition/start.input.json` (served by the file resolver)
 * remains the documented request body.
 *
 * The start form is authored exactly like a task form: the BPMN `startEvent`
 * carries `flowable:formKey="<key>"` and the SAME deployment ships a
 * `<key>.schema.json` resource ({@see DeploymentResourceFormSchemaSource});
 * inline `formProperty` elements work as fallback
 * ({@see FlowableFormDataConverter}). The resolved field schema is wrapped in
 * the same envelope as `start.input.json` — `businessKey` + `variables` +
 * `apiConfiguration` — so {@see \Dmstr\Flowable\State\ProcessDefinitionStartProcessor}
 * consumes it unchanged.
 */
final class StartFormInputSchemaResolver implements InputSchemaResolverInterface
{
    private const OPERATION = 'flow_process_definition_start';

    /** @var list<TaskFormSchemaSourceInterface> */
    private readonly array $sources;

    /**
     * @param iterable<TaskFormSchemaSourceInterface> $sources priority-ordered
     */
    public function __construct(
        private readonly FlowableClientLocator $locator,
        iterable $sources,
    ) {
        $this->sources = $sources instanceof \Traversable
            ? iterator_to_array($sources, false)
            : array_values($sources);
    }

    public function supports(string $operationName, array $context = []): bool
    {
        return self::OPERATION === $operationName
            && isset($context['id'])
            && '' !== (string) $context['id'];
    }

    public function resolve(string $operationName, array $context = []): ?array
    {
        if (!$this->supports($operationName, $context)) {
            return null;
        }

        $processDefinitionId = (string) $context['id'];
        $apiConfiguration = isset($context['apiConfiguration']) ? (string) $context['apiConfiguration'] : null;
        $client = $this->locator->resolve('' !== (string) $apiConfiguration ? $apiConfiguration : null);

        $formData = $client->getStartFormData($processDefinitionId);
        if (null === $formData) {
            return null;
        }

        $fields = null;
        foreach ($this->sources as $source) {
            $fields = $source->build($formData, $client);
            if (null !== $fields) {
                break;
            }
        }
        if (null === $fields) {
            return null;
        }

        return $this->envelope($fields, $client->findProcessDefinition($processDefinitionId));
    }

    /**
     * The authored form schema may carry a top-level `x-businessKey` object
     * customising the envelope's `businessKey` property — e.g. a Jedison
     * `x-watch`/`x-template` pair composing the key live from form variables:
     *
     *   "x-businessKey": {
     *     "x-watch": { "jahr": "#/variables/jahr" },
     *     "x-template": "foo-{{ jahr.value }}"
     *   }
     *
     * The extension is merged over the default `businessKey` definition and
     * stripped from the `variables` schema, so the composition rule ships with
     * the process deployment instead of being hard-coded in a UI.
     *
     * @param array<string,mixed>      $fields     a `type: object` field schema
     * @param array<string,mixed>|null $definition the raw Flowable process definition (for the title)
     * @return array<string,mixed>
     */
    private function envelope(array $fields, ?array $definition): array
    {
        $definitionName = \is_array($definition) ? ($definition['name'] ?? $definition['key'] ?? null) : null;
        $hasRequired = isset($fields['required']) && \is_array($fields['required']) && [] !== $fields['required'];

        $businessKey = [
            'type' => 'string',
            'description' => 'Optional business key for the new process instance.',
        ];
        if (isset($fields['x-businessKey']) && \is_array($fields['x-businessKey'])) {
            $businessKey = array_replace($businessKey, $fields['x-businessKey']);
            unset($fields['x-businessKey']);
        }

        $schema = [
            '$schema' => 'http://json-schema.org/draft-07/schema#',
            'title' => self::OPERATION.' input',
            'description' => null !== $definitionName
                ? sprintf('Start "%s" — provide the start form variables.', (string) $definitionName)
                : 'Start a new process instance — provide the start form variables.',
            'type' => 'object',
            'properties' => [
                'businessKey' => $businessKey,
                'variables' => $fields,
                'apiConfiguration' => [
                    'description' => 'Optional Flowable ApiConfiguration selector (full or partial UUID).',
                    'oneOf' => [
                        ['type' => 'string', 'pattern' => '^[0-9a-f]{8}(-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})?$'],
                        ['type' => 'object', 'required' => ['uuid'], 'properties' => ['uuid' => ['type' => 'string']], 'additionalProperties' => false],
                    ],
                ],
            ],
            'additionalProperties' => false,
        ];
        if ($hasRequired) {
            $schema['required'] = ['variables'];
        }

        return $schema;
    }
}
