<?php
// file generated with AI assistance: Claude Code - 2026-07-29 15:09:23 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

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
    /** Extensions the event registry interprets as deployable resources. */
    private const ALLOWED_EXTENSIONS = ['event', 'channel'];

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
            $filename = basename((string) $path);
            $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
            if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                throw new \RuntimeException(sprintf(
                    'Unsupported resource extension ".%s". Allowed: %s (the event registry accepts no .bar/.zip bundles).',
                    $extension,
                    implode(', ', self::ALLOWED_EXTENSIONS),
                ));
            }
            $this->requireActingUser($input);
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

            return $this->renderItem($io, $client->createEventDeployment($filename, $content, $query));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
