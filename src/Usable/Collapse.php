<?php

namespace Kompo;

use Kompo\Komponents\Traits\HasHref;
use Kompo\Komponents\Traits\HasSubmenu;
use Kompo\Komponents\Trigger;

class Collapse extends Trigger
{
	use HasHref, HasSubmenu;

	public $vueComponent = 'Collapse';
    public $bladeComponent = 'Collapse';
}
