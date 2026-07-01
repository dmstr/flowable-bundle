<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

use Dmstr\Flowable\Client\FlowableClientInterface;

/**
 * Converts Flowable's resolved form-data (legacy `activiti:formProperty` /
 * `flowable:formProperty`, returned by `/form/form-data`) into a Draft-07
 * JSON-Schema. The engine already parses the BPMN form definition, so no XML
 * parsing is needed here — this maps the structured `formProperties` array.
 *
 * Enum options are emitted as `enum` (the value ids) plus `enumTitles` (the
 * parallel labels) — the keyword the Jedison editor consumes (verified against
 * the bundled jedison build; not `enumNames`/`enumLabels`).
 *
 * This is the pluggable dialect adapter for inline Flowable forms; other
 * dialects (e.g. Camunda) can ship their own {@see TaskFormSchemaSourceInterface}.
 */
final class FlowableFormDataConverter implements TaskFormSchemaSourceInterface
{
    public function build(array $formData, FlowableClientInterface $client): ?array
    {
        $props = $formData['formProperties'] ?? null;
        if (!\is_array($props) || [] === $props) {
            return null;
        }

        $properties = [];
        $required = [];
        foreach ($props as $prop) {
            if (!\is_array($prop) || !isset($prop['id']) || !\is_string($prop['id'])) {
                continue;
            }
            $id = $prop['id'];
            $properties[$id] = $this->fieldSchema($prop);
            if (true === ($prop['required'] ?? false)) {
                $required[] = $id;
            }
        }

        if ([] === $properties) {
            return null;
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => false,
        ];
        if ([] !== $required) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * @param array<string,mixed> $prop
     * @return array<string,mixed>
     */
    private function fieldSchema(array $prop): array
    {
        $type = \is_string($prop['type'] ?? null) ? $prop['type'] : 'string';
        $name = $prop['name'] ?? null;
        $title = \is_string($name) && '' !== $name ? $name : (string) $prop['id'];

        $schema = ['title' => $title];

        switch ($type) {
            case 'long':
            case 'integer':
            case 'short':
                $schema['type'] = 'integer';
                break;
            case 'double':
                $schema['type'] = 'number';
                break;
            case 'boolean':
                $schema['type'] = 'boolean';
                break;
            case 'date':
                $schema['type'] = 'string';
                $schema['format'] = 'date-time';
                break;
            case 'enum':
                $schema['type'] = 'string';
                [$values, $titles] = $this->enumOptions($prop);
                if ([] !== $values) {
                    $schema['enum'] = $values;
                    $schema['enumTitles'] = $titles;
                }
                break;
            default:
                $schema['type'] = 'string';
        }

        // Read-only form properties are display-only on completion.
        if (false === ($prop['writable'] ?? true)) {
            $schema['readOnly'] = true;
        }

        // Literal string defaults only; `${...}` are runtime expressions, and
        // non-string defaults risk a type mismatch against the mapped type.
        $value = $prop['value'] ?? null;
        if ('string' === $schema['type'] && \is_string($value) && '' !== $value && !str_contains($value, '${')) {
            $schema['default'] = $value;
        }

        return $schema;
    }

    /**
     * @param array<string,mixed> $prop
     * @return array{0:list<string>,1:list<string>}
     */
    private function enumOptions(array $prop): array
    {
        $values = [];
        $titles = [];
        $options = $prop['enumValues'] ?? null;
        if (\is_array($options)) {
            foreach ($options as $option) {
                if (!\is_array($option) || !isset($option['id'])) {
                    continue;
                }
                $values[] = (string) $option['id'];
                $titles[] = isset($option['name']) ? (string) $option['name'] : (string) $option['id'];
            }
        }

        return [$values, $titles];
    }
}
