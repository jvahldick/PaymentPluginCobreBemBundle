<?php

namespace JHV\Payment\Plugin\CobreBemBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class JHVPaymentPluginCobreBemExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
        
        // Classe de plugin de cartão de crédito e formulário
        $container->setParameter('jhv_payment_plugin_cobre_bem.parameter.credit_card.class', $config['cartao_credito']['class']);
        $container->setParameter('jhv_payment_plugin_cobre_bem.parameter.credit_card.form_type.class', $config['cartao_credito']['form_type']);
        
        
        $ambientes = array('production', 'sandbox');
        $ambiente = $config['ambiente'];
        if (false === in_array($ambiente, $ambientes)) {
            throw new \InvalidArgumentException(sprintf(
                'O ambiente definido %s não é valido. Os ambientes autorizados são: %s',
                $ambiente,
                join(', ', $ambientes)
            ));
        }
        
        // Definição dos parâmetros de acordo com o ambiente
        $container->setParameter('jhv_payment_plugin_cobre_bem.parameter.url_autorizacao', $config[$ambiente]['destino_autorizacao']);
        $container->setParameter('jhv_payment_plugin_cobre_bem.parameter.url_captura', $config[$ambiente]['destino_captura']);
        $container->setParameter('jhv_payment_plugin_cobre_bem.parameter.url_cancelamento', $config[$ambiente]['destino_cancelamento']);
        
        
    }
}
