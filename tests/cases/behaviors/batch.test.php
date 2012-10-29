<?php

App::import('Core', array('AppModel', 'Model'));


class User extends CakeTestModel {
	var $name = 'User';
	var $validate = array('user' => 'notEmpty', 'password' => 'notEmpty');
}

class Article extends CakeTestModel {
	var $name = 'Article';
	var $belongsTo = array('User');

	var $validate = array(
		'user_id' => 'numeric',
		'title' => array('allowEmpty' => false, 'rule' => 'notEmpty'),
		'body' => 'notEmpty'
	);

	function processSilent($data, $extra) {
		return true;
	}

	function processFail($data, $extra) {
		return $extra;
	}

}


class BatchBehaviorTest extends CakeTestCase {

	var $fixtures = array(
		'core.article', 'core.user'
	);

	function startTest() {
		$this->User =& ClassRegistry::init('User');
		$this->Article =& ClassRegistry::init('Article');

		$this->User->bindModel(array(
			'hasMany' => array('Article')
		), false);

	}

	function endTest() {
		unset($this->Article);
		unset($this->User);

		ClassRegistry::flush();
	}


	function testBatchValidateOk() {
		$this->Article->Behaviors->attach('Batch',
			array(
				'header' => array('user_id', 'title', 'body', 'published'),
				'delimiter' => ';',
			)
		);

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_ok.csv');
		$ret = $this->Article->batchValidate($file);

		$this->assertEqual(array(), $ret);
	}

	function testBatchLoadValidationErrors() {
		$this->Article->Behaviors->attach('Batch',
			array(
				'header' => array('user_id', 'title', 'body', 'published'),
				'delimiter' => ';',
			)
		);

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_fail.csv');
		$ret = $this->Article->batchValidate($file);

		$this->assertEqual(4, count($ret));
		$this->assertPattern('/Number of items/', $ret[0]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[1]['errors']['line']);
		$this->assertPattern('/cannot be left blank/', $ret[2]['errors']['user_id']);
		$this->assertPattern('/cannot be left blank/', $ret[3]['errors']['title']);
	}

	function testBatchLoadValidationHeader() {
		$this->Article->Behaviors->attach('Batch',
			array(
				'header' => array('foo'),
				'delimiter' => ';',
			)
		);

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_fail.csv');
		$ret = $this->Article->batchValidate($file);

		$this->assertEqual(5, count($ret));
		$this->assertPattern('/Number of items/', $ret[0]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[1]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[2]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[3]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[4]['errors']['line']);
	}

	function testBatchLoadValidationBadDelimeter() {
		$this->Article->Behaviors->attach('Batch',
			array(
				'header' => array('user_id', 'title', 'body', 'published'),
				'delimiter' => ',',
			)
		);

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_fail.csv');
		$ret = $this->Article->batchValidate($file);

		$this->assertEqual(5, count($ret));
		$this->assertPattern('/Number of items/', $ret[0]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[1]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[2]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[3]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[4]['errors']['line']);
	}

	function testBatchLoadValidationAutoParams() {
		$this->Article->Behaviors->attach('Batch', array(
			'delimiter' => 'auto',
		));

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_fail.csv');
		$ret = $this->Article->batchValidate($file);

		$this->assertEqual(4, count($ret));
		$this->assertPattern('/Number of items/', $ret[0]['errors']['line']);
		$this->assertPattern('/Number of items/', $ret[1]['errors']['line']);
	}

	function testBatchLoadValidationHash() {
		$this->Article->Behaviors->attach('Batch', array(
			'hash' => true,
		));

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_ok.csv');
		$ret = $this->Article->batchValidate($file);

		$this->assertEqual('587157fc26c650a3cef29f9fc88e2543e6284029', $ret['hash']);
	}

	function testBatchLoadBasic() {
		$this->Article->Behaviors->attach('Batch');

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_ok.csv');
		$ret = $this->Article->batchProcess($file, 'processSilent');

		$this->assertEqual(6, $ret['total']);
		$this->assertEqual(2, $ret['ok']);
		$this->assertEqual(4, $ret['skipped']);
	}

	function testBatchLoadFailCustom() {
		$this->Article->Behaviors->attach('Batch');

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_ok.csv');
		$ret = $this->Article->batchProcess($file, 'processFail', 'custom_fail');


		$this->assertEqual(6, $ret['total']);
		$this->assertEqual(2, $ret['failed']);
		$this->assertEqual(4, $ret['skipped']);
		$this->assertEqual(2, $ret['reasons']['custom_fail']);
	}

	function testBatchLoadFailFile() {
		$this->Article->Behaviors->attach('Batch');

		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_fail.csv');
		$ret = $this->Article->batchProcess($file, 'processFail', 'custom_fail');

		$this->assertEqual(5, $ret['total']);
		$this->assertEqual(4, $ret['failed']);
		$this->assertEqual(1, $ret['skipped']);
		$this->assertEqual(2, $ret['reasons']['custom_fail']);
	}

	function testBatchValidateMissingFile() {
		$this->Article->Behaviors->attach('Batch');
		$ret = $this->Article->batchValidate('/foo/bar');

		$this->assertTrue(isset($ret['errors']['file']));
	}

	function testBatchProcessMissingFile() {
		$this->Article->Behaviors->attach('Batch');
		$ret = $this->Article->batchProcess('/foo/bar', 'processSilent');

		$this->assertEqual(-1, $ret['total']);
		$this->assertEqual(1, $ret['failed']);
	}

	function testBatchValidateMissSeparator() {
		$this->Article->Behaviors->attach('Batch');
		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_pipe_separator.csv');
		$ret = $this->Article->batchValidate($file);

		$this->assertTrue(isset($ret['errors']['file']));
	}

	function testBatchProcessMissSeparator() {
		$this->Article->Behaviors->attach('Batch');
		$file = realpath(dirname(__FILE__) . DS .'..'. DS .'..'. DS .'fixtures'. DS .'batch_articles_pipe_separator.csv');
		$ret = $this->Article->batchProcess($file, 'processSilent');

		$this->assertEqual(-1, $ret['total']);
		$this->assertEqual(1, $ret['failed']);
	}


}
