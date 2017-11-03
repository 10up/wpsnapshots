# WP Snapshots (beta)

WP Snapshots is a project sharing tool for WordPress. Operated via the command line, this tool empowers developers to easily push snapshots of projects into the cloud for sharing with team members. Team members can pull snapshots into existing WordPress installs such that everything "just works". No more downloading files, SQL dumps, fixing table prefixes, running search/replace commands, etc. WP Snapshots even works with multisite.

__WP Snapshots is currently a private tool for internal 10up use only.__

## How Does It Work?

WP Snapshots stores snapshots in a centralized repository (AWS). Users setup up WP Snapshots with their team's AWS credentials. Users can then push, pull, and search for snapshots. When a user pushes a snapshot, an instance of their current environment (`wp-content/` and database) is pushed to Amazon and associated with a particular project slug. When a snapshot is pulled, files are pulled from the cloud replacing `wp-content/` and data is intelligently merged into the database.

Snapshot files (`wp-content/`) and WordPress database tables are stored in Amazon S3. General snapshot meta data is stored in Amazon DynamoDB.

## Install

WP Snapshots is easiest to use as a global Composer package. Right now, it is available only as a private 10up package. It's highly recommended you run WP Snapshots from WITHIN your dev environment (inside VM or container). Assuming you have Composer/MySQL installed and SSH keys setup within GitHub/10up organiziation, do the following:

1. Add the 10up/wpsnapshots repository as a global Composer repository:
  ```
  composer global config repositories.wpsnapshots vcs https://github.com/10up/wpsnapshots
  ```
2. Lower your global minimum Composer stability to `dev`. This is necessary since WP Snapshots is beta software.
  ```
  composer global config minimum-stability dev
  ```
3. Install WP Snapshots as a global Composer package:
  ```
  composer global require 10up/wpsnapshots:dev-master -n
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

WP Snapshots currently relies on AWS to store files and data. As such, you need to connect to a "repository" hosted on AWS:

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
  wpsnapshots configure 10up --aws_key=AAABBBCCC --aws_secret=AAA111BBB222 --user_name="Jane Smith" --user_email="noreply@10up.com"
  ```

  10up's AWS keys for WP Snapshots are [located in a Google Doc](https://docs.google.com/document/d/1C0N7mMfAA3KHJhYjrE-U4DRMoF59VxMshDkxtzKV9zc/edit).

If WP Snapshots has not been setup for your team/company, you'll need to create the WP Snapshots repository:

```
wpsnapshots create-repository
```

If a repository has already been created, this command will do nothing.

## Usage

WP Snapshots revolves around pushing, pulling, and searching for snapshots. WP Snapshots can push any setup WordPress install. WP Snapshots can pull any snapshot regardless of whether WordPress is setup or not. If WordPress is not setup during a pull, WP Snapshots will guide you through setting it up.

Documentation for each operation is as follows:

* __wpsnapshots push [--exclude-uploads] [--no-scrub] [--path] [--db_host] [--db_name] [--db_user] [--db_password] [--verbose]__

  This command pushes a snapshot of a WordPress install to the repository. The command will return a snapshot ID once it's finished that you could pass to a team member.

  By default all passwords are converted to `password`. The `--no-scrub` option will disable scrubbing.

  Pushing a snapshot will not replace older snapshots with the same name. There's been discussion on this. It seems easier and safer not to delete old snapshots (otherwise we have to deal with permissions). This could certainly change in the future after we see how the project is used.

* __wpsnapshots pull \<instance-id\> [--path] [--db_host] [--db_name] [--db_user] [--db_password] [--verbose]__

  This command pulls an existing snapshot from the repository into your current WordPress install (or in a new one it creates) replacing your database and `wp-content` directory entirely. The command will interactively prompt you to map URLs to be search and replaced. If the snapshot is a multisite, you will have to map URLs interactively for each blog in the network.

* __wpsnapshots search \<search-text\>__

  This command searches the repository for snapshots. `<search-text>` will be compared against project names and authors. Searching for "\*" will return all snapshots.

* __wpsnapshots delete \<instance-id\> [--verbose]__

  This command deletes a snapshot from the repository.

## Identity Access Management

WP Snapshots relies on AWS for access management. Each snapshot is associated with a project slug. Using AWS IAM, specific users can be restricted to specific projects.

## Troubleshooting

* __WP Snapshots can't establish a connection to the database__

  This can happen if you are calling WP Snapshots outside of your dev environment running in a VM or container. WP Snapshots reads database credentials from `wp-config.php`. In order to connect to your database from your host machine, the database host address will need to be different. For VVV it's 192.168.50.4, for WP Local Docker, it's 127.0.0.1. You can pass a host override via the command line using the `--db_host` option. We recommend running WP Snapshots from inside your development environment.

* __During a pull, MySQL is timing or erroring out while replacing the database.__

  If you are pulling a massive database, there are all sorts of memory and MySQL optimization issues you can encounter. Try running WP Snapshots as root (`--db_user=root`) so it can attempt to tweak settings for the large import.

## Windows

WP Snapshots has been used successfully inside [Windows Subsystem in Linux](https://msdn.microsoft.com/en-us/commandline/wsl/install-win10).
