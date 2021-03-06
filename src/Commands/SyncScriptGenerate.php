<?php namespace AwkwardIdeas\SyncScript\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use AwkwardIdeas\SyncScript\SyncScript;

class SyncScriptGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'syncscript:generate {--from=} {--to=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //Create Migration Files
        if ($this->option('from') != "") {
            $from = $this->option('from');
        } else {
            $from= $this->ask('What database do you want to be synchronized?');
        }

        if ($this->option('to') != "") {
            $to = $this->option('to');
        } else {
            $to= $this->ask('What database do you want to receive the synchronized data?');
        }

        $this->comment("Migrating from $from to $to.");
        $this->comment(PHP_EOL.SyncScript::Generate($from, $to).PHP_EOL);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('from', null, InputOption::VALUE_OPTIONAL, "Database to Sync from",""),
            array('to', null, InputOption::VALUE_OPTIONAL, "Database to Sync to","")
        );
    }
}
