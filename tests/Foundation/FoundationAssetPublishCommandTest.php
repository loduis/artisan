<?php

use Mockery as m;

class FoundationAssetPublishCommandTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testCommandCallsPublisherWithProperPackageName()
	{
		$command = new Illuminate\Foundation\Console\AssetPublishCommand($pub = m::mock('Illuminate\Foundation\Publishing\AssetPublisher'));
		$pub->shouldReceive('alreadyPublished')->andReturn(false);
		$pub->shouldReceive('publishPackage')->once()->with('foo');
		$command->run(new Symfony\Component\Console\Input\ArrayInput(array('package' => 'foo')), new Symfony\Component\Console\Output\NullOutput);
	}

}
