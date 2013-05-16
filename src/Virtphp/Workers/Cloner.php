<?php

/*
 * This file is part of VirtPHP.
 *
 * (c) Jordan Kasper <github @jakerella> 
 *     Ben Ramsey <github @ramsey>
 *     Jacques Woodcock <github @jwoodcock> 
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Virtphp\Workers;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;


class Cloner
{

    /** 
     * @var string
     */
    protected $originalPath = array();

    /** 
     * @var string
     */
    protected $fullPath;

    /** 
     * @var string
     */
    protected $envName;

    /** 
     * @var string
     */
    protected $realPath;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Constructs the clone worker
     *
     * @param string $originalPath
     * @param string $envName
     * @param OutputInterface $output
     */
    public function __construct($originalPath, $envName, OutputInterface $output)
    {
        $this->originalPath = realpath($originalPath);
        $this->envName = $envName;
        $this->realPath = realpath($envName);
        $this->filesystem = new Filesystem();
        $this->output = $output;
    }

    /**
     * Function is the guts of the worker, reading the provided
     * directory and copying those files over.
     *
     * @return boolean Whether or not the action was successful
     */
    public function execute()
    {
        $this->output->writeln("<comment>Creating new virtPHP env.</comment>");
        $this->filesystem->mkdir($this->envName);

        try {

            $this->cloneEnv();
            $this->updateActivateFile();
            $this->updatePhpIni();
            $this->createPhpBinWrapper();
            $this->sourcePear();

            return true;

        } catch (Exception $e) {
            $this->filesystem->remove($this->realPath);
            $this->output->writeln("<error>Error: cloning directory failed.</error>");

            return false;
        }

    }

    /**
     * Function gets the real path value of new virtPHP environment
     * copies over all the files and folders to the new virtPHP environment
     * and creates the fullPath property. 
     */
    protected function cloneEnv()
    {
        $this->output->writeln("<comment>Copying contents of " . $this->originalPath . ".</comment>");
        $this->filesystem->mirror($this->originalPath, $this->realPath);
    }

    /**
     * Function takes the contents of the original activate file, replaces the path
     * with a reference to the new virtPHP, deletes the file, then saves an updated
     * version.
     */
    protected function updateActivateFile()
    {
        $this->output->writeln('<comment>Updating activate file.</comment>');
        // Get paths for files and folers
        $binPath = $this->realPath . DIRECTORY_SEPARATOR . 'bin'; 
        $activateFilePath = $binPath . DIRECTORY_SEPARATOR . 'activate.sh'; 

        // GET activate of new directory to replace path variable
        $originalContents = file_get_contents($activateFilePath);

        // Replace paths from old env to new cloned env
        $newContents = str_replace($this->originalPath, $binPath, $originalContents);

        // remove file to avoide collision
        $this->filesystem->remove($activateFilePath);

        // Write actiave file again
        $this->filesystem->dumpFile($activateFilePath, $newContents, 0644);
    }

    /**
     * Updates paths in new php.ini file
     */
    protected function updatePhpIni()
    {
        $this->output->writeln('<comment>Updating PHP ini file.</comment>');
        // Get paths for files and folers
        $sharePath = DIRECTORY_SEPARATOR . 'share' . DIRECTORY_SEPARATOR . 'php';
        $libPath = DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'php'; 
        $iniPHPLocation = $this->realPath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'php.ini'; 

        $phpIni = file_get_contents($iniPHPLocation);

        $phpIni = str_replace(
            $this->originalPath . $sharePath,
            $this->realPath . $sharePath,
            $phpIni
        );

        $phpIni = str_replace(
            $this->originalPath . $sharePath,
            $this->realPath . $sharePath,
            $phpIni
        );

        $this->filesystem->dumpFile(
            $iniPHPLocation,
            $phpIni,
            0644
        );
    }

    /**
     * Creates new PHP bin wrapper with new paths
     */
    protected function createPhpBinWrapper()
    {
        $this->output->writeln('<comment>Updating PHP bin wrapper.</comment>');
        $phpBinWrapPath = $this->realPath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php';
        $newIniPath = $this->realPath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'php.ini';

        $currentWrapper = file_get_contents($phpBinWrapPath);

        $newWrapper = str_replace(
            $this->originalPath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'php.ini',
            $this->realPath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'php.ini',
            $currentWrapper
        );
 
        $this->filesystem->dumpFile(
            $this->realPath . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'php',
            $newWrapper,
            0644
        );
    }

    /**
     * Updates PEAR and config settings for new environment
     */
    protected function sourcePear()
    {
        $this->output->writeln('<comment>Updating Pear</comment>');

        $pearConfigContents = file_get_contents($this->realPath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'pear.conf');
        $pearConfigArray = unserialize($pearConfigContents);

        $newPearConfig = serialize($this->processConfigSettings($pearConfigArray));

        $this->filesystem->dumpFile(
            $this->realPath . DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'pear.conf',
            $newPearConfig,
            0644
        );
    }

    /**
     * Replaces original path with new path in pear config file
     * 
     * @param  array $pearConfig The old array of config options
     * @return array The new array of config options
     */
    protected function processConfigSettings(array $pearConfig = array())
    {
        foreach($pearConfig as $key => &$value) {
            if (is_array($value)) {
                $value = $this->processConfigSettings($value);
            }
            if (is_string($value)) {
                $value = str_replace($this->originalPath, $this->realPath, $value);
            }
        }

        return $pearConfig;
    }

}