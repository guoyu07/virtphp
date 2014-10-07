<?php

/*
 * This file is part of virtPHP.
 *
 * (c) Jordan Kasper <github @jakerella>
 *     Ben Ramsey <github @ramsey>
 *     Jacques Woodcock <github @jwoodcock>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Virtphp\Console;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Virtphp\Command;
use Virtphp\Factory;
use Virtphp\System\Shell\Bash;
use Virtphp\System\ShellInterface;
use Virtphp\Util\ErrorHandler;
use Virtphp\Virtphp;

/**
 * The console application that handles the commands
 */
class Application extends BaseApplication
{
    /**
     * virtPHP ASCII logo
     */
    private static $logo = '        _      __  ____  __  ______
 _   __(_)____/ /_/ __ \/ / / / __ \
| | / / / ___/ __/ /_/ / /_/ / /_/ /
| |/ / / /  / /_/ ____/ __  / ____/
|___/_/_/   \__/_/   /_/ /_/_/
';

    protected $shell;

    public static $testPhpVersion = false;

    public function __construct()
    {
        if (function_exists('ini_set')) {
            ini_set('xdebug.show_exception_trace', false);
            ini_set('xdebug.scream', false);
        }

        if (function_exists('date_default_timezone_set')
            && function_exists('date_default_timezone_get')
        ) {
            date_default_timezone_set(@date_default_timezone_get());
        }

        ErrorHandler::register();
        parent::__construct('virtPHP', Virtphp::VERSION);
    }

    /**
     * {@inheritDoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $output) {
            $styles = Factory::createAdditionalStyles();
            $formatter = new OutputFormatter(null, $styles);
            $output = new ConsoleOutput(
                ConsoleOutput::VERBOSITY_NORMAL,
                null,
                $formatter
            );
        }

        return $this->parentRun($input, $output);
    }

    /**
     * {@inheritDoc}
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        if (version_compare(PHP_VERSION, '5.3.3', '<') || self::$testPhpVersion) {
            $output->writeln(
                '<warning>virtPHP only officially supports PHP 5.3.3 and above,'
                . ' you will most likely encounter problems with your PHP '
                . PHP_VERSION
                . ', upgrading is strongly recommended.</warning>'
            );
        }

        if (defined('VIRTPHP_DEV_WARNING_TIME')
            && $this->getCommandName($input) !== 'self-update'
        ) {
            if (time() > VIRTPHP_DEV_WARNING_TIME) {
                $output->writeln(
                    sprintf(
                        '<warning>Warning: This development build of virtPHP is over 30 days old.'
                        . " It is recommended to update it by running \"%s self-update\""
                        . ' to get the latest version.</warning>',
                        $_SERVER['PHP_SELF']
                    )
                );
            }
        }

        $result = $this->parentDoRun($input, $output);

        return $result;
    }

    /**
     * Return all help information and the virtPHP ASCII logo
     *
     * @return string
     */
    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }

    /**
     * Initializes all the composer commands we have
     *
     * @return array The array of *Command classes
     */
    protected function getDefaultCommands()
    {
        $commands   = parent::getDefaultCommands();
        $commands[] = new Command\CreateCommand();
        $commands[] = new Command\CloneCommand();
        $commands[] = new Command\DestroyCommand();
        $commands[] = new Command\ShowCommand();

        return $commands;
    }

    /**
     * {@inheritDoc}
     */
    public function getLongVersion()
    {
        return parent::getLongVersion() . ' ' . Virtphp::RELEASE_DATE;
    }

    /**
     * @return ShellInterface
     */
    public function getShell(){
        if(!$this->shell){
            $this->shell = new Bash();
        }
        return $this->shell;
    }

    /**
     * Calls the parent run() method; used to mock in tests
     *
     * @codeCoverageIgnore
     */
    protected function parentRun($input, $output)
    {
        return parent::run($input, $output);
    }

    /**
     * Calls the parent doRun() method; used to mock in tests
     *
     * @codeCoverageIgnore
     */
    protected function parentDoRun($input, $output)
    {
        return parent::doRun($input, $output);
    }
}
