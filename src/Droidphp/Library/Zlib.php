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

class Zlib extends AbstractLib
{
    public function configure()
    {
        return sprintf('./configure --prefix=%s --static', $this->getEnv()->get('ROOTFS'));
    }
}
