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

    /**
     * @deprecated soon
     * @throws yii\base\Exception
     * @ignore legacy version of upload mutiples files, this function is maintained only for outdated internal projects.
     * 
     * @return object Closure
     */
    public function LegacyMultiples()
    {
        return function($model, $attribute, $relations, $modelFile, $modelUnion)
        {
            if (empty($model->$attribute)) {
                return false;
            }
            
            $connection = $model->db;
            
            foreach($model->{$attribute} as $file) {
                
                $transaction = $connection->beginTransaction();
                try {
                    
                    $arquivo = new $modelFile;
                    $arquivo->modelClass = $model->tablename();
                    
                    if (!$arquivo->load($file)) {
                        throw new \Exception('Não foi possível carregar o arquivo.');
                    }

                    if ($arquivo->tipo == $modelFile::TIPO_IMAGEM) {
                        $arquivo->posicao = $modelFile::getMaxPositionImage(
                            (new $modelUnion)->tablename(), 
                            current($relations[$model::classname()]),
                            $model->id
                        ) + 1;
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
                    $modelArquivo->id_pagina = $model->id;
                    $modelArquivo->id_arquivo = $arquivo->id;
                    $modelArquivo->tipo = $arquivo->tipo;
                    
                    if (!$modelArquivo->save()) {
                        foreach ($modelArquivo->getErrors() as $error) {
                            $error = is_array($error) ? current($error) : $error;
                            throw new \Exception($error);
                        }
                    }
                    $transaction->commit();
                } catch (\Exception $e) {
                    Yii::error(get_class().' - '.$e->getMessage());
                    $transaction->rollBack();
                    throw new \yii\web\HttpException(500, $e->getMessage());
                }
            }
            return true;
        };
    }
}