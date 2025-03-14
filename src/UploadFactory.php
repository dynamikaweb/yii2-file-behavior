<?php

namespace dynamikaweb\file;

use Yii;
use yii\base\Exception;

class UploadFactory
{
    /**
     * @deprecated soon
     * @throws yii\base\Exception
     * @ignore legacy version of upload single file, this function is maintained only for outdated internal projects.
     * 
     * @return object Closure
     */
    public static function Legacy()
    {
        return function ($model, $uploadAttribute, $fileAttribute, $modelFile, $attributeRelation) {
            if (empty($model->$uploadAttribute)) {
                return false;
            }

            $connection = $model->db;
            $transaction = $connection->beginTransaction();

            try {
                $arquivo = new $modelFile;
                $arquivo->imageVersions = $model->imageVersions;
                $arquivo->modelClass = $model::tableName();

                if ($arquivo->load($model->$uploadAttribute) && $arquivo->save()) {
                    if ($arquivo->upload()) {

                        $arquivoAtual = $model->$attributeRelation;
                        if (!empty($arquivoAtual)) {
                            $arquivoAtual->modelClass = $model::tableName();
                            $arquivoAtual->delete();
                        }

                        $model::updateAll([$fileAttribute => $arquivo->id], $model->getPrimaryKey(true));
                    } else {
                        throw new Exception('File failed to upload.');
                    }
                } else {
                    throw new Exception(current($arquivo->firstErrors));
                }
                $transaction->commit();
            } catch (Exception $e) {
                $transaction->rollBack();
                Yii::error(self::class . ' - ' . $e->getMessage());
                throw new Exception($e->getMessage());
            }
        };
    }

    /**
     * @deprecated soon
     * @throws yii\base\Exception
     * @ignore legacy version of upload mutiples files, this function is maintained only for outdated internal projects.
     * 
     * @return object Closure
     */
    public static function LegacyMultiples()
    {
        return function ($model, $attribute, $relations, $modelFile, $modelUnion) {
            if (empty($model->$attribute)) {
                return false;
            }

            $connection = $model->db;
            $id_file = array_key_first($relations[$modelFile]);
            $id_self = array_key_first($relations[$model::classname()]);
            $field_file = $relations[$modelFile][$id_file];
            $field_self = $relations[$model::classname()][$id_file];

            foreach ($model->{$attribute} as $file) {

                $transaction = $connection->beginTransaction();
                try {

                    $arquivo = new $modelFile;
                    $arquivo->modelClass = $model->tablename();

                    if (!$arquivo->load($file)) {
                        throw new \Exception('Não foi possível carregar o arquivo.');
                    }

                    if ($arquivo->tipo == $modelFile::TIPO_IMAGEM) {
                        $arquivo->posicao = $modelFile::getMaxPositionImage((new $modelUnion)->tablename(), $field_self, $model->{$id_self}) + 1;
                    }

                    if (!$arquivo->save()) {
                        foreach ($arquivo->getErrors() as $error) {
                            $error = is_array($error) ? current($error) : $error;
                            throw new \Exception($error);
                        }
                    }

                    if (!$arquivo->upload()) {
                        throw new \Exception('Não foi possível fazer upload do arquivo.');
                    }

                    $modelArquivo = new $modelUnion;
                    $modelArquivo->{$field_self} = $model->{$id_self};
                    $modelArquivo->{$field_file} = $arquivo->{$id_file};
                    $modelArquivo->tipo = $arquivo->tipo;

                    if (!$modelArquivo->save()) {
                        foreach ($modelArquivo->getErrors() as $error) {
                            $error = is_array($error) ? current($error) : $error;
                            throw new \Exception($error);
                        }
                    }
                    $transaction->commit();
                } catch (\Exception $e) {
                    Yii::error(self::class . ' - ' . $e->getMessage());
                    $transaction->rollBack();
                    throw new \yii\web\HttpException(500, $e->getMessage());
                }
            }
            return true;
        };
    }
}
