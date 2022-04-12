<?php
namespace IC;

use GetOpt\GetOpt;
use GetOpt\Option;
use League\CLImate\CLImate;

/**
 * Instance Connect Utility
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 */
class CLI
{
    /**
     * @var CLImate
     */
    public CLImate $climate;

    /**
     * @var GetOpt
     */
    public GetOpt $getOpt;

    /**
     * @var string
     */
    public string $user = 'ubuntu';

    /**
     * @var string
     */
    public string $region = 'us-west-1';

    /**
     * @var string
     */
    public ?string $name;

    /**
     * @var boolean
     */
    public bool $noConnect = false;

    public function __construct()
    {
        $this->climate = new CLImate();
        $this->getOpt = new GetOpt();

        // configure get opt settings
        $this->getOpt->set(GetOpt::SETTING_SCRIPT_NAME, 'ic');

        $this->getOpt->addOptions([
            Option::create('v', 'version', GetOpt::NO_ARGUMENT)->setDescription('Show version information and quit'),
            Option::create('h', 'help', GetOpt::NO_ARGUMENT)->setDescription('Show this help and quit'),
            Option::create('N', 'no-connect', GetOpt::NO_ARGUMENT)->setDescription('Authorize the SSH key and exit.'),
            Option::create('u', 'user', GetOpt::REQUIRED_ARGUMENT)->setDescription('Set user for SSH connection. Defaults to ubuntu.'),
            Option::create('R', 'region', GetOpt::REQUIRED_ARGUMENT)->setDescription('AWS Region'),
            Option::create('n', 'name', GetOpt::REQUIRED_ARGUMENT)->setDescription('The name of the instance (AWS Tag \'Name\')'),
        ])->addOperands([
            \GetOpt\Operand::create('instanceId')->setDescription('Target instance'),
        ]);
        
        try {
            $this->getOpt->process();
        } catch (\Exception $e) {
            $this->climate->error($e->getMessage());
            exit;
        }
        
        
        if (isset($this->getOpt->getOptions()['help'])) {
            $this->showHelp();
            exit;
        }
        if (isset($this->getOpt->getOptions()['version'])) {
            $this->showVersion();
            exit;
        }
        if (isset($this->getOpt->getOptions()['region'])) {
            $this->region = $this->getOpt->getOptions()['R'];
        }
        if (isset($this->getOpt->getOptions()['u'])) {
            $this->user = $this->getOpt->getOptions()['u'];
        }
        if (isset($this->getOpt->getOptions()['name'])) {
            $this->name = $this->getOpt->getOptions()['name'];
        }

        if (isset($this->getOpt->getOptions()['N'])) {
            $this->noConnect = true;
        }

        $awsConfig = getenv('HOME').'/.aws/config';
        if (file_exists($awsConfig)) {
            $configuration = parse_ini_file($awsConfig);
        }

        $this->region = $configuration['region'] ?? $this->region;
    }

    public function showHelp(): void
    {
        $this->climate->out($this->getOpt->getHelpText());
    }

    public function showVersion(): void
    {
        $package = json_decode(file_get_contents('composer.json'));
        $this->climate->info(sprintf('iam-ssh %s - %s', $package->version, $package->authors[0]->name));
    }

    /**
     * Sets up a connection
     *
     * @return void
     */
    public function handle()
    {
        $IC = new InstanceConnect();
        $IC->region = $this->region;
        $IC->user = $this->user;
        $IC->name = isset($this->name) ?? $this->name;
        $IC->noConnect = $this->noConnect;
        
        /**
         *
         * $IC->publicKey = '/home/user/.ssh/public.pub';
         * $IC->privateKey = '/home/user/.ssh/private';
         */
    
        $IC->start();
    }
}
