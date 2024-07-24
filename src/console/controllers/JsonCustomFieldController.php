<?php

namespace wsydney76\extras\console\controllers;

use Craft;
use craft\console\Controller;
use wsydney76\extras\models\JsonCustomField;
use yii\console\ExitCode;

class JsonCustomFieldController extends Controller
{
    /**
     * Dumps a JSON custom field for the given field identifier and collation, revealing the generated valueSql
     *
     * Usage craft _extras/json-custom-field/dump-field "person.lastName"
     *
     * @param $fieldIdent string The field identifier
     * @param $collation string The collation type (default is 'ci'). Either a full collation string or one of the following: 'pb', 'ci', 'cs'
     * @return int
     */
    public function actionDumpField($fieldIdent, $collation = 'ci'): int
    {

        try {
            $field = new JsonCustomField($fieldIdent, $collation);
            \Craft::dd($field);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

        return ExitCode::OK;
    }

    /**
     * Create a functional index for a JSON custom field
     *
     * Field ident and collation must be exactly the same as when the field is queried.
     *
     * Usage craft _extras/json-custom-field/create-functional-index "person.lastName"
     *
     * @param $fieldIdent string The field identifier
     * @param $collation string The collation type (default is 'ci'). Either a full collation string or one of the following: 'pb', 'ci', 'cs'
     * @return int
     */
    public function actionCreateFunctionalIndex(string $fieldIdent, string $collation = 'ci'): int
    {
        try {
            $field = new JsonCustomField($fieldIdent, $collation);

            $sql = $field->getFunctionalIndexSql();

            if (!$this->confirm("Create the following functional index? \n\n" . $sql . "\n\n")) {
                return ExitCode::OK;
            }

            $this->stdout('Creating index...' . PHP_EOL);
            Craft::$app->getDb()->createCommand($sql)->execute();

        } catch (\Exception $e) {
            $this->stderr($e->getMessage() . PHP_EOL);
            return ExitCode::UNSPECIFIED_ERROR;
        }


        $this->stdout('Index created' . PHP_EOL);

        return ExitCode::OK;
    }

}