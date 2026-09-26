<?php

declare(strict_types=1);

namespace app\modules\analytics;

use app\modules\analytics\repositories\AnalyticsRepository;
use app\modules\analytics\services\AnalyticsService;
use app\services\AnalyticsCache;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Модуль аналитики.
 */
final class Module extends \yii\base\Module
{
    /** @inheritDoc */
    public $controllerNamespace = 'app\\modules\\analytics\\controllers';

    /**
     * @inheritDoc
     * @return void
     */
    public function init(): void
    {
        parent::init();

        $this->set(AnalyticsRepository::class, [
            'class' => AnalyticsRepository::class,
        ]);
        $this->set(AnalyticsService::class, function (): AnalyticsService {
            /** @var AnalyticsRepository $repository */
            $repository = $this->get(AnalyticsRepository::class);
            $cache = Yii::$app->get('analyticsCache', false);
            if (!$cache instanceof AnalyticsCache) {
                throw new InvalidConfigException('Кеш аналитики не настроен.');
            }

            return new AnalyticsService($repository, $cache);
        });
    }
}
