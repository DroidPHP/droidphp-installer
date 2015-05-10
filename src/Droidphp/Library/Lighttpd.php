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

class Lighttpd extends AbstractLib
{
    public function initialize()
    {
        $cc = sprintf('%s -static --sysroot=%s',
            $this->env->get('CC'),
            $this->env->get('SYSROOT_PATH')
        );
        $rootfs = $this->env->get('ROOTFS');

        $this->env->set('CC', $cc);
        $this->env->set('CFLAGS', sprintf('-DLIGHTTPD_STATIC -I%s/include ', $rootfs));
        $this->env->set('LDFLAGS', sprintf('-L%s/lib', $rootfs));
        $this->env->set('LIBS', '-lpcre -lz -ldl');

        return $this;
    }

    public function boot(InputInterface $input, OutputInterface $output)
    {
        $output->write('    Patching   : ');
        $command = sprintf('patch -p1 < %s/patch/lighttpd_embadded_arm_support.diff', $this->projectDir);
        $process = new Process($command, $this->getPath());
        $process->run();
        $output->writeln($process->isSuccessful() ? '✔' : '✕');
    }

    public function configure()
    {
        $rootfs = $this->getEnv()->get('ROOTFS');
        $configure = <<<COMMAND
    LIBS="-lpcre -lz -ldl" ./configure \
    --prefix={$rootfs}/lighttpd \
    --disable-shared \
    --enable-static \
    --with-openssl={$rootfs}/usr \
    --with-pcre \
    --with-sysroot={$this->getEnv()->get('SYSROOT_PATH')} \
    --with-zlib \
    --without-bzip2 \
    --without-lua \
    --host={$this->getEnv()->get('TOOLCHAIN')}
COMMAND;

        return $configure;
    }
}
