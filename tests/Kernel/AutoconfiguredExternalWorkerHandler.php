<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:28:46 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use Dmstr\Flowable\Worker\ExternalWorkerHandlerInterface;
use Dmstr\Flowable\Worker\ExternalWorkerJob;
use Dmstr\Flowable\Worker\ExternalWorkerOutcome;

/**
 * An external worker handler as an application writes it: it implements
 * the interface and nothing else, no attribute, no tag. Registered by the
 * `external_worker_handlers` option of {@see TestKernel}, see
 * {@see ExternalWorkerHandlerAutoconfigurationTest}.
 */
final class AutoconfiguredExternalWorkerHandler implements ExternalWorkerHandlerInterface
{
    public const array TOPICS = ['test.autoconfigured.alpha', 'test.autoconfigured.beta'];

    public function topics(): array
    {
        return self::TOPICS;
    }

    public function handle(ExternalWorkerJob $job): ExternalWorkerOutcome
    {
        return ExternalWorkerOutcome::complete();
    }
}
