<?php
namespace PunktDe\NodeReplicator;

/*
 *  (c) 2017 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Domain\Model\Node;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;

class Package extends BasePackage
{
    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(Node::class, 'nodeAdded', NodeSignalInterceptor::class, 'nodeAdded');
        $dispatcher->connect(Node::class, 'nodePropertyChanged', NodeSignalInterceptor::class, 'nodePropertyChanged');
        $dispatcher->connect(Node::class, 'nodeRemoved', NodeSignalInterceptor::class, 'nodeRemoved');
    }
}
