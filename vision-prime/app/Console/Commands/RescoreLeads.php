<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Marketing\Actions\ScoreLead;
use App\Models\Lead;
use Illuminate\Console\Command;

class RescoreLeads extends Command
{
    protected $signature = 'leads:rescore';

    protected $description = 'بازمحاسبهٔ امتیاز خودکار همهٔ لیدها بر اساس دادهٔ کمپین و رفتار';

    public function handle(ScoreLead $scoreLead): int
    {
        $leads = Lead::query()->get();
        $bar = $this->output->createProgressBar($leads->count());

        foreach ($leads as $lead) {
            $scoreLead->handle($lead);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("امتیاز {$leads->count()} لید بازمحاسبه شد.");

        return self::SUCCESS;
    }
}
