<?php

use Mockery as m;

class FoundationAssetPublisherTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testBasicPathPublishing()
	{
		$pub = new Illuminate\Foundation\Publishing\AssetPublisher($files = m::mock('Illuminate\Filesystem\Filesystem'), __DIR__);
		$files->shouldReceive('isDirectory')->once()->with(__DIR__.'/packages/bar')->andReturn(true);
		$files->shouldReceive('copyDirectory')->once()->with('foo', __DIR__.'/packages/bar')->andReturn(true);

		$this->assertTrue($pub->publish('bar', 'foo'));
	}


	public function testPackageAssetPublishing()
	{
		$pub = new Illuminate\Foundation\Publishing\AssetPublisher($files = m::mock('Illuminate\Filesystem\Filesystem'), __DIR__);
		$pub->setPackagePath(__DIR__.'/vendor');
		$files->shouldReceive('isDirectory')->once()->with(__DIR__.'/vendor/foo/public')->andReturn(true);
		$files->shouldReceive('isDirectory')->once()->with(__DIR__.'/packages/foo')->andReturn(true);
		$files->shouldReceive('copyDirectory')->once()->with(__DIR__.'/vendor/foo/public', __DIR__.'/packages/foo')->andReturn(true);

		$this->assertTrue($pub->publishPackage('foo'));

		$pub = new Illuminate\Foundation\Publishing\AssetPublisher($files2 = m::mock('Illuminate\Filesystem\Filesystem'), __DIR__);
		$files2->shouldReceive('isDirectory')->once()->with(__DIR__.'/custom-packages/foo/public')->andReturn(true);
		$files2->shouldReceive('isDirectory')->once()->with(__DIR__.'/packages/foo')->andReturn(true);
		$files2->shouldReceive('copyDirectory')->once()->with(__DIR__.'/custom-packages/foo/public', __DIR__.'/packages/foo')->andReturn(true);

		$this->assertTrue($pub->publishPackage('foo', __DIR__.'/custom-packages'));
	}

}
