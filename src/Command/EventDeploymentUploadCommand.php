<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Dmstr\Flowable\State\EventDeploymentUploadProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/event_deployments/upload.
 *
 * Reads a local .event or .channel resource and deploys it to the event
 * registry. Like the REST upload there is no JSON input schema — the payload is
 * the file itself plus optional deployment metadata. The event registry accepts
 * no .bar/.zip bundles, so a deployment needing several resources is uploaded
 * one file per run.
 */
#[AsCommand(name: 'flowable:events:deployments:upload', description: 'Deploy a local .event or .channel resource to the event registry')]
final class EventDeploymentUploadCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addApiConfigurationOption();
        $this->addOption('acting-user', 'u', InputOption::VALUE_REQUIRED, 'Acting za7 user UUID (recorded on the audit channel)');
        $this->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to the .event or .channel resource file to deploy');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Deployment name (defaults to the file name)');
        $this->addOption('category', null, InputOption::VALUE_REQUIRED, 'Deployment category');
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant id');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $path = $input->getOption('file');
            if ($path === null || !is_file((string) $path)) {
                throw new \RuntimeException(sprintf('Resource file not found: %s', $path ?? '(none)'));
            }
            $filename = $this->uploadFileName(
                (string) $path,
                EventDeploymentUploadProcessor::ALLOWED_EXTENSIONS,
                EventDeploymentUploadProcessor::EXTENSION_HINT,
            );
            $actor = $this->requireActingUser($input);
            $client = $this->client($input);

            // Engine spelling: deploymentName (camelCase), read off the query string.
            $query = ['deploymentName' => (string) ($input->getOption('name') ?? $filename)];
            foreach (['category' => 'category', 'tenant' => 'tenantId'] as $opt => $key) {
                $value = $input->getOption($opt);
                if ($value !== null && $value !== '') {
                    $query[$key] = (string) $value;
                }
            }

            $content = (string) file_get_contents((string) $path);

            return $this->renderItem($io, $this->audited(
                'event_deployment.upload',
                $actor,
                ['file' => $filename, 'sha256' => hash('sha256', $content)],
                static fn (): array => $client->createEventDeployment($filename, $content, $query),
                static fn (array $deployment): array => ['deployment' => $deployment['id'] ?? null],
            ));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
// - revised 2026-11-26 (engine call wrapped in audited(): audit entries via cli)
// - revised 2026-11-26 (file name and suffix rule of the HTTP upload, extensions read from the processor)
