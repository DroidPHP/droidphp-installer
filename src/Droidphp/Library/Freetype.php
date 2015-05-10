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

class Freetype extends AbstractLib
{
    public function initialize()
    {
        $this->env->add([
            'LIBPNG_CFLAGS' => $this->env->get('CPPFLAGS'),
            'LIBPNG_LDFLAGS' => $this->env->get('LDFLAGS'),
        ]);

        return $this;
    }

    public function configure()
    {
        $configure = sprintf('./configure --prefix=%s --host=%s',
            $this->getEnv()->get('ROOTFS'),
            $this->getEnv()->get('TOOLCHAIN')
        );
        //@todo: Unable to detect libpng
        $configure .= ' --enable-biarch-config --disable-shared --without-png';

        return $configure;
    }
}
