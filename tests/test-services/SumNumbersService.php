<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace restEasy\tests;

use webfiori\http\RequestParameter;
use webfiori\json\Json;
/**
 * Description of SumNumbersService
 *
 * @author Ibrahim
 */
class SumNumbersService extends AbstractNumbersService {
    
    public function __construct() {
        parent::__construct('sum-array');
        $this->addRequestMethod('post');
        $this->addRequestMethod('get');
        $this->setDescription('Returns a JSON string that has the sum of array of numbers.');
        $this->addParameter(new RequestParameter('numbers', 'array'));
    }

    public function processRequest() {
        $sum = 0;
        $numbersArr = $this->getParamVal('numbers');
        foreach ($numbersArr as $num) {
            if (gettype($num) == 'integer' || gettype($num) == 'double') {
                $sum += $num;
            }
        }
        $j = new Json();
        $j->add('sum', $sum);
        $this->send('application/json', $j);
    }

}
