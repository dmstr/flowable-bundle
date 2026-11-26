<?php
// file generated with AI assistance: Claude Code - 2026-10-08 23:26:51 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Dmstr\Flowable\ApiResource\FlowDeployment;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * MCP tool flowable_deploy_bundle (composite): deploys several inline files
 * (BPMN plus its form schemas, e.g. <formKey>.schema.json) as ONE process
 * deployment.
 *
 * The files are packed into a flat .bar (zip) archive in a temporary file
 * with ZipArchive and uploaded through the same client call as
 * POST /deployments/upload with a .bar file — Flowable unpacks the archive
 * into one deployment. The archive is never half-sent: an invalid file
 * (name, extension, duplicate, encoding, size) fails the tool before
 * anything reaches the engine.
 *
 * Limits (UploadRule): at most 20 files, 1 MiB per file and 4 MiB in total
 * after decoding; file names follow the shared upload file name rule.
 *
 * Decision tables (.dmn) are refused: a process deployment does not register
 * them in the DMN engine; flowable_dmn_deploy deploys them. The full suffix
 * counts (UploadRule::rejectedSuffix()): ".dmn.xml" and ".cmmn.xml", which
 * Flowable reads as DMN and CMMN resources, are refused like ".dmn" and
 * ".cmmn", while ".bpmn20.xml" and a plain ".xml" pass.
 *
 * @implements ProcessorInterface<mixed, FlowDeployment>
 */
final class DeploymentBundleProcessor extends AbstractFlowableProcessor implements ProcessorInterface
{
    public const string INPUT_SCHEMA = 'FlowDeployment/mcpBundle.input.json';

    /** Extensions accepted for files inside the bundle. */
    public const ENTRY_EXTENSIONS = ['bpmn', 'xml', 'form', 'json'];

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): FlowDeployment
    {
        $body = $this->validatedBody($context);
        $client = $this->client($body, $context);

        $files = self::files(\is_array($body['files'] ?? null) ? $body['files'] : []);
        $deploymentName = (string) ($body['deploymentName'] ?? '');
        $archiveName = self::archiveName($deploymentName);

        $fields = ['deployment-name' => $deploymentName];
        foreach (['deploymentSource' => 'deployment-source', 'category' => 'category', 'tenantId' => 'tenantId'] as $argument => $field) {
            if (\is_string($body[$argument] ?? null) && '' !== $body[$argument]) {
                $fields[$field] = $body[$argument];
            }
        }

        // Hash exactly the bytes that are sent to the engine.
        $archive = self::zip($files);

        return $this->audited(
            'deployment.bundle',
            $context,
            [
                'files' => array_keys($files),
                'fileSha256' => array_map(static fn (string $content): string => hash('sha256', $content), $files),
                'archive' => $archiveName,
                'archiveSha256' => hash('sha256', $archive),
            ],
            static fn (): FlowDeployment => FlowDeployment::fromApi($client->createDeployment($archiveName, $archive, $fields)),
            static fn (FlowDeployment $deployment): array => ['deployment' => $deployment->id],
        );
    }

    /**
     * Validate and decode the file list: at most UploadRule::MAX_BUNDLE_FILES
     * files, each name a plain file name (UploadRule::assertFileName()) with
     * a suffix of ENTRY_EXTENSIONS (UploadRule::rejectedSuffix(), full
     * suffix), each decoded content within
     * UploadRule::assertInlineSize(), all together within
     * UploadRule::MAX_BUNDLE_BYTES.
     *
     * @param array<mixed> $files
     * @return array<string,string> file name => content
     */
    public static function files(array $files): array
    {
        if ([] === $files) {
            throw new BadRequestHttpException('"files" must contain at least one file.');
        }
        if (\count($files) > UploadRule::MAX_BUNDLE_FILES) {
            throw new BadRequestHttpException(sprintf('"files" must contain at most %d files.', UploadRule::MAX_BUNDLE_FILES));
        }

        $out = [];
        $total = 0;
        foreach ($files as $index => $file) {
            $name = \is_array($file) && \is_string($file['name'] ?? null) ? $file['name'] : '';
            UploadRule::assertFileName($name, sprintf('files[%d].name', $index));
            $suffix = UploadRule::rejectedSuffix($name, self::ENTRY_EXTENSIONS);
            if (null !== $suffix) {
                throw new BadRequestHttpException(sprintf(
                    'files[%d]: unsupported extension "%s". Allowed: %s (deploy .dmn with flowable_dmn_deploy).',
                    $index,
                    $suffix,
                    implode(', ', self::ENTRY_EXTENSIONS),
                ));
            }
            if (isset($out[$name])) {
                throw new BadRequestHttpException(sprintf('files[%d]: duplicate file name "%s".', $index, $name));
            }

            $content = UploadRule::decodeInlineContent(
                $file['content'] ?? null,
                $file['contentEncoding'] ?? null,
                sprintf('files[%d].content', $index),
                sprintf('files[%d].content must be a non-empty string.', $index),
                sprintf('files[%d].content is not valid base64.', $index),
                sprintf('files[%d]: unsupported contentEncoding; only "base64" is accepted.', $index),
            );
            $total += \strlen($content);
            if ($total > UploadRule::MAX_BUNDLE_BYTES) {
                throw new BadRequestHttpException(sprintf(
                    'The files exceed %d bytes (4 MiB) in total after decoding.',
                    UploadRule::MAX_BUNDLE_BYTES,
                ));
            }

            $out[$name] = $content;
        }

        return $out;
    }

    /**
     * Pack the files into a flat zip archive (the .bar format Flowable reads).
     *
     * @param array<string,string> $files file name => content
     */
    public static function zip(array $files): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('flowable_deploy_bundle needs the PHP zip extension (ext-zip).');
        }

        $path = tempnam(sys_get_temp_dir(), 'flowable-bar-');
        if (false === $path) {
            throw new \RuntimeException('Cannot create a temporary file for the deployment archive.');
        }

        try {
            $zip = new \ZipArchive();
            if (true !== $zip->open($path, \ZipArchive::OVERWRITE)) {
                throw new \RuntimeException('Cannot open the deployment archive for writing.');
            }
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            if (!$zip->close()) {
                throw new \RuntimeException('Cannot write the deployment archive.');
            }

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /** Archive file name derived from the deployment name; always ends in .bar. */
    private static function archiveName(string $deploymentName): string
    {
        $stem = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $deploymentName), '-.');

        return ('' !== $stem ? $stem : 'bundle').'.bar';
    }
}
// - revised 2026-11-26 (shared upload file name rule, file count and size limits)
// - revised 2026-11-26 (inline content decoding moved to UploadRule::decodeInlineContent)
// - revised 2026-11-26 (engine call wrapped in audited(): attempt, success and failure entries)
// - revised 2026-11-26 (input file declared once in INPUT_SCHEMA, read by validatedBody() and the MCP tool)
// - revised 2026-11-26 (full suffix checked: .dmn.xml and .cmmn.xml refused)
