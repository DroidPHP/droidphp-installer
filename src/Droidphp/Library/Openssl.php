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

class Openssl extends AbstractLib
{
    public function configure()
    {
        $env = $this->getEnv()->toArray();
        $configure[] = sprintf('./Configure linux-armv4 no-shared --prefix=/usr', $env['ROOTFS']);
        $configure[] = sprintf('--openssldir=%s', $env['ROOTFS']);
        $configure[] = sprintf('--with-zlib-lib=%s/lib', $env['ROOTFS']);
        $configure[] = sprintf('--with-zlib-include=%s/include', $env['ROOTFS']);

        return implode(' ', $configure);
    }
}
