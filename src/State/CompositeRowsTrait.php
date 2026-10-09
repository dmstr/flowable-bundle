<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Helpers for the composite MCP providers, which answer with several Flowable
 * lists in one result.
 *
 * Every sub-call goes straight to the client and is not caught: a failing
 * call raises its FlowableApiException and the tool fails as a whole — a
 * composite never answers with a partial result.
 */
trait CompositeRowsTrait
{
    /** Page size of every list inside a composite result. */
    public const COMPOSITE_LIMIT = 200;

    /**
     * A resource DTO as a plain row: its scalar and array fields, without the
     * raw Flowable payload and without object links (IRIs need a route, which
     * an MCP result does not have).
     *
     * @return array<string,mixed>
     */
    protected static function row(object $resource): array
    {
        $row = [];
        foreach ((new \ReflectionObject($resource))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $type = $property->getType();
            if ($property->isStatic() || 'raw' === $property->getName()
                || ($type instanceof \ReflectionNamedType && !$type->isBuiltin())) {
                continue;
            }
            $row[$property->getName()] = $property->getValue($resource);
        }

        return $row;
    }

    /**
     * Map the rows of a Flowable list envelope through a resource factory.
     *
     * @param array<string,mixed> $envelope Flowable list envelope
     * @param callable(array<string,mixed>):object $fromApi
     * @return array{0:list<array<string,mixed>>,1:int} [rows, total]
     */
    protected static function rows(array $envelope, callable $fromApi): array
    {
        $data = \is_array($envelope['data'] ?? null) ? $envelope['data'] : [];
        $rows = [];
        foreach ($data as $item) {
            if (\is_array($item)) {
                $rows[] = self::row($fromApi($item));
            }
        }

        return [$rows, (int) ($envelope['total'] ?? \count($rows))];
    }

    /**
     * The required id argument of a composite tool (URI variable "id").
     *
     * @param array<string,mixed> $uriVariables
     */
    protected static function requiredId(array $uriVariables, string $what): string
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_scalar($id) || '' === (string) $id) {
            throw new BadRequestHttpException(sprintf('Missing "id" argument (%s).', $what));
        }

        return (string) $id;
    }
}
