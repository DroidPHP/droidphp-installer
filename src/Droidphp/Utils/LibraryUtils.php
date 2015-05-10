<?php

/*
 * This file is part of the DroidPHP Installer package.
 *
 * (c) Shushant Kumar <shushantkumar786@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Droidphp\Utils;

class LibraryUtils
{
    public static function exist($libraryName, $includeDirs = [])
    {
        $finder = new \Symfony\Component\Finder\Finder();

        $finder
            ->files()
            ->name($libraryName)
            ->in($includeDirs);

        return 0 != $finder->count();
    }
}
