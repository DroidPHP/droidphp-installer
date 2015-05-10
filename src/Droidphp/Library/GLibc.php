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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class GLibc extends AbstractLib
{
    public function initialize()
    {
        foreach (['CPPFLAGS', 'CFLAGS', 'CXXFLAGS', 'LDFLAGS'] as $key) {
            $this->env->remove($key);
        }

        return $this;
    }

    public function boot(InputInterface $input, OutputInterface $output)
    {
        $output->write('    Patching   :  ');
        $command = sprintf('patch -p1 < %s/patch/glibc.patch', $this->projectDir);

        $process = new Process($command, $this->getPath());
        $process->run();
        $output->writeln($process->isSuccessful() ? '✔' : '✕');
    }

    public function configure()
    {
        $installDir = dirname($this->getEnv()->get('_packageDir')).'/sysroots/arm/sysroot/usr';
        $toolchainName = $this->getEnv()->get('_toolchainName');
        $configure =
            sprintf('./configure --prefix=%s --host=%s ', $installDir, $toolchainName);
        $configure .= ' --disable-build-nscd --enable-add-ons';
        $configure .= ' --with-gd --with-__thread  --enable-static-nss --with-tls --with-ports="nptl, ports"';

        return $configure;
    }
}
