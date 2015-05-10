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

class Msmtp extends AbstractLib
{
    public function configure()
    {
        $configure = <<<COMMAND
    LIBS="-ldl" ./configure --host={$this->getEnv()->get('TOOLCHAIN')} \
    --prefix={$this->getEnv()->get('ROOTFS')}/msmtp \
    --with-ssl=openssl \
    --without-libidn \
    --with-libiconv-prefix={$this->getEnv()->get('ROOTFS')}
COMMAND;

        return $configure;
    }
}
