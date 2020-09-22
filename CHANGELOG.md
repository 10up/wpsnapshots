# Changelog

All notable changes to this project will be documented in this file, per [the Keep a Changelog standard](http://keepachangelog.com/).

## [Unreleased] - TBD

## [2.1.0] - TBD
### Added
- `--overwirte_local_copy` flag to the `pull` command.
- `--format` option to the search command that accepts json and table values.

### Changed
- Search command arguments to allow multiple queries.
- `--include_files` and `--include_db` flags of `create`, `download`, `pull` and `push` commands to accept negative values.
- `--confirm_wp_version_change` flag of the `pull` command to accept negative values.

### Fixed
- Empty line issue rendered at the beginning of all commands.

## [2.0.1] - 2020-08-06
### Fixed
- User scrubbing on multisites (props [@felipeelia](https://github.com/felipeelia) via [#66](https://github.com/10up/wpsnapshots/pull/66))

## [2.0] - 2020-07-15
### Added
- CLI options for slug and description (props [@tlovett1](https://github.com/tlovett1) via [#60](https://github.com/10up/wpsnapshots/pull/60))

### Changed
- Updated questions to clearly identify default values (props [@eugene-manuilov](https://github.com/eugene-manuilov) via [#63](https://github.com/10up/wpsnapshots/pull/63))

## [1.6.3] - 2020-01-18
### Changed
- Disabled AWS Client Side Monitoring (props [@christianc1](https://github.com/christianc1) via [#59](https://github.com/10up/wpsnapshots/pull/59))

## [1.6.2] - 2019-10-17
### Fixed
- Sites mapping issue (props [@tlovett1](https://github.com/tlovett1))

## [1.6.1] - 2019-10-17
### Changed
- Improve hosts file suggestion (props [@adamsilverstein](https://github.com/adamsilverstein) via [#50](https://github.com/10up/wpsnapshots/pull/50))
- Use `--single-transaction` during the database backup phase of a create or push (props [@dustinrue](https://github.com/dustinrue) via [#58](https://github.com/10up/wpsnapshots/pull/58))
- Documentation updates (props [@jeffpaul](https://github.com/jeffpaul) via [#54](https://github.com/10up/wpsnapshots/pull/54))

## [1.6] - 2019-04-16
### Added
- `fs` method, `small` option, and cookie constants (props [@tlovett1](https://github.com/tlovett1))

## [1.5.4] - 2019-03-21
### Added
- Default, local repo (props [@tlovett1](https://github.com/tlovett1))

### Changed
- If unable to decode config, set empty array (props [@tlovett1](https://github.com/tlovett1))

## [1.5.3] - 2018-12-09

## [1.5.2] - 2018-11-14

## [1.5.1] - 2018-10-23

## [1.5] - 2018-10-14

## [1.4] - 2018-10-10

## [1.3.1] - 208-09-30

## [1.3] - 2018-09-23

## [1.2.1] - 2018-09-18

## [1.2] - 2018-09-16

## [1.1.3] - 2018-07-31

## [1.1.2] - 2018-02-23

## [1.1.1] - 2018-01-12

## [1.0] - 2017-12-11

[Unreleased]: https://github.com/10up/wpsnapshots/compare/2.1.0...develop
[2.1.0]: https://github.com/10up/wpsnapshots/compare/2.0.1...2.1.0
[2.0.1]: https://github.com/10up/wpsnapshots/compare/2.0...2.0.1
[2.0]: https://github.com/10up/wpsnapshots/compare/1.6.3...2.0
[1.6.3]: https://github.com/10up/wpsnapshots/compare/1.6.2...1.6.3
[1.6.2]: https://github.com/10up/wpsnapshots/compare/1.6.1...1.6.2
[1.6.1]: https://github.com/10up/wpsnapshots/compare/1.6...1.6.1
[1.6]: https://github.com/10up/wpsnapshots/compare/1.5.4...1.6
[1.5.4]: https://github.com/10up/wpsnapshots/compare/1.5.3...1.5.4
[1.5.3]: https://github.com/10up/wpsnapshots/compare/1.5.2...1.5.3
