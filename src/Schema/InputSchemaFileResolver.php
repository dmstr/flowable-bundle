<?php
// file generated with AI assistance: Claude Code - 2026-11-26 10:30:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Schema;

use Dmstr\OpenApiJsonSchema\Interface\InputSchemaResolverInterface;
use Dmstr\OpenApiJsonSchema\Service\OperationInputSchemaResolver;

/**
 * Static input-schema resolver for the bundle's operations: finds the
 * `<Resource>/<verb>.input.json` file with the lookup of
 * {@see OperationInputSchemaResolver::getSchemaFile()} and loads it through
 * {@see InputSchemaLoader}, so the OpenAPI requestBody and the form-schema
 * fallback receive the schema with its shared definitions resolved.
 *
 * Composes the dependency's resolver instead of re-implementing its lookup
 * (that class is final); only the loading differs, because its own
 * loadSchema() does not resolve references.
 */
final class InputSchemaFileResolver implements InputSchemaResolverInterface
{
    public function __construct(
        private readonly OperationInputSchemaResolver $files,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * The static file resolver ignores $context — a schema file either exists
     * for the operation or it does not.
     */
    public function supports(string $operationName, array $context = []): bool
    {
        return null !== $this->files->getSchemaFile($operationName);
    }

    public function resolve(string $operationName, array $context = []): ?array
    {
        $file = $this->files->getSchemaFile($operationName);

        return null === $file ? null : InputSchemaLoader::loadArray($file);
    }
}
