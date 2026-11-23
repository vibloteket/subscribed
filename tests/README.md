# Backend regression tests

Use PHP 8.4 and Composer, with PDO SQLite, mbstring and XML extensions:

```sh
composer install
composer test:unit
DB_DRIVER=sqlite composer test:integration
composer analyse:phpstan
```

The integration setup **drops all tables in its test database**. Never point it
at a live forum. The default SQLite database is created inside the testing
package's temporary directory.

The regressions exercise all five notification jobs against the actual database.
They also publish a discussion and reply through the Flarum API, checking author
exclusion, missing permissions, disabled preferences, channel selection and
visibility restrictions. Email is rendered through Flarum's real mail pipeline
using a null transport and captured via MessageSent; no external mail is sent.

The jobs fail with `no such function: regexp` on unmodified 2.0.0 under SQLite.
The fixed query deliberately retains explicitly saved boolean preferences rather
than opting users in through defaults. Custom lowercase driver names remain
supported.
