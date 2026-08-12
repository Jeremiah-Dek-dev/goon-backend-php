<?php

namespace App\Command;

use App\Service\AdminService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


#[AsCommand(
    name: 'app:ensure-super-admin',
    description: 'Creates the initial super admin if none exists'
)]
class EnsureSuperAdminCommand extends Command
{
    public function __construct(
        private readonly AdminService $adminService
    ) {
        parent::__construct();
    }


    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {

        $this->adminService->ensureSuperAdminExists();


        $output->writeln(
            '<info>Super admin verification completed.</info>'
        );


        return Command::SUCCESS;
    }
}