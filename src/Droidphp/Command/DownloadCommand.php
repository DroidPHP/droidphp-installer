<?php

/*
 * This file is part of the DroidPHP Installer package.
 *
 * (c) Shushant Kumar <shushantkumar786@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Droidphp\Command;

use Distill\Distill;
use Distill\Exception\IO\Input\FileCorruptedException;
use Distill\Exception\IO\Input\FileEmptyException;
use Distill\Exception\IO\Output\TargetDirectoryNotWritableException;
use Distill\Strategy\MinimumSize;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Subscriber\Progress\Progress;
use Symfony\Component\Console\Command\Command;

/**
 * Abstract command used by commands which download and extract compressed files.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 * @author Shushant Kumar <shushantkumar786@gmail.com>
 */
abstract class DownloadCommand extends Command
{
    protected function download()
    {
        $this->output->writeln(sprintf("\n Downloading %s...\n", $this->projectName));

        $distill = new Distill();
        $componentFile = $distill
            ->getChooser()
            ->setStrategy(new MinimumSize())
            ->addFile($this->remoteFileUrl)
            ->getPreferredFile();

        $downloadCallback = function ($expected, $total, $client, $request, $response) {
            // Don't initialize the progress bar for redirects as the size is much smaller
            if ($response->getStatusCode() >= 300) {
                return;
            }
            printf("    Download: %d %% \r", 100 * ($total / $expected));
        };
        $client = new Client();
        $client->getEmitter()->attach(new Progress(null, $downloadCallback));

        // store the file in a temporary hidden directory with a random name
        $this->downloadedFilePath = getcwd().DIRECTORY_SEPARATOR.'.'.uniqid(time()).DIRECTORY_SEPARATOR.$this->projectName.'.'.ltrim(strstr($componentFile, '.'), '.'); //pathinfo($symfonyArchiveFile, PATHINFO_EXTENSION);

        try {
            $response = $client->get($componentFile);
        } catch (ClientException $e) {
            throw new \RuntimeException(sprintf(
                "There was an error downloading %s from server:\n%s",
                $this->getDownloadedFileName(),
                $e->getMessage()
            ));
        }

        $this->fs->dumpFile($this->downloadedFilePath, $response->getBody());
        $this->output->writeln("\n");

        return $this;
    }

    /**
     * Extracts the compressed file (ZIP or TGZ) using the
     * native operating system commands if available or PHP code otherwise.
     *
     * @return DownloadCommand
     *
     * @throws \RuntimeException if the downloaded archive could not be extracted
     */
    protected function extract()
    {
        $this->output->writeln(sprintf(" Extracting <info>%s</info>\n", $this->projectName));

        try {
            $distill = new Distill();
            $extractionSucceeded = $distill->extractWithoutRootDirectory($this->downloadedFilePath, $this->projectDir);
        } catch (FileCorruptedException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted.\n".
                "To solve this issue, try executing this command again:\n%s",
                $this->getDownloadedFileName(), $this->getExecutedCommand()
            ));
        } catch (FileEmptyException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is empty.\n".
                "To solve this issue, try executing this command again:\n%s",
                $this->getDownloadedFileName(), $this->getExecutedCommand()
            ));
        } catch (TargetDirectoryNotWritableException $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the installer doesn't have enough\n".
                "permissions to uncompress and rename the package contents.\n".
                "To solve this issue, check the permissions of the %s directory and\n".
                "try executing this command again:\n%s",
                $this->getDownloadedFileName(), getcwd(), $this->getExecutedCommand()
            ));
        } catch (\Exception $e) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted\n".
                "or because the installer doesn't have enough permissions to uncompress and\n".
                "rename the package contents.\n".
                "To solve this issue, check the permissions of the %s directory and\n".
                "try executing this command again:\n%s",
                $this->getDownloadedFileName(), getcwd(), $this->getExecutedCommand()
            ));
        }

        if (!$extractionSucceeded) {
            throw new \RuntimeException(sprintf(
                "%s can't be installed because the downloaded package is corrupted\n".
                "or because the uncompress commands of your operating system didn't work.",
                $this->getDownloadedFileName()
            ));
        }

        return $this;
    }

    /**
     * Utility method to show the number of bytes in a readable format.
     *
     * @param int $bytes The number of bytes to format
     *
     * @return string The human readable string of bytes (e.g. 4.32MB)
     */
    protected function formatSize($bytes)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = $bytes ? floor(log($bytes, 1024)) : 0;
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return number_format($bytes, 2).' '.$units[$pow];
    }

    /**
     * Generates a good random value.
     *
     * @return string
     */
    protected function generateRandomSecret()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            return hash('sha1', openssl_random_pseudo_bytes(23));
        }

        return hash('sha1', uniqid(mt_rand(), true));
    }

    /**
     * Returns the executed command with all its arguments
     * (e.g. "droidphp build:setup package platform").
     *
     * @return string
     */
    protected function getExecutedCommand()
    {
        $commandBinary = $_SERVER['PHP_SELF'];
        $commandBinaryDir = dirname($commandBinary);
        $pathDirs = explode(PATH_SEPARATOR, $_SERVER['PATH']);
        if (in_array($commandBinaryDir, $pathDirs)) {
            $commandBinary = basename($commandBinary);
        }
        $commandName = $this->getName();

        return sprintf('%s %s', $commandBinary, $commandName);
    }

    /**
     * Checks whether the given directory is empty or not.
     *
     * @param string $dir the path of the directory to check
     *
     * @return bool
     */
    protected function isEmptyDirectory($dir)
    {
        // glob() cannot be used because it doesn't take into account hidden files
        // scandir() returns '.'  and '..'  for an empty dir
        return 2 === count(scandir($dir.'/'));
    }

    /**
     * Returns the name of the downloaded file in a human readable format.
     *
     * @return string
     */
    protected function getDownloadedFileName()
    {
        return basename($this->remoteFileUrl);
    }
}
