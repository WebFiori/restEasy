<?php

namespace restEasy\tests;
use webfiori\http\AbstractWebService;

/**
 * Description of NotImplService
 *
 * @author Eng.Ibrahim
 */
class NotImplService extends AbstractWebService {
    public function __construct() {
        parent::__construct('not-implemented');
        $this->addRequestMethod('post');
    }
    public function isAuthorized() {
        
    }

    public function processRequest() {
        $this->getManager()->serviceNotImplemented();
    }

}
