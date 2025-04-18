<?php

declare(strict_types=1);

namespace AnzuSystems\CommonBundle\Kernel;

use AnzuSystems\Contracts\AnzuApp;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

class AnzuKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * If this header is sent, application context uses this identity rather than generating a new one.
     */
    public const string CONTEXT_IDENTITY_HEADER = 'X-Context-ID';

    /**
     * Load balancer IP is dynamic and can change anytime.
     * HAProxy is setting this header to each request and removes it on each response,
     * so we can trust it and append it to trusted proxies.
     */
    private const string LOAD_BALANCER_IP_HEADER = 'X-LoadBalancer-IP';
    protected int $userIdAdmin = 1;
    protected int $userIdConsole = 2;
    protected int $userIdAnonymous = 3;
    private string $loadBalancerIp = '';
    private string $contextId = '';

    public function __construct(
        private readonly string $appNamespace,
        private readonly string $appSystem,
        private readonly string $appVersion,
        private readonly bool $appReadOnlyMode,
        string $environment,
        bool $debug,
    ) {
        parent::__construct(
            environment: $environment,
            debug: $debug
        );
    }

    /**
     * Override to set up static stuff at boot time.
     */
    public function boot(): void
    {
        AnzuApp::init(
            appNamespace: $this->appNamespace,
            appSystem: $this->appSystem,
            appVersion: $this->appVersion,
            appReadOnlyMode: $this->appReadOnlyMode,
            projectDir: $this->getProjectDir(),
            appEnv: $this->getEnvironment(),
            userIdAdmin: $this->userIdAdmin,
            userIdConsole: $this->userIdConsole,
            userIdAnonymous: $this->userIdAnonymous,
            contextId: $this->contextId
        );

        parent::boot();

        $trustedProxies = Request::getTrustedProxies();
        if ($this->loadBalancerIp && $trustedProxies) {
            $trustedProxies[] = $this->loadBalancerIp;
            /** @psalm-suppress ArgumentTypeCoercion */
            Request::setTrustedProxies($trustedProxies, Request::getTrustedHeaderSet());
        }
    }

    public function handle(
        Request $request,
        int $type = HttpKernelInterface::MAIN_REQUEST,
        bool $catch = true
    ): Response {
        $this->loadBalancerIp = (string) $request->headers->get(self::LOAD_BALANCER_IP_HEADER);
        $this->contextId = (string) $request->headers->get(self::CONTEXT_IDENTITY_HEADER);

        return parent::handle($request, $type, $catch);
    }

    protected function build(ContainerBuilder $container): void
    {
        $this->registerORMMapping($container);
    }

    private function registerORMMapping(ContainerBuilder $container): void
    {
        /** @var string $projectDir */
        $projectDir = $container->getParameter('kernel.project_dir');
        $finder = Finder::create();
        $finder
            ->in($projectDir . '/src')
            ->name('Entity')
            ->directories()
        ;
        $namespaces = [];
        $directories = [];

        foreach ($finder as $dir) {
            $namespace = str_replace('/', '\\', sprintf('App\%s', $dir->getRelativePathname()));
            $namespaces[] = $namespace;
            $directories[] = $dir->getPathname();
        }

        $container->addCompilerPass(DoctrineOrmMappingsPass::createAttributeMappingDriver(
            namespaces: $namespaces,
            directories: $directories,
            reportFieldsWhereDeclared: true,
        ));
    }
}
