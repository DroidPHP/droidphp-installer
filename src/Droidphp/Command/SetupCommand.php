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

use Droidphp\Collections\ArrayCollection;
use Droidphp\Library\GLibc;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class SetupCommand extends DownloadCommand
{
    /** @var Filesystem */
    protected $fs;
    protected $projectName;
    protected $projectDir;
    protected $baseDir;
    protected $remoteFileUrl;
    protected $downloadedFilePath;
    /** @var OutputInterface */
    protected $output;
    /** @var  ArrayCollection */
    protected $env;

    protected function configure()
    {
        $this
            ->setName('build:setup')
            ->setDescription('Creates a new SYSROOT to enable cross compiling for Android/ARM')
            ->addArgument('package', InputArgument::REQUIRED, 'Create new package')
            ->addArgument('toolchain', InputArgument::REQUIRED, 'Enter the path to toolchain with suffix (for example /path/bin/arm-none-linux-gnueabi)');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->env = new ArrayCollection();
        $this->output = $output;

        $directory = rtrim(trim($input->getArgument('package')), DIRECTORY_SEPARATOR);
        $this->baseDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd().DIRECTORY_SEPARATOR.$directory;

        $repo = $this->getRepository();
        $this->remoteFileUrl = $repo['glibc'];

        $projectName = str_replace(['.tar.gz', '.tar.bz2', '.tar.xz'], [''], basename($this->remoteFileUrl));
        $this->projectName = $projectName;

        $this->projectDir = $this->baseDir.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.$this->projectName;
        $this->downloadedFilePath = getcwd().DIRECTORY_SEPARATOR.'.'.uniqid(time()).DIRECTORY_SEPARATOR.$this->projectName;

        $this->env->set('_packageDir', $this->baseDir);

        if (!$this->fs->exists($cc = $input->getArgument('toolchain').'-gcc')) {
            throw new \Exception(sprintf('Unable to find "%s" with toolchain suffix : %s',
                    $cc,
                    $input->getArgument('toolchain'))
            );
        }

        $this->env->set('_toolchainDir', dirname($input->getArgument('toolchain')));
        $this->env->set('_toolchainName', $toolChainName = basename($input->getArgument('toolchain')));
        $this->env->set('PATH', $_SERVER['PATH'].':'.$this->env->get('_toolchainDir'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->downloadConfiguration();

        $library = new GLibc();
        try {
            $this
                ->download()
                ->extract();

            $library
                ->setEnv($this->env)
                ->setProjectDir($this->baseDir)
                ->initialize()
                ->boot($input, $output);

            $configure = $this->projectDir.'/'.$library->configure();
            $this->projectDir = dirname($this->projectDir).'/glibc-build';
            $this->fs->mkdir($this->projectDir);
            $this->output->write('    Building   :  ');
            $process = new Process($configure, $this->projectDir, $this->env->toArray());
            $process->setTimeout(0);
            $process->run();

            if ($process->isSuccessful()) {
                $process->setCommandLine('make -j4 && make -j4 install');
                $process->run();
            }
            if ($process->isSuccessful()) {
                $message = '<info>✔</info>';
            } else {
                $message = '<error>✕</error>';
            }

            $this->output->writeln($message);
        } catch (\Exception $e) {
            $this->cleanUp();
            throw $e;
        }
        $this->createConfiguration();

        $this->output->writeln(sprintf(
            " <info>%s</info>  Droidphp Installer <info>successfully configured</info> Now you can:\n".
            "    * Run :\n".
            "        1. Execute the <comment>%s build:components</comment> command.\n".
            " To Build the project\n\n",
            defined('PHP_WINDOWS_VERSION_BUILD') ? 'OK' : '✔',
            basename($_SERVER['PHP_SELF'])
        ));
    }

    private function getRepository()
    {
        $yaml = new Parser();
        try {
            return $yaml->parse(@file_get_contents($this->baseDir.'/libraries.yml'));
        } catch (ParseException $e) {
            throw new \Exception(sprintf('Unable to parse the YAML string: %s', $e->getMessage()));
        }
    }

    private function createConfiguration()
    {
        $this->output->write('    Updating   :  ');

        $envs = [
            'TOOLCHAIN' => $toolChainName = $this->env->get('_toolchainName'),
            'ROOTFS' => $rootfs = $this->baseDir.'/rootfs',
            'SYSROOT_PATH' => dirname($this->env->get('_packageDir')).'/sysroots/arm/sysroot/usr',

            'CC' => $toolChainName.'-gcc',
            'CXX' => $toolChainName.'-g++',
            'RANLIB' => $toolChainName.'-ranlib',
            'STRIP' => $toolChainName.'-strip',
            'LD' => $toolChainName.'-ld',
            'AR' => $toolChainName.'-ar',

            'CPPFLAGS' => sprintf('-I%s/include -I%s/usr/include ', $rootfs, $rootfs),
            'CFLAGS' => '-O2 ',
            'LDFLAGS' => sprintf('-L%s/lib -L%s/usr/lib ', $rootfs, $rootfs),
            'CXXFLAGS' => sprintf('-O2 -I%s/include ', $rootfs),
            'PATH' => $_SERVER['PATH'].':'.$this->env->get('_toolchainDir'),
            'ac_cv_func_malloc_0_nonnull' => 'yes',
            'ac_cv_func_realloc_0_nonnull' => 'yes',
        ];
        $dumper = new Dumper();
        $yaml = $dumper->dump($envs, 1);
        $this->fs->dumpFile($this->baseDir.'/environment.yml', $yaml);

        $this->output->writeln("\n");
    }

    private function downloadConfiguration()
    {
        $this->output->writeln("\n Please wait preparing project...".PHP_EOL);

        $client = new Client();

        $downloadBaseUrl = 'https://raw.github.com/droidphp/droidphp-installer/master/';
        $files = [
            'libraries.yml',
            'patch/glibc.patch',
            'patch/lighttpd_embadded_arm_support.diff',
            'patch/nginx-1.5.11.patch',
            'patch/php_proc_sh_fix.patch',
        ];
        foreach ($files as $remoteFile) {
            $this->output->writeln(sprintf('    Downloading <info>%s</info>  ', $remoteFile));
            $downloadedFile = $this->baseDir.'/'.$remoteFile;
            try {
                $response = $client->get($downloadBaseUrl.$remoteFile);
                $this->fs->dumpFile($downloadedFile, $response->getBody());
            } catch (ClientException $e) {
                throw new \RuntimeException(sprintf(
                    "There was an error downloading %s from server:\n%s",
                    $this->getDownloadedFileName(),
                    $e->getMessage()
                ));
            }
        }
        $this->output->write("\n");
    }
}
