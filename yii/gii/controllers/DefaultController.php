<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\gii\controllers;

use Yii;
use yii\web\Controller;
use yii\web\HttpException;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class DefaultController extends Controller
{
	public $layout = 'generator';
	/**
	 * @var \yii\gii\Module
	 */
	public $module;
	/**
	 * @var \yii\gii\Generator
	 */
	public $generator;

	public function actionIndex()
	{
		$this->layout = 'main';
		return $this->render('index');
	}

	public function actionView($id)
	{
		$generator = $this->loadGenerator($id);
		$params = array('generator' => $generator, 'id' => $id);
		if (isset($_POST['preview']) || isset($_POST['generate'])) {
			if ($generator->validate()) {
				$generator->saveStickyAttributes();
				$files = $generator->generate();
				if (isset($_POST['generate']) && !empty($_POST['answers'])) {
					$params['hasError'] = $generator->save($files, (array)$_POST['answers'], $results);
					$params['results'] = $results;
				} else {
					$params['files'] = $files;
					$params['answers'] = isset($_POST['answers']) ? $_POST['answers'] : null;
				}
			}
		}

		return $this->render('view', $params);
	}

	public function actionPreview($id, $file)
	{
		$generator = $this->loadGenerator($id);
		if ($generator->validate()) {
			foreach ($generator->generate() as $f) {
				if ($f->id === $file) {
					$content = $f->preview();
					if ($content !== false) {
						return  '<div class="content">' . $content . '</content>';
					} else {
						return '<div class="error">Preview is not available for this file type.</div>';
					}
				}
			}
		}
		throw new HttpException(404, "Code file not found: $file");
	}

	public function actionDiff($id, $file)
	{
		$generator = $this->loadGenerator($id);
		if ($generator->validate()) {
			foreach ($generator->generate() as $f) {
				if ($f->id === $file) {
					return $this->renderPartial('diff', array(
						'diff' => $f->diff(),
					));
				}
			}
		}
		throw new HttpException(404, "Code file not found: $file");
	}

	/**
	 * Runs an action defined in the generator.
	 * Given an action named "xyz", the method "actionXyz()" in the generator will be called.
	 * If the method does not exist, a 400 HTTP exception will be thrown.
	 * @param string $id the ID of the generator
	 * @param string $name the action name
	 * @return mixed the result of the action.
	 * @throws HttpException if the action method does not exist.
	 */
	public function actionAction($id, $name)
	{
		$generator = $this->loadGenerator($id);
		$method = 'action' . $name;
		if (method_exists($generator, $method)) {
			return $generator->$method();
		} else {
			throw new HttpException(400, "Unknown generator action: $name");
		}
	}

	public function createUrl($route, $params = array())
	{
		if (!isset($params['id']) && $this->generator !== null) {
			foreach ($this->module->generators as $id => $generator) {
				if ($generator === $this->generator) {
					$params['id'] = $id;
					break;
				}
			}
		}
		return parent::createUrl($route, $params);
	}

	public function createActionUrl($name, $params = array())
	{
		foreach ($this->module->generators as $id => $generator) {
			if ($generator === $this->generator) {
				$params['id'] = $id;
				break;
			}
		}
		$params['name'] = $name;
		return parent::createUrl('action', $params);
	}

	/**
	 * Loads the generator with the specified ID.
	 * @param string $id the ID of the generator to be loaded.
	 * @return \yii\gii\Generator the loaded generator
	 * @throws \yii\web\HttpException
	 */
	protected function loadGenerator($id)
	{
		if (isset($this->module->generators[$id])) {
			$this->generator = $this->module->generators[$id];
			$this->generator->loadStickyAttributes();
			$this->generator->load($_POST);
			return $this->generator;
		} else {
			throw new HttpException(404, "Code generator not found: $id");
		}
	}
}
