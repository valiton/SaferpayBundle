<?php

namespace Valiton\Payment\SaferpayBundle\DependencyInjection;

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
        $treeBuilder->root('valiton_payment_saferpay')
            ->children()
                ->scalarNode('account')->isRequired()->end()
                ->scalarNode('jsonapi_key')->isRequired()->end()
                ->scalarNode('jsonapi_pwd')->isRequired()->end()
                ->scalarNode('return_url')->defaultNull()->end()
                ->scalarNode('error_url')->defaultNull()->end()
                ->scalarNode('cancel_url')->defaultNull()->end()
                ->booleanNode('saferpay_test')->defaultTrue()->end()
                ->enumNode('cardrefid')->values(array('new', 'random'))->defaultValue('new')->end()
                ->scalarNode('cardrefid_prefix')->defaultNull()->end()
                ->scalarNode('cardrefid_length')->defaultValue(40)->end()
            ->end()
        ;
        return $treeBuilder;
    }
}
