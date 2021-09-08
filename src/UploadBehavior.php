<?php 

namespace dynamikaweb\file;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;
use yii\helpers\FileHelper;
use yii\data\ActiveDataProvider;

class UploadBehavior extends \yii\behaviors\AttributeBehavior
{
    public $root = '@uploads';
    public $path = '{root}/{tablename}/{id_relation}/';
    public $relation;
    public $modelClass;
    public $storageClosure;
    public $attributeValidate;
    public $attributeRelation;
    public $attributeDataProvider;
    private $_cache;
    private $_file;

    /**
	 * @inheritdoc
	 */
    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_VALIDATE => 'beforeValidate',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
            ActiveRecord::EVENT_AFTER_INSERT => 'afterSave',
            ActiveRecord::EVENT_AFTER_UPDATE => 'afterSave',
            ActiveRecord::EVENT_AFTER_DELETE => 'afterDelete'
        ];
    }

    /**
	 * @inheritdoc
	 */
    public function hasMethod($name)
    {
        if ($name !== 'get'.ucfirst($this->attributeDataProvider)) {
			return parent::hasMethod($name);
		}

        return true;
    }

    /**
	 * @inheritdoc
	 */
	public function canGetProperty($name, $checkVars = true)
    {
		if (!in_array($name, [$this->attributeValidate, $this->attributeRelation, $this->attributeDataProvider])) {
			return parent::canGetProperty($name, $checkVars);
		}

		return true;
	}

    /**
	 * @inheritdoc
	 */
	public function canSetProperty($name, $checkVars = true)
    {
		if (!in_array($name, [$this->attributeValidate])) {
			return parent::canSetProperty($name, $checkVars);
		}

		return true;
	}

    /**
     * prepare to validate server side
     */
    public function beforeValidate($event)
    {
        $this->_file = UploadedFile::getInstances($this->owner, $this->attributeValidate);
    }

    /**
     * storage in corresponding folder
     */
    public function afterSave($event)
    {
        if ($this->_file) {
            call_user_func($this->storageClosure, 
                $this->owner,
                $this->attributeValidate,
                current($this->relation),
                $this->modelClass
            );
        }
    }

    /**
     * before deleting model, discover attachment folder
     */
    public function beforeDelete($event)
    {
	if ($this->owner->{$this->attributeRelation}){
            $this->_cache = strtr($this->path, [
                '{root}' => Yii::getAlias($this->root),
                '{tablename}' => $this->owner->tableName(),
                '{id_relation}' => $this->owner->{$this->attributeRelation}->getPrimaryKey(),
                '{id_owner}' => $this->owner->getPrimaryKey()
            ]);    
        }
    }

    /**
     * after deleting model, delete folder with attached files
     */
    public function afterDelete($event)
    {
        FileHelper::removeDirectory($this->_cache);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        switch ($name)
        {
            case $this->attributeValidate: {
                return $this->_file;
            }
            
            case $this->attributeDataProvider: {
                return $this->{'get'.ucfirst($this->attributeDataProvider)}();
            }

            case $this->attributeRelation: {
                return $this->owner->hasOne($this->modelClass, $this->relation);
            }

            default: {
                return null;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        switch ($name)
        {
            case $this->attributeValidate: {
                return $this->_file = $value;
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        if ($name !== 'get'.ucfirst($this->attributeDataProvider)) {
			return null;
		}

        $field = array_key_first($this->relation);
        $id = $this->owner->getAttribute($this->relation[$field]);

        return new ActiveDataProvider([
            'pagination' => false,
            'query' => $this->modelClass::find()->where([
                $field => $id
            ])
        ]);
    }
            
}
