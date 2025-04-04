<?php

/*
 * This file is part of the Neos.Restore.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Restore\Ui\ViewModel;

use Neos\Flow\Annotations as Flow;


/**
 * @internal for communication within the Restore UI only
 */
#[Flow\Proxy(false)]
final readonly class RestoreListItem
{
    public function __construct(
        public string $name,
        public string $status,
        public string $nodTypeLabel,
        public array $breadcrumb,
        public ?string $workspaceName,
        public string $lastModifiedUser,
        public \DateTime $lastModifiedDate,
        public bool $isUserAllowedToEdit
    ) {
    }
}
