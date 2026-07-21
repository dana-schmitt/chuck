<?php

namespace App\Command;

use App\Services\JokeOfTheDaySelector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:joke-of-the-day:select',
    description: "Selects (or reuses) today's Joke of the Day. Intended to run once daily via cron.",
)]
class SelectJokeOfTheDayCommand extends Command
{
    public function __construct(
        private readonly JokeOfTheDaySelector $selector,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $jokeOfTheDay = $this->selector->selectForToday();

        $io->success(sprintf(
            'Joke of the Day for %s: %s',
            $jokeOfTheDay->getDate()->format('Y-m-d'),
            $jokeOfTheDay->getJoke()->getJoke(),
        ));

        return Command::SUCCESS;
    }
}
