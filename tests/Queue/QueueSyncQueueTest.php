<?php

use Mockery as m;

class QueueSyncQueueTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}


	public function testPushShouldFireJobInstantly()
	{
		unset($_SERVER['__sync.test']);

		/**
		 * Test Synced Closure
		 */
		$sync = new Illuminate\Queue\SyncQueue;
		$container = new Illuminate\Container\Container;
		$encrypter = new Illuminate\Encryption\Encrypter(str_random(32));
		$container->instance('Illuminate\Contracts\Encryption\Encrypter', $encrypter);
		$sync->setContainer($container);
		$sync->setEncrypter($encrypter);
		$sync->push(function($job) {
			$_SERVER['__sync.test'] = true;
			$job->delete();
		});

		$this->assertTrue($_SERVER['__sync.test']);
		unset($_SERVER['__sync.test']);

		/**
		 * Test Synced Class Handler
		 */
		$sync->push('SyncQueueTestHandler', ['foo' => 'bar']);
		$this->assertInstanceOf('Illuminate\Queue\Jobs\SyncJob', $_SERVER['__sync.test'][0]);
		$this->assertEquals(['foo' => 'bar'], $_SERVER['__sync.test'][1]);
	}


	public function testQueueableEntitiesAreSerializedAndResolved()
	{
		$sync = new Illuminate\Queue\SyncQueue;
		$sync->setContainer($container = new Illuminate\Container\Container);
		$container->instance('Illuminate\Contracts\Queue\EntityResolver', $resolver = m::mock('Illuminate\Contracts\Queue\EntityResolver'));
		$resolver->shouldReceive('resolve')->once()->with('SyncQueueTestEntity', 1)->andReturn(new SyncQueueTestEntity);
		$sync->push('SyncQueueTestHandler', ['entity' => new SyncQueueTestEntity]);

		$this->assertInstanceOf('SyncQueueTestEntity', $_SERVER['__sync.test'][1]['entity']);
	}


	public function testQueueableEntitiesAreSerializedAndResolvedWhenPassedAsSingleEntities()
	{
		$sync = new Illuminate\Queue\SyncQueue;
		$sync->setContainer($container = new Illuminate\Container\Container);
		$container->instance('Illuminate\Contracts\Queue\EntityResolver', $resolver = m::mock('Illuminate\Contracts\Queue\EntityResolver'));
		$resolver->shouldReceive('resolve')->once()->with('SyncQueueTestEntity', 1)->andReturn(new SyncQueueTestEntity);
		$sync->push('SyncQueueTestHandler', new SyncQueueTestEntity);

		$this->assertInstanceOf('SyncQueueTestEntity', $_SERVER['__sync.test'][1]);
	}

}

class SyncQueueTestEntity implements Illuminate\Contracts\Queue\QueueableEntity {
	public function getQueueableId() {
		return 1;
	}
}

class SyncQueueTestHandler {
	public function fire($job, $data) {
		$_SERVER['__sync.test'] = func_get_args();
	}
}
