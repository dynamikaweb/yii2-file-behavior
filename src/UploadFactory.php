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
        return function($model, $uploadAttribute, $fileAttribute, $modelFile)
        {
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

                        $arquivoAtual = $model->arquivo;
                        if (!empty($arquivoAtual)) {
                            $arquivoAtual->modelClass = $modelFile;
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
            } 
            catch (Exception $e) {
                $transaction->rollBack();
                Yii::error(get_class().' - '.$e->getMessage());
                throw new Exception($e->getMessage());
            }
        };
    }
}
