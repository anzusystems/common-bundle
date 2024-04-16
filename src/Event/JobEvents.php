<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Event;

/**
 * Contains all Job events dispatched by an Application.
 */
final class JobEvents
{
    /**
     * The ERROR event occurs when an uncaught exception or error appears.
     *
     * This event allows you to deal with the exception/error or
     * to modify the thrown exception.
     *
     * @Event("AnzuSystems\CommonBundle\Event\JobErrorEvent")
     */
    public const string ERROR = 'anzu_systems_common.job.error';

    /**
     * The COMPLETED event allows you to attach listeners after a job is
     * completed.
     *
     * @Event("AnzuSystems\CommonBundle\Event\JobCompletedEvent")
     */
    public const string COMPLETED = 'anzu_systems_common.job.completed';
}
