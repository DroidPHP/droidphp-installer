<?php

/*
 * This file is part of the DroidPHP Installer package.
 *
 * (c) Shushant Kumar <shushantkumar786@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Droidphp\Library;

use Droidphp\Collections\ArrayCollection;
use Droidphp\LibraryProviderInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Parser;

abstract class AbstractLib implements LibraryProviderInterface
{
    const REPOSITORY_FILE = '/libraries.json';

    /** @var ArrayCollection */
    protected $env;
    protected $projectDir;

    public function __construct(ArrayCollection $env = null)
    {
        $this->env = $env;
    }

    public function boot(InputInterface $input, OutputInterface $output)
    {
    }

    public function initialize()
    {
        return $this;
    }

    public function configure()
    {
        $env = $this->env;
        $configure = sprintf('./configure --prefix=%s --host=%s --with-sysroot=%s --enable-static --disable-shared ',
            $env->get('ROOTFS'),
            $env->get('TOOLCHAIN'),
            $env->get('SYSROOT_PATH')
        );

        return $configure;
    }

    public function invokeBeforeMake(OutputInterface $output)
    {
    }

    public function setEnv(ArrayCollection $env)
    {
        $this->env = $env;

        return $this;
    }

    public function setProjectDir($projectDir)
    {
        $this->projectDir = $projectDir;

        return $this;
    }

    protected function getEnv()
    {
        return $this->env;
    }

    protected function getPath()
    {
        $classNameWithNs = strtolower(get_class($this));
        $libName = end(explode('\\', $classNameWithNs));
        $repositories = $this->getRepository();

        if (array_key_exists($libName, $repositories)) {
            $iterator = new \DirectoryIterator($this->projectDir.'/build');
            foreach ($iterator as $dir) {
                if (!$dir->isDir()) {
                    continue;
                }
                if (false !== strpos($dir->getFilename(), $libName) && preg_match('/\d/', $dir->getFilename())) {
                    return $dir->getRealPath();
                }
            }
        }

        return false;
    }

    private function getRepository()
    {
        $yaml = new Parser();
        try {
            return $yaml->parse(@file_get_contents($this->projectDir.'/libraries.yml'));
        } catch (ParseException $e) {
            throw new \Exception(sprintf('Unable to parse the YAML string: %s', $e->getMessage()));
        }
    }
}
