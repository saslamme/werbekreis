<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CompanyImageStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:company-images:cleanup', description: 'List orphaned company images older than one hour; --delete removes them.')]
final class CompanyImageCleanupCommand extends Command
{
    public function __construct(private readonly CompanyImageStorage $storage)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('delete', mode: InputOption::VALUE_NONE, description: 'Remove the listed unused files. Default is dry-run.');
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $files = $this->storage->cleanup((bool) $input->getOption('delete'));
        if ($files !== []) {
            $io->listing($files);
        }
        $io->success(sprintf('%d orphaned images %s.', count($files), $input->getOption('delete') ? 'processed for deletion' : 'found (dry-run)'));

        return Command::SUCCESS;
    }
}
