# ![#MercrediFiction](public/mercredifiction.png)

[![Is the website up?](https://img.shields.io/website-up-down-green-red/https/mercredifiction.io.svg?label=mercredifiction.io)](https://mercredifiction.io) [![Build Status](https://travis-ci.org/Deuchnord/mercredifiction.svg?branch=master)](https://travis-ci.org/Deuchnord/mercredifiction)

[![A Mastodon-related project!](https://img.shields.io/badge/-Mastodon-grey.svg?logo=mastodon)](https://joinmastodon.org)
[![PHP 7.2](https://img.shields.io/badge/PHP-7.2-purple.svg?logo=php)](https://php.net)
[![Symfony 4.1](https://img.shields.io/badge/Symfony-4.1-black.svg?logo=symfony)](https://symfony.com)
[![Licenced under the GNU Affero General Public license](https://img.shields.io/badge/license-AGPL_v3-blue.svg)](LICENSE)

## What is _#MercrediFiction_?

_\#MercrediFiction_ is an initiative from the French-speaking community on Mastodon that invites
each member of the social network to tell, each Wednesday, a fictive story in (generally)
500-characters or less, with the hashtag - you guessed it! - _#MercrediFiction_ (which may be translated as
_#FictionWednesday_).
Started very early when the French community landed on Mastodon, the initiative continued its way
and became soon a tradition.

## About this project

After more than one year of fictions on Mastodon, it became a little difficult to see all new
fictions. Plus, the more time passes, the more the oldest fictions become inaccessible.

The goal of this project is to make it easier to read all the fictions of an author by enhancing
their presentation on a website that makes them more visible.

## Join the community!

- **On Matrix:** [`#mercredifiction:matrix.deuchnord.fr`](https://matrix.to/#/#mercredifiction:matrix.deuchnord.fr)

## Installation

_Note: it is strongly recommended to use Linux (I mean a real one, not a Windows Linux subsystem) or macOS for this._

Installing the project is as easy as any Symfony project. First, install PHP (≥ 7.2), [Composer](https://getcomposer.org/download) and MySQL or MariaDB. Then:

1. Clone the repository:
   ```sh
   git clone https://github.com/Deuchnord/mercredifiction.git
   ```
2. Install the components:
   ```sh
   php /path/to/composer.phar install
   ```
   NB: if Composer complains about the `APP_ENV` environment variable not defined, just `export APP_ENV="dev"`, then clear the cache with `php bin/console console:cache`.
3. Create an empty database on your RDBMS
4. Complete `DATABASE_URL` variable in your `.env` file (line 23) to reflect your configuration. _This file is personal and must not be pushed to the Git repository._
5. Install the database:
   ```sh
   php bin/console doctrine:schema:create
   ```

That's it, now you have a fresh install! Even if it is usable _as it_, it does not contain anything for now. Feel free to add manually some data in the database!

## How to contribute

There are plenty of ways to contribute to this project:

- Help developing new functions, fixing bugs...
- Report bugs
- Or in any other way you find!

If you decide to contribute to the code, think to ensure that your code meets the coding standards by simply invoking the following command:

```sh
php /path/to/composer.phar run-script php-cs-fixer
```

This will modify the files so that they meet [the coding recommandations](https://symfony.com/doc/current/contributing/code/standards.html) for a Symfony application. If the coding standard are not met, your pull request will fail at the continuous-integration testing and your branch will not be mergeable.

This project follows the [all-contributors](https://github.com/kentcdodds/all-contributors/blob/master/README.md)
specification. Contributions of any kind are welcome!

## Contributors

<!-- ALL-CONTRIBUTORS-LIST:START - Do not remove or modify this section -->
- [Jérôme Deuchnord](https://deuchnord.fr) [💬](#questions "Answering questions") [💻](https://github.com/Deuchnord/mercredifiction/commits?author=Deuchnord "Writes code") [🎨](#design "Logo, design of the website") [👀](#reviewer "Reviews pull requests") [🤔](#planning "Planning")
- [Brigitte lareinedeselfes](https://framapiaf.org/@lareinedeselfes) [🐛](#bugs "Bug reporter")
<!-- ALL-CONTRIBUTORS-LIST:END -->
