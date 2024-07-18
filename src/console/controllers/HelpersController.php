<?php

namespace wsydney76\extras\console\controllers;

use craft\console\Controller;
use wsydney76\extras\models\JsonColumn;
use yii\base\ExitException;
use yii\console\ExitCode;

class HelpersController extends Controller
{
    public function actionFieldValueSql($fieldIdent)
    {

        try {
            $col = new JsonColumn($fieldIdent);
            \Craft::dd($col);
        } catch (\Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

        return ExitCode::OK;
    }

}