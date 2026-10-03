<?php

namespace Canva\Bridge\Symfony;

use Canva\Client;
use Canva\FileTokenStorage;
use Canva\OAuth;
use Canva\TokenStorageInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * glitchr/canva in a Symfony application: Canva\OAuth and Canva\Client as
 * services on the integration's id and secret, the token kept by the
 * TokenStorageInterface the application names (a JSON file under var/ by
 * default). The controller that starts the flow and answers the callback
 * is the application's: it knows its session and its routes.
 *
 *     canva:
 *         client_id: '%env(CANVA_CLIENT_ID)%'
 *         client_secret: '%env(CANVA_CLIENT_SECRET)%'
 *         redirect_route: app_canva_callback
 *         scopes: ['design:meta:read', 'design:content:read', 'asset:read', 'profile:read']
 *         token_storage: App\Tools\CanvaTokens
 */
final class CanvaBundle extends AbstractBundle
{
    protected string $extensionAlias = 'canva';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('client_id')->defaultValue('')->end()
                ->scalarNode('client_secret')->defaultValue('')->end()
                ->scalarNode('redirect_route')->defaultValue('canva_callback')->info('The route the application answers Canva\'s redirect at.')->end()
                ->arrayNode('scopes')->scalarPrototype()->end()->defaultValue(['design:meta:read', 'design:content:read', 'asset:read', 'profile:read'])->end()
                ->scalarNode('token_storage')->defaultNull()->info('A service implementing Canva\TokenStorageInterface; null: a JSON file under var/.')->end()
                ->scalarNode('base_url')->defaultValue(Client::BASE_URL)->end()
            ->end();
    }

    /** @param array{client_id: string, client_secret: string, redirect_route: string, scopes: list<string>, token_storage: ?string, base_url: string} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()->set('canva.redirect_route', $config['redirect_route']);
        $services = $container->services();

        if ($config['token_storage']) {
            $services->alias(TokenStorageInterface::class, $config['token_storage']);
        } else {
            $services->set(FileTokenStorage::class)->args(['%kernel.project_dir%/var/canva/token.json']);
            $services->alias(TokenStorageInterface::class, FileTokenStorage::class);
        }

        $services->set(OAuth::class)
            ->args([service('http_client'), service(TokenStorageInterface::class), $config['client_id'], $config['client_secret'], $config['scopes']])
            ->public();
        $services->set(Client::class)
            ->args([service('http_client'), service(OAuth::class), $config['base_url']])
            ->public();
    }
}
