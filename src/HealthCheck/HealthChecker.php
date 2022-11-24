<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\HealthCheck;

use AnzuSystems\CommonBundle\HealthCheck\Module\ModuleInterface;
use AnzuSystems\CommonBundle\Model\HealthCheck\ModuleResult;
use AnzuSystems\CommonBundle\Model\HealthCheck\SummarizeResult;
use AnzuSystems\SerializerBundle\Exception\SerializerException;
use AnzuSystems\SerializerBundle\Serializer;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class HealthChecker
{
    /**
     * Number of seconds that triggers warning log for too long health check.
     */
    private const WARNING_THRESHOLD_TIME_SECONDS = 5;

    /**
     * @var iterable<ModuleInterface>
     */
    private iterable $modules;

    public function __construct(
        iterable $modules,
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        private readonly ?Serializer $serializer = null,
    ) {
        $this->modules = $modules;
    }

    /**
     * @throws SerializerException
     */
    public function check(): SummarizeResult
    {
        $startTime = microtime(true);

        $result = new SummarizeResult();
        $result
            ->setLeadTime(
                $this->formatDuration(
                    $this->getDiffSecondsFromCurrentToStart(
                        (float) $this->requestStack->getMainRequest()?->server->get('REQUEST_TIME_FLOAT')
                    )
                )
            );

        foreach ($this->modules as $module) {
            $moduleStartTime = microtime(true);
            $moduleResult = ModuleResult::getInstance($module);
            $moduleResultHealth = $moduleResult->isHealthy();
            $moduleResult->setTime(
                $this->formatDuration(
                    $this->getDiffSecondsFromCurrentToStart($moduleStartTime)
                )
            );
            $result->addModuleResult($moduleResult);
            if (false === $moduleResultHealth) {
                $result->setHealthy(false);
            }
        }

        $diffSeconds = $this->getDiffSecondsFromCurrentToStart($startTime);
        $result->setTime(
            $this->formatDuration($diffSeconds)
        );

        if ($this->serializer instanceof Serializer) {
            $context = [
                'response' => $this->serializer->serialize($result),
                'ip' => $this->requestStack->getMainRequest()?->getClientIp(),
            ];
            if ($diffSeconds >= self::WARNING_THRESHOLD_TIME_SECONDS) {
                $this->logger->warning(
                    sprintf('[HealthChecker] Health check took more than %d seconds.', self::WARNING_THRESHOLD_TIME_SECONDS),
                    $context
                );
            }
            if (false === $result->isHealthy()) {
                $this->logger->critical(
                    '[HealthChecker] Health check found container unhealthy.',
                    $context
                );
            }
        }

        return $result;
    }

    private function formatDuration(float $diffSeconds): string
    {
        return number_format($diffSeconds, 3) . 's';
    }

    private function getDiffSecondsFromCurrentToStart(float $startTime): float
    {
        return microtime(true) - $startTime;
    }
}
