<?php

declare(strict_types=1);

namespace app\commands;

use app\services\DemoResetService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Управление публичными демоданными.
 */
final class DemoController extends Controller
{
    /**
     * @return int
     */
    public function actionReset(): int
    {
        /** @var DemoResetService $service */
        $service = Yii::$app->get('demoResetService');
        $service->reset();
        $this->stdout("Демоданные восстановлены.\n");

        return ExitCode::OK;
    }
}
