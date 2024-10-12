<?php

/* **************** TODO: UPGRADE TO CRAFT 5 **************** */

namespace wsydney76\extras\base\services;

use Craft;
use craft\base\Component;
use yii\helpers\Console;

/**
 * A base service class for all services to extend from.
 */
class BaseService extends Component
{
    protected string $logCategory = 'BaseService';

    /**
     * @param string $string
     * @return void
     */
    protected function info(string $string): void
    {
        if (Craft::$app->request->isConsoleRequest) {
            Console::stdout($string . PHP_EOL);
        } else {
            Craft::info($string, $this->logCategory);
        }
    }

    /**
     * @param string $string
     * @return void
     */
    protected function error(string $string): void
    {
        if (Craft::$app->request->isConsoleRequest) {
            Console::stderr($string . PHP_EOL);
        } else {
            Craft::error($string, $this->logCategory);
        }
    }
}
