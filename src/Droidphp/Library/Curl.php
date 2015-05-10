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

class Curl extends AbstractLib
{
    public function initialize()
    {
        $this->env->add([
            'SSL_CPPFLAGS' => $this->env->get('CPPFLAGS'),
            'SSL_LDFLAGS' => $this->env->get('LDFLAGS'),
        ]);

        return $this;
    }

    public function configure()
    {
        $configure = sprintf('./configure --prefix=%s --host=%s', $this->getEnv()->get('ROOTFS'), $this->getEnv()->get('TOOLCHAIN'));
        $configure .= ' --disable-dependency-tracking --enable-optimize --enable-http --enable-ftp';
        $configure .= ' --disable-ldap  --disable-manual';
        //@todo: Unable to detect Openssl libraries
        //$configure .= sprintf(' --without-ssl=%s/usr', $this->getEnv()->get('ROOTFS'));
        $configure .= ' --disable-verbose --disable-ntlm-wb --enable-hidden-symbols --disable-dict';
        $configure .= ' --enable-file --enable-zlib --enable-ipv6';

        return $configure;
    }
}
