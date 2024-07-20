<?php

namespace wsydney76\extras\console\controllers;

use Collator;
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

    function actionCompareStrings($str1, $str2) {
        $collator = new Collator('de_DE');

        // Optional: Set the collation strength to SECONDARY to ignore differences in case and accents
        $collator->setStrength(Collator::PRIMARY);

        $result = $collator->compare($str1, $str2);

        echo match ($result) {
            -1 => "$str1 is less than $str2",
            0 => "$str1 is equal to $str2",
            1 => "$str1 is greater than $str2",
        } . PHP_EOL;

        return ExitCode::OK;
    }

}