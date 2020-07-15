# WP Snapshots

> WP Snapshots is a project sharing tool for WordPress. Operated via the command line, this tool empowers developers to easily push snapshots of projects into the cloud for sharing with team members. Team members can pull snapshots, either creating new WordPress development environments or into existing installs such that everything "just works". No more downloading files, matching WordPress versions, SQL dumps, fixing table prefixes, running search/replace commands, etc. WP Snapshots even works with multisite.

[![Support Level](https://img.shields.io/badge/support-active-green.svg)](#support-level) [![Release Version](https://img.shields.io/github/tag/10up/wpsnapshots.svg?label=release)](https://github.com/10up/wpsnapshots/releases/latest) [![MIT License](https://img.shields.io/github/license/10up/wpsnapshots.svg)](https://github.com/10up/wpsnapshots/blob/develop/LICENSE.md)

## Table of Contents  
* [Overview](#overview)
* [2.0 Upgrade Notice](#upgrade)
* [Installation](#install)
  * [Windows-specific Installation](#windows)
* [Configure](#configure)
* [Usage](#usage)
* [Identity Access Management](#identity-access-management)
* [Troubleshooting](#troubleshooting)

## Overview

WP Snapshots stores snapshots in a centralized repository (AWS). Users setup up WP Snapshots with their team's AWS credentials. Users can then push, pull, and search for snapshots. When a user pushes a snapshot, an instance of their current environment (`wp-content/`, database, etc.) is pushed to Amazon and associated with a particular project slug. When a snapshot is pulled, files are pulled from the cloud either by creating a new WordPress install with the pulled database or by replacing `wp-content/` and intelligently merging the database. WP Snapshots will ensure your local version of WordPress matches the snapshot..

A snapshot can contain files, the database, or both. Snapshot files (`wp-content/`) and WordPress database tables are stored in Amazon S3. General snapshot meta data is stored in Amazon DynamoDB.

## Upgrade

WP Snapshots 2.0+ allows users to store database and files independently. As such, some snapshots may only have files or vice-versa. Therefore, WP Snapshots pre 2.0 will break when attempting to pull a 2.0+ snapshot that contains only files or database. WP Snapshots 2.0 works perfectly with older snapshots. If you are running an older version of WP Snapshots, you should upgrade immediately.

## Install

WP Snapshots is easiest to use as a global Composer package. It's highly recommended you run WP Snapshots from WITHIN your dev environment (inside VM or container). Assuming you have Composer/MySQL installed and SSH keys setup within GitHub/10up organiziation, do the following:

Install WP Snapshots as a global Composer package via Packagist:
  ```
  composer global require 10up/wpsnapshots
  ```

If global Composer scripts are not in your path, add them:

```
export PATH=~/.composer/vendor/bin:$PATH
```

If you are using VVV, add global Composer scripts to your path with this command:

```
export PATH=~/.config/composer/vendor/bin:$PATH
```

## Configure

WP Snapshots currently relies on AWS to store files and data. As such, you need to connect to a "repository" hosted on AWS. We have compiled [instructions on how to setup a repository on AWS.](https://github.com/10up/wpsnapshots/wiki/Setting-up-Amazon-Web-Services-to-Store-Snapshots)

* __wpsnapshots configure \<repository\> [--region] [--aws_key] [--aws_secret] [--user_name] [--user_email]__

  This command sets up WP Snapshots with AWS info and user info.  If the optional arguments are not passed
  to the command, the user will be promted to enter them, with the exception of region which will default to
  `us-west-1`.

  __Example Usage With Prompts :__
  ```
  wpsnapshots configure 10up
  ```
  __Example Usage Without Prompts (No Interaction) :__
  ```
  wpsnapshots configure yourcompany --aws_key=AAABBBCCC --aws_secret=AAA111BBB222 --user_name="Jane Smith" --user_email="noreply@yourcompany.com"
  ```

If WP Snapshots has not been setup for your team/company, you'll need to create the WP Snapshots repository:

```
wpsnapshots create-repository <repository>
```

If a repository has already been created, this command will do nothing.

## Usage

WP Snapshots revolves around pushing, pulling, and searching for snapshots. WP Snapshots can push any setup WordPress install. WP Snapshots can pull any snapshot regardless of whether WordPress is setup or not. If WordPress is not setup during a pull, WP Snapshots will guide you through setting it up.

Documentation for each operation is as follows:

* __wpsnapshots push [\<snapshot-id\>] [--exclude_uploads] [--exclude] [--scrub] [--path] [--db_host] [--db_name] [--db_user] [--db_password] [--verbose] [--small] [--slug] [--description] [--include_files] [--include_db]

  This command pushes a snapshot of a WordPress install to the repository. The command will return a snapshot ID once it's finished that you could pass to a team member. When pushing a snapshot, you can include files and/or the database.

  WP Snapshots scrubs all user information by default including names, emails, and passwords.

  Pushing a snapshot will not replace older snapshots with the same name. There's been discussion on this. It seems easier and safer not to delete old snapshots (otherwise we have to deal with permissions).

  `--small` will take 250 posts from each post type along with the associated terms and post meta and delete the rest of the data. This will modify your local database so be careful.

* __wpsnapshots pull \<snapshot-id\> [--path] [--db_host] [--db_name] [--db_user] [--db_password] [--verbose] [--include_files] [--include_db]__

  This command pulls an existing snapshot from the repository into your current WordPress install replacing your database and/or `wp-content` directory entirely. If a WordPress install does not exist, it will prompt you to create it. The command will interactively prompt you to map URLs to be search and replaced. If the snapshot is a multisite, you will have to map URLs interactively for each blog in the network. This command will also (optionally) match your current version of WordPress with the snapshots.

  After pulling, you can login as admin with the user `wpsnapshots`, password `password`.

* __wpsnapshots search \<search-text\>__

  This command searches the repository for snapshots. `<search-text>` will be compared against project names and authors. Searching for "\*" will return all snapshots.

* __wpsnapshots delete \<snapshot-id\> [--verbose]__

  This command deletes a snapshot from the repository.

## Identity Access Management

WP Snapshots relies on AWS for access management. Each snapshot is associated with a project slug. Using AWS IAM, specific users can be restricted to specific projects.

## Troubleshooting

* __WP Snapshots can't establish a connection to the database__

  This can happen if you are calling WP Snapshots outside of your dev environment running in a VM or container. WP Snapshots reads database credentials from `wp-config.php`. In order to connect to your database from your host machine, the database host address will need to be different. For VVV it's 192.168.50.4, for WP Local Docker, it's 127.0.0.1. You can pass a host override via the command line using the `--db_host` option. For VVV, you also might need to pass in a special database user and password like so `--db_user=external --db_password=external`. We recommend running WP Snapshots from inside your development environment.

* __I received the error: `env: mysqldump: No such file or directory`__

  You don't have `mysqldump` installed. This is most likely because you are running WP Snapshots from outside your container or VM. Either install `mysqldump` or run WP Snapshots inside your container or VM.

* __During a pull, MySQL is timing or erroring out while replacing the database.__

  If you are pulling a massive database, there are all sorts of memory and MySQL optimization issues you can encounter. Try running WP Snapshots as root (`--db_user=root`) so it can attempt to tweak settings for the large import.


* __wpsnapshots search displays signature expired error.__

  This happens when your local system clock is skewed. To fix:
  * If you're using VVV, try `vagrant reload`
  * If you're using Docker, try `docker-machine ssh default 'sudo ntpclient -s -h pool.ntp.org'`

* __wpsnapshots push or pull is crashing.__

  A fatal error is most likely occuring when bootstrapping WordPress. Look at your error log to see what's happening. Often this happens because of a missing PHP class (Memcached) which is a result of not running WP Snapshots inside your environment (container or VM).

## Windows

WP Snapshots has been used successfully inside [Windows Subsystem for Linux](https://msdn.microsoft.com/en-us/commandline/wsl/install-win10).

## Support Level

**Active:** 10up is actively working on this, and we expect to continue work for the foreseeable future including keeping tested up to the most recent version of WordPress.  Bug reports, feature requests, questions, and pull requests are welcome.

## Like what you see?

<a href="http://10up.com/contact/"><img src="https://10updotcom-wpengine.s3.amazonaws.com/uploads/2016/10/10up-Github-Banner.png" width="850" alt="Work with us at 10up"></a>
