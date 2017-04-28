<?php

namespace alexpott\ConfigSyncMerge\Tests;

use alexpott\ConfigSyncMerge\ConfigSyncMergeFactory;
use Drupal\Core\Site\Settings;

/**
 * @coversDefaultClass \alexpott\ConfigSyncMerge\ConfigSyncMergeFactory
 */
class ConfigSyncMergeFactoryTest extends ConfigSyncMergeTestBase
{
    /**
     * @dataProvider dataProviderTestListAll
     */
    public function testGetSync(array $dirs, $prefix, $expected) {
        $settings = new Settings([
            'config_sync_merge_directories' => $this->convertDirsToVfs($dirs)
        ]);
        $factory = new ConfigSyncMergeFactory($settings, $this->core);
        $this->assertSame($expected, $factory->getSync()->listAll($prefix));
    }

}
