# Config sync merge
## About
This library allows you to merge configuration from other directories with your
Drupal site's configuration sync directory. The additional directories are
treated as read-only. If configuration exists in multiple locations, your
configuration sync directory is read first and then the other directories in the
order that they are defined in settings.php.

If you export configuration using ```drush config-export``` it will only make
changes to your configuration sync directory. The other directories will not
be changed. If configuration being saved is an exact match for configuration in
an other directory it will not be written to your config sync directory. If the
configuration is different, it will be written.

**IMPORTANT:** Configuration in the deepest directory should represent a complete
site. This will make it easier to manage configuration removals.

## Installation
Get the code:
```bash
composer require alexpott/config-sync-merge
```

Add the following lines to your settings.php:
```php
$settings['container_yamls'][] = $app_root . '/vendor/alexpott/config-sync-merge/drupal.services.yml';
$settings['config_sync_merge_directories'] = [
  'PATH/TO/ADDITIONAL/CONFIG'
];
```

NB: If you have changed the vendor location you will need to alter the first line.

## Suggested workflows
@todo