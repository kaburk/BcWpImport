<?php
declare(strict_types=1);

namespace BcWpImport\Model\Entity;

use Cake\ORM\Entity;

class BcWpImportJob extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
