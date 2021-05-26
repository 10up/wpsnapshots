# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/).

## [Unreleased] - TBD

## [2.2.1] - TBD
### Fixed
- Allow skipping `--overwrite_local_copy` without prompt (props [dinhtungdu](https://github.com/dinhtungdu) via [#86](https://github.com/10up/wpsnapshots/pull/86)).

## [2.2.0] - 2021-05-17
- Support overriding version for create and push as well as setting version to `nightly` (props [dinhtungdu](https://github.com/dinhtungdu).
- Better error handling for `get_download_url` (props [paulschreiber](https://github.com/paulschreiber).
- Message warning users about using production databases.
- Update `rmccue/requests` version.

## [2.1.0] - 2021-01-22
### Added
- `--overwirte_local_copy` flag to the `pull` command (props [@eugene-manuilov](https://github.com/eugene-manuilov) via [#71](https://github.com/10up/wpsnapshots/pull/71)).
- `--suppress_instructions` flag to the `pull` command (props [@eugene-manuilov](https://github.com/eugene-manuilov) via [#76](https://github.com/10up/wpsnapshots/pull/76)).
- `--format` option to the search command that accepts json and table values (props [@eugene-manuilov](https://github.com/eugene-manuilov) via [#70](https://github.com/10up/wpsnapshots/pull/70)).
- GitHub actions to build and push a new docker image when a new release is published (props [@eugene-manuilov](https://github.com/eugene-manuilov) via [#72](https://github.com/10up/wpsnapshots/pull/72), [#73](https://github.com/10up/wpsnapshots/pull/73)).

### Changed
- Search command arguments to allow multiple queries.
- `--include_files` and `--include_db` flags of `create`, `download`, `pull` and `push` commands to accept negative values.
- `--confirm_wp_version_change` flag of the `pull` command to accept negative values.
- Documentation updates (props [@eugene-manuilov](https://github.com/eugene-manuilov), [@jeffpaul](https://github.com/jeffpaul) via [#75](https://github.com/10up/wpsnapshots/pull/75)).
- Docker image to be compatible with the current version of `10up/wpsnapshots:dev` (props [@eugene-manuilov](https://github.com/eugene-manuilov) via [#74](https://github.com/10up/wpsnapshots/pull/74)).

### Removed
- `--column-statistics=0 --no-tablespaces` parameters (props [@felipeelia](https://github.com/felipeelia) via [#68](https://github.com/10up/wpsnapshots/pull/68)).

### Fixed
- Empty line issue rendered at the beginning of all commands.

## [2.0.1] - 2020-08-06
### Fixed
- User scrubbing on multisites (props [@felipeelia](https://github.com/felipeelia) via [#66](https://github.com/10up/wpsnapshots/pull/66)).

## [2.0] - 2020-07-15
### Added
- CLI options for slug and description (props [@tlovett1](https://github.com/tlovett1) via [#60](https://github.com/10up/wpsnapshots/pull/60)).

### Changed
- Updated questions to clearly identify default values (props [@eugene-manuilov](https://github.com/eugene-manuilov) via [#63](https://github.com/10up/wpsnapshots/pull/63)).

## [1.6.3] - 2020-01-18
### Changed
- Disabled AWS Client Side Monitoring (props [@christianc1](https://github.com/christianc1) via [#59](https://github.com/10up/wpsnapshots/pull/59)).

## [1.6.2] - 2019-10-17
### Fixed
- Sites mapping issue (props [@tlovett1](https://github.com/tlovett1)).

## [1.6.1] - 2019-10-17
### Changed
- Improve hosts file suggestion (props [@adamsilverstein](https://github.com/adamsilverstein) via [#50](https://github.com/10up/wpsnapshots/pull/50)).
- Use `--single-transaction` during the database backup phase of a create or push (props [@dustinrue](https://github.com/dustinrue) via [#58](https://github.com/10up/wpsnapshots/pull/58)).
- Documentation updates (props [@jeffpaul](https://github.com/jeffpaul) via [#54](https://github.com/10up/wpsnapshots/pull/54)).

## [1.6] - 2019-04-16
### Added
- `fs` method, `small` option, and cookie constants (props [@tlovett1](https://github.com/tlovett1)).

## [1.5.4] - 2019-03-21
### Added
- Default, local repo (props [@tlovett1](https://github.com/tlovett1)).

### Changed
- If unable to decode config, set empty array (props [@tlovett1](https://github.com/tlovett1)).

## [1.5.3] - 2018-12-09
### Fixed
- S3 push error warning (props [@tlovett1](https://github.com/tlovett1)).

## [1.5.2] - 2018-11-14
### Added
- Scrubs email addresses as well as passwords (props [@ChaosExAnima](https://github.com/ChaosExAnima) via [#47](https://github.com/10up/wpsnapshots/pull/47)).

## [1.5.1] - 2018-10-23
### Added
- Custom snapshot directory environment variable (props [@tlovett1](https://github.com/tlovett1)).

## [1.5] - 2018-10-14
### Changed
- Refactoring from Connection to RepositoryManager and backcompat for filling repo name in (props [@tlovett1](https://github.com/tlovett1)).

## [1.4] - 2018-10-10
### Added
- Multi-repo support and verbose logging (props [@tlovett1](https://github.com/tlovett1)).

### Changed
- Search and replace optimizations, get site and home URLs directly (props [@tlovett1](https://github.com/tlovett1)).

### Fixed
- `region` variable in `LocationConstraint` (props [@tlovett1](https://github.com/tlovett1)).

## [1.3.1] - 208-09-30
### Fixed
- Line splitting issue (props [@tlovett1](https://github.com/tlovett1)).

## [1.3] - 2018-09-23
### Added
- Enable pushing an already created snapshot, smart defaults for Pull (props [@tlovett1](https://github.com/tlovett1)).
- Auto-write multisite constatns to `wp-config.php` (props [@tlovett1](https://github.com/tlovett1)).
- Port to blog domain, main domain CLI option (props [@tlovett1](https://github.com/tlovett1)).

## [1.2.1] - 2018-09-18
### Fixed
- Provide `signature`, `region`, and `version` in the `::test` method (props [@cmmarslender](https://github.com/cmmarslender) via [#40](https://github.com/10up/wpsnapshots/pull/40)).

## [1.2] - 2018-09-16
### Added
- `Create` and `Download` commands (props [@tlovett1](https://github.com/tlovett1)).
- Snapshot caching (props [@tlovett1](https://github.com/tlovett1)).
- Save `meta.json` file inside snapshot directory with snapshot data (props [@tlovett1](https://github.com/tlovett1)).
- PHPCS standardization and fixes (props [@tlovett1](https://github.com/tlovett1)).
- Store all multisite data in snapshot (props [@tlovett1](https://github.com/tlovett1)).
- Store `blogname` in snapshot (props [@tlovett1](https://github.com/tlovett1)).
- Symfony console update (props [@christianc1](https://github.com/christianc1), [@colorful-tones](https://github.com/colorful-tones) via [#36](https://github.com/10up/wpsnapshots/pull/36)).
- `wpsnapshots` user (props [@tlovett1](https://github.com/tlovett1)).

### Changed
- Move config file to `~/.wpsnapshots/config.json`(props [@tlovett1](https://github.com/tlovett1)).
- Abstract out `Snapshot` class to make programmatic interaction with WP Snapshots easier (props [@tlovett1](https://github.com/tlovett1)).
- Properly test MySQL connection before bootstrapping WordPress (props [@tlovett1](https://github.com/tlovett1)).
- Multisite pull changes: should URLs inside existing snapshots, make sure type full URLs instead of paths (props [@tlovett1](https://github.com/tlovett1)).
- `&>` to `2>&1`. `&>` is a bash shortcut for the other, but not running bash with `shell_exec` so sometimes still see the errors (props [@cmmarslender](https://github.com/cmmarslender) via [#37](https://github.com/10up/wpsnapshots/pull/37)).
- Update AWS SDK and require PHP 7 (props [@tlovett1](https://github.com/tlovett1)).
- Ensure WordPRess is present (props [@tlovett1](https://github.com/tlovett1)).

## [1.1.3] - 2018-07-31
### Added
- Reference AWS Setup documentation in main repo readme (props [@christianc1](https://github.com/christianc1) via [#28](https://github.com/10up/wpsnapshots/pull/28)).

### Fixed
- Error on `CreateRepository` command (props [@tlovett1](https://github.com/tlovett1), [@EvanAgee](https://github.com/EvanAgee)).
- Bug where downloading WordPress and moving `wp-content` when it already existed threw an error (props [@tlovett1](https://github.com/tlovett1)).
- `maxdepth` parameter order (props [@tlovett1](https://github.com/tlovett1)).

## [1.1.2] - 2018-02-23
### Fixed
-  Use specific S3 region when creating a bucket (props [@tlovett1](https://github.com/tlovett1), [@nick-jansen](https://github.com/nick-jansen)).

## [1.1.1] - 2018-01-12
### Added
- `WPSNAPSHOTS` constant to bootstrap (props [@joeyblake](https://github.com/joeyblake) via [#19](https://github.com/10up/wpsnapshots/pull/19)).
- Documentation updates (props [@qriouslad](https://github.com/qriouslad), [@tlovett1](https://github.com/tlovett1) via [#17](https://github.com/10up/wpsnapshots/pull/17)).

### Fixed
- Fix space in path bug (props [@tlovett1](https://github.com/tlovett1)).

## [1.0] - 2017-12-11
- Initial WP Snapshots release.

[Unreleased]: https://github.com/10up/wpsnapshots/compare/master...develop
[2.2.0]: https://github.com/10up/wpsnapshots/compare/2.1.0...2.2.0
[2.1.0]: https://github.com/10up/wpsnapshots/compare/2.0.1...2.1.0
[2.0.1]: https://github.com/10up/wpsnapshots/compare/2.0...2.0.1
[2.0]: https://github.com/10up/wpsnapshots/compare/1.6.3...2.0
[1.6.3]: https://github.com/10up/wpsnapshots/compare/1.6.2...1.6.3
[1.6.2]: https://github.com/10up/wpsnapshots/compare/1.6.1...1.6.2
[1.6.1]: https://github.com/10up/wpsnapshots/compare/1.6...1.6.1
[1.6]: https://github.com/10up/wpsnapshots/compare/1.5.4...1.6
[1.5.4]: https://github.com/10up/wpsnapshots/compare/1.5.3...1.5.4
[1.5.3]: https://github.com/10up/wpsnapshots/compare/1.5.2...1.5.3
[1.5.2]: https://github.com/10up/wpsnapshots/compare/1.5.1...1.5.2
[1.5.1]: https://github.com/10up/wpsnapshots/compare/1.5...1.5.1
[1.5]: https://github.com/10up/wpsnapshots/compare/1.4...1.5
[1.4]: https://github.com/10up/wpsnapshots/compare/1.3.1...1.4
[1.3.1]: https://github.com/10up/wpsnapshots/compare/1.3...1.3.1
[1.3]: https://github.com/10up/wpsnapshots/compare/1.2.1...1.3
[1.2.1]: https://github.com/10up/wpsnapshots/compare/1.2...1.2.1
[1.2]: https://github.com/10up/wpsnapshots/compare/1.1.3...1.2
[1.1.3]: https://github.com/10up/wpsnapshots/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/10up/wpsnapshots/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/10up/wpsnapshots/compare/1.0...1.1.1
[1.0]: https://github.com/10up/wpsnapshots/releases/tag/1.0
