<?php

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
require_once dirname(__FILE__) . '/../../bootstrap/unit.php';
require_once sfConfig::get('sf_symfony_lib_dir').'/vendor/swiftmailer/classes/Swift/Mailer.php';
spl_autoload_register(array('sfMailer', 'autoload'));
require_once dirname(__FILE__).'/../../../lib/mailer/sfMailer.class.php';
require_once dirname(__FILE__).'/fixtures/TestMailMessage.class.php';
require_once dirname(__FILE__).'/fixtures/TestMailerTransport.class.php';
require_once dirname(__FILE__).'/fixtures/TestMailerTransportQueue.class.php';

$t = new lime_test(32);

$dispatcher = new sfEventDispatcher();

// ::autoload()
$t->diag('::autoload()');
$t->ok(false === sfMailer::autoload('FooBar'), '::autoload() returns false if the class does not start with Swift');
$t->ok(false === sfMailer::autoload('Swift_FooBar'), '::autoload() returns false if the class starts with Swift but does not exist');
$t->ok(class_exists('Swift_Preferences', true), '::autoload() require the class if it exists');

// __construct()
$t->diag('__construct()');
try
{
  new sfMailer($dispatcher, array('delivery_strategy' => 'foo'));

  $t->fail('__construct() throws an InvalidArgumentException exception if the strategy is not valid');
}
catch (InvalidArgumentException $e)
{
  $t->pass('__construct() throws an InvalidArgumentException exception if the strategy is not valid');
}

// main transport
$mailer = new sfMailer($dispatcher, array(
  'logging' => true,
  'delivery_strategy' => 'queue',
  'queue_transport_class' => 'TestMailerTransportQueue',
  'model_class' => 'TestMailMessage',
  'transport' => array('class' => 'TestMailerTransport', 'param' => array('foo' => 'bar', 'bar' => 'foo')),
));
$t->is($mailer->getTransport()->getTransport()->getFoo(), 'bar', '__construct() passes the parameters to the main transport');

// queue
try
{
  $mailer = new sfMailer($dispatcher, array('delivery_strategy' => 'queue'));

  $t->fail('__construct() throws an InvalidArgumentException exception if the queue_transport_class option is not set with the queue delivery strategy');
}
catch (InvalidArgumentException $e)
{
  $t->pass('__construct() throws an InvalidArgumentException exception if the queue_transport_class option is not set with the queue delivery strategy');
}

try
{
  $mailer = new sfMailer($dispatcher, array('delivery_strategy' => 'queue', 'queue_transport_class' => 'TestMailerTransportQueue'));

  $t->fail('__construct() throws an InvalidArgumentException exception if the model_class option is not set with the queue delivery strategy');
}
catch (InvalidArgumentException $e)
{
  $t->pass('__construct() throws an InvalidArgumentException exception if the model_class option is not set with the queue delivery strategy');
}

$mailer = new sfMailer($dispatcher, array('delivery_strategy' => 'queue', 'queue_transport_class' => 'TestMailerTransportQueue', 'model_class' => 'TestMailMessage'));
$t->is($mailer->getTransport()->getTransportQueue()->getModel(), 'TestMailMessage', '__construct() recognizes the queue delivery strategy');

// single address
try
{
  $mailer = new sfMailer($dispatcher, array('delivery_strategy' => 'single_address'));

  $t->fail('__construct() throws an InvalidArgumentException exception if the delivery_address option is not set with the queue single_address strategy');
}
catch (InvalidArgumentException $e)
{
  $t->pass('__construct() throws an InvalidArgumentException exception if the delivery_address option is not set with the queue single_address strategy');
}

$mailer = new sfMailer($dispatcher, array('delivery_strategy' => 'single_address', 'delivery_address' => 'foo@example.com'));
$t->is($mailer->getTransport()->getDeliveryAddress(), 'foo@example.com', '__construct() recognizes the single_address delivery strategy');

// logging
$mailer = new sfMailer($dispatcher, array('logging' => false));
$t->is($mailer->getTransport()->getLogger(), null, '__construct() disables logging if the logging option is set to false');
$mailer = new sfMailer($dispatcher, array('logging' => true));
$t->ok($mailer->getTransport()->getLogger() instanceof sfMailerMessageLoggerPlugin, '__construct() enables logging if the logging option is set to true');

// ->compose()
$t->diag('->compose()');
$mailer = new sfMailer($dispatcher, array('delivery_strategy' => 'none'));
$t->ok($mailer->compose() instanceof Swift_Message, '->compose() returns a Swift_Message instance');
$message = $mailer->compose('from@example.com', 'to@example.com', 'Subject', 'Body');
$t->is($message->getFrom(), array('from@example.com' => ''), '->compose() takes the from address as its first argument');
$t->is($message->getTo(), array('to@example.com' => ''), '->compose() takes the to address as its second argument');
$t->is($message->getSubject(), 'Subject', '->compose() takes the subject as its third argument');
$t->is($message->getBody(), 'Body', '->compose() takes the body as its fourth argument');

// ->composeAndSend()
$t->diag('->composeAndSend()');
$mailer = new sfMailer($dispatcher, array('logging' => true, 'delivery_strategy' => 'none'));
$mailer->composeAndSend('from@example.com', 'to@example.com', 'Subject', 'Body');
$t->is($mailer->getTransport()->getLogger()->countMessages(), 1, '->composeAndSend() composes and sends the message');
$messages = $mailer->getTransport()->getLogger()->getMessages();
$t->is($messages[0]->getFrom(), array('from@example.com' => ''), '->composeAndSend() takes the from address as its first argument');
$t->is($messages[0]->getTo(), array('to@example.com' => ''), '->composeAndSend() takes the to address as its second argument');
$t->is($messages[0]->getSubject(), 'Subject', '->composeAndSend() takes the subject as its third argument');
$t->is($messages[0]->getBody(), 'Body', '->composeAndSend() takes the body as its fourth argument');

// ->sendQueue()
$t->diag('->sendQueue()');
$mailer = new sfMailer($dispatcher, array('delivery_strategy' => 'none'));
$mailer->composeAndSend('from@example.com', 'to@example.com', 'Subject', 'Body');
try
{
  $mailer->sendQueue();

  $t->fail('->sendQueue() throws a LogicException exception if the delivery_strategy is not queue');
}
catch (LogicException $e)
{
  $t->pass('->sendQueue() throws a LogicException exception if the delivery_strategy is not queue');
}

$mailer = new sfMailer($dispatcher, array(
  'delivery_strategy' => 'queue',
  'queue_transport_class' => 'TestMailerTransportQueue',
  'model_class' => 'TestMailMessage',
  'transport' => array('class' => 'TestMailerTransport'),
));
$transportNormal = $mailer->getTransport()->getTransport();
$transportQueue = $mailer->getTransport()->getTransportQueue();

$mailer->composeAndSend('from@example.com', 'to@example.com', 'Subject', 'Body');
$t->is($transportQueue->getQueuedCount(), 1, '->sendQueue() sends messages in the queue');
$t->is($transportNormal->getSentCount(), 0, '->sendQueue() sends messages in the queue');
$mailer->sendQueue();
$t->is($transportQueue->getQueuedCount(), 0, '->sendQueue() sends messages in the queue');
$t->is($transportNormal->getSentCount(), 1, '->sendQueue() sends messages in the queue');

// ->sendNextImmediately()
$t->diag('->sendNextImmediately()');
$mailer = new sfMailer($dispatcher, array(
  'logging' => true,
  'delivery_strategy' => 'queue',
  'queue_transport_class' => 'TestMailerTransportQueue',
  'model_class' => 'TestMailMessage',
  'transport' => array('class' => 'TestMailerTransport'),
));
$transportNormal = $mailer->getTransport()->getTransport();
$transportQueue = $mailer->getTransport()->getTransportQueue();
$t->is($mailer->sendNextImmediately(), $mailer, '->sendNextImmediately() implements a fluid interface');
$mailer->composeAndSend('from@example.com', 'to@example.com', 'Subject', 'Body');
$t->is($transportQueue->getQueuedCount(), 0, '->sendNextImmediately() bypasses the queue');
$t->is($transportNormal->getSentCount(), 1, '->sendNextImmediately() bypasses the queue');
$transportNormal->reset();
$transportQueue->reset();

$mailer->composeAndSend('from@example.com', 'to@example.com', 'Subject', 'Body');
$t->is($transportQueue->getQueuedCount(), 1, '->sendNextImmediately() bypasses the queue but only for the very next message');
$t->is($transportNormal->getSentCount(), 0, '->sendNextImmediately() bypasses the queue but only for the very next message');
