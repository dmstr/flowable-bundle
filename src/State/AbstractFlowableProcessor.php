<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Schema\InputSchemaPath;
use Dmstr\Flowable\Service\ActingUserResolver;
use Dmstr\Flowable\Service\FlowableAuditLogger;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
use Mcp\Schema\Request\CallToolRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shared concerns for the synchronous Flowable write processors (design D8):
 * raw-body access, client resolution (explicit apiConfiguration from query or
 * body, else implicit), bundle-local schema paths, and variable mapping.
 *
 * Input helpers take the operation $context: when the operation runs as an
 * MCP tool, inputs come from $context['mcp_data'] instead of the request (see
 * OperationInputTrait). Without a context the request is read as before.
 *
 * MCP input contract (tool arguments):
 *  - JSON-body operations: the body properties as top-level arguments, plus
 *    the operation's URI variables (e.g. "id"), which are not part of the body;
 *  - query-only options (e.g. "cascade" on delete): top-level arguments;
 *  - apiConfiguration: top-level argument, a selector string or {uuid}; any
 *    other value (e.g. {"uuid": 5}) is rejected, not read as "no selection";
 *  - uploads: "name" (file name incl. extension) and "content" (file content
 *    as string; set "contentEncoding": "base64" for binary content), plus
 *    the optional form fields as deploymentName, deploymentSource, category,
 *    tenantId (multipart keeps deployment-name / deployment-source).
 *
 * Uploads follow UploadRule: the file name rule over every transport, the
 * 1 MiB limit for decoded inline (MCP) content.
 */
abstract class AbstractFlowableProcessor
{
    use OperationInputTrait;

    /** Multipart form field => MCP tool argument, where the names differ. */
    private const MCP_UPLOAD_FIELDS = [
        'deployment-name' => 'deploymentName',
        'deployment-source' => 'deploymentSource',
    ];

    /**
     * The input file of this processor's operations, relative to
     * src/ApiResource (e.g. 'FlowTask/complete.input.json'), the one place
     * that names it: {@see self::validatedBody()} validates the body of
     * every operation the processor serves against it, HTTP and MCP alike,
     * and an MCP tool served by the processor publishes the same constant as
     * its input file (`McpToolInputSchemaFactory::EXTRA_KEY => ['input' =>
     * TaskCompleteProcessor::INPUT_SCHEMA]`), so the published and the
     * enforced schema have one source. Null for a processor that validates no
     * body against a file (deletes, uploads).
     */
    public const ?string INPUT_SCHEMA = null;

    /** Built on first use from $flowableLogger, so the constructor stays as it is. */
    private ?FlowableAuditLogger $auditLogger = null;

    public function __construct(
        protected readonly FlowableClientLocator $locator,
        protected readonly RequestStack $requestStack,
        protected readonly InputSchemaValidator $validator,
        protected readonly FlowableVariableMapper $variableMapper,
        protected readonly ActingUserResolver $actingUser,
        protected readonly LoggerInterface $flowableLogger,
    ) {
    }

    /**
     * Run the engine call of this write with its audit entries (attempt, then
     * success or failure; see {@see FlowableAuditLogger::audited()}),
     * attributed to the authenticated user. Call it after permission checks
     * and input validation, with only the engine call (and the mapping of its
     * answer) in $call: an exception thrown before writes no entry, one
     * thrown by $call a failure entry, and is rethrown unchanged.
     *
     * The origin comes from the operation context: API Platform's MCP handler
     * (ApiPlatform\Mcp\Server\Handler) puts the JSON-RPC request under
     * "mcp_request" — besides "request", which then is the transport request
     * to the MCP endpoint — so "mcp_request" is checked first; for a tool
     * call it is a CallToolRequest whose name is the tool name. Without it
     * the write came in over HTTP.
     *
     * @template T
     * @param array<string,mixed> $context operation context
     * @param array<string,mixed> $auditContext what is known before the call
     * @param \Closure(): T $call the engine call
     * @param (\Closure(T): array<string,mixed>)|null $successContext keys derived from the result
     * @return T
     */
    protected function audited(string $action, array $context, array $auditContext, \Closure $call, ?\Closure $successContext = null): mixed
    {
        $mcpRequest = $context['mcp_request'] ?? null;

        $this->auditLogger ??= new FlowableAuditLogger($this->flowableLogger);

        return $this->auditLogger->audited(
            $action,
            $mcpRequest !== null ? FlowableAuditLogger::VIA_MCP : FlowableAuditLogger::VIA_HTTP,
            $this->actingUser->currentUserId(),
            $mcpRequest instanceof CallToolRequest ? $mcpRequest->name : null,
            $auditContext,
            $call,
            $successContext,
        );
    }

    /**
     * Raw JSON body: the request content over HTTP; for an MCP tool call the
     * tool arguments minus the URI variables, re-encoded as a JSON object.
     *
     * The MCP SDK decodes tool arguments into PHP arrays, which turns a
     * nested {} into []. Empty arrays are therefore re-encoded as {} at every
     * level ({@see McpToolInputValidator::jsonObject()}), so an object-typed
     * argument such as eventPayload: {} passes the schema as it does over
     * HTTP. validateRaw() returns the associative decoding, so the processors
     * see the same data.
     *
     * @param array<string,mixed> $context operation context (MCP arguments)
     */
    protected function rawBody(array $context = []): string
    {
        $mcpBody = $this->mcpBody($context);
        if ($mcpBody !== null) {
            return json_encode(
                McpToolInputValidator::jsonObject($mcpBody),
                \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
            );
        }

        return $this->requestStack->getCurrentRequest()?->getContent() ?? '';
    }

    /**
     * Resolve the client: an explicit apiConfiguration parameter (query string,
     * or MCP argument) wins over one in the body, else implicit resolution.
     *
     * @param array<string,mixed> $body decoded request body (may be empty)
     * @param array<string,mixed> $context operation context (MCP arguments)
     */
    protected function client(array $body = [], array $context = []): FlowableClientInterface
    {
        $id = self::configurationId($this->inputParam('apiConfiguration', $context));
        if ($id === null) {
            $id = self::configurationId($body['apiConfiguration'] ?? null);
        }

        return $this->locator->resolve($id);
    }

    /**
     * The deployable resource of an upload operation: the multipart "file"
     * part over HTTP, the inline name/content arguments for an MCP tool call.
     * The file name must follow {@see UploadRule::assertFileName()} and
     * {@see UploadRule::assertResourceSuffix()} with $allowedExtensions;
     * decoded inline content is limited by
     * {@see UploadRule::assertInlineSize()}.
     *
     * @param array<string,mixed> $context operation context (MCP arguments)
     * @param list<string> $allowedExtensions
     * @param string $extensionHint appended to the unsupported-extension error
     * @return array{name:string,content:string}
     */
    protected function uploadedResource(array $context, array $allowedExtensions, string $extensionHint = ''): array
    {
        $mcp = $this->mcpData($context);
        if ($mcp !== null) {
            $filename = \is_string($mcp['name'] ?? null) ? $mcp['name'] : '';
            if ($filename === '') {
                throw new BadRequestHttpException('Missing "name" argument (file name including extension).');
            }
            UploadRule::assertFileName($filename, '"name"');
            $content = UploadRule::decodeInlineContent(
                $mcp['content'] ?? null,
                $mcp['contentEncoding'] ?? null,
                '"content"',
                'Missing "content" argument (file content).',
                '"content" is not valid base64.',
                'Unsupported "contentEncoding"; only "base64" is accepted.',
            );
        } else {
            $file = $this->requestStack->getCurrentRequest()?->files->get('file');
            if (!$file instanceof UploadedFile) {
                throw new BadRequestHttpException('Missing multipart "file" part.');
            }
            if (!$file->isValid()) {
                throw new BadRequestHttpException(sprintf('Upload failed: %s', $file->getErrorMessage()));
            }

            $filename = $file->getClientOriginalName();
            if ($filename === '') {
                throw new BadRequestHttpException('Uploaded file has no name.');
            }
            UploadRule::assertFileName($filename, 'The uploaded file name');
            $content = null;
        }

        UploadRule::assertResourceSuffix($filename, $allowedExtensions, $extensionHint);

        // Read the upload only once the name has been accepted (as before).
        $content ??= (string) file_get_contents($file->getPathname());

        return ['name' => $filename, 'content' => $content];
    }

    /**
     * Optional upload form fields that are set and non-empty, keyed by their
     * multipart name. Over MCP they are read from the tool arguments under the
     * names in MCP_UPLOAD_FIELDS (camelCase), others under the same name.
     *
     * @param array<string,mixed> $context operation context (MCP arguments)
     * @param list<string> $fields multipart field names
     * @return array<string,string>
     */
    protected function uploadFields(array $context, array $fields): array
    {
        $mcp = $this->mcpData($context);
        $form = $this->requestStack->getCurrentRequest()?->request;

        $out = [];
        foreach ($fields as $field) {
            $value = $mcp !== null
                ? ($mcp[self::MCP_UPLOAD_FIELDS[$field] ?? $field] ?? null)
                : $form?->get($field);
            if (\is_scalar($value) && $value !== '') {
                $out[$field] = (string) $value;
            }
        }

        return $out;
    }

    /**
     * Absolute path of this processor's input file ({@see self::INPUT_SCHEMA}),
     * or null when it validates no body against a file.
     */
    public static function inputSchemaPath(): ?string
    {
        return static::INPUT_SCHEMA === null ? null : InputSchemaPath::relative(static::INPUT_SCHEMA);
    }

    /**
     * The raw body ({@see self::rawBody()}) validated against the full input
     * file of this processor ({@see self::INPUT_SCHEMA}), decoded.
     *
     * @param array<string,mixed> $context operation context (MCP arguments)
     * @return array<string,mixed>
     */
    protected function validatedBody(array $context): array
    {
        $path = static::inputSchemaPath()
            ?? throw new \LogicException(sprintf('%s declares no INPUT_SCHEMA.', static::class));

        return $this->validator->validateRaw($this->rawBody($context), $path);
    }

    /**
     * Map request variables and append a non-spoofable actor marker.
     *
     * Flowable 7.2 REST ignores a body startUserId on the runtime start
     * endpoint (it derives the start user from the authenticated REST user),
     * so the za7 actor is recorded as a process variable instead — the
     * fallback anticipated in the design risk notes (verified 2026-06-16).
     *
     * @param array<string,mixed> $body
     * @return list<array{name:string,value:mixed,type:string}>
     */
    protected function variablesWithActor(array $body, string $marker = 'triggeredBy'): array
    {
        $variables = $this->variableMapper->toFlowable($body['variables'] ?? null);
        $actor = $this->actingUser->currentUserId();
        if ($actor !== null) {
            $variables[] = ['name' => $marker, 'value' => $actor, 'type' => 'string'];
        }

        return $variables;
    }
}
// - revised 2026-10-09 (nested {} in MCP arguments)
// - revised 2026-11-26 (upload file name rule and inline size limit, strict apiConfiguration value, MCP input validator)
// - revised 2026-11-26 (schema path built by Schema\InputSchemaPath)
// - revised 2026-11-26 (inline content decoding moved to UploadRule::decodeInlineContent)
// - revised 2026-11-26 (configurationId() moved to OperationInputTrait)
// - revised 2026-11-26 (audit entry written by Service\FlowableAuditLogger)
// - revised 2026-11-26 (audited(): attempt, success and failure entries with origin and MCP tool name)
// - revised 2026-11-26 (per-provider MCP input validation removed, done centrally)
// - revised 2026-11-26 (INPUT_SCHEMA, inputSchemaPath() and validatedBody() replace schemaPath(): one source for the published and the enforced input file)
// - revised 2026-11-26 (resource suffix checked by UploadRule::assertResourceSuffix, full suffix)
