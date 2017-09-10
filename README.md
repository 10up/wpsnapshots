# WP Projects (beta)

WP Projects is a project syncing tool for WordPress, operated via the command line, empowering developers to easily push instances of projects into the cloud for sharing with team members. Team members can pull project instances into existing WordPress installs such that everything "just works". No more downloading files, SQL dumps, fixing table prefixes, running search/replace commands, etc. WP Projects even works with multisite.

## How Does It Work?

WP Projects stores projects in a centralized repository (AWS). Users setup up WP Projects with their team's AWS credentials. Users can then push, pull, and search for project instances. When a user pushes a project instance, an instance of their current environment (`wp-content/` and database) is pushed to Amazon. When a project instance is pulled, files are pulled from the cloud replacing `wp-content/` and data is intelligently merged into the database.

Projects files (`wp-content/`) and WordPress database tables are stored in Amazon S3. General project data is stored in Amazon DynamoDB.

## Install

WP Projects is easiest to use as a global Composer package. Right now, it is available only as a private 10up package. Assuming you have Composer installed and SSH keys setup within GitHub/10up organiziation, do the following:

1. Add the 10up/wpprojects repository as a global Composer repository:
  ```
  composer global config repositories.wpprojects vcs https://github.com/10up/wpprojects
  ```
2. Lower your global minimum Composer stability to `dev`. This is necessary since WP Projects is beta software.
  ```
  composer global config minimum-stability dev
  ```
3. Install WP Projects as a global Composer package:
  ```
  composer global require 10up/wpprojects:dev-master -n
  ```
If global Composer scripts are not in your path, add them:

```
export PATH=~/.composer/vendor/bin:$PATH
```
## Configure

WP Projects currently relies on AWS to store files and data. As such, you need to connect to a "repository" hosted on AWS:

```
wpprojects connect 10up
```

You'll be prompted for AWS keys. 10up's AWS keys for WP Projects are [located in a Google Doc](https://docs.google.com/document/d/1C0N7mMfAA3KHJhYjrE-U4DRMoF59VxMshDkxtzKV9zc/edit).

If WP Projects has not been setup for your team/company, you'll need to create the WP Projects repository:

```
wpprojects create-repository
```

If a repository has already been setup, this command will do nothing.

## Usage

WP Projects revolves around pushing, pulling, and searching for project instances. Right now, WP Projects can only push and pull into existing WordPress installs (working version of WordPress connected to a database). In a future version, WordPress will be setup if neeeded.

Documentation for each operation is as follows:

* __wpprojects push [--no-uploads] [--no-scrub]__ - Must be run from root of installed WordPress instance.
  
  This command pushes an instance of the current project to the repository. Data about the project (name, author, environment, etc.) is stored in a `wpprojects.json` file. Project instances are searchable by name and author so set these carefully. The push command will prompt you to create the file, if one doesn't already exist. The command will return a project instance ID once it's finished that you could pass to a team member.
  
  By default all passwords are converted to `password`. The `--no-scrub` option will disable scrubbing.
  
* __wpprojects pull \<instance-id\>__ - Must be run from root of installed WordPress instance.
  
  This command pulls an existing project instance from the repository into your current WordPress install replacing your database and `wp-content` directory entirely. The command will interactively prompt you to map URLs to be search and replaced. If the project instance is a multisite, you will have to map URLs interactively for each blog in the network.
  
* __wpprojects search \<search-text\>__
  
  This command searches the repository for project instances. `<search-text>` will be compared against project names and authors.
  
* __wpprojects delete \<instance-id\>__
  
  This command deletes a project instance from the repository.
  
## Windows

Someone with Windows needs to figure this out :)


