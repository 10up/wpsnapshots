# WP Projects

WP Projects is a project syncing tool for WordPress empowering developers to easily push instances of projects into the cloud for sharing with team members.

## How Does It Work?

WP Projects stores projects in a centralized repository (AWS). Users setup up WP Projects with their teams AWS credentials. Users can then push, pull, and search for projects. When a user pushes a project, an instance of their current environment (wp-content/ and database) is pushed to Amazon. When a project is pulled, files are pulled from the cloud replacing wp-content/ and data is intelligently merged into the database.

Projects files (wp-content/) and WordPress database tables are stored in Amazon S3. General project data is stored in Amazon DynamoDB.

## Install

WPProjects is easiest to use as a global Composer package. Right now it is available only as a private 10up package. Assuming you have Composer installed and SSH keys setup within Github/10up organiziation, do the following:

1. Add the 10up/wpprojects repository as a global Composer repository:
  ```
  composer global config repositories.wpprojects vcs https://github.com/10up/wpprojects
  ```
2. Lower your global minimum Composer stability to `dev`. This is necessary since WPProjects is beta software.
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