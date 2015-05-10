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

use Symfony\Component\Console\Output\OutputInterface;

class Nginx extends AbstractLib
{
    public function initialize()
    {
        //small hack to cross compile nginx
        $this->env->set('CC', 'gcc');
        $this->env->set('CFLAGS', '');
    }

    public function configure()
    {
        $configure = sprintf('./configure --prefix=%s/nginx', $this->getEnv()->get('ROOTFS'));

        return $configure;
    }

    public function invokeBeforeMake(OutputInterface $output)
    {
        $env = $this->getEnv()->toArray();
        $output->writeln('    Patching Makefile');
        $libraryDir = $this->getPath();

        if (false !== $content = file_get_contents($libraryDir.'/objs/ngx_auto_config.h')) {
            $defineRemove = [
                '#define NGX_HAVE_TCP_FASTOPEN' => '//#define NGX_HAVE_TCP_FASTOPEN',
                '#define NGX_HAVE_O_PATH' => '//#define NGX_HAVE_O_PATH',
            ];

            $content = str_replace(
                array_keys($defineRemove),
                array_values($defineRemove),
                $content);
            file_put_contents($libraryDir.'/objs/ngx_auto_config.h', $content);
        }

        if (false !== $content = file_get_contents($libraryDir.'/objs/Makefile')) {
            $replaces = [
                'CC =	gcc' => sprintf('CC =    %s-gcc -static --sysroot=%s', $env['TOOLCHAIN'], $env['SYSROOT_PATH']),
                'CFLAGS =  -pipe  -O -W -Wall -Wpointer-arith -Wno-unused-parameter -Werror -g ' => sprintf('CFLAGS = %s --sysroot=%s ', $env['CPPFLAGS'], $env['SYSROOT_PATH']),
                'CPP =	gcc -E' => '',
            ];
            $content = str_replace(array_keys($replaces), array_values($replaces), $content);
            $content = str_replace(['-lpthread'], [$env['LDFLAGS'].' -lpthread'], $content);
            file_put_contents($libraryDir.'/objs/Makefile', $content);
        }
    }
}
