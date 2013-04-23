<?php

namespace JHV\Payment\Plugin\CobreBemBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('jhv_payment_plugin_cobre_bem');

        $rootNode
            ->children()
                
                // Ambiente de conexão
                ->scalarNode('ambiente')->isRequired()->end()
                
                // Classes 
                ->arrayNode('cartao_credito')
                    ->children()
                        ->scalarNode('class')->defaultValue('JHV\\Payment\\Plugin\\CobreBemBundle\\Gateway\\CreditCardPlugin')->end()
                        ->scalarNode('form_type')->defaultValue('JHV\\Payment\\Plugin\\CobreBemBundle\\Form\\Type\\CreditCardType')->end()
                    ->end()
                    ->isRequired()
                ->end()
                
                // Conexão para ambiente de testes
                ->arrayNode('sandbox')
                    ->children()
                        ->scalarNode('destino_autorizacao')->isRequired()->end()
                        ->scalarNode('destino_captura')->isRequired()->end()
                        ->scalarNode('destino_cancelamento')->isRequired()->end()
                    ->end()
                    ->isRequired()
                ->end()
                
                // Conexão para ambiente de produção
                ->arrayNode('production')
                    ->children()
                        ->scalarNode('destino_autorizacao')->isRequired()->end()
                        ->scalarNode('destino_captura')->isRequired()->end()
                        ->scalarNode('destino_cancelamento')->isRequired()->end()
                    ->end()
                    ->isRequired()
                ->end()
                
            ->end()
        ;

        return $treeBuilder;
    }
}
