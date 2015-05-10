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
use Droidphp\Library\Curl;
use Droidphp\Library\Freetype;
use Droidphp\Library\Gmp;
use Droidphp\Library\Iconv;
use Droidphp\Library\Jpeg;
use Droidphp\Library\Lighttpd;
use Droidphp\Library\MCrypt;
use Droidphp\Library\Mhash;
use Droidphp\Library\Msmtp;
use Droidphp\Library\Ncurses;
use Droidphp\Library\Nginx;
use Droidphp\Library\Openssl;
use Droidphp\Library\Pcre;
use Droidphp\Library\Php;
use Droidphp\Library\Png;
use Droidphp\Library\Readline;
use Droidphp\Library\Xml2;
use Droidphp\Library\Zlib;
use Droidphp\LibraryProviderInterface;
use Droidphp\Utils\LibraryUtils;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser;

class BuildCommand extends DownloadCommand
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
    /** @var  InputInterface */
    private $input;
    /** @var ArrayCollection */
    private $collection;
    /** @var array LibraryProviderInterface */
    private $libraries;
    /** @var  array */
    protected $findDirs;
    /** @var  ArrayCollection */
    protected $env;
    protected $libraryName;

    const CONFIGURED = 1;
    const COMPILED = 2;
    const INSTALLED = 3;

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('build:components')
            ->setDescription('Creates a new Project.')
            ->addArgument('package', InputArgument::REQUIRED, 'Create new package');
        //->addArgument('platform', InputArgument::REQUIRED, 'Platform to compile project supported platform : arm');
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->loadLibrary();

        $this->input = $input;
        $this->output = $output;

        $this->fs = new Filesystem();
        $this->env = $this->getEnv();

        $directory = rtrim(trim($input->getArgument('package')), DIRECTORY_SEPARATOR);
        $this->baseDir = $this->fs->isAbsolutePath($directory) ? $directory : getcwd().DIRECTORY_SEPARATOR.$directory;

        $requireDirs = [
            $this->baseDir.'/build',
            $this->baseDir.'/source',
            $this->baseDir.'/rootfs',
            $this->baseDir.'/rootfs/include',
            $this->baseDir.'/rootfs/lib',
        ];

        $this->findDirs = [
            $this->baseDir.'/rootfs/lib',
            $this->baseDir.'/rootfs/include',
            /* Openssl */
            $this->baseDir.'/rootfs/usr/lib',
            $this->baseDir.'/rootfs/usr/include',
            /* PHP */
            $this->baseDir.'/rootfs/php/bin',
            /* MSMTP */
            $this->baseDir.'/rootfs/msmtp/bin',
            /* LIGHTTPD */
            $this->baseDir.'/rootfs/lighttpd/sbin',
            /* NGINX */
            $this->baseDir.'/rootfs/nginx/sbin',
        ];

        $this->fs->mkdir($requireDirs);
        $this->fs->mkdir($this->findDirs);

        $yaml = new Parser();

        try {
            $env = $yaml->parse(file_get_contents($this->baseDir.'/environment.yml'));
        } catch (ParseException $e) {
            throw new \Exception(sprintf('Unable to parse the YAML string: %s', $e->getMessage()));
        }
        $this->env = new ArrayCollection($env);
        $this->env->set('ROOTFS', $this->baseDir.'/rootfs');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $repositories = $this->getRepository();
        /** @var $library LibraryProviderInterface */
        foreach ((array) $this->libraries as $library) {
            $this->libraryName = $libraryName = $this->getLibraryName($library);
            $this->output->writeln(sprintf("\n Preparing <comment>%s</comment> ...\n", $this->libraryName));
            if (LibraryUtils::exist($libraryName.'*', $this->findDirs)) {
                $this->output->writeln(sprintf('     %s   :  <info>✔</info>', ucfirst($libraryName)));
                continue;
            }

            $this->remoteFileUrl = $repositories[$libraryName];

            $projectName = str_replace(['.tar.gz', '.tar.bz2', '.tar.xz'], [''], basename($this->remoteFileUrl));
            $this->projectName = $projectName;
            $this->projectDir = $this->baseDir.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.$projectName;

            try {
                if (!$this->fs->exists($this->projectDir.'/configure')) {
                    $this
                        ->download()
                        ->extract()
                        ->cleanUp();
                }
            } catch (\Exception $e) {
                $this->cleanUp();
                throw $e;
            }

            $library
                ->setEnv($env = $this->env)
                ->setProjectDir($this->baseDir)
                ->initialize()
                ->boot($input, $output);

            $this->invokeConfigure($library);
            $library->invokeBeforeMake($output);
            $this->invokeMake($library);

            $output->write("\n\n");
        }
        $output->write("\n\n");

        $this->optimizeBinaries();
//        $this->displayResult();
    }

    private function getEnv()
    {
        return $this->collection;
    }

    private function loadLibrary()
    {
        $this->libraries = [
            new Zlib(),
//            new BZip2(),
            new Png(),
//            new LibEvent(),
            new Jpeg(),
            new Ncurses(),
            new Readline(),
            new Openssl(),
            new Curl(),
            new Iconv(),
            new Pcre(),
            new Xml2(),
            new Freetype(),
            new Mhash(),
            new MCrypt(),
            new Gmp(),
            //new Memcached(),
            new Php(),
            new Msmtp(),
            new Lighttpd(),
            new Nginx(),
        ];
    }

    private function invokeConfigure(LibraryProviderInterface $library)
    {
        $this->output->write('    Configure  :  ');

        if (!$this->isAlreadyAction($this->projectDir, self::CONFIGURED)) {
            $configure = $library->configure();
            $process = new Process($configure, $this->projectDir, $this->env->toArray());
            $process->setTimeout(0);
            if (0 != $process->run()) {
                throw new \RuntimeException(sprintf('Unable to configure "%s" error "%s"', $this->libraryName, $process->getOutput()));
            }
            $process->wait();
            $this->showResponse($process->isSuccessful());
            if ($process->isSuccessful()) {
                //touch($libPath . '/.configured');
            }
        } else {
            $this->output->writeln('SKIP');
        }
    }

    private function invokeMake(LibraryProviderInterface $library)
    {
        $this->output->write('    Compiling  :  ');

        if (!$this->isAlreadyAction($this->projectDir, self::COMPILED)) {
            $command = 'make -j 8';
            if ($library instanceof BZip) {
                $command = sprintf('make install PREFIX=%s', $this->env->get('ROOTFS'));
            }
            if ($library instanceof Openssl) {
                $command = sprintf('make install INSTALL_PREFIX=%s', $this->env->get('ROOTFS'));
            }

            $process = new Process($command, $this->projectDir, $this->env->toArray());
            $process->setTimeout(0);
            if (0 != $process->run()) {
                throw new \RuntimeException(sprintf('Unable to configure "%s" error "%s"', $this->libraryName, $process->getErrorOutput()));
            }
            $this->showResponse($process->isSuccessful());

//            if ($process->isSuccessful()) {
//                touch($libPath . '/.make_compiled');
//            }
        }

        $this->output->write('    Installing :  ');

        if (!$this->isAlreadyAction($this->projectDir, self::INSTALLED)) {
            $command = 'make -j 8 install';
            if ($library instanceof BZip) {
                $command = null;
            }

            if (null === $command) {
                return;
            }

            $process = new Process($command, $this->projectDir, $this->env->toArray());
            $process->setTimeout(0);
            $process->run();
            $this->showResponse($process->isSuccessful());
        }
    }

    private function isAlreadyAction($libPath, $flags)
    {
        if ($this->input->hasOption('force')) {
            return false;
        }

        $dotFilename = '';
        switch ($flags) {
            case static::CONFIGURED:
                $dotFilename = '.configured';
                break;
            case static::COMPILED:
                $dotFilename = '.make_compiled';
                break;
            case static::INSTALLED:
                $dotFilename = '.make_installed';
                break;
        }

        return (Boolean) file_exists($libPath.'/'.$dotFilename);
    }

    private function getRepository()
    {
        $yaml = new Parser();
        try {
            return $yaml->parse(file_get_contents($this->baseDir.'/libraries.yml'));
        } catch (ParseException $e) {
            throw new \Exception(sprintf('Unable to parse the YAML string: %s', $e->getMessage()));
        }
    }

    private function getLibraryName(LibraryProviderInterface $lib)
    {
        $classNameWithNs = strtolower(get_class($lib));

        return end(explode('\\', $classNameWithNs));
    }

    /**
     * Removes all the temporary files and directories created to
     * download the remote file.
     *
     * @return BuildCommand
     */
    private function cleanUp()
    {
        $this->fs->remove(dirname(dirname(dirname($this->downloadedFilePath))));

        return $this;
    }

    private function optimizeBinaries()
    {
        $this->output->write(" Preparing to  Optimize...\n");

        $binaries = [
            /* PHP */
            $this->baseDir.'/rootfs/php/bin/php-cgi',
            /* MSMTP */
            $this->baseDir.'/rootfs/msmtp/bin/msmtp',
            /* LIGHTTPD */
            $this->baseDir.'/rootfs/lighttpd/sbin/lighttpd',
            /* NGINX */
            $this->baseDir.'/rootfs/nginx/sbin/nginx',
        ];

        foreach ($binaries as $bin) {
            $this->output->write(sprintf('    Optimizing %s  :  ', basename($bin)));
            $command = $this->env->get('TOOLCHAIN').'-strip '.$bin;
            $process = new Process($command, null, $this->env->toArray());
            $process->setTimeout(0);
            $process->run();
            echo $process->getErrorOutput();
            $this->showResponse($process->isSuccessful());
        }
        $this->output->write("\n");
    }

    private function compressComponentIntoZipFile()
    {
    }

    private function displayResult()
    {
        $this->output->writeln('Droidphp Installer <info>successfully compiles</info> the components');

        return $this;
    }

    private function showResponse($isSuccess)
    {
        if ($isSuccess) {
            $message = '<info>✔</info>';
        } else {
            $message = '<error>✕</error>';
        }
        $this->output->writeln($message);
    }
}
