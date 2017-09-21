# WP Snapshots (beta)

WP Snapshots is a project sharing tool for WordPress. Operated via the command line, this tool empowers developers to easily push snapshots of projects into the cloud for sharing with team members. Team members can pull snapshots into existing WordPress installs such that everything "just works". No more downloading files, SQL dumps, fixing table prefixes, running search/replace commands, etc. WP Snapshots even works with multisite.

__WP Snapshots is currently a private tool for internal 10up use only.__

## How Does It Work?

WP Snapshots stores snapshots in a centralized repository (AWS). Users setup up WP Snapshots with their team's AWS credentials. Users can then push, pull, and search for snapshots. When a user pushes a snapshot, an instance of their current environment (`wp-content/` and database) is pushed to Amazon. When a snapshot is pulled, files are pulled from the cloud replacing `wp-content/` and data is intelligently merged into the database.

Snapshot files (`wp-content/`) and WordPress database tables are stored in Amazon S3. General snapshot meta data is stored in Amazon DynamoDB.

## Install

WP Snapshots is easiest to use as a global Composer package. Right now, it is available only as a private 10up package. Assuming you have Composer installed and SSH keys setup within GitHub/10up organiziation, do the following:

1. Make sure you have mysql installed locally. MySQL is needed only for the `mysqldump` command.
  ```
  brew install mysql
  ```
2. Add the 10up/wpsnapshots repository as a global Composer repository:
  ```
  composer global config repositories.wpsnapshots vcs https://github.com/10up/wpsnapshots
  ```
3. Lower your global minimum Composer stability to `dev`. This is necessary since WP Snapshots is beta software.
  ```
  composer global config minimum-stability dev
  ```
4. Install WP Snapshots as a global Composer package:
  ```
  composer global require 10up/wpsnapshots:dev-master -n
  ```
If global Composer scripts are not in your path, add them:

```
export PATH=~/.composer/vendor/bin:$PATH
```
## Configure

WP Snapshots currently relies on AWS to store files and data. As such, you need to connect to a "repository" hosted on AWS:

```
wpsnapshots configure 10up
```

You'll be prompted for AWS keys. 10up's AWS keys for WP Snapshots are [located in a Google Doc](https://docs.google.com/document/d/1C0N7mMfAA3KHJhYjrE-U4DRMoF59VxMshDkxtzKV9zc/edit).

If WP Snapshots has not been setup for your team/company, you'll need to create the WP Snapshots repository:

```
wpsnapshots create-repository
```

If a repository has already been created, this command will do nothing.

## Usage

WP Snapshots revolves around pushing, pulling, and searching for snapshots. Right now, WP Snapshots can only push and pull into existing WordPress installs (working version of WordPress connected to a database). In a future version, WordPress will be setup if needed.

Documentation for each operation is as follows:

* __wpsnapshots push [--no-uploads] [--no-scrub] [--path] [--db_host] [--db_name] [--db_user] [--db_password]__
  
  This command pushes a snapshot of a WordPress install to the repository. The command will return a snapshot ID once it's finished that you could pass to a team member.
  
  By default all passwords are converted to `password`. The `--no-scrub` option will disable scrubbing.
  
* __wpsnapshots pull \<instance-id\> [--path] [--db_host] [--db_name] [--db_user] [--db_password]__
  
  This command pulls an existing snapshot from the repository into your current WordPress install (or in a new one it creates) replacing your database and `wp-content` directory entirely. The command will interactively prompt you to map URLs to be search and replaced. If the snapshot is a multisite, you will have to map URLs interactively for each blog in the network.
  
* __wpsnapshots search \<search-text\>__
  
  This command searches the repository for snapshots. `<search-text>` will be compared against project names and authors.
  
* __wpsnapshots delete \<instance-id\>__
  
  This command deletes a snapshot from the repository.
  
## Windows

Someone with Windows needs to figure this out :)


