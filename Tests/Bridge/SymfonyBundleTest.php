<?php

namespace Canva\Tests\Bridge;

use Canva\Bridge\Symfony\CanvaBundle;
use Canva\Client;
use Canva\OAuth;
use Canva\TokenStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;

final class SymfonyBundleTest extends TestCase
{
    public function testTheBundleRegistersTheClientAndTheOauthFlow(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->set('http_client', new MockHttpClient());
        $bundle = new CanvaBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('canva', ['client_id' => 'id', 'client_secret' => 'secret', 'redirect_route' => 'app_cb']);
        $container->compile();

        self::assertInstanceOf(OAuth::class, $container->get(OAuth::class));
        self::assertInstanceOf(Client::class, $container->get(Client::class));
        self::assertSame('app_cb', $container->getParameter('canva.redirect_route'));
    }
}
