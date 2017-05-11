<?php

namespace alexpott\ConfigSyncMerge\Tests;

use alexpott\ConfigSyncMerge\DataAdapters\CoreExtension;
use Drupal\Core\Config\FileStorage;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \alexpott\ConfigSyncMerge\DataAdapters\CoreExtension
 */
class CoreExtensionTest extends ConfigSyncMergeTestBase
{

    /**
     * @param string[] $dirs List of directory names relative to the fixtures directory
     * @return \Drupal\Core\Config\StorageInterface[]
     */
    protected function getStorages($dirs)
    {
        $storages = [$this->core];
        foreach ($this->convertDirsToVfs($dirs) as $directory) {
            $storages[] = new FileStorage($directory);
        }
        return $storages;
    }

    /**
     * @dataProvider dataProviderTestApplies
     */
    public function testApplies($name, $expected)
    {
        $data_adapter = new CoreExtension();
        $this->assertSame($expected, $data_adapter->applies($name));
    }

    public function dataProviderTestApplies()
    {
        return [
            ['core.extension', TRUE],
            ['system.site', FALSE],
            [['core.extension', 'system.site'], TRUE],
            [['system.file', 'system.site'], FALSE],
        ];
    }

    /**
     * @covers ::read
     * @dataProvider dataProviderTestRead
     */
    public function testRead(array $dirs, $expected_modules, $expected_themes, $expected_profile)
    {
        $storages = $this->getStorages($dirs);
        $data_adapter = new CoreExtension();
        $data = $data_adapter->read('core.extension', $storages);
        $this->assertSame($expected_modules, $data['module']);
        $this->assertSame($expected_themes, $data['theme']);
        $this->assertSame($expected_profile, $data['profile']);
    }

    public function dataProviderTestRead()
    {
        return [
            [
                [],
                ['contact' => 0, 'minimal_plus' => 1000],
                ['bartik' => 0],
                'minimal_plus'
            ],
            [
                // Even though merge1 contains a core.extension using the
                // minimal profile it will merge into the core storage which is
                // using the minimal_plus profile.
                ['merge1'],
                ['block' => 0, 'contact' => 0, 'dblog' => 0, 'dynamic_page_cache' => 0, 'field' => 0, 'file' => 0, 'filter' => 0, 'node' => 0, 'page_cache' => 0, 'simpletest' => 0, 'system' => 0, 'text' => 0, 'update' => 0, 'user' => 0, 'minimal_plus' => 1000],
                ['bartik' => 0, 'classy' => 0, 'stark' => 0],
                'minimal_plus'
            ],
        ];
    }

    /**
     * @covers ::readMultiple
     * @dataProvider dataProviderTestRead
     */
    public function testReadMultiple(array $dirs, $expected_modules, $expected_themes, $expected_profile)
    {
        $storages = $this->getStorages($dirs);
        $data_adapter = new CoreExtension();
        $data = $data_adapter->readMultiple(['core.extension', 'foo.bar'], $storages);
        $this->assertSame($expected_modules, $data['core.extension']['module']);
        $this->assertSame($expected_themes, $data['core.extension']['theme']);
        $this->assertSame($expected_profile, $data['core.extension']['profile']);
        $this->assertArrayNotHasKey('foo.bar', $data);
    }

    /**
     * @covers ::write
     * @covers ::moduleSort
     */
    public function testWrite()
    {
        $storages = $this->getStorages(['merge1']);
        $data_adapter = new CoreExtension();
        $current_data = $data_adapter->read('core.extension', $storages);
        $expected = $storages[0]->read('core.extension');
        $data_to_write = $data_adapter->write('core.extension', $current_data, $storages);
        // There's nothing to change.
        $this->assertSame([], $data_to_write);

        // Remove a top level module.
        unset($current_data['module']['contact']);
        unset($expected['module']['contact']);
        $data_to_write = $data_adapter->write('core.extension', $current_data, $storages);
        $this->assertSame($expected, $data_to_write);

        // Remove a top level module.
        unset($current_data['theme']['bartik']);
        unset($expected['theme']['bartik']);
        $data_to_write = $data_adapter->write('core.extension', $current_data, $storages);
        $this->assertSame($expected, $data_to_write);

        // Add a new modules and themes.
        $current_data['module']['page_manager'] = 0;
        $current_data['module']['devel'] = 10;
        $current_data['theme']['seven'] = 0;
        $current_data['theme']['bartik'] = 0;
        $expected = [
            'module' => ['page_manager' => 0, 'devel' => 10, 'minimal_plus' => 1000],
            'theme' => ['bartik' => 0, 'seven' => 0],
            'profile' => 'minimal_plus',
            '_core' => ['default_config_hash' => 'R4IF-ClDHXxblLcG0L7MgsLvfBIMAvi_skumNFQwkDc'],
        ];

        $data_to_write = $data_adapter->write('core.extension', $current_data, $storages);
        $this->assertSame($expected, $data_to_write);

        // Test what happens when there is no core.extension in the top storage.
        $current_data = $storages[1]->read('core.extension');
        $storages[0] = new FileStorage(vfsStream::url('root') . '/merge2');
        $data_to_write = $data_adapter->write('core.extension', $current_data, $storages);
        $this->assertSame([], $data_to_write);

        // Add a new modules and themes.
        $current_data['module']['page_manager'] = 0;
        $current_data['module']['devel'] = 10;
        $current_data['theme']['seven'] = 0;
        $current_data['theme']['bartik'] = 0;
        $expected = [
            'module' => ['page_manager' => 0, 'devel' => 10],
            'theme' => ['bartik' => 0, 'seven' => 0],
            'profile' => 'minimal',
            '_core' => ['default_config_hash' => 'R4IF-ClDHXxblLcG0L7MgsLvfBIMAvi_skumNFQwkDc'],
        ];

        $data_to_write = $data_adapter->write('core.extension', $current_data, $storages);
        $this->assertSame($expected, $data_to_write);


    }

    /**
     * @covers ::write
     */
    public function testWriteModuleException()
    {
        $storages = $this->getStorages(['merge1']);
        $data_adapter = new CoreExtension();
        $current_data = $data_adapter->read('core.extension', $storages);
        // Remove modules that are merged.
        unset($current_data['module']['node']);
        unset($current_data['module']['text']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected module removal: node, text');

        $data_adapter->write('core.extension', $current_data, $storages);
    }

    /**
     * @covers ::write
     */
    public function testWriteThemeException()
    {
        $storages = $this->getStorages(['merge1']);
        $data_adapter = new CoreExtension();
        $current_data = $data_adapter->read('core.extension', $storages);
        // Remove modules that are merged.
        unset($current_data['theme']['classy']);
        unset($current_data['theme']['stark']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected theme removal: classy, stark');

        $data_adapter->write('core.extension', $current_data, $storages);
    }
}
