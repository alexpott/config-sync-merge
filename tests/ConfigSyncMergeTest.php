<?php

namespace alexpott\ConfigSyncMerge\Tests;

use alexpott\ConfigSyncMerge\ConfigSyncMerge;
use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Site\Settings;
use org\bovigo\vfs\vfsStream;
use \PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \alexpott\ConfigSyncMerge\ConfigSyncMerge
 */
class ConfigSyncMergeTest extends TestCase
{

    /**
     * @var \org\bovigo\vfs\vfsStreamDirectory
     */
    protected $fixtures;

    /**
     * @var \Drupal\Core\Config\StorageInterface
     */
    protected $core;

    public function setUp() {
        // Ensure that the static file cache factory is unique per test method.
        FileCacheFactory::setPrefix(__CLASS__ . ':' . $this->getName());
        $root = vfsStream::setup('root');
        $this->fixtures = vfsStream::copyFromFileSystem(__DIR__ . '/fixtures', $root);
        $this->core = new FileStorage(vfsStream::url('root') . '/core');
    }

    /**
     * @param string[] $dirs
     *   An array of relative directories to the fixtures directory.
     *
     * @return string[]
     */
    protected function convertDirsToVfs($dirs)
    {
        foreach ($dirs as &$dir) {
            $dir = vfsStream::url('root') . '/' . $dir;
        }
        return $dirs;
    }

    /**
     * @dataProvider dataProviderTestExists
     */
    public function testExists(array $dirs, $name, $expected)
    {
        $settings = new Settings(['config_sync_merge_directories' => $this->convertDirsToVfs($dirs)]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertSame($expected, $config_sync_merge->exists($name));
    }

    public function dataProviderTestExists()
    {
        return [
            [[], 'foo.bar', TRUE],
            [[], 'bar.foo', FALSE],
            [['merge1'], 'foo.bar', TRUE],
            [['merge1'], 'bar.foo', TRUE],
            [['merge1'], 'baa.baa', FALSE],
            [['merge1', 'merge2'], 'baa.baa', TRUE],
        ];
    }

    /**
     * @dataProvider dataProviderTestRead
     */
    public function testRead(array $dirs, $name, $expected)
    {
        $settings = new Settings(['config_sync_merge_directories' => $this->convertDirsToVfs($dirs)]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertSame($expected, $config_sync_merge->read($name));
    }

    public function dataProviderTestRead()
    {
        return [
            [[], 'foo.bar', ['value' => 'core']],
            [[], 'bar.foo', FALSE],
            [['merge1'], 'foo.bar', ['value' => 'core']],
            [['merge1'], 'bar.foo', ['value' => 'merge1']],
            [['merge1'], 'baa.baa', FALSE],
            [['merge1', 'merge2'], 'baa.baa', ['value' => 'merge2']],
            [['merge1', 'merge2'], 'foo.bar', ['value' => 'core']],
        ];
    }

    /**
     * @dataProvider dataProviderTestReadMultiple
     */
    public function testReadMultiple(array $dirs, array $names, $expected)
    {
        $settings = new Settings(['config_sync_merge_directories' => $this->convertDirsToVfs($dirs)]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertSame($expected, $config_sync_merge->readMultiple($names));
    }

    public function dataProviderTestReadMultiple()
    {
        return [
            [[], ['foo.bar'], ['foo.bar' => ['value' => 'core']]],
            [[], ['bar.foo'], []],
            [['merge1'], ['foo.bar', 'bar.foo'], ['bar.foo' => ['value' => 'merge1'], 'foo.bar' => ['value' => 'core']]],
            [['merge1'], ['foo.bar', 'bar.foo', 'baa.baa'], ['bar.foo' => ['value' => 'merge1'], 'foo.bar' => ['value' => 'core']]],
            [['merge1', 'merge2'], ['foo.bar', 'bar.foo', 'baa.baa'], ['baa.baa' => ['value' => 'merge2'], 'bar.foo' => ['value' => 'merge1'], 'foo.bar' => ['value' => 'core']]],
            [['merge1', 'merge2'], ['a.a', 'b.b'], []],

        ];
    }

    public function testWrite() {
        $dirs = $this->convertDirsToVfs(['merge1', 'merge2']);
        $settings = new Settings(['config_sync_merge_directories' => $dirs]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $config_sync_merge->write('test.write', ['test' => 'data']);
        $this->assertTrue(file_exists(vfsStream::url('root') . '/core/test.write.yml'));
        foreach ($dirs as $dir) {
            $this->assertFalse(file_exists($dir . '/test.write.yml'));
        }
        $config_sync_merge->write('baa.baa', ['value' => 'merge2_write']);
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/baa.baa.yml'));
        $this->assertSame(['value' => 'merge2_write'], $config_sync_merge->decode(file_get_contents($dirs[1] . '/baa.baa.yml')));
    }

    public function testDelete() {
        $dirs = $this->convertDirsToVfs(['merge1', 'merge2']);
        $settings = new Settings(['config_sync_merge_directories' => $dirs]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertFalse($config_sync_merge->delete('a.a'));
        $this->assertTrue(file_exists(vfsStream::url('root') . '/core/foo.bar.yml'));
        $this->assertTrue($config_sync_merge->delete('foo.bar'));
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/foo.bar.yml'));
        foreach ($dirs as $dir) {
            $this->assertFalse(file_exists($dir . '/foo.bar.yml'));
        }
        $this->assertTrue($config_sync_merge->exists('baa.baa'));
        $this->assertTrue($config_sync_merge->delete('baa.baa'));
        $this->assertFalse($config_sync_merge->exists('baa.baa'));
    }

    public function testRename() {
        $dirs = $this->convertDirsToVfs(['merge1', 'merge2']);
        $settings = new Settings(['config_sync_merge_directories' => $dirs]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertFalse($config_sync_merge->rename('a.a', 'b.b'));
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/a.a.yml'));
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/b.b.yml'));
        $this->assertTrue($config_sync_merge->rename('foo.bar', 'test.bar'));
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/foo.bar.yml'));
        $this->assertTrue(file_exists(vfsStream::url('root') . '/core/test.bar.yml'));
        foreach ($dirs as $dir) {
            $this->assertFalse(file_exists($dir . '/foo.bar.yml'));
            $this->assertTrue(file_exists($dir . '/test.bar.yml'));
        }
        $this->assertTrue($config_sync_merge->rename('baa.baa', 'foo.foo'));
        $this->assertFalse(file_exists(vfsStream::url('root') . '/core/foo.foo.yml'));
        $this->assertFalse(file_exists($dirs[0] . '/foo.foo.yml'));
        $this->assertTrue(file_exists($dirs[1] . '/foo.foo.yml'));
    }

    /**
     * @dataProvider dataProviderTestListAll
     */
    public function testListAll(array $dirs, $prefix, $expected)
    {
        $settings = new Settings(['config_sync_merge_directories' => $this->convertDirsToVfs($dirs)]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertSame($expected, $config_sync_merge->listAll($prefix));
    }

    public function dataProviderTestListAll()
    {
        return [
            [[], '', ['foo.bar']],
            [[], 'foo', ['foo.bar']],
            [[], 'bar', []],
            [['merge1'], '', ['bar.foo', 'foo.bar']],
            [['merge1'], 'bar', ['bar.foo']],
            [['merge1'], 'baa', []],
            [['merge1', 'merge2'], '', ['baa.baa', 'bar.foo', 'foo.bar']],
        ];
    }

    /**
     * @dataProvider dataProviderTestListAll
     */
    public function testDeleteAll(array $dirs, $prefix, $expected)
    {
        $settings = new Settings(['config_sync_merge_directories' => $this->convertDirsToVfs($dirs)]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertSame($expected, $config_sync_merge->listAll($prefix));
    }

    public function dataProviderTestDeleteAll()
    {
        return [
            [[], '', []],
            [[], 'foo', []],
            [[], 'bar', ['foo.bar']],
            [['merge1'], '', []],
            [['merge1'], 'bar', ['foo.bar']],
            [['merge1'], 'baa', ['bar.foo', 'foo.bar']],
            [['merge1', 'merge2'], 'test', ['baa.baa', 'bar.foo', 'foo.bar']],
            [['merge1', 'merge2'], '', []],
            [['merge1', 'merge2'], 'ba', ['foo.bar']],
        ];
    }

    public function testCreateCollection()
    {
        $dirs = $this->convertDirsToVfs(['merge1', 'merge2']);
        $settings = new Settings(['config_sync_merge_directories' => $dirs]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $config_sync_merge = $config_sync_merge->createCollection('fr');
        $this->assertSame(['value' => 'fr-core'], $config_sync_merge->read('foo.bar'));
        $this->assertFalse($config_sync_merge->exists('baa.baa'));
        $this->assertSame(['value' => 'fr-merge1'], $config_sync_merge->read('bar.foo'));

        $config_sync_merge = new ConfigSyncMerge($settings, $this->core, 'fr');
        $this->assertSame(['value' => 'fr-core'], $config_sync_merge->read('foo.bar'));
        $this->assertFalse($config_sync_merge->exists('baa.baa'));
        $this->assertSame(['value' => 'fr-merge1'], $config_sync_merge->read('bar.foo'));

        $config_sync_merge = new ConfigSyncMerge($settings, $this->core, 'lol');
        $this->assertSame([], $config_sync_merge->listAll());
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertSame([], $config_sync_merge->createCollection('lol')->listAll());
    }

    /**
     * @dataProvider dataProviderTestGetAllCollectionNames
     */
    public function testGetAllCollectionNames($dirs, $expected) {
        $settings = new Settings(['config_sync_merge_directories' => $this->convertDirsToVfs($dirs)]);
        $config_sync_merge = new ConfigSyncMerge($settings, $this->core);
        $this->assertSame($expected, $config_sync_merge->getAllCollectionNames());
    }

    public function dataProviderTestGetAllCollectionNames() {
        return [
            [[], ['fr']],
            [['merge1'], ['fr']],
            [['merge1', 'merge2'], ['de', 'fr']],
        ];
    }
}

namespace Drupal\Core\Config;

if (!function_exists('drupal_chmod')) {
    function drupal_chmod($uri, $mode = NULL) {
        return TRUE;
    }
}

if (!function_exists('drupal_unlink')) {
    function drupal_unlink($uri) {
        return unlink($uri);
    }
}