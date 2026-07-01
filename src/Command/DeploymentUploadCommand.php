<?php
// file generated with AI assistance: Claude Code - 2026-06-16 00:00:00 UTC

declare(strict_types=1);

namespace Dmstr\Flowable\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Mirrors POST /api/flowable/deployments/upload.
 *
 * Reads a local resource file (BPMN/DMN/form/.bar/.zip) and deploys it to the
 * engine. Like the REST upload there is no JSON input schema — the payload is
 * the file itself plus optional deployment metadata.
 */
#[AsCommand(name: 'flowable:deployments:upload', description: 'Deploy a local BPMN/DMN/form resource or .bar/.zip bundle')]
final class DeploymentUploadCommand extends AbstractFlowableCommand
{
    protected function configure(): void
    {
        $this->addApiConfigurationOption();
        $this->addOption('acting-user', 'u', InputOption::VALUE_REQUIRED, 'Acting za7 user UUID (recorded on the audit channel)');
        $this->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Path to the resource file to deploy');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Deployment name (defaults to the file name)');
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Deployment source marker');
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
            $this->requireActingUser($input);
            $client = $this->client($input);

            $filename = basename((string) $path);
            $fields = ['deployment-name' => (string) ($input->getOption('name') ?? $filename)];
            foreach (['source' => 'deployment-source', 'category' => 'category', 'tenant' => 'tenantId'] as $opt => $field) {
                $value = $input->getOption($opt);
                if ($value !== null && $value !== '') {
                    $fields[$field] = (string) $value;
                }
            }

            $content = (string) file_get_contents((string) $path);

            return $this->renderItem($io, $client->createDeployment($filename, $content, $fields));
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
