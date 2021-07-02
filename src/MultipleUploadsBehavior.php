<?php

namespace dynamikaweb\file;

use Yii;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;
use yii\helpers\FileHelper;
use yii\helpers\ArrayHelper;
use yii\data\ActiveDataProvider;

class MultipleUploadsBehavior extends \yii\behaviors\AttributeBehavior
{
    public $root = '@uploads';
    public $path = '{root}/{tablename}/{id_relation}/';
    public $relations;
    public $unionClass;
    public $modelClass;
    public $storageClosure;
    public $attributeValidate;
    public $attributeRelation;
    public $attributeDataProvider;
    private $attributeDataProviderFunc;
    private $_cache = [];
    private $_files = [];

    /**
	 * @inheritdoc
	 */
    public function init()
    {
        parent::init();

        // convert string to array with unique element
        if (!is_array($this->attributeValidate)) {
            $this->attributeValidate = [$this->attributeValidate];
        }

        // dynamic method attribute dataPrivider
        $this->attributeDataProviderFunc = strtr('get{attributeDataProvider}', [
            '{attributeDataProvider}' => ucfirst($this->attributeDataProvider),
        ]);
    }

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
        if (!in_array($name, [$this->attributeDataProviderFunc])) {
			return parent::hasMethod($name);
		}

        return true;
    }

    /**
	 * @inheritdoc
	 */
	public function canGetProperty($name, $checkVars = true)
    {
		if (!in_array($name, array_merge($this->attributeValidate, [$this->attributeRelation, $this->attributeDataProvider]))) {
			return parent::canGetProperty($name, $checkVars);
		}

		return true;
	}

    /**
	 * @inheritdoc
	 */
	public function canSetProperty($name, $checkVars = true)
    {
        if (!in_array($name, $this->attributeValidate)) {
        	return parent::canSetProperty($name, $checkVars);
		}

		return true;
	}

    /**
     * storage in corresponding folder
     */
    public function afterSave($event)
    {
        
    }

    /**
     * before deleting model, discover attachment folder
     */
    public function beforeDelete($event)
    {
        $this->_cache = array_map(
            function ($model_file) {
                return strtr($this->path,[
                    '{root}' => Yii::getAlias($this->root),
                    '{tablename}' => $this->owner->tableName(),
                    '{id_relation}' => $model_file->getPrimaryKey(),
                    '{id_owner}' => $this->owner->getPrimaryKey()
                ]);
            }
        ,
            $this->owner->{$this->attributeRelation}
        );
    }

    /**
     * after deleting model, delete folder with attached files
     */
    public function afterDelete($event)
    {
        foreach($this->_cache as $cache) {
            FileHelper::removeDirectory($cache);
        }
    }


    /**
     * @inheritdoc
     */
    public function __call($name, $params)
    {
        if ($name != $this->attributeDataProviderFunc) {
            return null;
        }

        // first paramter
        $options = ArrayHelper::getValue($params, 0, []);

        // relation
        $table_file = $this->modelClass::tablename();
        $table_union = $this->unionClass::tablename();
        $field_file = current($this->relations[$this->modelClass]);
        $field_self = current($this->relations[$this->owner::classname()]);
        $id_file = array_key_first($this->relations[$this->modelClass]);
        $id_self = $this->owner->{array_key_first($this->relations[$this->owner::classname()])};

        return new ActiveDataProvider(['query' => $this->modelClass::find()
            ->andFilterWhere(ArrayHelper::getValue($options, 'filterWhere', []))
            ->andWhere(ArrayHelper::getValue($options, 'where', []))
            ->orderBy(ArrayHelper::getValue($options, 'order', []))
            ->andWhere(["{$table_union}.{$field_self}" => $id_self])
            ->leftJoin($this->unionClass::tablename(), "{$table_union}.{$field_file} = {$table_file}.{$id_file}")
        ]);
    }

    /**
     * @inheritdoc
     */
    public function __get($name)
    {
        foreach($this->attributeValidate as $attribute) {
            if ($name == $attribute) {
                return ArrayHelper::getValue($this->_files, $attribute, null);
            }
        }

        switch ($name)
        {
            case $this->attributeDataProvider: {
                return $this->__call($this->attributeDataProviderFunc, []);
            }

            case $this->attributeRelation: {
                return $this->owner->hasMany($this->modelClass, $this->relations[$this->modelClass])
                    ->viaTable($this->unionClass::tablename(), array_flip($this->relations[$this->owner::classname()]));
            }
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        foreach($this->attributeValidate as $attribute) {
            if ($name == $attribute) {
                return ($this->_files[$attribute] = $value);
            }
        }
    }
}
