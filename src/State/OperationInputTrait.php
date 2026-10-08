<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:12:47 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

/**
 * Single input source for the Flowable state providers and processors.
 *
 * An operation is invoked either over HTTP or as an MCP tool. API Platform's
 * MCP handler (ApiPlatform\Mcp\Server\Handler) passes the tool arguments as
 * the decoded JSON object in $context['mcp_data']; the HTTP request on the
 * stack at that point is the JSON-RPC transport request to /mcp and carries
 * none of the operation's inputs. So:
 *
 *  - when $context['mcp_data'] is present, it is the ONLY input source — the
 *    request is never consulted, not even per key;
 *  - otherwise the request is read exactly as before (query string, raw body,
 *    multipart form fields and files).
 *
 * Tool arguments that name a URI variable of the operation (e.g. "id") are
 * also present in mcp_data; the handler copies them into $uriVariables and
 * they are stripped from the MCP body (see mcpBody()), so a body schema with
 * additionalProperties: false still accepts the tool arguments.
 *
 * The using class provides the RequestStack as $this->requestStack.
 */
trait OperationInputTrait
{
    /**
     * MCP tool arguments, or null for an HTTP call.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    protected function mcpData(array $context): ?array
    {
        $data = $context['mcp_data'] ?? null;

        return \is_array($data) ? $data : null;
    }

    /**
     * Raw parameter value: the MCP tool argument when invoked as a tool,
     * otherwise the query-string value of the current request (unchanged).
     *
     * @param array<string,mixed> $context
     */
    protected function inputParam(string $key, array $context): mixed
    {
        $mcp = $this->mcpData($context);
        if ($mcp !== null) {
            return $mcp[$key] ?? null;
        }

        return $this->requestStack->getCurrentRequest()?->query->get($key);
    }

    /**
     * Parameter as a non-empty string, or null when absent/empty. Booleans
     * from MCP arguments become "true"/"false" — the spelling Flowable expects
     * on its query string; non-scalar MCP values are ignored.
     *
     * @param array<string,mixed> $context
     */
    protected function inputString(string $key, array $context): ?string
    {
        $value = $this->inputParam($key, $context);
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (!\is_scalar($value) || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * MCP tool arguments minus the operation's URI variables, i.e. the part
     * that corresponds to an HTTP request body. Null for an HTTP call.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    protected function mcpBody(array $context): ?array
    {
        $mcp = $this->mcpData($context);
        if ($mcp === null) {
            return null;
        }
        $uriVariables = \is_array($context['uri_variables'] ?? null) ? $context['uri_variables'] : [];

        return array_diff_key($mcp, $uriVariables);
    }
}
