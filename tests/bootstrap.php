<?php

declare(strict_types=1);

use AnzuSystems\CommonBundle\Tests\AnzuTestKernel;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// these annotations are not required, they are optional
AnnotationReader::addGlobalIgnoredNamespace('OpenApi');
AnnotationReader::addGlobalIgnoredNamespace('Nelmio');

$kernel = new AnzuTestKernel(
    appNamespace: 'petitpress',
    appSystem: 'commonbundle',
    appVersion: 'dev',
    appReadOnlyMode: false,
    environment: 'test',
    debug: false,
);
$kernel->boot();

$app = new Application($kernel);
$app->setAutoExit(false);

$output = new ConsoleOutput();

# Clear cache
$input = new ArrayInput([
    'command' => 'cache:clear',
    '--no-warmup' => true,
    '--env' => getenv('APP_ENV'),
]);
$input->setInteractive(false);
$app->run($input, $output);

# Database drop
$input = new ArrayInput([
    'command' => 'doctrine:database:drop',
    '--force' => true,
    '--if-exists' => true,
]);
$input->setInteractive(false);
$app->run($input, $output);

# Database create
$input = new ArrayInput([
    'command' => 'doctrine:database:create',
]);
$input->setInteractive(false);
$app->run($input, $output);

# Update schema
$input = new ArrayInput([
    'command' => 'doctrine:schema:update',
    '--force' => true,
    '--complete' => true,
]);
$input->setInteractive(false);
$app->run($input, $output);

# Database fixtures
$input = new ArrayInput([
    'command' => 'anzusystems:fixtures:generate',
]);
$input->setInteractive(false);
$app->run($input, $output);
