<?php
namespace Deployer;

use Symfony\Component\Console\Input\InputOption;
use TheRat\SymDep\BuildType;

/*
 * Entry recipe for symdep. Passed to `dep -f` by bin/symdep.
 *
 * The Deployer 7 PHAR doesn't load the consuming project's composer
 * autoload by itself, so we do it here. Without this, classes under the
 * TheRat\SymDep\* namespace would not resolve inside the dep process.
 */
$symdepAutoload = getenv('SYMDEP_USER_AUTOLOAD');
if ($symdepAutoload && is_readable($symdepAutoload)) {
    require_once $symdepAutoload;
}

option(
    'build-type',
    't',
    InputOption::VALUE_REQUIRED,
    'Deploy strategy (build type), D|T|P',
    BuildType::TYPE_DEV
);

option(
    'skip-branch',
    null,
    InputOption::VALUE_NONE,
    'Skip branch detection'
);

// Deployer\option() only adds to the per-task inputDefinition. Mirror the
// options into the application's global definition so built-in console
// commands (list, tree, ssh, run, ...) don't reject them when symdep wraps
// invocations like `bin/symdep --build-type=p tree deploy`.
$console = Deployer::get()->getConsole();
foreach (['build-type', 'skip-branch'] as $opt) {
    $definition = $console->getDefinition();
    if (!$definition->hasOption($opt)) {
        $definition->addOption(
            Deployer::get()->inputDefinition->getOption($opt)
        );
    }
}

$helper = new BuildType();
$buildType = $helper->getType();

require_once 'recipe/common.php';

// Defaults that used to come from Deployer 6's recipe/symfony3.php.
set('bin_dir', 'bin');
set('var_dir', 'var');
set('bin/console', '{{release_path}}/{{bin_dir}}/console');
set('composer_action', 'install');
set('console_options', '--no-interaction --env={{symfony_env}}');

// Environment vars
set('env', []);
set('build_type', $buildType);
set('symfony_env', '{{build_type}}');

require_once __DIR__ . '/general.php';
require_once __DIR__ . '/' . $helper->getRecipeFile($buildType);

// Finally pull in the consumer's deploy.php (host inventory, repository, etc.).
$symdepUserDeployFile = getenv('SYMDEP_USER_DEPLOY_FILE');
if ($symdepUserDeployFile && is_readable($symdepUserDeployFile)) {
    set('deploy_file', $symdepUserDeployFile);
    require $symdepUserDeployFile;
}
