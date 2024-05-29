## [8.0.0](https://github.com/anzusystems/common-bundle/compare/7.0.0...8.0.0) (2024-05-29)
### Features
* Added command `anzusystems:user:sync-base` for loading basic user set (depends on `user_sync_data` configuration)
* Added `BaseUserDto` to `UserDto`, added `UserTracking` and `TimeTracking` fields 
* Added `mapDataFn` to `findByApiParams` and `findByApiParamsWithInfiniteListing` functions

### Changes
* BC change -> Abstract voter expects `ROLE_SUPER_ADMIN` instead of `ROLE_ADMIN` to grant full access

## [7.0.0](https://github.com/anzusystems/common-bundle/compare/6.0.4...7.0.0) (2024-05-13)
### Changes
* Fix sending job with old status to event dispatcher and not updating modifiedBy by @pulzarraider in #56
* Update to anzusystems/serializer-bundle 4.0 by @pulzarraider in #57
Read the UPGRADE.md if you want to update to this version.
