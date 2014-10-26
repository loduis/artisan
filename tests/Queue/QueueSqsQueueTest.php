<?php

use Mockery as m;

use Aws\Sqs\SqsClient;
use Guzzle\Service\Resource\Model;

class QueueSqsQueueTest extends PHPUnit_Framework_TestCase {

	public function tearDown()
	{
		m::close();
	}

	public function setUp() {

		// Use Mockery to mock the SqsClient
		$this->sqs = m::mock('Aws\Sqs\SqsClient');

		$this->account = '1234567891011';
		$this->queueName = 'emails';
		$this->baseUrl = 'https://sqs.someregion.amazonaws.com';

		// This is how the modified getQueue builds the queueUrl
		$this->queueUrl = $this->baseUrl . '/' . $this->account . '/' . $this->queueName;

		$this->mockedJob = 'foo';
		$this->mockedData = array('data');
		$this->mockedPayload = json_encode(array('job' => $this->mockedJob, 'data' => $this->mockedData));
		$this->mockedDelay = 10;
		$this->mockedMessageId = 'e3cd03ee-59a3-4ad8-b0aa-ee2e3808ac81';
		$this->mockedReceiptHandle = '0NNAq8PwvXuWv5gMtS9DJ8qEdyiUwbAjpp45w2m6M4SJ1Y+PxCh7R930NRB8ylSacEmoSnW18bgd4nK\/O6ctE+VFVul4eD23mA07vVoSnPI4F\/voI1eNCp6Iax0ktGmhlNVzBwaZHEr91BRtqTRM3QKd2ASF8u+IQaSwyl\/DGK+P1+dqUOodvOVtExJwdyDLy1glZVgm85Yw9Jf5yZEEErqRwzYz\/qSigdvW4sm2l7e4phRol\/+IjMtovOyH\/ukueYdlVbQ4OshQLENhUKe7RNN5i6bE\/e5x9bnPhfj2gbM';

		$this->mockedSendMessageResponseModel = new Model(array('Body' => $this->mockedPayload,
						      			'MD5OfBody' => md5($this->mockedPayload),
						      			'ReceiptHandle' => $this->mockedReceiptHandle,
						      			'MessageId' => $this->mockedMessageId,
						      			'Attributes' => array('ApproximateReceiveCount' => 1)));

		$this->mockedReceiveMessageResponseModel = new Model(array('Messages' => array( 0 => array(
												'Body' => $this->mockedPayload,
						     						'MD5OfBody' => md5($this->mockedPayload),
						      						'ReceiptHandle' => $this->mockedReceiptHandle,
						     						'MessageId' => $this->mockedMessageId))));
	}


	public function testPopProperlyPopsJobOffOfSqs()
	{
		$queue = $this->getMock('Illuminate\Queue\SqsQueue', array('getQueue'), array($this->sqs, $this->queueName, $this->account));
		$queue->setContainer(m::mock('Illuminate\Container\Container'));
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->will($this->returnValue($this->queueUrl));
		$this->sqs->shouldReceive('receiveMessage')->once()->with(array('QueueUrl' => $this->queueUrl, 'AttributeNames' => array('ApproximateReceiveCount')))->andReturn($this->mockedReceiveMessageResponseModel);
		$result = $queue->pop($this->queueName);
		$this->assertInstanceOf('Illuminate\Queue\Jobs\SqsJob', $result);
	}


	public function testDelayedPushWithDateTimeProperlyPushesJobOntoSqs()
	{
		$now = Carbon\Carbon::now();
		$queue = $this->getMock('Illuminate\Queue\SqsQueue', array('createPayload', 'getSeconds', 'getQueue'), array($this->sqs, $this->queueName, $this->account));
		$queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->mockedData)->will($this->returnValue($this->mockedPayload));
		$queue->expects($this->once())->method('getSeconds')->with($now)->will($this->returnValue(5));
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->will($this->returnValue($this->queueUrl));
		$this->sqs->shouldReceive('sendMessage')->once()->with(array('QueueUrl' => $this->queueUrl, 'MessageBody' => $this->mockedPayload, 'DelaySeconds' => 5))->andReturn($this->mockedSendMessageResponseModel);
		$id = $queue->later($now->addSeconds(5), $this->mockedJob, $this->mockedData, $this->queueName);
		$this->assertEquals($this->mockedMessageId, $id);
	}


	public function testDelayedPushProperlyPushesJobOntoSqs()
	{
		$queue = $this->getMock('Illuminate\Queue\SqsQueue', array('createPayload', 'getSeconds', 'getQueue'), array($this->sqs, $this->queueName, $this->account));
		$queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->mockedData)->will($this->returnValue($this->mockedPayload));
		$queue->expects($this->once())->method('getSeconds')->with($this->mockedDelay)->will($this->returnValue($this->mockedDelay));
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->will($this->returnValue($this->queueUrl));
		$this->sqs->shouldReceive('sendMessage')->once()->with(array('QueueUrl' => $this->queueUrl, 'MessageBody' => $this->mockedPayload, 'DelaySeconds' => $this->mockedDelay))->andReturn($this->mockedSendMessageResponseModel);
		$id = $queue->later($this->mockedDelay, $this->mockedJob, $this->mockedData, $this->queueName);
		$this->assertEquals($this->mockedMessageId, $id);
	}


	public function testPushProperlyPushesJobOntoSqs()
	{
		$queue = $this->getMock('Illuminate\Queue\SqsQueue', array('createPayload', 'getQueue'), array($this->sqs, $this->queueName, $this->account));
		$queue->expects($this->once())->method('createPayload')->with($this->mockedJob, $this->mockedData)->will($this->returnValue($this->mockedPayload));
		$queue->expects($this->once())->method('getQueue')->with($this->queueName)->will($this->returnValue($this->queueUrl));
		$this->sqs->shouldReceive('sendMessage')->once()->with(array('QueueUrl' => $this->queueUrl, 'MessageBody' => $this->mockedPayload))->andReturn($this->mockedSendMessageResponseModel);
		$id = $queue->push($this->mockedJob, $this->mockedData, $this->queueName);
		$this->assertEquals($this->mockedMessageId, $id);
	}

}
