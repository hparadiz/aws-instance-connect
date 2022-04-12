<?php
namespace IC;

use Aws\Ec2\Ec2Client;
use phpseclib3\Crypt\RSA;
use League\CLImate\CLImate;
use Aws\EC2InstanceConnect\EC2InstanceConnectClient;

class InstanceConnect
{
    /**
     * @var Ec2Client
     */
    public Ec2Client $Ec2Client;

    /**
     * @var string
     */
    public string $region = 'us-west-1';

    /**
     * @var CLImate
     */
    public CLImate $climate;

    /**
     * @var string
     */
    public string $publicKey;

    /**
     * @var string
     */
    public string $privateKey;

    /**
     * @var boolean
     */
    public bool $noConnect = false;

    public function __construct()
    {
        $this->climate = new CLImate();

        $this->generateKey();
    }

    /**
     * Makes an OpenSSH 2048 bit key.
     * Drops it into public / private key files.
     * chmods to only allow read/write by owner
     *
     * @return void
     */
    public function generateKey(): void
    {
        if (empty($this->publicKey) || empty($this->privateKey)) {
            /**
             * @var PrivateKey
             */
            $private = RSA::createKey();

            /**
             * @var PublicKey
             */
            $public = $private->getPublicKey();

            $priv = $private->toString('OpenSSH');
            $pub = $public->toString('OpenSSH');

            $this->privateKey = tempnam('/tmp/', '_ic_');
            $this->publicKey = $this->privateKey . '.pub';


            file_put_contents($this->privateKey, $priv);
            file_put_contents($this->publicKey, $pub);
            chmod($this->privateKey, 0600);
            chmod($this->publicKey, 0600);
        }
    }

    /**
     * Deletes the public/private key files
     *
     * @return void
     */
    public function deleteKey(): void
    {
        unlink($this->privateKey);
        unlink($this->publicKey);
    }

    public function start()
    {
        $this->Ec2Client = new Ec2Client([
            'region' => $this->region,
            'version' => 'latest',
            'profile' => 'default',
        ]);

        // check credentials and exit if they aren't configured
        $promise = $this->Ec2Client->getCredentials();
        if (is_a($promise, 'GuzzleHttp\Promise\RejectedPromise')) {
            $promise->then(null, function ($reason) {
                $this->climate->error($reason->getMessage());
                throw new \Exception($reason->getMessage());
            });
        }
 
        try {
            $this->getAvailableInstances();
            $instanceId = $this->prompt();
            if (!$instance = $this->getInstanceByInstanceId($instanceId)) {
                if ($instances = $this->getInstancesByTag('Name', $this->name)) {
                    $instance = $instances[0];
                } else {
                    $this->climate->error('Instance not found');
                    exit;
                }
            }
            $this->authorize($instance);
        } catch (\Exception $e) {
            $this->climate->error($e->getMessage());
        }
    }

    public function getAvailableInstances()
    {
        $result = $this->Ec2Client->describeInstances();
        $this->availableInstances = $result->search('Reservations[*].Instances[*][]');
    }

    /**
     * Prompts you to select an instance
     *
     * @return string Selected instance
     */
    public function prompt()
    {
        $servers = [];
        $i = 1;
        foreach ($this->availableInstances as $instance) {
            if ($instance['State']['Name'] == 'stopped' || $instance['State']['Name'] == 'terminated') {
                continue;
            }
            if (isset($instance['Tags'])) {
                foreach ($instance['Tags'] as $Tag) {
                    if ($Tag['Key'] == 'Name') {
                        $instance['NameTag'] = $Tag['Value'];
                    }
                }
            } else {
                $instance['NameTag'] = '[UNTITLED]';
            }
            $servers[$instance['InstanceId']] = [
                '#'				=> '<cyan>'.$i++.'</cyan>',
                'name'			=> '<yellow>'.$instance['NameTag'].'</yellow>',
                'instanceId'	=> $instance['InstanceId'],
                'PublicDnsName' => $instance['PublicDnsName']
            ];
        }
        uasort($servers, function ($a, $b) {
            return intval(intval($a['#']) > intval($b['#']));
        });
        $this->climate->table($servers);
        $input    = $this->climate->input('Please type the number of the instance to connect to:');
        while ($response = $input->prompt()) {
            foreach ($servers as $server) {
                if ($server['#'] === sprintf('<cyan>%s</cyan>', $response)) {
                    return $server['instanceId'];
                }
            }
            $this->climate->error('Please type in a valid instance number.');
        }
    }

    /**
     * Authorizes a key to work with AWS Instance Connect
     *
     * @param array $instance
     * @return EC2InstanceConnectClient
     */
    public function authorize(array $instance)
    {
        $EC2InstanceConnectClient = new EC2InstanceConnectClient([
            'region' => $this->region,
            'version' => 'latest',
            'profile' => 'default',
        ]);

        $result = $EC2InstanceConnectClient->SendSSHPublicKey([
            'region' => $this->region,
            'InstanceOSUser' => $this->user,
            'AvailabilityZone' => $instance['Placement']['AvailabilityZone'],
            'InstanceId' => $instance['InstanceId'],
            'SSHPublicKey' => file_get_contents($this->publicKey),
        ]);

        if ($result->search('Success')) {
            if (!$this->noConnect) {
                $this->connect($instance);
                return $EC2InstanceConnectClient;
            } else {
                $this->climate->info('The private key [' . $this->privateKey . '] is now authorized for 60 seconds.');
            }
        } else {
            $this->climate->error('EC2 Instance Connect API Call SendSSHPublicKey failed.');
        }
    }

    /**
     * Establishes an SSH Connection for you to the instance specified
     *
     * @param string $instance
     * @return void
     */
    public function connect($instance)
    {
        $cmd = sprintf('ssh -i "%s" %s@%s', $this->privateKey, $this->user, $instance['PublicDnsName']);
        passthru($cmd);
        $this->deleteKey();
    }

    /**
     * Gets instances
     *
     * @param string $instanceId
     *
     * @return array Instance
     */
    public function getInstanceByInstanceId($instanceId)
    {
        /**
         * @var \Aws\Result $instance
         * **/
        $result = $this->Ec2Client->describeInstances();
        return $result->search(sprintf('Reservations[*].Instances[?InstanceId==`%s`][]|[0]', $instanceId));
    }

    /**
     * Gets instances by Tag
     *
     * @param string $key
     * @param string $value
     * @return array Instances
     */
    public function getInstancesByTag($key, $value)
    {
        /**
         * @var \Aws\Result $instance
         * **/
        $result = $this->Ec2Client->describeInstances();
        return $result->search(sprintf('Reservations[?not_null(Instances[?Tags[?Key==`%s` && Value == `%s`]])].Instances[*][]', $key, $value));
    }
}
