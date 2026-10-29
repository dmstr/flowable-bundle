<?php
// file generated with AI assistance: Claude Code - 2026-10-28 23:50:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Http;

use Dmstr\Flowable\Tests\Kernel\TestHeaderAuthenticator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends one entry of {@see PermissionCatalog::sensitiveOperations()}, as JSON
 * or as a multipart upload. For {@see ApiTestCase} subclasses.
 */
trait OperationRequests
{
    /**
     * @param array{string, string, string, ?array<string, mixed>, ?string} $operation
     * @param list<string> $roles
     */
    private function perform(array $operation, array $roles): Response
    {
        [$method, $uri, , $body, $upload] = $operation;
        if ($upload === null) {
            return $this->requestAs($method, $uri, $roles, $body);
        }

        $path = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, '<definitions/>');
        try {
            $this->client->request(
                $method,
                $uri,
                files: ['file' => new UploadedFile($path, $upload, null, null, true)],
                server: [
                    'HTTP_ACCEPT' => 'application/ld+json',
                    'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_ROLES)) => implode(',', $roles),
                    'HTTP_' . strtoupper(str_replace('-', '_', TestHeaderAuthenticator::HEADER_USER)) => TestHeaderAuthenticator::DEFAULT_USER,
                ],
            );
        } finally {
            @unlink($path);
        }

        return $this->client->getResponse();
    }
}
