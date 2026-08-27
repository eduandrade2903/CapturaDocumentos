<?php

use Adianti\Control\TWindow;
use Adianti\Widget\Base\TElement;

/**
 * ExternalSystemWindowView
 *
 * @version    1.0
 * @package    samples
 * @subpackage tutor
 * @author     Pablo Dall'Oglio
 * @copyright  Copyright (c) 2006 Adianti Solutions Ltd. (http://www.adianti.com.br)
 * @license    https://adiantiframework.com.br/license-tutor
 */
class DocumentoView extends TWindow
{
    private $iframe;

    public function __construct()
    {
        parent::__construct();
        parent::setTitle('Visualização do documento enviado.');
        parent::removePadding();
        parent::setSize(0.6, 550);
        
        $this->iframe = new TElement('iframe');
        $this->iframe->id = "iframe_external";
        $this->iframe->src = $_GET['link'];
        $this->iframe->frameborder = "0";
        $this->iframe->scrolling = "yes";
        $this->iframe->width = "100%";
        $this->iframe->height = "100%";
        
        parent::add($this->iframe);
    }
}
