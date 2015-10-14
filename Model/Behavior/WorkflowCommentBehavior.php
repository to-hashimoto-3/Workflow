<?php
/**
 * WorkflowComment Behavior
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Shohei Nakajima <nakajimashouhei@gmail.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('ModelBehavior', 'Model');

/**
 * WorkflowComment Behavior
 *
 * @author Shohei Nakajima <nakajimashouhei@gmail.com>
 * @package NetCommons\Workflow\Model\Behavior
 */
class WorkflowCommentBehavior extends ModelBehavior {

/**
 * beforeValidate is called before a model is validated, you can use this callback to
 * add behavior validation rules into a models validate array. Returning false
 * will allow you to make the validation fail.
 *
 * @param Model $model Model using this behavior
 * @param array $options Options passed from Model::save().
 * @return mixed False or null will abort the operation. Any other result will continue.
 * @see Model::save()
 */
	public function beforeValidate(Model $model, $options = array()) {
		if (! isset($model->data['WorkflowComment'])) {
			return true;
		}

		$model->loadModels(array(
			'WorkflowComment' => 'Workflow.WorkflowComment',
		));

		//コメントの登録(ステータス 差し戻しのみコメント必須)
		if (! isset($model->data[$model->alias]['status'])) {
			$model->data[$model->alias]['status'] = null;
		}
		if ($model->data[$model->alias]['status'] === WorkflowComponent::STATUS_DISAPPROVED ||
				isset($model->data['WorkflowComment']['comment']) && $model->data['WorkflowComment']['comment'] !== '') {

			$model->WorkflowComment->set($model->data['WorkflowComment']);
			$model->WorkflowComment->validates();

			if ($model->WorkflowComment->validationErrors) {
				$model->validationErrors = Hash::merge($model->validationErrors, $model->WorkflowComment->validationErrors);
				return false;
			}
		}

		return true;
	}

/**
 * afterSave is called after a model is saved.
 *
 * @param Model $model Model using this behavior
 * @param bool $created True if this save created a new record
 * @param array $options Options passed from Model::save().
 * @return bool
 * @throws InternalErrorException
 * @see Model::save()
 */
	public function afterSave(Model $model, $created, $options = array()) {
		if (! isset($model->data['WorkflowComment']) || ! $model->data['WorkflowComment']['comment']) {
			return true;
		}

		$model->loadModels([
			'WorkflowComment' => 'Workflow.WorkflowComment',
		]);

		$model->data['WorkflowComment']['plugin_key'] = Inflector::underscore($model->plugin);
		$model->data['WorkflowComment']['block_key'] = $model->data['Block']['key'];
		$model->data['WorkflowComment']['content_key'] = $model->data[$model->alias]['key'];

		if (! $model->WorkflowComment->save($model->data['WorkflowComment'], false)) {
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}

		return parent::afterSave($model, $created, $options);
	}

/**
 * Get WorkflowComment data
 *
 * @param Model $model Model using this behavior
 * @param string $contentKey Content key
 * @return array
 */
	public function getCommentsByContentKey(Model $model, $contentKey) {
		$model->WorkflowComment = ClassRegistry::init('Workflow.WorkflowComment');

		if (! $contentKey) {
			return array();
		}

		$conditions = array(
			'content_key' => $contentKey,
			'plugin_key' => Inflector::underscore($model->plugin),
		);
		$comments = $model->WorkflowComment->find('all', array(
			'conditions' => $conditions,
			'order' => 'WorkflowComment.id DESC',
		));

		return $comments;
	}

/**
 * Delete comments by content key
 *
 * @param Model $model Model using this behavior
 * @param string $contentKey content key
 * @return bool True on success
 * @throws InternalErrorException
 */
	public function deleteCommentsByContentKey(Model $model, $contentKey) {
		$model->loadModels(array(
			'WorkflowComment' => 'Workflow.WorkflowComment',
		));

		if (! $model->WorkflowComment->deleteAll(array($model->WorkflowComment->alias . '.content_key' => $contentKey), false)) {
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}

		return true;
	}
}