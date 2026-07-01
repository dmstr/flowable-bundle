<?php
// file generated with AI assistance: Claude Code - 2026-06-17 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Service;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Validates a decoded request body against a bundle-local JSON schema using
 * opis/json-schema. The same schemas back the REST operations and the CLI
 * mirror, so both enforce identical input contracts.
 */
final class InputSchemaValidator
{
    /**
     * Decode raw JSON and validate it against the schema at $schemaPath.
     *
     * @return array<string,mixed> the decoded, validated payload
     */
    public function validateRaw(string $rawJson, string $schemaPath): array
    {
        $rawJson = trim($rawJson);
        if ($rawJson === '') {
            $rawJson = '{}';
        }

        $decoded = json_decode($rawJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Invalid JSON: '.json_last_error_msg());
        }
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }

        // json_decode(..., true) collapses both {} and [] to an empty PHP array,
        // so an empty body (and the API Platform Admin's []) would otherwise be
        // re-encoded as a JSON [] and fail a `type: object` schema. Decode the
        // raw JSON object-preserving for validation, and treat an empty body as
        // an empty object so a no-variable start is accepted.
        $payload = $decoded === [] ? new \stdClass() : json_decode($rawJson);
        $this->validateValue($payload, $schemaPath);

        return $decoded;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function validate(array $data, string $schemaPath): void
    {
        // An empty assoc array is ambiguous ({} vs []); the operations expect an
        // object, so validate it as one. Non-empty arrays keep their JSON shape.
        $payload = $data === [] ? new \stdClass() : json_decode(json_encode($data, JSON_THROW_ON_ERROR));
        $this->validateValue($payload, $schemaPath);
    }

    private function validateValue(mixed $payload, string $schemaPath): void
    {
        if (!is_file($schemaPath)) {
            throw new \RuntimeException(sprintf('Flowable input schema not found: %s', $schemaPath));
        }
        $schema = json_decode((string) file_get_contents($schemaPath));
        if (!\is_object($schema)) {
            throw new \RuntimeException(sprintf('Invalid Flowable input schema: %s', $schemaPath));
        }

        $result = (new Validator())->validate($payload, $schema);
        if (!$result->isValid()) {
            $errors = (new ErrorFormatter())->format($result->error());
            throw new UnprocessableEntityHttpException(
                'Input validation failed: '.json_encode($errors, JSON_UNESCAPED_SLASHES),
            );
        }
    }
}
