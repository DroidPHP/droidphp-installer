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

class LibEvent extends AbstractLib
{
    public function boot(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('    <info>Patching </info>');
        $content = file_get_contents($this->getPath().'/regress_bufferevent.c');
        file_put_contents(str_replace(
            '__asm__("int3");',
            '//__asm__("int3");', $content), $this->getPath().'/regress_bufferevent.c');
    }

    public function configure()
    {
        $configure[] = <<<COMMAND
    LIBS="-lpcre -lz -ldl" ./configure \
    --prefix={$this->getEnv()->get('ROOTFS')} \
    --disable-shared \
    --enable-static \
    --disable-samples \
    --disable-debug-mode \
    --host={$this->getEnv()->get('TOOLCHAIN')}
COMMAND;

        return $configure;
    }
}
