<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use Dmstr\Flowable\Client\FlowableClientInterface;
use Dmstr\Flowable\Client\FlowableClientLocator;
use Dmstr\Flowable\Service\ActingUserResolver;
use Dmstr\Flowable\Service\FlowableVariableMapper;
use Dmstr\Flowable\Service\InputSchemaValidator;
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
 *  - apiConfiguration: top-level argument, a (partial) UUID string or {uuid};
 *  - uploads: "name" (file name incl. extension) and "content" (file content
 *    as string; set "contentEncoding": "base64" for binary .bar/.zip), plus
 *    the optional form fields as deploymentName, deploymentSource, category,
 *    tenantId (multipart keeps deployment-name / deployment-source).
 */
abstract class AbstractFlowableProcessor
{
    use OperationInputTrait;

    /** Multipart form field => MCP tool argument, where the names differ. */
    private const MCP_UPLOAD_FIELDS = [
        'deployment-name' => 'deploymentName',
        'deployment-source' => 'deploymentSource',
    ];

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
     * @param array<string,mixed> $context
     */
    protected function audit(string $action, array $context = []): void
    {
        $this->flowableLogger->info('flowable.'.$action, $context + ['actor' => $this->actingUser->currentUserId()]);
    }

    /**
     * Raw JSON body: the request content over HTTP; for an MCP tool call the
     * tool arguments minus the URI variables, re-encoded as a JSON object.
     *
     * @param array<string,mixed> $context operation context (MCP arguments)
     */
    protected function rawBody(array $context = []): string
    {
        $mcpBody = $this->mcpBody($context);
        if ($mcpBody !== null) {
            return json_encode((object) $mcpBody, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
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
     * The file name must carry one of $allowedExtensions.
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
            if (!\is_string($mcp['content'] ?? null) || $mcp['content'] === '') {
                throw new BadRequestHttpException('Missing "content" argument (file content).');
            }
            $content = $mcp['content'];
            $encoding = $mcp['contentEncoding'] ?? null;
            if ($encoding === 'base64') {
                $content = base64_decode($content, true);
                if ($content === false) {
                    throw new BadRequestHttpException('"content" is not valid base64.');
                }
            } elseif ($encoding !== null && $encoding !== '') {
                throw new BadRequestHttpException('Unsupported "contentEncoding"; only "base64" is accepted.');
            }
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
            $content = null;
        }

        $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
        if (!\in_array($extension, $allowedExtensions, true)) {
            throw new BadRequestHttpException(sprintf(
                'Unsupported resource extension ".%s". Allowed: %s%s.',
                $extension,
                implode(', ', $allowedExtensions),
                $extensionHint,
            ));
        }

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

    /** ApiConfiguration selector as id string: a (partial) UUID or {uuid}. */
    private static function configurationId(mixed $ref): ?string
    {
        $id = \is_array($ref) ? ($ref['uuid'] ?? null) : $ref;

        return \is_string($id) && $id !== '' ? $id : null;
    }

    protected function schemaPath(string $entity, string $verb): string
    {
        return \dirname(__DIR__).'/ApiResource/'.$entity.'/'.$verb.'.input.json';
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
