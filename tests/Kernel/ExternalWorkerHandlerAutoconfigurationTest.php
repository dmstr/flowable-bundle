<?php
// file generated with AI assistance: Claude Code - 2026-11-27 00:28:46 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Tests\Kernel;

use Dmstr\Flowable\Worker\ExternalWorkerHandlerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * A class that only implements ExternalWorkerHandlerInterface and is
 * registered as an ordinary autoconfigured service, without an attribute,
 * a tag or any configuration, is a handler of the registry: FlowableBundle
 * autoconfigures the interface with the tag `flowable.external_worker_handler`
 * and config/services.yaml hands the registry that tag as `!tagged_iterator`.
 *
 * The kernel with the option has its own environment and cache directory
 * ({@see TestKernel}), so the other kernel tests are not affected; the plain
 * kernel registers no handler.
 */
final class ExternalWorkerHandlerAutoconfigurationTest extends TestCase
{
    private ?KernelInterface $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        parent::tearDown();
    }

    public function testInterfaceOnlyHandlerIsInTheRegistry(): void
    {
        $registry = $this->registry([AutoconfiguredExternalWorkerHandler::class]);

        self::assertSame(AutoconfiguredExternalWorkerHandler::TOPICS, $registry->topics());
        foreach (AutoconfiguredExternalWorkerHandler::TOPICS as $topic) {
            self::assertTrue($registry->has($topic), $topic);
            self::assertInstanceOf(AutoconfiguredExternalWorkerHandler::class, $registry->get($topic));
        }
        self::assertFalse($registry->has('test.unknown'));
    }

    public function testWithoutAHandlerTheRegistryIsEmpty(): void
    {
        self::assertSame([], $this->registry([])->topics());
    }

    /**
     * @param list<class-string> $handlers
     */
    private function registry(array $handlers): ExternalWorkerHandlerRegistry
    {
        $this->kernel = new TestKernel(TestKernel::ENV_WITH_VOTER, true, ['external_worker_handlers' => $handlers]);
        $this->kernel->boot();

        $registry = $this->kernel->getContainer()->get(TestKernel::WORKER_HANDLER_REGISTRY);
        self::assertInstanceOf(ExternalWorkerHandlerRegistry::class, $registry);

        return $registry;
    }
}
