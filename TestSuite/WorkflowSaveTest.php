<?php
/**
 * WorkflowSaveTest
 *
 * @author Shohei Nakajima <nakajimashouhei@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('WorkflowComponent', 'Workflow.Controller/Component');
App::uses('NetCommonsSaveTest', 'NetCommons.TestSuite');

/**
 * WorkflowSaveTest
 *
 * @author Shohei Nakajima <nakajimashouhei@gmail.com>
 * @package NetCommons\Workflow\TestSuite
 */
class WorkflowSaveTest extends NetCommonsSaveTest {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();

		Current::$current['Block']['id'] = '2';
		Current::$current['Room']['id'] = '1';
		Current::$current['Permission']['content_editable']['value'] = true;
		Current::$current['Permission']['content_publishable']['value'] = true;
	}

/**
 * Save(公開)のテスト
 *
 * @param array $data 登録データ
 * @dataProvider dataProviderSave
 * @return array 登録後のデータ
 */
	public function testSave($data) {
		$model = $this->_modelName;
		$method = $this->_methodName;

		//チェック用データ取得
		if (isset($data[$this->$model->alias]['id'])) {
			$before = $this->$model->find('first', array(
				'recursive' => -1,
				'conditions' => array('id' => $data[$this->$model->alias]['id']),
			));
			$saveData = Hash::remove($data, $this->$model->alias . '.id');
		} else {
			$saveData = $data;
		}

		//テスト実行
		$result = $this->$model->$method($saveData);
		$this->assertNotEmpty($result);
		$lastInsertId = $this->$model->getLastInsertID();

		//登録データ取得
		$latest = $this->$model->find('first', array(
			'recursive' => -1,
			'conditions' => array('id' => $lastInsertId),
		));

		$actual = $latest;

		//is_latestのチェック
		if (isset($before)) {
			$after = $this->$model->find('first', array(
				'recursive' => -1,
				'conditions' => array('id' => $data[$this->$model->alias]['id']),
			));
			$this->assertEquals($after,
				Hash::merge($before, array(
					$this->$model->alias => array('is_latest' => false)
				)
			));
			$actual[$this->$model->alias] = Hash::remove($actual[$this->$model->alias], 'modified');
			$actual[$this->$model->alias] = Hash::remove($actual[$this->$model->alias], 'modified_user');
		} else {
			$actual[$this->$model->alias] = Hash::remove($actual[$this->$model->alias], 'created');
			$actual[$this->$model->alias] = Hash::remove($actual[$this->$model->alias], 'created_user');
			$actual[$this->$model->alias] = Hash::remove($actual[$this->$model->alias], 'modified');
			$actual[$this->$model->alias] = Hash::remove($actual[$this->$model->alias], 'modified_user');

			$data[$this->$model->alias]['key'] =
					OriginalKeyBehavior::generateKey($this->$model->name, $this->$model->useDbConfig);
			$before[$this->$model->alias] = array();
		}

		$expected[$this->$model->alias] = Hash::merge(
			$before[$this->$model->alias],
			$data[$this->$model->alias],
			array(
				'id' => $lastInsertId,
				'is_active' => true,
				'is_latest' => true
			)
		);
		$expected[$this->$model->alias] = Hash::remove($expected[$this->$model->alias], 'modified');
		$expected[$this->$model->alias] = Hash::remove($expected[$this->$model->alias], 'modified_user');

		$this->assertEquals($expected, $actual);

		return $latest;
	}

/**
 * Test to call WorkflowBehavior::beforeSave
 *
 * WorkflowBehaviorをモックに置き換えて登録処理を呼び出します。<br>
 * WorkflowBehavior::beforeSaveが1回呼び出されることをテストします。<br>
 * ##### 参考URL
 * http://stackoverflow.com/questions/19833495/how-to-mock-a-cakephp-behavior-for-unit-testing]
 *
 * @param array $data 登録データ
 * @dataProvider dataProviderSave
 * @return void
 * @throws CakeException Workflow.Workflowがロードされていないとエラー
 */
	public function testCallWorkflowBehavior($data) {
		$model = $this->_modelName;
		$method = $this->_methodName;

		if (! $this->$model->Behaviors->loaded('Workflow.Workflow')) {
			$error = '"Workflow.Workflow" not loaded in ' . $this->$model->alias . '.';
			throw new CakeException($error);
		};

		ClassRegistry::removeObject('WorkflowBehavior');
		$workflowBehaviorMock = $this->getMock('WorkflowBehavior', ['beforeSave']);
		ClassRegistry::addObject('WorkflowBehavior', $workflowBehaviorMock);
		$this->$model->Behaviors->unload('Workflow');
		$this->$model->Behaviors->load('Workflow', $this->$model->actsAs['Workflow.Workflow']);

		$workflowBehaviorMock
			->expects($this->once())
			->method('beforeSave')
			->will($this->returnValue(true));

		$this->$model->$method($data);
	}

}
