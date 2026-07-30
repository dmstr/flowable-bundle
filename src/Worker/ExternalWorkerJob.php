<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:06:05 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Worker;

/**
 * One acquired external worker job, as handed to an
 * {@see ExternalWorkerHandlerInterface}.
 *
 * A readonly DTO, not a service: it exists so handlers never have to know the
 * engine's wire format. In particular `$variables` is a flat `name => value`
 * map, decoded from the engine's `[{name,type,value}, …]` list — the raw payload
 * stays available on `$raw` for the rare handler that needs the declared types.
 */
final readonly class ExternalWorkerJob
{
    /**
     * @param array<string,mixed> $variables flat name => value map
     * @param array<string,mixed> $raw the engine's acquired-job representation
     */
    public function __construct(
        public string $id,
        public string $topic,
        public ?string $processInstanceId,
        public ?string $executionId,
        public ?string $elementId,
        public ?string $elementName,
        public int $retries,
        public array $variables,
        public array $raw,
    ) {
    }

    /**
     * Build from an acquired-job payload.
     *
     * The topic is passed in rather than read from the payload: the engine's job
     * representation does not echo it back, the caller knows it because acquire
     * is per topic.
     *
     * @param array<string,mixed> $data
     */
    public static function fromApi(array $data, string $topic): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            topic: $topic,
            processInstanceId: isset($data['processInstanceId']) ? (string) $data['processInstanceId'] : null,
            executionId: isset($data['executionId']) ? (string) $data['executionId'] : null,
            elementId: isset($data['elementId']) ? (string) $data['elementId'] : null,
            elementName: isset($data['elementName']) ? (string) $data['elementName'] : null,
            retries: (int) ($data['retries'] ?? 0),
            variables: self::flattenVariables($data['variables'] ?? null),
            raw: $data,
        );
    }

    /** Convenience accessor with a default, for the common scalar case. */
    public function variable(string $name, mixed $default = null): mixed
    {
        return $this->variables[$name] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    private static function flattenVariables(mixed $variables): array
    {
        if (!\is_array($variables)) {
            return [];
        }

        $flat = [];
        foreach ($variables as $variable) {
            if (\is_array($variable) && isset($variable['name'])) {
                $flat[(string) $variable['name']] = $variable['value'] ?? null;
            }
        }

        return $flat;
    }
}
