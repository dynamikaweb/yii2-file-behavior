<?php 

namespace dynamikaweb\file;

use Yii;
use yii\helpers\ArrayHelper;
use yii\base\InvalidParamException;
use yii\base\InvalidConfigException;

/**
 * @property string $attribute
 * @property Closure $closure
 * @property string|boolean $default
 * @property array $params
 */
class ThumbBehavior extends \yii\behaviors\AttributeBehavior
{
    public $attribute;
    public $closure;
    public $default = false;
    public $params = [];
    private $_cache;

    /**
     * @inheritdoc
     * 
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();

        if (!(is_string($this->default) || $this->default === false)) {
            throw new InvalidConfigException("'default' must be string or false");
        }

        if (!is_string($this->attribute)) {
            throw new InvalidConfigException("'attribute' must be string");
        }

        if (!is_array($this->params)) {
            throw new InvalidConfigException("'params' must be array");
        }

        if (!is_callable($this->closure)) {
            throw new InvalidConfigException("'closure' must be callable");
        }
    }

    /**
	   * @inheritdoc
	   */
    public function hasMethod($name)
    {
        if ($name !== $this->attribute) {
            return parent::hasMethod($name);
        }

        return true;
    }

    /**
     * @inheritdoc
     * 
     * @throws InvalidParamException
     */
    public function __call($name, $params)
    {
        if ($name != $this->attribute) {
            return null;
        }

        /** get paramters  */
        $version = ArrayHelper::getValue($params, 0, null);
        $default = ArrayHelper::getValue($params, 1, $this->default);
        $params = ArrayHelper::getValue($params, 2, []);

        /** strongly typed for first parameter */
        if(!is_string($version)) {
            throw new InvalidParamException("in method {$this->owner::className()}::{$this->attribute} the first param 'version' must be string");
        }

        /** strongly typed for second parameter */
        if(!(is_string($default) || $default === false)) {
            throw new InvalidParamException("in method {$this->owner::className()}::{$this->attribute} the second param 'default' must be string or false");
        }

        /** strongly typed for thirdy parameter */
        if(!is_array($params)) {
            throw new InvalidParamException("in method {$this->owner::className()}::{$this->attribute} the second param 'params' must be array");
        }

        /** use ram cache */
        if (isset($this->_cache[$version])) {
            return $this->_cache[$version];
        }

        /** set in cache the default version */
        $this->_cache[$version] = $default === false? null: $default;
        
        /** search by behaviors */
        $behaviors = array_filter($this->owner->behaviors(), 
            function($behavior) {
                return in_array($behavior['class'], [
                    MultipleUploadsBehavior::className(),
                    UploadBehavior::className()
                ]);
            }
        );

        /** does not have upload injection dependency */
        if (empty($behaviors)) {
            throw new InvalidConfigException("Class {$this->owner::className()} must be contain 'MultipleUploadsBehavior' or 'UploadBehavior' injected dependency.");
        }
            
        /** config data provider */
        $behavior = current($behaviors);
        $attributeDataProvider = 'get'.ucfirst($behavior['attributeDataProvider']);
        $params = array_merge(['limit' => 1], $this->params, $params);
        $dataProvider = $this->owner->$attributeDataProvider($params);

        if ($dataProvider->count) {
            $this->_cache[$version] = call_user_func($this->closure, current($dataProvider->models), $version, $this->_cache[$version], $params);
        }

        return $this->_cache[$version];
    }
}
