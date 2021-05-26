# Contributing and Maintaining

First, thank you for taking the time to contribute!

The following is a set of guidelines for contributors as well as information and instructions around our maintenance process.  The two are closely tied together in terms of how we all work together and set expectations, so while you may not need to know everything in here to submit an issue or pull request, it's best to keep them in the same document.

## Ways to contribute

Contributing isn't just writing code - it's anything that improves the project.  All contributions for WP Snapshots are managed right here on GitHub.  Here are some ways you can help:

### Reporting bugs

If you're running into an issue with the project, please take a look through [existing issues](https://github.com/10up/wpsnapshots/issues) and [open a new one](https://github.com/10up/wpsnapshots/issues/new) if needed.  If you're able, include steps to reproduce, environment information, and screenshots/screencasts as relevant.

### Suggesting enhancements

New features and enhancements are also managed via [issues](https://github.com/10up/wpsnapshots/issues).

### Pull requests

Pull requests represent a proposed solution to a specified problem.  They should always reference an issue that describes the problem and contains discussion about the problem itself.  Discussion on pull requests should be limited to the pull request itself (e.g., code review).

For more on how 10up writes and manages code, check out our [10up Engineering Best Practices](https://10up.github.io/Engineering-Best-Practices/).

## Workflow

The `develop` branch is the development branch which means it contains the next version to be released.  `master` contains the current latest release and the corresponding stable development version.  Always work on the `develop` branch and open up PRs against `develop`.

## Release instructions

1. Branch: Starting from `develop`, cut a release branch named `release/X.Y.Z` for your changes.
1. Version bump: Bump the version number in `src/bootstrap.php` if it does not already reflect the version being released.
1. Changelog: Add/update the changelog in `CHANGELOG.md`.
1. Props: Update `CREDITS.md` file with any new contributors, confirm maintainers are accurate
1. Readme updates: Make any other readme changes as necessary in `README.md`.
1. Merge: Make a non-fast-forward merge from your release branch to `develop` (or merge the pull request), then do the same for `develop` into `master` (`git checkout master && git merge --no-ff develop`).  `master` contains the stable development version.
1. Push: Push your `master` branch to GitHub (e.g. `git push origin master`).
1. Release: Create a [new release](https://github.com/10up/wpsnapshots/releases/new), naming the tag and the release as `X.Y.Z`, and targeting the `master` branch.  Paste the changelog from `CHANGELOG.md` into the body of the release and include a link to the closed issues on the [milestone](https://github.com/10up/wpsnapshots/milestone/#?closed=1).  The release should now appear under [releases](https://github.com/10up/wpsnapshots/releases).
1. [Wait for build](https://xkcd.com/303/): Head to the [Actions](https://github.com/10up/wpsnapshots/actions) tab in the repo and wait for it to finish if it hasn't already. If it doesn't succeed, figure out why and start over.  The new images should now appear under [tags](https://hub.docker.com/r/10up/wpsnapshots/tags?page=1&ordering=last_updated) as `X`, `X.Y.Z`, and `latest`.
1. Close milestone: Edit the [milestone](https://github.com/10up/wpsnapshots/milestone/#) with release date (in the `Due date (optional)` field) and link to GitHub release (in the `Description field`), then close the milestone.
1. Punt incomplete items: If any open issues or PRs which were milestoned for `X.Y.Z` do not make it into the release, update their milestone to `X.Y.Z+1`, `X.Y+1.0`, `X+1.0.0`, or `Future Release`.
