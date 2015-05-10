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

class Php extends AbstractLib
{
    public function initialize()
    {
        $cc = sprintf('%s --sysroot=%s',
            $this->env->get('CC'),
            $this->env->get('SYSROOT_PATH')
        );
        $cflags = sprintf('%s -static ',
            $this->env->get('CFLAGS')
        );

        $this->env->set('CC', $cc);
        $this->env->set('CFLAGS', $cflags);

        return $this;
    }

    public function boot(InputInterface $input, OutputInterface $output)
    {
        $output->write('    Patching   : ');
        $command = sprintf('patch -p1 < %s/patch/php_proc_sh_fix.patch', $this->projectDir);

        $process = new Process($command, $this->getPath());
        $process->run();
        $output->writeln($process->isSuccessful() ? '✔' : '✕');
    }

    public function configure()
    {
        $env = $this->getEnv()->toArray();

        $configure = sprintf('./configure --prefix=%s/php --host=%s --enable-static --disable-shared ',
            $env['ROOTFS'],
            $env['TOOLCHAIN']
        );

        $configure .= '--enable-filter --enable-calendar --enable-ctype --enable-dom --enable-exif ';
        $configure .= '--enable-phpdbg --enable-fileinfo --enable-ftp --enable-posix --enable-shmop ';
        $configure .= '--enable-simplexml --enable-sysvmsg --enable-sysvsem --enable-tokenizer ';
        $configure .= '--enable-wddx  --enable-xmlreader --enable-xmlwriter --enable-opcache=no ';
        $configure .= '--enable-pcntl --enable-soap  --enable-cgi --enable-json ';
        $configure .= '--enable-sockets --enable-bcmath --enable-mbstring --enable-mbregex --enable-session ';
        $configure .= sprintf('--with-openssl=%s/usr --with-mhash=%s --disable-intl ',
            $env['ROOTFS'],
            $env['ROOTFS']
        );
        $configure .= '--with-zlib --enable-zip --with-mysql --enable-mysqlnd --with-mysqli=mysqlnd ';
        $configure .= ' --enable-pdo --with-pdo-mysql=mysqlnd --enable-libxml --with-pdo-sqlite ';
        $configure .= sprintf('--with-sqlite3 --with-zlib-dir=%s --with-libxml-dir=%s --with-curl=%s ',
            $env['ROOTFS'],
            $env['ROOTFS'],
            $env['ROOTFS']
        );
        $configure .= sprintf('--with-jpeg-dir=%s --with-png-dir=%s --with-freetype-dir=%s ',
            $env['ROOTFS'],
            $env['ROOTFS'],
            $env['ROOTFS']
        );
        $configure .= sprintf('--with-gd --enable-gd-native-ttf --with-iconv-dir=%s --with-mcrypt=%s ',
            $env['ROOTFS'],
            $env['ROOTFS']
        );
        $configure .= '--enable-zend-signals --with-zend-vm';

        return $configure;
    }

    public function invokeBeforeMake(OutputInterface $output)
    {
        $command = <<<COMMAND
    mv ext/mysqlnd/config9.m4 ext/mysqlnd/config.m4
    sed -i "s{-I/usr/include{ {" Makefile
    sed -i=".backup" "s{ext/mysqlnd/php_mysqlnd_config.h{config.h{" ext/mysqlnd/mysqlnd_portability.h
    sed -i=".backup" 's/-export-dynamic/-all-static/g' Makefile
    sed -i=".backup" 's/PHP_BINARIES. pharcmd$/PHP_BINARIES)/g' Makefile
    sed -i=".backup" 's/install-programs install-pharcmd$/install-programs/g' Makefile
COMMAND;

        $output->writeln('    Patching Makefile');
        $process = new Process($command, $this->getPath());
        $process->run();
    }
}
