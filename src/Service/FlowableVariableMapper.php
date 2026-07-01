<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Service;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Maps request variables to the Flowable variable format [{name,value,type}].
 *
 * Two input shapes are accepted (design D9, decision 2026-06-16):
 *   - explicit list: [{ "name": "x", "value": 1, "type": "long" }, ...]
 *     — the type is honoured (supports date/long that shorthand cannot express);
 *   - shorthand map: { "x": 1, "approved": true }
 *     — the Flowable type is inferred from the PHP type.
 */
final class FlowableVariableMapper
{
    private const ALLOWED_TYPES = ['string', 'integer', 'short', 'long', 'double', 'boolean', 'date', 'json'];

    /**
     * @param array<int,array<string,mixed>>|array<string,mixed>|null $input
     * @return list<array{name:string,value:mixed,type:string}>
     */
    public function toFlowable(array|null $input): array
    {
        if ($input === null || $input === []) {
            return [];
        }

        // Explicit list form: a list whose entries carry a "name" key.
        if (array_is_list($input) && isset($input[0]) && \is_array($input[0])) {
            return array_map(fn (array $v): array => $this->normalizeExplicit($v), $input);
        }

        // Shorthand map form.
        $out = [];
        foreach ($input as $name => $value) {
            $out[] = ['name' => (string) $name, 'value' => $value, 'type' => $this->inferType($value)];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $variable
     * @return array{name:string,value:mixed,type:string}
     */
    private function normalizeExplicit(array $variable): array
    {
        if (!isset($variable['name'])) {
            throw new UnprocessableEntityHttpException('Each explicit variable requires a "name".');
        }
        $type = $variable['type'] ?? $this->inferType($variable['value'] ?? null);
        if (!\in_array($type, self::ALLOWED_TYPES, true)) {
            throw new UnprocessableEntityHttpException(sprintf(
                'Unsupported Flowable variable type "%s" for "%s". Allowed: %s.',
                (string) $type,
                (string) $variable['name'],
                implode(', ', self::ALLOWED_TYPES),
            ));
        }

        return [
            'name' => (string) $variable['name'],
            'value' => $variable['value'] ?? null,
            'type' => (string) $type,
        ];
    }

    private function inferType(mixed $value): string
    {
        return match (true) {
            \is_bool($value) => 'boolean',
            \is_int($value) => 'integer',
            \is_float($value) => 'double',
            \is_array($value) => 'json',
            default => 'string',
        };
    }
}
