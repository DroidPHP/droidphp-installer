Droidphp Installer
=================

**This is the official Droidphp installer**

Installing the installer
------------------------

This step is only needed the first time you use the installer:

### Linux and Mac OS X

```bash
$ curl -LsS https://raw.github.com/droidphp/droidphp-installer/master/droidphp-installer.phar > droidphp-installer.phar
$ sudo mv droidphp-installer.phar /usr/local/bin/droidphp-installer
$ chmod a+x /usr/local/bin/droidphp-installer
```

### Windows

```bash
c:\> php -r "readfile('https://raw.github.com/droidphp/droidphp-installer/master/droidphp-installer.phar');" > droidphp-installer.phar
```

Move the downloaded `droidphp-installer.phar` file to your projects directory and execute
it as follows:

```bash
c:\> php droidphp-installer.phar
```

Using the installer
-------------------

**1. Setup new project**

Execute the `build:setup` command and provide the name of your project:

```bash
# Linux, Mac OS X
$ droidphp-installer build:setup org.opendroidphp /path/to/bin/arm-none-linux-gnueabi

```
Please download `arm-none-linux-gnueabi` toolchain from CodeSourcery and `/path/to/bin/arm-none-linux-gnueabi` is suffix and it will resolve to `/path/to/bin/arm-none-linux-gnueabi-gcc`

**2. Build new Project**

```bash
# Linux, Mac OS X
$ droidphp-installer build:components org.opendroidphp arm

```