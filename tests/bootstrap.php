<?php

declare(strict_types=1);
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

// these annotations are not required, they are optional
AnnotationReader::addGlobalIgnoredNamespace('OpenApi');
AnnotationReader::addGlobalIgnoredNamespace('Nelmio');
