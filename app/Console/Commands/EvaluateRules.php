<?php

namespace App\Console\Commands;

use App\Services\RuleEngine;
use Illuminate\Console\Command;

class EvaluateRules extends Command
{
    protected $signature = 'rules:evaluate';
    protected $description = 'Evaluate automation rules and trigger actions';

    public function handle(RuleEngine $ruleEngine)
    {
        $this->info('Evaluating automation rules...');
        $ruleEngine->evaluate();
        $this->info('Rule evaluation completed.');
    }
}












