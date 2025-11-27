<?php

namespace OpenTimestamps\CLI;

require_once __DIR__ . '/../../vendor/autoload.php';

use OpenTimestamps\TimestampFile\TimestampFile;
use OpenTimestamps\Calendar\CalendarClient;
use OpenTimestamps\Ops\SHA256Op;
use OpenTimestamps\Ops\AppendOp;
use OpenTimestamps\Ops\PrependOp;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CLI {
    public static function run(): void {
        $app = new Application('OpenTimestamps PHP');
        $app->add(new StampCommand());
        // Additional commands like verify, info, upgrade can be added here
        $app->run();
    }
}

CLI::run();
