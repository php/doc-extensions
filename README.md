# PHP Third-Party Extension Documentation

This repository hosts documentation for third-party PHP extensions,
extensions maintained outside the [`php-src`](https://github.com/php/php-src)
repository (typically distributed via PECL or PIE).

It exists as a result of
[the RFC on separation of third-party extension documentation](https://wiki.php.net/rfc/third_party_ext_documentation),
and is intended to be rendered as part of the PHP manual at
[php.net/manual/extensions/](https://www.php.net/manual/extensions/).

## Contributing

Pull requests are welcome from anyone. Extension maintainers may additionally
request commit access.

Each extension lives in its own directory under `reference/`. Only the English
canonical documentation is maintained here; per the RFC, existing translations
were not carried over from the main manual.

For general guidance on the documentation format, refer to the
[contribution guidelines](https://doc.php.net/guide/contributing.md).

## Creating this setup

This manual builds on its own, independently of the main manual. The checkout has
to be named `extensions`, as that is the directory `configure.php` is pointed at:

```bash
git clone https://github.com/php/doc-extensions extensions
git clone https://github.com/php/doc-base
php doc-base/configure.php --disable-libxml-check --with-base-lang=extensions
```

For general information related to creating this setup, see the
[contribution guidelines](https://github.com/php/doc-base/blob/master/docs/contributing.md)
or [this page](https://doc.php.net/tutorial/local-setup.php) on the documentation
website.

## Building With make and Docker

- Install Docker (https://docs.docker.com/get-docker/)
- Rebuild the documentation using `make`
- Open output/php-chunked-xhtml/ in your browser.

If the `doc-base` or `phd` repositories are available in directories to the
adjacent to this directory, those will be used for building.

To force the Docker image used for building to itself be rebuilt, you can run
`make -B build`, otherwise the `Makefile` will only build it if does not
already exist.

You can also build the `web` version of the documentation with `make php`
and the output will be placed in output/php-web

## Documentation pipeline

For more information on the various repositories that make up PHP's documentation pipeline,
see this [overview](https://github.com/php/doc-base/blob/master/docs/overview.md).
