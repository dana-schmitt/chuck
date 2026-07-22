<?php

namespace App\Command;

use App\Entity\ApiToken;
use App\Repository\ApiTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api-token:create',
    description: 'Creates a new read-only API token for a non-browser client (e.g. the MCP server).',
)]
class CreateApiTokenCommand extends Command
{
    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('label', InputArgument::REQUIRED, 'A short label identifying who/what this token is for (e.g. "mcp-server")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rawToken = bin2hex(random_bytes(32));
        $this->apiTokenRepository->save(new ApiToken(ApiTokenRepository::hash($rawToken), $input->getArgument('label')));

        $io->success('API token created.');
        $io->writeln('Token (shown once - store it now): '.$rawToken);
        $io->note('Only the hash is stored. If this value is lost, create a new token instead - it cannot be recovered.');

        return Command::SUCCESS;
    }
}
